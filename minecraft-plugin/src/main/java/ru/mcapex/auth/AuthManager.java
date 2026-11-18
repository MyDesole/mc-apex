package ru.mcapex.auth;

import org.bukkit.Bukkit;
import org.bukkit.Location;
import org.bukkit.World;
import org.bukkit.entity.Player;
import org.bukkit.scheduler.BukkitTask;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Следит за входом игроков.
 *
 * Порядок: зашёл → на спавн, заморожен → если ник не заявлен на сайте,
 * игрок заявляет его на сайте → затем вводит пароль сайта → разморожен.
 *
 * Удачный вход запоминается на N минут (и по желанию привязан к IP):
 * повторно пароль не спрашиваем — иначе он превращается в повинность.
 *
 * Ключ — ник. Плагин пускает только того, чей ник совпадает с заявленным на
 * сайте, поэтому зайти под чужим ником нельзя: пароль спрашивается у
 * владельца заявки, а не у того, кто зашёл.
 *
 * При недоступном сайте игрока не пускаем (fail-closed): иначе проверку
 * можно было бы обойти, уронив сайт.
 */
public final class AuthManager {

    /** Как часто спрашиваем сайт, заявлен ли ник. */
    private static final long POLL_INTERVAL_TICKS = 60L;

    private final ApexAuthPlugin plugin;
    private final Map<UUID, AuthSession> sessions = new ConcurrentHashMap<>();

    private PluginConfig config;
    private ApiClient api;
    private SessionStore store;

    public AuthManager(ApexAuthPlugin plugin) {
        this.plugin = plugin;
    }

    /** Перечитывает настройки и пересоздаёт клиент сайта. */
    public void reload() {
        this.config = new PluginConfig(plugin.getConfig());
        this.api = new ApiClient(
                config.apiUrl(),
                config.serverKey(),
                config.httpTimeoutSeconds(),
                plugin.getLogger(),
                config.proxy()
        );
        this.store = new SessionStore(plugin);
    }

    public PluginConfig config() {
        return config;
    }

    public AuthSession session(Player player) {
        return sessions.get(player.getUniqueId());
    }

    public boolean isAuthenticated(Player player) {
        AuthSession session = session(player);

        return session != null && session.isAuthenticated();
    }

    /**
     * Пропускать ли игрока без проверки.
     *
     * Оператор сервера получает все права, включая apexauth.bypass, поэтому
     * его пускаем только при явной настройке auth.op-bypass — иначе владелец
     * сервера молча обходил бы плагин и не видел, как он работает.
     */
    public boolean bypasses(Player player) {
        if (player.isOp()) {
            return config.opBypass();
        }

        return player.hasPermission("apexauth.bypass");
    }

    /** Runs only on the async pre-login thread: no account means no world admission. */
    public boolean canAdmit(String nickname) throws ApiClient.ApiException {
        if (config == null || !config.isUsable()) return false;
        return api.resolve(nickname).claimed();
    }

    /* ----------------------------- Вход ----------------------------- */

    public void handleJoin(Player player) {
        if (config == null || !config.isUsable()) { player.kickPlayer("Авторизация недоступна."); return; }
        if (bypasses(player)) {
            player.sendMessage(config.prefix() + "§7Оператор: вход без проверки.");

            return;
        }

        AuthSession session = new AuthSession(player.getUniqueId(), player.getName());

        sessions.put(player.getUniqueId(), session);

        // Сначала на спавн: до входа игрок не должен оказаться там, где вышел
        prepareSpawn(player);
        session.frozenAt(player.getLocation().clone());

        // Замораживаем сразу: пока сайт отвечает, игрок уже ничего не может
        freeze(player);
        refreshVisibility();

        scheduleTimeout(player, session);
        scheduleReminder(player, session);

        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> resolveNickname(player, session));
    }

    public void handleQuit(Player player) {
        AuthSession session = sessions.remove(player.getUniqueId());

        if (session != null) {
            cancelTasks(session);
        }
    }

    /** Телепорт на спавн первого мира (событие с причиной PLUGIN не режем). */
    private void prepareSpawn(Player player) {
        if (!config.spawnOnJoin()) {
            return;
        }

        if (Bukkit.getWorlds().isEmpty()) {
            return;
        }

        World world = Bukkit.getWorlds().get(0);
        Location spawn = world.getSpawnLocation();

        teleportFrozen(player, spawn);
    }

    /**
     * Заявлен ли ник игрока на сайте.
     *
     * Сайт — главный: сначала спрашиваем его, и только потом решаем, можно
     * ли не спрашивать пароль. Иначе откреплённый ник продолжил бы пускать
     * по старой памяти плагина.
     */
    private void resolveNickname(Player player, AuthSession session) {
        try {
            ApiClient.ResolveResult result = api.resolve(session.name());

            if (!result.claimed()) {
                // Ник снят на сайте — локальный вход больше не действует
                store.forget(player);

                Bukkit.getScheduler().runTask(plugin, () -> askToClaim(player, session));
                startPolling(player, session);

                return;
            }

            String account = result.accountUsername();

            // Вход подтверждён недавно и сайт всё ещё закрепляет ник за тем
            // же аккаунтом — тогда пароль не нужен
            if (config.sessionMinutes() > 0 && store.valid(player, account)) {
                Bukkit.getScheduler().runTask(plugin, () -> restoreSession(player, session, account));

                return;
            }

            Bukkit.getScheduler().runTask(plugin, () -> askPassword(player, session, account));
        } catch (ApiClient.ApiException e) {
            Bukkit.getScheduler().runTask(plugin, () -> failClosed(player, session));
        }
    }

    /** Вход по сохранённой сессии: без пароля, но только после ответа сайта. */
    private void restoreSession(Player player, AuthSession session, String account) {
        if (!player.isOnline()) {
            return;
        }

        session.state(AuthSession.State.AUTHENTICATED);
        session.siteUsername(account);

        cancelTasks(session);
        unfreeze(player);
        refreshVisibility();

        player.sendMessage(config.prefix() + config.sessionRestored());
    }

    /** Просим заявить ник в профиле на сайте. */
    private void askToClaim(Player player, AuthSession session) {
        if (!player.isOnline()) {
            return;
        }

        session.state(AuthSession.State.NOT_CLAIMED);

        player.sendMessage("");
        player.sendMessage(config.prefix() + config.message(config.claimPrompt(), "nickname", session.name()));
        player.sendMessage(config.prefix() + "§7Плагин проверяет это сам — ждать ничего не нужно.");
        player.sendMessage("");

        player.sendTitle("§eПривяжи ник", "§7" + session.name() + " · на сайте");
    }

    /** Спрашиваем пароль сайта. */
    private void askPassword(Player player, AuthSession session, String accountUsername) {
        if (!player.isOnline()) {
            return;
        }

        session.state(AuthSession.State.PASSWORD);
        session.siteUsername(accountUsername);

        cancelPolling(session);

        if (accountUsername != null) {
            player.sendMessage(config.prefix() + config.message(config.nicknameClaimed(), "account", accountUsername));
        }

        player.sendMessage(config.prefix() + config.passwordPrompt());
        player.sendTitle("§eВведи пароль", "§7от сайта mc-apex.ru");
    }

    /**
     * Игрок ввёл строку в чат.
     *
     * @return true, если сообщение перехвачено и не должно уходить в общий чат
     */
    public boolean handleChat(Player player, String message) {
        AuthSession session = session(player);

        if (session == null || session.isAuthenticated()) {
            return false;
        }

        if (session.state() == AuthSession.State.PASSWORD) {
            submitPassword(player, session, message);
        } else {
            // Ждём заявки ника на сайте
            player.sendMessage(config.prefix() + config.message(config.claimPrompt(), "nickname", session.name()));
        }

        return true;
    }

    /** Отправляет пароль на сайт. Сам пароль нигде не сохраняется. */
    private void submitPassword(Player player, AuthSession session, String password) {
        player.sendMessage(config.prefix() + "§7Проверяем...");

        String uuid = session.uuid().toString();

        Bukkit.getScheduler().runTaskAsynchronously(plugin, () -> {
            ApiClient.LoginResult result;

            try {
                result = api.login(session.name(), password, uuid);
            } catch (ApiClient.ApiException e) {
                Bukkit.getScheduler().runTask(plugin, () -> failClosed(player, session));

                return;
            }

            Bukkit.getScheduler().runTask(plugin, () -> applyLoginResult(player, session, result));
        });
    }

    private void applyLoginResult(Player player, AuthSession session, ApiClient.LoginResult result) {
        if (!player.isOnline()) {
            return;
        }

        if (result.ok()) {
            authenticate(player, session, result);

            return;
        }

        if ("throttled".equals(result.reason())) {
            player.kickPlayer(config.prefix() + config.message(
                    config.throttled(), "seconds", String.valueOf(result.retryAfter())
            ));

            return;
        }

        session.attemptsLeft(result.attemptsLeft());

        if (result.attemptsLeft() <= 0) {
            player.kickPlayer(config.prefix() + config.attemptsUsedUp());

            return;
        }

        player.sendMessage(config.prefix() + config.message(
                config.wrongPassword(),
                "attempts", String.valueOf(result.attemptsLeft())
        ));
    }

    /** Вход выполнен: размораживаем, запоминаем и показываем данные игрока. */
    private void authenticate(Player player, AuthSession session, ApiClient.LoginResult result) {
        session.state(AuthSession.State.AUTHENTICATED);

        cancelTasks(session);
        unfreeze(player);
        refreshVisibility();

        // Не спрашивать пароль при следующем заходе
        store.remember(player, config.sessionMinutes(), config.sessionBindIp(), session.siteUsername());

        player.sendMessage(config.prefix() + config.message(
                config.welcome(),
                "player", result.siteUsername() != null ? result.siteUsername() : player.getName()
        ));

        StringBuilder extra = new StringBuilder();

        if (result.tier() != null && !result.tier().isBlank()) {
            extra.append("§7Тир: §b").append(result.tier()).append("  ");
        }

        if (result.bridgeRank() != null && !result.bridgeRank().isBlank()) {
            extra.append("§7Бридж: §d").append(result.bridgeRank()).append("  ");
        }

        if (result.clanTag() != null && !result.clanTag().isBlank()) {
            extra.append("§7Клан: §f").append(result.clanTag());
        }

        if (!extra.isEmpty()) {
            player.sendMessage(config.prefix() + extra.toString().trim());
        }
    }

    /* ----------------------------- Опрос сайта ----------------------------- */

    private void startPolling(Player player, AuthSession session) {
        BukkitTask task = Bukkit.getScheduler().runTaskTimerAsynchronously(plugin, () -> {
            if (!player.isOnline() || session.state() != AuthSession.State.NOT_CLAIMED) {
                cancelPolling(session);

                return;
            }

            try {
                ApiClient.ResolveResult result = api.resolve(session.name());

                if (result.claimed()) {
                    cancelPolling(session);
                    Bukkit.getScheduler().runTask(plugin, () -> askPassword(player, session, result.accountUsername()));
                }
            } catch (ApiClient.ApiException e) {
                // Сайт моргнул — не выкидываем игрока, попробуем на следующем круге
                plugin.getLogger().fine("Опрос заявки ника не удался: " + e.getMessage());
            }
        }, POLL_INTERVAL_TICKS, POLL_INTERVAL_TICKS);

        session.pollTaskId(task.getTaskId());
    }

    private void scheduleTimeout(Player player, AuthSession session) {
        if (!config.kickOnTimeout()) {
            return;
        }

        long ticks = Math.max(20L, config.authTimeoutSeconds() * 20L);

        BukkitTask task = Bukkit.getScheduler().runTaskLater(plugin, () -> {
            if (!player.isOnline() || session.isAuthenticated()) {
                return;
            }

            player.kickPlayer(config.prefix() + config.timeoutKick());
        }, ticks);

        session.timeoutTaskId(task.getTaskId());
    }

    /** Периодически напоминаем, чего от игрока ждут. */
    private void scheduleReminder(Player player, AuthSession session) {
        long ticks = Math.max(20L, config.remindSeconds() * 20L);

        BukkitTask task = Bukkit.getScheduler().runTaskTimer(plugin, () -> {
            if (!player.isOnline() || session.isAuthenticated()) {
                cancelReminder(session);

                return;
            }

            remind(player, session);
        }, ticks, ticks);

        session.reminderTaskId(task.getTaskId());
    }

    private void remind(Player player, AuthSession session) {
        if (session.state() == AuthSession.State.PASSWORD) {
            player.sendMessage(config.prefix() + config.passwordPrompt());
            player.sendTitle("§eВведи пароль", "§7от сайта mc-apex.ru");

            return;
        }

        player.sendMessage(config.prefix() + config.message(config.claimPrompt(), "nickname", session.name()));
        player.sendTitle("§eПривяжи ник", "§7" + session.name() + " · на сайте");
    }

    private void cancelPolling(AuthSession session) {
        Integer id = session.pollTaskId();

        if (id != null) {
            Bukkit.getScheduler().cancelTask(id);
            session.pollTaskId(null);
        }
    }

    private void cancelReminder(AuthSession session) {
        Integer id = session.reminderTaskId();

        if (id != null) {
            Bukkit.getScheduler().cancelTask(id);
            session.reminderTaskId(null);
        }
    }

    private void cancelTasks(AuthSession session) {
        cancelPolling(session);
        cancelReminder(session);

        Integer timeout = session.timeoutTaskId();

        if (timeout != null) {
            Bukkit.getScheduler().cancelTask(timeout);
            session.timeoutTaskId(null);
        }
    }

    /* ----------------------------- Заморозка ----------------------------- */

    private void failClosed(Player player, AuthSession session) {
        if (!player.isOnline()) {
            return;
        }

        cancelTasks(session);
        player.kickPlayer(config.prefix() + config.siteDownKick());
    }

    private final java.util.Set<UUID> ownTeleports = java.util.Collections.newSetFromMap(new ConcurrentHashMap<UUID, Boolean>());
    public boolean ownTeleport(Player player) { return ownTeleports.contains(player.getUniqueId()); }
    public void teleportFrozen(Player player, Location location) {
        ownTeleports.add(player.getUniqueId());
        try { player.teleport(location); } finally { ownTeleports.remove(player.getUniqueId()); }
    }

    private void freeze(Player player) {
        // Pending-player damage is cancelled by AuthListeners (Bukkit 1.8).
        player.setFoodLevel(20);
        player.setFireTicks(0);

        // Никакого полёта и двойного прыжка, пока не вошёл
        player.setAllowFlight(false);
        player.setFlying(false);

        // Дополнительно к PlayerMoveEvent: игрок не «уезжает» рывками
        player.setWalkSpeed(0f);
        player.setFlySpeed(0f);
    }

    private void unfreeze(Player player) {

        player.setWalkSpeed(0.2f);
        player.setFlySpeed(0.1f);
    }

    /**
     * Прячет неавторизованных от остальных, если так настроено.
     *
     * 1.8.8: hidePlayer/showPlayer принимают только игрока — форма
     * hidePlayer(Plugin, Player) появилась позже, на этом сервере её нет.
     */
    private void refreshVisibility() {
        if (!config.hideUnauthenticated()) {
            return;
        }

        for (Player viewer : Bukkit.getOnlinePlayers()) {
            for (AuthSession other : sessions.values()) {
                if (other.isAuthenticated()) {
                    continue;
                }

                Player hidden = Bukkit.getPlayer(other.uuid());

                if (hidden == null || viewer.getUniqueId().equals(hidden.getUniqueId())) {
                    continue;
                }

                viewer.hidePlayer(hidden);
                hidden.hidePlayer(viewer);
            }
        }
    }

    /** Разморозить всех при выключении плагина, чтобы не оставить замороженных. */
    public void releaseAll() {
        for (AuthSession session : sessions.values()) {
            cancelTasks(session);

            Player player = Bukkit.getPlayer(session.uuid());

            if (player != null && player.isOnline()) {
                unfreeze(player);

                if (config.hideUnauthenticated()) {
                    for (Player other : Bukkit.getOnlinePlayers()) {
                        other.showPlayer(player);
                        player.showPlayer(other);
                    }
                }
            }
        }

        sessions.clear();
    }
}
