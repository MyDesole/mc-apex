package ru.mcapex.tiertests;

import com.andrei1058.bedwars.api.arena.GameState;
import com.andrei1058.bedwars.api.arena.IArena;
import com.andrei1058.bedwars.arena.Arena;
import com.andrei1058.bedwars.api.events.gameplay.GameEndEvent;
import com.andrei1058.bedwars.api.events.player.PlayerKillEvent;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import java.util.*;

final class BattleStyleBw implements Listener {
    private final BattleStyle styles;
    BattleStyleBw(BattleStyle styles){this.styles=styles;}
    void start(){Bukkit.getPluginManager().registerEvents(this,styles.plugin);}
    boolean inCombat(Player p){IArena arena=Arena.getArenaByPlayer(p);return arena!=null&&arena.getStatus()==GameState.playing;}
    @EventHandler(priority=EventPriority.MONITOR) public void killed(PlayerKillEvent e){styles.kill(e.getKiller(),e.getVictim());}
    @EventHandler(priority=EventPriority.MONITOR) public void ended(GameEndEvent e){
        if(e.getArena()==null||e.getWinners()==null)return;
        Set<UUID> audience=new LinkedHashSet<UUID>();
        for(Player p:e.getArena().getPlayers())audience.add(p.getUniqueId());
        for(Player p:e.getArena().getSpectators())audience.add(p.getUniqueId());
        audience.addAll(e.getWinners());if(e.getLosers()!=null)audience.addAll(e.getLosers());
        styles.victory("bw:"+System.identityHashCode(e),e.getWinners(),audience,"BedWars");
    }
}
