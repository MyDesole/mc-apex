package ru.mcapex.auth;

import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.Player;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;

/**
 * Запомненные входы: не заставляем вводить пароль при каждом заходе.
 *
 * Запись живёт ограниченное время (auth.session-minutes) и по желанию
 * привязана к IP: зашёл с другого адреса — пароль спросим заново.
 * Хранится в plugins/ApexAuth/sessions.yml, чтобы пережить перезапуск.
 */
public final class SessionStore {

    private final ApexAuthPlugin plugin;
    private final File file;
    private final Map<String, Entry> entries = new HashMap<>();

    public SessionStore(ApexAuthPlugin plugin) {
        this.plugin = plugin;
        this.file = new File(plugin.getDataFolder(), "sessions.yml");
        load();
    }

    /**
     * Заход подтверждён недавно, с того же IP и тем же аккаунтом сайта?
     *
     * Аккаунт обязателен: ник на сайте можно открепить или отдать другому,
     * и локальная память не должна пускать в обход этого.
     */
    public boolean valid(Player player, String account) {
        if (account == null || account.isEmpty()) {
            return false;
        }

        String uuid = player.getUniqueId().toString();
        Entry entry = entries.get(uuid);

        if (entry == null || !account.equals(entry.account)) {
            return false;
        }

        if (System.currentTimeMillis() >= entry.expires) {
            entries.remove(uuid);
            save();

            return false;
        }

        if (entry.boundIp != null && !entry.boundIp.equals(address(player))) {
            return false;
        }

        return true;
    }

    /** Запомнить вход после успешного пароля. */
    public void remember(Player player, int minutes, boolean bindIp, String account) {
        if (minutes <= 0 || account == null || account.isEmpty()) {
            return;
        }

        Entry entry = new Entry();
        entry.name = player.getName();
        entry.account = account;
        entry.expires = System.currentTimeMillis() + minutes * 60_000L;
        entry.boundIp = bindIp ? address(player) : null;

        entries.put(player.getUniqueId().toString(), entry);
        save();
    }

    /** Забыть вход (например, после снятия привязки на сайте). */
    public void forget(Player player) {
        if (entries.remove(player.getUniqueId().toString()) != null) {
            save();
        }
    }

    private String address(Player player) {
        return player.getAddress() != null && player.getAddress().getAddress() != null
                ? player.getAddress().getAddress().getHostAddress()
                : null;
    }

    private void load() {
        if (!file.isFile()) {
            return;
        }

        YamlConfiguration yaml = YamlConfiguration.loadConfiguration(file);

        for (String uuid : yaml.getKeys(false)) {
            Entry entry = new Entry();
            entry.name = yaml.getString(uuid + ".name", "");
            entry.account = yaml.getString(uuid + ".account", null);
            entry.expires = yaml.getLong(uuid + ".expires", 0L);
            entry.boundIp = yaml.getString(uuid + ".ip", null);

            if (entry.expires > System.currentTimeMillis() && entry.account != null) {
                entries.put(uuid, entry);
            }
        }
    }

    private void save() {
        YamlConfiguration yaml = new YamlConfiguration();

        for (Map.Entry<String, Entry> item : entries.entrySet()) {
            yaml.set(item.getKey() + ".name", item.getValue().name);
            yaml.set(item.getKey() + ".account", item.getValue().account);
            yaml.set(item.getKey() + ".expires", item.getValue().expires);
            yaml.set(item.getKey() + ".ip", item.getValue().boundIp);
        }

        try {
            if (!plugin.getDataFolder().isDirectory() && !plugin.getDataFolder().mkdirs()) {
                plugin.getLogger().warning("Не удалось создать каталог плагина для sessions.yml");

                return;
            }

            yaml.save(file);
        } catch (IOException e) {
            plugin.getLogger().warning("Не удалось сохранить sessions.yml: " + e.getMessage());
        }
    }

    private static final class Entry {
        String name;
        /** Аккаунт сайта, который подтвердил вход. */
        String account;
        long expires;
        String boundIp;
    }
}
