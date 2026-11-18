package ru.mcapex.auth;

import org.bukkit.command.Command;
import org.bukkit.command.CommandSender;
import org.bukkit.event.HandlerList;
import org.bukkit.plugin.java.JavaPlugin;

/**
 * ApexAuth: привязка игроков к сайту mc-apex.ru и вход по паролю сайта.
 *
 * Что делает плагин:
 *   1. при первом заходе выдаёт код, игрок вводит его на сайте;
 *   2. при следующих заходах просит пароль сайта прямо в чат;
 *   3. до успешного входа игрок заморожен и не виден остальным.
 *
 * Пароль уходит только на сайт по HTTPS и нигде не сохраняется.
 *
 * ВАЖНО: сервер обязан работать с online-mode=true. В offline-mode ник
 * и UUID не подтверждаются, и любой сможет назваться чужим ником.
 */
public final class ApexAuthPlugin extends JavaPlugin {

    private AuthManager manager;

    @Override
    public void onEnable() {
        saveDefaultConfig();
        migrateConfig();

        manager = new AuthManager(this);
        manager.reload();

        getServer().getPluginManager().registerEvents(new AuthListeners(this, manager), this);

        if (!manager.config().isUsable()) {
            getLogger().severe("Плагин выключен: проверь api.url и api.server-key в config.yml.");
            getLogger().severe("Ключ должен совпадать с MINECRAFT_SERVER_KEY на сайте.");

            // Keep the admission listener active: bad configuration must never open the server.

            return;
        }

        /*
         * В offline-режиме UUID выводится из ника, поэтому ник нельзя
         * подтвердить: его займёт тот, кто привяжет первым. Плагин это не
         * лечит — но права берутся из аккаунта сайта, а не из ника, так что
         * чужой ник не даёт чужих прав. Что реально нужно — инструмент
         * админа, чтобы освободить ник (/apexauth unlink).
         */
        if (!getServer().getOnlineMode()) {
            getLogger().warning("Сервер в offline-mode.");
            getLogger().warning("Ник здесь нельзя подтвердить: его займёт тот, кто заявит его на сайте первым.");
            getLogger().warning("Если настоящий владелец ника не может зайти — освободи ник:");
            getLogger().warning("  /apexauth whois <ник>   — на какой аккаунт привязан");
            getLogger().warning("  /apexauth unlink <ник>  — освободить ник");
            getLogger().warning("Не выдавай права по нику — только по аккаунту сайта.");
        }

        getLogger().info("ApexAuth включён, сайт: " + manager.config().apiUrl());
    }

    /**
     * Раньше сайт жил на порту 8443, теперь — на обычном 443.
     *
     * saveDefaultConfig() не трогает уже существующий config.yml, поэтому у
     * тех, кто ставил плагин раньше, остаётся старый адрес — и плагин
     * стучится в закрытый порт, а с fail-closed это блокирует вход всем.
     */
    private void migrateConfig() {
        String url = getConfig().getString("api.url", "");

        if (url != null && url.trim().contains(":8443")) {
            String fixed = url.trim().replace(":8443", "");
            getConfig().set("api.url", fixed);
            saveConfig();

            getLogger().info("Адрес сайта обновлён на " + fixed);
        }
    }

    @Override
    public void onDisable() {
        for (org.bukkit.entity.Player player : getServer().getOnlinePlayers()) player.kickPlayer("Авторизация перезапускается. Подключись снова.");
        if (manager != null) {
            // Снимаем заморозку, иначе игроки останутся обездвиженными
            manager.releaseAll();
        }

        HandlerList.unregisterAll(this);
    }

    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (!command.getName().equalsIgnoreCase("apexauth")) {
            return false;
        }

        if (!sender.hasPermission("apexauth.admin")) {
            sender.sendMessage("§cНедостаточно прав.");

            return true;
        }

        if (args.length == 0) {
            sender.sendMessage("§7Использование:");
            sender.sendMessage("§f/apexauth reload §7— перечитать настройки");
            sender.sendMessage("§f/apexauth test §7— проверить связь с сайтом");
            sender.sendMessage("§f/apexauth whois <ник> §7— на какой аккаунт заявлен ник");
            sender.sendMessage("§f/apexauth unlink <ник> §7— освободить ник");

            return true;
        }

        if (args[0].equalsIgnoreCase("reload")) {
            reloadConfig();
            manager.reload();

            sender.sendMessage("§aНастройки перечитаны. Сайт: §f" + manager.config().apiUrl());

            return true;
        }

        if (args[0].equalsIgnoreCase("test")) {
            testConnection(sender);

            return true;
        }

        if (args[0].equalsIgnoreCase("whois") || args[0].equalsIgnoreCase("unlink")) {
            if (args.length < 2) {
                sender.sendMessage("§cУкажи ник: §f/apexauth " + args[0].toLowerCase() + " <ник>");

                return true;
            }

            if (args[0].equalsIgnoreCase("whois")) {
                lookupNickname(sender, args[1]);
            } else {
                unlinkNickname(sender, args[1]);
            }

            return true;
        }

        sender.sendMessage("§7Использование: §f/apexauth reload|whois|unlink");

        return true;
    }

    /**
     * Проверяет связь с сайтом по шагам: DNS, TCP, HTTPS.
     *
     * Вывод уходит и в консоль: по нему видно, на каком шаге рвётся.
     */
    private void testConnection(CommandSender sender) {
        sender.sendMessage("§7Проверяю связь с сайтом...");

        getServer().getScheduler().runTaskAsynchronously(this, () -> {
            ApiClient api = new ApiClient(
                    manager.config().apiUrl(),
                    manager.config().serverKey(),
                    manager.config().httpTimeoutSeconds(),
                    getLogger(),
                    manager.config().proxy()
            );

            java.util.List<String> report = api.diagnose();

            getServer().getScheduler().runTask(this, () -> {
                for (String line : report) {
                    sender.sendMessage(line);

                    // В консоль — без цветовых кодов, чтобы читалось в логе
                    getLogger().info("[тест] " + org.bukkit.ChatColor.stripColor(line));
                }
            });
        });
    }

    /**
     * Показывает, на какой аккаунт сайта заявлен ник.
     *
     * В offline-режиме доказать принадлежность ника нельзя, поэтому админу
     * нужен способ увидеть, кто его занял, и освободить при споре.
     */
    private void lookupNickname(CommandSender sender, String nickname) {
        getServer().getScheduler().runTaskAsynchronously(this, () -> {
            try {
                ApiClient api = new ApiClient(
                        manager.config().apiUrl(),
                        manager.config().serverKey(),
                        manager.config().httpTimeoutSeconds(),
                        getLogger(),
                        manager.config().proxy()
                );

                ApiClient.LookupResult result = api.lookup(nickname);

                getServer().getScheduler().runTask(this, () -> {
                    if (!result.claimed()) {
                        sender.sendMessage("§7Ник §f" + nickname + "§7 ни за кем не заявлен.");

                        return;
                    }

                    sender.sendMessage("§7Ник §f" + result.nickname()
                            + "§7 заявлен аккаунтом §b" + result.accountUsername());
                    sender.sendMessage("§7Заявлен: §f" + result.claimedAt());
                });
            } catch (ApiClient.ApiException e) {
                getServer().getScheduler().runTask(this, () ->
                        sender.sendMessage("§cНе удалось спросить сайт: " + e.getMessage()));
            }
        });
    }

    /** Освобождает ник, чтобы настоящий владелец смог заявить его заново. */
    private void unlinkNickname(CommandSender sender, String nickname) {
        getServer().getScheduler().runTaskAsynchronously(this, () -> {
            try {
                ApiClient api = new ApiClient(
                        manager.config().apiUrl(),
                        manager.config().serverKey(),
                        manager.config().httpTimeoutSeconds(),
                        getLogger(),
                        manager.config().proxy()
                );

                boolean done = api.adminUnlink(nickname);

                getServer().getScheduler().runTask(this, () -> {
                    if (done) {
                        sender.sendMessage("§aНик §f" + nickname + "§a освобождён. "
                                + "Настоящий владелец сможет заявить его заново.");

                        return;
                    }

                    sender.sendMessage("§7Ник §f" + nickname + "§7 ни за кем не заявлен.");
                });
            } catch (ApiClient.ApiException e) {
                getServer().getScheduler().runTask(this, () ->
                        sender.sendMessage("§cНе удалось спросить сайт: " + e.getMessage()));
            }
        });
    }
}
