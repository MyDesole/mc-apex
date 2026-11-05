package ru.mcapex.tiertests;

import org.bukkit.configuration.ConfigurationSection;
import org.bukkit.configuration.file.YamlConfiguration;

import java.io.File;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.Comparator;
import java.util.Iterator;
import java.util.List;
import java.util.UUID;
import java.util.logging.Logger;

/**
 * Рекорды и попытки игроков по картам fastbridge.
 *
 * Для каждой пары игрок и карта храним абсолютный рекорд (и запись, где он поставлен),
 * а также финиши и попытки за последние 24 часа - из них считается «рекорд сессии».
 */
final class FbStats {
    static final long DAY = 24L * 3600 * 1000;

    private final File file;
    private final Logger log;
    private YamlConfiguration yml;
    private boolean dirty;

    FbStats(File file, Logger log) {
        this.file = file;
        this.log = log;
        this.yml = YamlConfiguration.loadConfiguration(file);
    }

    private String base(UUID u, FbMap m) {
        return "players." + u + ".maps." + m.id;
    }

    /** Любимая карта игрока (последняя выбранная). */
    FbMap lastMap(UUID u) {
        FbMap m = FbMap.of(yml.getString("players." + u + ".last_map"));
        return m != null ? m : FbMap.B32;
    }

    void setLastMap(UUID u, String name, FbMap m) {
        yml.set("players." + u + ".name", name);
        yml.set("players." + u + ".last_map", m.id);
        dirty = true;
    }

    /** UUID игрока по нику (если он когда-то играл) или null. */
    UUID uuidByName(String name) {
        ConfigurationSection players = yml.getConfigurationSection("players");
        if (players == null) return null;
        for (String u : players.getKeys(false)) {
            if (name.equalsIgnoreCase(yml.getString("players." + u + ".name"))) {
                try {
                    return UUID.fromString(u);
                } catch (IllegalArgumentException ignored) {
                }
            }
        }
        return null;
    }

    /** Абсолютный рекорд или -1. */
    long best(UUID u, FbMap m) {
        return yml.contains(base(u, m) + ".best") ? yml.getLong(base(u, m) + ".best") : -1;
    }

    String bestReplay(UUID u, FbMap m) {
        return yml.getString(base(u, m) + ".best_replay");
    }

    /** Попытка началась (первый блок). */
    void attempt(UUID u, FbMap m) {
        List<Long> list = prune(yml.getLongList(base(u, m) + ".attempts"));
        list.add(System.currentTimeMillis());
        yml.set(base(u, m) + ".attempts", list);
        dirty = true;
    }

    int attempts24h(UUID u, FbMap m) {
        return prune(yml.getLongList(base(u, m) + ".attempts")).size();
    }

    /**
     * Законченная попытка. Возвращает true, если это новый абсолютный рекорд.
     */
    boolean finish(UUID u, String name, FbMap m, long value, String replayId) {
        String b = base(u, m);
        yml.set("players." + u + ".name", name);
        List<String> fin = new ArrayList<String>(yml.getStringList(b + ".finishes"));
        long now = System.currentTimeMillis();
        Iterator<String> it = fin.iterator();
        while (it.hasNext()) {
            String[] p = it.next().split(":");
            if (p.length != 2 || now - Long.parseLong(p[0]) > DAY) it.remove();
        }
        fin.add(now + ":" + value);
        yml.set(b + ".finishes", fin);
        long cur = best(u, m);
        boolean record = cur < 0 || m.better(value, cur);
        if (record) {
            yml.set(b + ".best", value);
            yml.set(b + ".best_replay", replayId);
        }
        dirty = true;
        return record;
    }

    /** Лучший результат за 24 часа или -1. */
    long best24h(UUID u, FbMap m) {
        long best = -1;
        long now = System.currentTimeMillis();
        for (String s : yml.getStringList(base(u, m) + ".finishes")) {
            String[] p = s.split(":");
            if (p.length != 2 || now - Long.parseLong(p[0]) > DAY) continue;
            long v = Long.parseLong(p[1]);
            if (best < 0 || m.better(v, best)) best = v;
        }
        return best;
    }

    /** Лучшие игроки карты: {ник, результат}. */
    List<String[]> top(final FbMap m, int limit) {
        List<String[]> rows = new ArrayList<String[]>();
        ConfigurationSection players = yml.getConfigurationSection("players");
        if (players != null) {
            for (String u : players.getKeys(false)) {
                String path = "players." + u + ".maps." + m.id + ".best";
                if (yml.contains(path)) rows.add(new String[]{yml.getString("players." + u + ".name", "?"), String.valueOf(yml.getLong(path))});
            }
        }
        Collections.sort(rows, new Comparator<String[]>() {
            public int compare(String[] a, String[] b) {
                long x = Long.parseLong(a[1]), y = Long.parseLong(b[1]);
                return m.endless() ? Long.compare(y, x) : Long.compare(x, y);
            }
        });
        return rows.size() > limit ? rows.subList(0, limit) : rows;
    }

    /** Идентификаторы рекордных записей: их не удаляем. */
    List<String> recordReplays() {
        List<String> ids = new ArrayList<String>();
        ConfigurationSection players = yml.getConfigurationSection("players");
        if (players == null) return ids;
        for (String u : players.getKeys(false)) {
            ConfigurationSection maps = yml.getConfigurationSection("players." + u + ".maps");
            if (maps == null) continue;
            for (String m : maps.getKeys(false)) {
                String id = yml.getString("players." + u + ".maps." + m + ".best_replay");
                if (id != null) ids.add(id);
            }
        }
        return ids;
    }

    private List<Long> prune(List<Long> in) {
        List<Long> out = new ArrayList<Long>();
        long now = System.currentTimeMillis();
        for (Long t : in) if (now - t <= DAY) out.add(t);
        return out;
    }

    void flush() {
        if (!dirty) return;
        try {
            yml.save(file);
            dirty = false;
        } catch (IOException ex) {
            log.warning("FastBridge: рекорды не сохранены: " + ex.getMessage());
        }
    }
}
