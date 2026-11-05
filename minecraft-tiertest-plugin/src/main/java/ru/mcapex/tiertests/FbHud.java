package ru.mcapex.tiertests;

import org.bukkit.Bukkit;
import org.bukkit.ChatColor;
import org.bukkit.entity.Player;
import org.bukkit.scoreboard.DisplaySlot;
import org.bukkit.scoreboard.Objective;
import org.bukkit.scoreboard.Scoreboard;
import org.bukkit.scoreboard.Team;

import java.lang.reflect.Field;
import java.lang.reflect.Method;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.logging.Logger;

/**
 * Всё, что игрок видит поверх игры: полоса над инвентарём (action bar) и скорборд сбоку.
 *
 * Скорборд рисуем свой, на отдельной доске игрока. Alley вне своей зоны доску не показывает
 * (его доску снимает AlleyGate), поэтому они не мешают друг другу.
 */
final class FbHud {
    /** Что показывать игроку: заголовок и строки, либо null, если это не наш игрок. */
    interface Source {
        String title(Player p);

        List<String> lines(Player p);
    }

    private final Logger log;
    private final Source source;
    private final Map<UUID, Sidebar> own = new HashMap<UUID, Sidebar>();

    FbHud(Logger log, Source source) {
        this.log = log;
        this.source = source;
    }

    /* ----------------------------- свой скорборд ----------------------------- */

    /** Обновить скорборд игрока. */
    void refresh(Player p) {
        List<String> lines = source.lines(p);
        if (lines == null) {
            release(p);
            return;
        }
        Sidebar s = own.get(p.getUniqueId());
        if (s == null) {
            s = new Sidebar(p);
            own.put(p.getUniqueId(), s);
        }
        s.show(source.title(p), lines);
    }

    void release(Player p) {
        Sidebar s = own.remove(p.getUniqueId());
        if (s != null && p.isOnline()) p.setScoreboard(Bukkit.getScoreboardManager().getMainScoreboard());
    }

    static final class Sidebar {
        private final Player p;
        private final Scoreboard sb;
        private final Objective obj;
        private final String[] entries = new String[15];
        private final String[] last = new String[15];
        private int shown;

        Sidebar(Player p) {
            this.p = p;
            sb = Bukkit.getScoreboardManager().getNewScoreboard();
            obj = sb.registerNewObjective("fb", "dummy");
            obj.setDisplaySlot(DisplaySlot.SIDEBAR);
            for (int i = 0; i < entries.length; i++) {
                entries[i] = ChatColor.values()[i].toString() + ChatColor.RESET;
                Team t = sb.registerNewTeam("l" + i);
                t.addEntry(entries[i]);
            }
            p.setScoreboard(sb);
        }

        void show(String title, List<String> lines) {
            if (p.getScoreboard() != sb) p.setScoreboard(sb);
            if (title != null && !title.equals(obj.getDisplayName())) obj.setDisplayName(cut(title, 32));
            int n = Math.min(lines.size(), entries.length);
            for (int i = 0; i < n; i++) {
                String text = lines.get(i);
                if (!text.equals(last[i])) {
                    setLine(i, text);
                    last[i] = text;
                }
                obj.getScore(entries[i]).setScore(n - i);
            }
            for (int i = n; i < shown; i++) {
                sb.resetScores(entries[i]);
                last[i] = null;
            }
            shown = n;
        }

        private void setLine(int i, String text) {
            Team t = sb.getTeam("l" + i);
            String prefix = text.length() <= 16 ? text : text.substring(0, 16);
            String rest = text.length() <= 16 ? "" : text.substring(16);
            // не рвём цветовой код пополам
            if (prefix.endsWith("§") && !rest.isEmpty()) {
                prefix = prefix.substring(0, 15);
                rest = "§" + rest;
            }
            String suffix = rest.isEmpty() ? "" : (ChatColor.getLastColors(prefix) + rest);
            t.setPrefix(prefix);
            t.setSuffix(cut(suffix, 16));
        }
    }

    private static String cut(String s, int n) {
        return s.length() <= n ? s : s.substring(0, n);
    }

    /* ----------------------------- полоса над инвентарём ----------------------------- */

    private static Method chatSerializer;
    private static java.lang.reflect.Constructor<?> packetCtor;
    private static Method getHandle;
    private static Field connectionField;
    private static Method sendPacket;
    private static boolean nmsFailed;

    /** Action bar для 1.8: пакет чата с позицией 2. */
    static void actionBar(Player p, String text) {
        if (nmsFailed) return;
        try {
            if (packetCtor == null) {
                String v = Bukkit.getServer().getClass().getPackage().getName().split("\\.")[3];
                Class<?> baseComp = Class.forName("net.minecraft.server." + v + ".IChatBaseComponent");
                Class<?> serializer = Class.forName("net.minecraft.server." + v + ".IChatBaseComponent$ChatSerializer");
                chatSerializer = serializer.getMethod("a", String.class);
                Class<?> packet = Class.forName("net.minecraft.server." + v + ".PacketPlayOutChat");
                packetCtor = packet.getConstructor(baseComp, byte.class);
                Class<?> craftPlayer = Class.forName("org.bukkit.craftbukkit." + v + ".entity.CraftPlayer");
                getHandle = craftPlayer.getMethod("getHandle");
                Class<?> entityPlayer = Class.forName("net.minecraft.server." + v + ".EntityPlayer");
                connectionField = entityPlayer.getField("playerConnection");
                Class<?> conn = Class.forName("net.minecraft.server." + v + ".PlayerConnection");
                sendPacket = conn.getMethod("sendPacket", Class.forName("net.minecraft.server." + v + ".Packet"));
            }
            Object comp = chatSerializer.invoke(null, "{\"text\":\"" + text.replace("\\", "\\\\").replace("\"", "\\\"") + "\"}");
            Object packetObj = packetCtor.newInstance(comp, (byte) 2);
            Object handle = getHandle.invoke(p);
            sendPacket.invoke(connectionField.get(handle), packetObj);
        } catch (Throwable t) {
            nmsFailed = true;
            Bukkit.getLogger().warning("FastBridge: action bar недоступен: " + t);
        }
    }

    static List<String> list(String... lines) {
        List<String> out = new ArrayList<String>();
        for (String l : lines) out.add(l);
        return out;
    }
}
