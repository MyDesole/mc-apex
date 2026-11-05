package ru.mcapex.mod.core;

import com.google.gson.JsonObject;

/** Значок рядом с ником: тир или звание бриджера, цвет приходит с сервера. */
public final class Badge {
    public final boolean bridge;
    public final String label;
    public final String shortLabel;
    public final int rgb;

    public Badge(boolean bridge, String label, String shortLabel, int rgb) {
        this.bridge = bridge;
        this.label = label;
        this.shortLabel = shortLabel;
        this.rgb = rgb;
    }

    /** Без тира сайт отдаёт label = null: значка нет. */
    public boolean hasLabel() {
        return label != null && !label.isEmpty();
    }

    /** Текст для узких мест (ник, таб): короткое звание или тир. */
    public String text() {
        return shortLabel != null && !shortLabel.isEmpty() ? shortLabel : label;
    }

    static Badge parse(JsonObject o) {
        if (o == null) {
            return null;
        }
        String color = PlayerInfo.str(o, "color");
        int rgb = 0x6b7280;
        try {
            if (color != null) {
                rgb = Integer.parseInt(color.replace("#", ""), 16);
            }
        } catch (NumberFormatException ignored) {
        }
        return new Badge("bridge".equals(PlayerInfo.str(o, "type")), PlayerInfo.str(o, "label"), PlayerInfo.str(o, "short"), rgb);
    }
}
