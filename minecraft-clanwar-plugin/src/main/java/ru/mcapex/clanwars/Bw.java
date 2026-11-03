package ru.mcapex.clanwars;

import com.andrei1058.bedwars.api.BedWars;
import com.andrei1058.bedwars.api.arena.GameState;
import com.andrei1058.bedwars.api.arena.IArena;
import com.andrei1058.bedwars.api.arena.team.ITeam;
import com.andrei1058.bedwars.api.events.gameplay.GameEndEvent;
import com.andrei1058.bedwars.api.events.player.PlayerJoinArenaEvent;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.Listener;
import org.bukkit.plugin.RegisteredServiceProvider;

import java.util.List;
import java.util.Set;
import java.util.UUID;

/** Мост к BedWars1058. Грузится только если плагин BedWars на сервере есть. */
final class Bw implements Listener {
    private final ApexClanWars plugin;
    private final String arenaName;

    Bw(ApexClanWars plugin, String arenaName) {
        this.plugin = plugin;
        this.arenaName = arenaName;
    }

    private BedWars api() {
        RegisteredServiceProvider<BedWars> r = Bukkit.getServicesManager().getRegistration(BedWars.class);
        return r == null ? null : r.getProvider();
    }

    /** Свободна ли арена КВ. */
    String check() {
        BedWars api = api();
        if (api == null) return "BedWars1058 не загружен.";
        IArena a = api.getArenaUtil().getArenaByName(arenaName);
        if (a == null) return "Арена " + arenaName + " не найдена.";
        if (a.getStatus() != GameState.waiting) return "Арена КВ сейчас занята.";
        return null;
    }

    /** Заводит игроков обеих кланов на арену: первый клан - Red, второй - Blue. */
    void join(List<Player> first, List<Player> second) {
        IArena a = api().getArenaUtil().getArenaByName(arenaName);
        for (Player p : first) a.addPlayer(p, true);
        for (Player p : second) a.addPlayer(p, true);
        ITeam red = a.getTeam("Red");
        ITeam blue = a.getTeam("Blue");
        if (red != null) for (Player p : first) red.addPlayers(p);
        if (blue != null) for (Player p : second) blue.addPlayers(p);
    }

    /** Запускает отсчёт 5 секунд. */
    void start() {
        IArena a = api().getArenaUtil().getArenaByName(arenaName);
        if (a.getStatus() == GameState.waiting) a.changeStatus(GameState.starting);
        if (a.getStartingTask() != null) a.getStartingTask().setCountdown(5);
    }

    boolean inArena(Player p) {
        BedWars api = api();
        return api != null && api.getArenaUtil().getArenaByPlayer(p) != null;
    }

    @EventHandler
    public void onJoin(PlayerJoinArenaEvent e) {
        if (!e.getArena().getArenaName().equalsIgnoreCase(arenaName)) return;
        Set<UUID> allowed = plugin.bwAllowed();
        if (e.getPlayer() == null || !allowed.contains(e.getPlayer().getUniqueId())) {
            e.setCancelled(true);
            if (e.getPlayer() != null) e.getPlayer().sendMessage("§cЭта арена только для клановых войн.");
        }
    }

    @EventHandler
    public void onEnd(GameEndEvent e) {
        if (!e.getArena().getArenaName().equalsIgnoreCase(arenaName)) return;
        ITeam w = e.getTeamWinner();
        plugin.bwFinished(w == null ? null : w.getName());
    }
}
