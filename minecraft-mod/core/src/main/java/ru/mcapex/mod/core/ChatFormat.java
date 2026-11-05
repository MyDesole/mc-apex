package ru.mcapex.mod.core;

import java.util.ArrayList;
import java.util.List;

/**
 * Нейтральное описание вывода /apex: строки из сегментов (текст + цвет).
 * Загрузчики превращают их в свои компоненты чата.
 */
public final class ChatFormat {
    public static final int GRAY = 0xaaaaaa;
    public static final int WHITE = 0xffffff;
    public static final int ACCENT = 0xa78bfa;
    public static final int NONE = -1;

    public static final class Seg {
        public final String text;
        public final int rgb;
        public final boolean bold;

        Seg(String text, int rgb, boolean bold) {
            this.text = text;
            this.rgb = rgb;
            this.bold = bold;
        }
    }

    public static final class Line {
        public final List<Seg> segs = new ArrayList<Seg>();
        public String clickUrl;

        Line add(String text, int rgb, boolean bold) {
            segs.add(new Seg(text, rgb, bold));
            return this;
        }
    }

    private ChatFormat() {
    }

    public static List<Line> player(PlayerInfo p) {
        List<Line> out = new ArrayList<Line>();
        int accent = p.badge != null ? p.badge.rgb : ACCENT;

        Line head = new Line().add("APEX ", ACCENT, true);
        head.add(p.username, accent, true);
        if (p.minecraftUsername != null && !p.minecraftUsername.equalsIgnoreCase(p.username)) {
            head.add("  (Minecraft: " + p.minecraftUsername + ")", GRAY, false);
        }
        out.add(head);

        Line tier = new Line().add("Тир: ", GRAY, false);
        if (p.tier != null) {
            tier.add(p.tier, p.badge != null && !p.badge.bridge ? p.badge.rgb : accent, true);
            tier.add(" (" + trim(p.tierScore) + " очк.)", GRAY, false);
        } else {
            tier.add("без тира", GRAY, false);
        }
        out.add(tier);

        if (p.badge != null && p.badge.bridge) {
            out.add(new Line().add("Звание бриджера: ", GRAY, false).add(p.badge.label, p.badge.rgb, true)
                    .add(" [" + p.badge.shortLabel + "]", GRAY, false));
        }

        if (!p.visible) {
            out.add(new Line().add("Профиль закрыт, подробности скрыты", GRAY, false));
        } else {
            for (PlayerInfo.AspectGroup g : p.aspects) {
                Line l = new Line().add("Аспекты " + g.mode.toUpperCase() + ": ", GRAY, false).add(trim(g.percent) + "%", WHITE, true);
                StringBuilder sb = new StringBuilder();
                for (PlayerInfo.Aspect a : g.items) {
                    sb.append(sb.length() == 0 ? " (" : ", ").append(a.label).append(' ').append(trim(a.value));
                }
                if (sb.length() > 0) {
                    l.add(sb.append(')').toString(), GRAY, false);
                }
                out.add(l);
            }
            if (p.bridgeCount > 0) {
                out.add(new Line().add("Виды бриджа: ", GRAY, false)
                        .add(p.bridgeCount + " (сумма аспектов " + p.bridgeAspectsTotal + ")", WHITE, false));
                for (PlayerInfo.Technique t : p.techniques) {
                    out.add(new Line().add("  - " + t.label, WHITE, false)
                            .add("  ст " + t.stability + " / ск " + t.speed + " / сл " + t.difficulty, GRAY, false));
                }
            }
            if (p.clanName != null) {
                Line clan = new Line().add("Клан: ", GRAY, false).add("[" + p.clanTag + "] " + p.clanName, WHITE, true);
                String role = p.clanTitle != null && !p.clanTitle.isEmpty() ? p.clanTitle : roleName(p.clanRole);
                if (role != null) {
                    clan.add("  " + role, GRAY, false);
                }
                out.add(clan);
            } else {
                out.add(new Line().add("Клан: нет", GRAY, false));
            }
            if (p.friendsCount >= 0 || p.achievementsCount >= 0) {
                out.add(new Line().add("Друзей: ", GRAY, false).add(String.valueOf(Math.max(p.friendsCount, 0)), WHITE, false)
                        .add("   Ачивок: ", GRAY, false).add(String.valueOf(Math.max(p.achievementsCount, 0)), WHITE, false));
            }
        }

        Line link = new Line().add("Профиль: ", GRAY, false).add(p.profileUrl, 0x60a5fa, false);
        link.clickUrl = p.profileUrl;
        out.add(link);
        return out;
    }

    static String roleName(String role) {
        if (role == null) {
            return null;
        }
        switch (role) {
            case "leader":
                return "лидер";
            case "officer":
            case "co_leader":
                return "офицер";
            case "member":
                return "участник";
            default:
                return role;
        }
    }

    static String trim(double v) {
        return v == Math.floor(v) ? String.valueOf((long) v) : String.format(java.util.Locale.ROOT, "%.1f", v);
    }

    /** Ближайший из 16 цветов чата (индекс 0..15) для версий без hex-цветов, например 1.8.9. */
    public static int nearestLegacyIndex(int rgb) {
        int[] palette = {0x000000, 0x0000aa, 0x00aa00, 0x00aaaa, 0xaa0000, 0xaa00aa, 0xffaa00, 0xaaaaaa,
                0x555555, 0x5555ff, 0x55ff55, 0x55ffff, 0xff5555, 0xff55ff, 0xffff55, 0xffffff};
        int best = 15;
        long bestD = Long.MAX_VALUE;
        for (int i = 0; i < palette.length; i++) {
            int dr = ((rgb >> 16) & 255) - ((palette[i] >> 16) & 255);
            int dg = ((rgb >> 8) & 255) - ((palette[i] >> 8) & 255);
            int db = (rgb & 255) - (palette[i] & 255);
            long d = (long) dr * dr + (long) dg * dg + (long) db * db;
            if (d < bestD) {
                bestD = d;
                best = i;
            }
        }
        return best;
    }
}
