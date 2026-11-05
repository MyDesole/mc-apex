package ru.mcapex.tiertests;

import org.bukkit.Material;

/**
 * Карты fastbridge: длина пропасти между островками. У бесконечной карты финиша нет,
 * результат - сколько блоков построил до падения.
 */
enum FbMap {
    B16("16", 16, "16 блоков", Material.WOOL, 0),
    B24("24", 24, "24 блока", Material.WOOL, 4),
    B32("32", 32, "32 блока", Material.WOOL, 1),
    B64("64", 64, "64 блока", Material.WOOL, 14),
    INF("inf", -1, "Бесконечно", Material.EMERALD_BLOCK, 0);

    /** Длина дорожки для бесконечной карты: дальше игрок не уйдёт. */
    static final int INF_LIMIT = 1500;

    final String id;
    /** Пропасть в блоках, -1 для бесконечной. */
    final int gap;
    final String title;
    final Material icon;
    final int iconData;

    FbMap(String id, int gap, String title, Material icon, int iconData) {
        this.id = id;
        this.gap = gap;
        this.title = title;
        this.icon = icon;
        this.iconData = iconData;
    }

    boolean endless() {
        return gap < 0;
    }

    static FbMap of(String id) {
        if (id != null) {
            for (FbMap m : values()) {
                if (m.id.equalsIgnoreCase(id) || (m == INF && (id.equalsIgnoreCase("infinite") || id.equals("∞")))) return m;
            }
        }
        return null;
    }

    /** Результат лучше? Время - меньше, дистанция - больше. */
    boolean better(long candidate, long current) {
        return endless() ? candidate > current : candidate < current;
    }
}
