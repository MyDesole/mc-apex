package ru.mcapex.tiertests;

import org.bukkit.configuration.ConfigurationSection;
import org.bukkit.configuration.file.YamlConfiguration;
import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.StandardCopyOption;
import java.util.*;
import java.util.logging.Logger;

/** Main-thread profiles; synchronized protection checks shared with replay writer. */
final class FbPerks {
    static final String[] RANKS = {"default", "vip", "mvp", "mvp_plus", "apex", "legend"};
    private static final int[][] DEFAULTS = {
        {0, 0, 40, 7, 50}, {2, 5, 80, 30, 250}, {3, 10, 120, 45, 500},
        {5, 20, 200, 60, 1000}, {8, 30, 300, 90, 1500}, {12, 50, 400, 120, 2000}
    };
    private final File file;
    private final Logger log;
    private final YamlConfiguration data;
    private final YamlConfiguration config;
    private boolean dirty;

    FbPerks(File directory, Logger log) {
        this.log = log;
        file = new File(directory, "fastbridge-profiles.yml");
        data = YamlConfiguration.loadConfiguration(file);
        File cfg = new File(directory, "fastbridge-perks.yml");
        config = YamlConfiguration.loadConfiguration(cfg);
        String[] keys = {"presets", "favorites", "replays", "days", "history"};
        for (int rank = 0; rank < RANKS.length; rank++) {
            for (int k = 0; k < keys.length; k++) config.addDefault("limits." + RANKS[rank] + "." + keys[k], DEFAULTS[rank][k]);
        }
        config.options().copyDefaults(true);
        config.addDefault("private-min-rank", 4);
        config.options().copyDefaults(true);
        try { config.save(cfg); } catch (IOException e) { log.warning("FastBridge: config: " + e); }
        if (!data.contains("introduced")) {
            data.set("introduced", System.currentTimeMillis());
            dirty = true;
            flush();
        }
    }

    int limit(int rank, String kind) {
        rank = Math.max(0, Math.min(5, rank));
        String[] keys = {"presets", "favorites", "replays", "days", "history"};
        int index = Arrays.asList(keys).indexOf(kind);
        if (index < 0) throw new IllegalArgumentException(kind);
        return Math.max(kind.equals("replays") || kind.equals("days") ? 1 : 0,
                Math.min(10000, config.getInt("limits." + RANKS[rank] + "." + kind, DEFAULTS[rank][index])));
    }

    boolean privateAllowed(int rank) { return rank >= Math.max(1, config.getInt("private-min-rank", 4)); }
    private String root(UUID u) { return "players." + u; }
    synchronized String setting(UUID u, String key, String fallback) { return data.getString(root(u) + ".settings." + key, fallback); }
    synchronized void putSetting(UUID u, String key, String value) { data.set(root(u) + ".settings." + key, value); dirty = true; }

    synchronized List<String> presets(UUID u) {
        ConfigurationSection section = data.getConfigurationSection(root(u) + ".presets");
        return section == null ? new ArrayList<String>() : new ArrayList<String>(section.getKeys(false));
    }

    static boolean validName(String name) { return name != null && name.matches("[A-Za-z0-9_-]{1,16}"); }

    synchronized boolean savePreset(UUID u, String name, String map, int rank) {
        if (!validName(name) || limit(rank, "presets") == 0) return false;
        List<String> names = presets(u);
        if (!names.contains(name) && names.size() >= limit(rank, "presets")) return false;
        String path = root(u) + ".presets." + name;
        data.set(path + ".map", map);
        data.set(path + ".block", setting(u, "block", "sandstone"));
        data.set(path + ".effect", setting(u, "effect", "none"));
        data.set(path + ".auto", setting(u, "auto", "on"));
        dirty = true;
        return true;
    }

    synchronized Map<String, String> preset(UUID u, String name, int rank) {
        if (!validName(name) || rank == 0) return null;
        List<String> names = presets(u);
        int index = names.indexOf(name);
        if (index < 0 || index >= limit(rank, "presets")) return null;
        String path = root(u) + ".presets." + name;
        Map<String, String> result = new HashMap<String, String>();
        for (String key : new String[]{"map", "block", "effect", "auto"}) result.put(key, data.getString(path + "." + key, ""));
        return result;
    }

    synchronized void deletePreset(UUID u, String name) {
        if (validName(name)) { data.set(root(u) + ".presets." + name, null); dirty = true; }
    }

    synchronized List<String> favorites(UUID u) { return new ArrayList<String>(data.getStringList(root(u) + ".favorites")); }
    synchronized boolean favorite(UUID u, String id) { return favorites(u).contains(id); }

    synchronized boolean toggleFavorite(UUID u, String id, int rank) {
        List<String> list = favorites(u);
        if (!list.remove(id)) {
            if (list.size() >= limit(rank, "favorites")) return false;
            list.add(id);
        }
        data.set(root(u) + ".favorites", list);
        dirty = true;
        flush(); // Acknowledged favorite must survive a restart before the next periodic flush.
        return true;
    }

    synchronized void markPrivate(String id, UUID owner) {
        data.set("private-replays." + id, owner.toString()); dirty = true;
    }
    synchronized boolean privateReplay(String id) { return data.contains("private-replays." + id); }
    synchronized boolean mayWatch(String id, UUID viewer) {
        String owner = data.getString("private-replays." + id);
        return owner == null || owner.equals(viewer.toString());
    }

    synchronized boolean protectedReplay(File f, Set<String> records) {
        String id = f.getName().replace(".json", "");
        if (records.contains(id)) return true;
        ConfigurationSection players = data.getConfigurationSection("players");
        if (players != null) for (String u : players.getKeys(false)) {
            if (data.getStringList("players." + u + ".favorites").contains(id)) return true;
        }
        return false;
    }

    synchronized boolean deleteReplay(File f, Set<String> records, int index, int rank, long now) {
        if (protectedReplay(f, records)) return false;
        String id = f.getName().replace(".json", "");
        boolean expired = f.lastModified() >= data.getLong("introduced")
                && now - f.lastModified() > limit(rank, "days") * 86400000L;
        if (index < limit(rank, "replays") && !expired) return false;
        if (!f.delete()) return false;
        data.set("private-replays." + id, null); dirty = true;
        return true;
    }

    synchronized void history(UUID u, int rank, Map<String, Object> entry) {
        List<Map<?, ?>> list = new ArrayList<Map<?, ?>>(data.getMapList(root(u) + ".history"));
        list.add(0, new LinkedHashMap<String, Object>(entry));
        int cap = limit(rank, "history");
        if (list.size() > cap) list = new ArrayList<Map<?, ?>>(list.subList(0, cap));
        data.set(root(u) + ".history", list); dirty = true;
    }
    synchronized List<Map<?, ?>> history(UUID u) { return new ArrayList<Map<?, ?>>(data.getMapList(root(u) + ".history")); }

    synchronized void flush() {
        if (!dirty) return;
        try {
            File temp = new File(file.getParentFile(), file.getName() + ".tmp");
            data.save(temp);
            try { Files.move(temp.toPath(), file.toPath(), StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE); }
            catch (java.nio.file.AtomicMoveNotSupportedException e) { Files.move(temp.toPath(), file.toPath(), StandardCopyOption.REPLACE_EXISTING); }
            dirty = false;
        } catch (IOException e) { log.warning("FastBridge: profiles not saved: " + e); }
    }
}
