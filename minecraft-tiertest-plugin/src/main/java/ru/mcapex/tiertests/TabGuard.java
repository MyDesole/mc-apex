package ru.mcapex.tiertests;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.ProfileService;
import dev.revere.alley.core.profile.enums.ProfileState;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.Listener;
import org.bukkit.event.player.PlayerQuitEvent;

import java.lang.reflect.Method;
import java.util.HashSet;
import java.util.Set;
import java.util.UUID;

/**
 * Пока игрок в матче (дуэль, ботов, FFA, событие, наблюдение) или в мире BedWars,
 * ники над головой ведёт Alley/BedWars со своими командными цветами, а TAB
 * ставится на паузу для этого игрока. В лобби и хабе TAB снова рисует ник
 * с тиром. TAB подключается через рефлексию: без него плагин просто молчит.
 */
final class TabGuard implements Listener {

    private final ApexTierTests plugin;
    private final Set<UUID> paused = new HashSet<UUID>();
    private Object nameTags;
    private Method getPlayer;
    private Method pause;
    private Method resume;
    private boolean ready;

    TabGuard(ApexTierTests plugin) {
        this.plugin = plugin;
    }

    void start() {
        if (Bukkit.getPluginManager().getPlugin("TAB") == null) return;
        Bukkit.getPluginManager().registerEvents(this, plugin);
        Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                tick();
            }
        }, 40L, 10L);
    }

    private boolean bind() {
        if (ready) return true;
        try {
            Class<?> api = Class.forName("me.neznamy.tab.api.TabAPI");
            Object instance = api.getMethod("getInstance").invoke(null);
            nameTags = api.getMethod("getNameTagManager").invoke(instance);
            if (nameTags == null) return false;
            Class<?> tabPlayer = Class.forName("me.neznamy.tab.api.TabPlayer");
            // методы берём у интерфейса: класс реализации закрыт для чужих плагинов
            Class<?> manager = Class.forName("me.neznamy.tab.api.nametag.NameTagManager");
            getPlayer = api.getMethod("getPlayer", UUID.class);
            pause = manager.getMethod("pauseTeamHandling", tabPlayer);
            resume = manager.getMethod("resumeTeamHandling", tabPlayer);
            this.api = instance;
            ready = true;
            return true;
        } catch (Throwable t) {
            return false;
        }
    }

    private Object api;

    private void tick() {
        if (!bind()) return;
        ProfileService profiles = AlleyPlugin.getInstance().getService(ProfileService.class);
        for (Player p : Bukkit.getOnlinePlayers()) {
            boolean shouldPause = inMatch(p, profiles);
            boolean isPaused = paused.contains(p.getUniqueId());
            if (shouldPause == isPaused) continue;
            try {
                Object tp = getPlayer.invoke(api, p.getUniqueId());
                if (tp == null) continue;
                if (shouldPause) {
                    pause.invoke(nameTags, tp);
                    paused.add(p.getUniqueId());
                } else {
                    resume.invoke(nameTags, tp);
                    paused.remove(p.getUniqueId());
                }
            } catch (Throwable t) {
                plugin.getLogger().warning("TabGuard: " + t);
                return;
            }
        }
    }

    private boolean inMatch(Player p, ProfileService profiles) {
        String world = p.getWorld().getName();
        // арены BedWars (bw_*) ведёт BedWars; лобби BedWars (bw_lobby) обычное, там ник рисует TAB
        if (world.startsWith("bw_") && !world.equals("bw_lobby")) return true;
        Profile pr = profiles.getProfile(p.getUniqueId());
        if (pr == null) return false;
        ProfileState s = pr.getState();
        return s == ProfileState.PLAYING || s == ProfileState.FIGHTING_BOT || s == ProfileState.SPECTATING
                || s == ProfileState.PLAYING_EVENT || s == ProfileState.FFA;
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        paused.remove(e.getPlayer().getUniqueId());
    }
}
