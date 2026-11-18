package ru.mcapex.auth;

import org.bukkit.Location;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.block.BlockBreakEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import org.bukkit.event.entity.EntityDamageByEntityEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.inventory.InventoryOpenEvent;
import org.bukkit.event.inventory.InventoryDragEvent;
import org.bukkit.event.player.PlayerPickupItemEvent;
import org.bukkit.event.player.AsyncPlayerChatEvent;
import org.bukkit.event.player.AsyncPlayerPreLoginEvent;
import org.bukkit.event.player.PlayerLoginEvent;
import java.util.concurrent.ConcurrentHashMap;
import java.util.Map;
import java.util.UUID;
import org.bukkit.event.player.PlayerCommandPreprocessEvent;
import org.bukkit.event.player.PlayerDropItemEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.event.player.PlayerToggleFlightEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.event.player.PlayerTeleportEvent;

/**
 * События игрока: заход, чат, заморозка до входа.
 *
 * Пароль перехватывается здесь и не уходит в общий чат: событие
 * отменяется, поэтому ни другие игроки, ни лог чата его не видят.
 */
public final class AuthListeners implements Listener {

    private final ApexAuthPlugin plugin;
    private final AuthManager manager;
    private final Map<UUID, Long> admitted = new ConcurrentHashMap<>();

    public AuthListeners(ApexAuthPlugin plugin, AuthManager manager) {
        this.plugin = plugin;
        this.manager = manager;
    }

    /** Игрок ещё не вошёл и должен быть заморожен. */
    private boolean isPending(Player player) {
        AuthSession session = manager.session(player);

        return !manager.bypasses(player) && (session == null || !session.isAuthenticated());
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void preLogin(AsyncPlayerPreLoginEvent event) {
        admitted.remove(event.getUniqueId());
        if (event.getLoginResult() != AsyncPlayerPreLoginEvent.Result.ALLOWED) return;
        if (!validNickname(event.getName())) {
            event.disallow(AsyncPlayerPreLoginEvent.Result.KICK_OTHER, "§cНик: 3–16 латинских букв, цифр или _. Кириллица не допускается.");
            return;
        }
        try {
            if (!manager.canAdmit(event.getName())) event.disallow(AsyncPlayerPreLoginEvent.Result.KICK_OTHER,
                    "§eСначала зарегистрируйся на mc-apex.ru и привяжи этот Minecraft-ник в профиле.");
            else admitted.put(event.getUniqueId(), System.currentTimeMillis());
        } catch (Exception ex) {
            event.disallow(AsyncPlayerPreLoginEvent.Result.KICK_OTHER, "§cПроверка аккаунта временно недоступна. Попробуй позже.");
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void login(PlayerLoginEvent event) {
        Long checkedAt = admitted.remove(event.getPlayer().getUniqueId());
        if (!validNickname(event.getPlayer().getName()) || checkedAt == null || System.currentTimeMillis() - checkedAt > 60000)
            event.disallow(PlayerLoginEvent.Result.KICK_OTHER, "§cПроверка входа не завершена. Подключись повторно.");
    }

    static boolean validNickname(String name) { return name != null && name.matches("[A-Za-z0-9_]{3,16}"); }

    /* ----------------------------- Заход и выход ----------------------------- */

    @EventHandler(priority = EventPriority.MONITOR)
    public void onJoin(PlayerJoinEvent event) {
        manager.handleJoin(event.getPlayer());
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onQuit(PlayerQuitEvent event) {
        manager.handleQuit(event.getPlayer());
    }

    /* ----------------------------- Чат ----------------------------- */

    /**
     * Ввод пароля.
     *
     * Событие отменяется всегда, пока игрок не вошёл: пароль не должен
     * попасть в общий чат и в лог.
     */
    @EventHandler(priority = EventPriority.LOWEST)
    public void onChat(AsyncPlayerChatEvent event) {
        Player player = event.getPlayer();

        if (manager.isAuthenticated(player) || manager.bypasses(player)) {
            return;
        }

        // Никому не показываем строку, даже если это не пароль
        event.setCancelled(true);
        event.getRecipients().clear();

        String message = event.getMessage();
        event.setMessage("");

        // Проверка пароля уходит в сеть, поэтому её делает менеджер асинхронно
        plugin.getServer().getScheduler().runTask(plugin, () -> manager.handleChat(player, message));
    }

    /* ----------------------------- Заморозка ----------------------------- */

    /**
     * Заморозка: поворачиваться можно, сдвинуться — нет.
     *
     * Одного setTo(from) мало: прыжок и полёт клиент предсказывает сам, и
     * игрок видит, что «прыгнул», хотя сервер его не пустил. Поэтому
     * отменяем событие и телепортируем назад — так позиция на сервере и у
     * клиента совпадает.
     */
    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onMove(PlayerMoveEvent event) {
        Player player = event.getPlayer();
        AuthSession session = manager.session(player);

        if (!isPending(player)) {
            return;
        }

        Location from = event.getFrom();
        Location to = event.getTo();

        if (to == null) {
            return;
        }

        // Поворот не считается движением
        if (from.getX() == to.getX() && from.getY() == to.getY() && from.getZ() == to.getZ()) {
            return;
        }

        event.setCancelled(true);

        Location target = (session != null && session.frozenAt() != null ? session.frozenAt() : from).clone();

        // Угол камеры оставляем как у игрока: иначе телепорт разворачивает
        // обзор на угол спавна и играть неудобно
        target.setYaw(player.getLocation().getYaw());
        target.setPitch(player.getLocation().getPitch());

        // Телепорт в следующем тике: иначе рекурсия и «резинка»
        plugin.getServer().getScheduler().runTask(plugin, () -> {
            if (player.isOnline() && !manager.isAuthenticated(player)) {
                Location now = player.getLocation();

                target.setYaw(now.getYaw());
                target.setPitch(now.getPitch());

                manager.teleportFrozen(player, target);
            }
        });
    }

    /** Прыжок с места: движение по Y тоже ловим отдельным событием. */
    @EventHandler(priority = EventPriority.HIGHEST)
    public void onFlightToggle(PlayerToggleFlightEvent event) {
        if (isPending(event.getPlayer())) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onCommand(PlayerCommandPreprocessEvent event) {
        if (!isPending(event.getPlayer())) {
            return;
        }

        /*
         * Отменяем до разбора команды: иначе пароль, случайно набранный
         * со слэшем, попал бы в лог сервера.
         */
        event.setCancelled(true);
        event.setMessage("/__apex_auth_pending__");
        event.getPlayer().sendMessage(manager.config().prefix() + "§7Сначала войди.");
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onInteract(PlayerInteractEvent event) {
        if (isPending(event.getPlayer())) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onBreak(BlockBreakEvent event) {
        if (isPending(event.getPlayer())) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onPlace(BlockPlaceEvent event) {
        if (isPending(event.getPlayer())) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onDrop(PlayerDropItemEvent event) {
        if (isPending(event.getPlayer())) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onInventoryOpen(InventoryOpenEvent event) {
        if (event.getPlayer() instanceof Player player && isPending(player)) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onInventoryClick(InventoryClickEvent event) {
        if (event.getWhoClicked() instanceof Player player && isPending(player)) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onTeleport(PlayerTeleportEvent event) {
        if (isPending(event.getPlayer()) && !manager.ownTeleport(event.getPlayer())) event.setCancelled(true);
    }

    @EventHandler(priority = EventPriority.HIGHEST) public void onDrag(InventoryDragEvent event) {
        if (event.getWhoClicked() instanceof Player player && isPending(player)) event.setCancelled(true);
    }

    @EventHandler(priority = EventPriority.HIGHEST) public void onPickup(PlayerPickupItemEvent event) {
        if (isPending(event.getPlayer())) event.setCancelled(true);
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onDamage(EntityDamageEvent event) {
        if (event.getEntity() instanceof Player player && isPending(player)) {
            event.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onDamageBy(EntityDamageByEntityEvent event) {
        if (event.getDamager() instanceof Player player && isPending(player)) {
            event.setCancelled(true);
        }
    }
}
