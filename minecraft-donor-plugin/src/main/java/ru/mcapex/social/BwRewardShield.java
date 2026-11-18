package ru.mcapex.social;

import com.andrei1058.bedwars.api.arena.IArena;
import org.bukkit.event.*;
import org.bukkit.plugin.RegisteredListener;
import java.util.*;

/** Suppresses only native persistent counters/rewards, leaving game events and cleanup intact. */
final class BwRewardShield {
    static final Set<String> TARGETS=new HashSet<>(Arrays.asList(
        "com.andrei1058.bedwars.stats.StatsListener",
        "com.andrei1058.bedwars.money.internal.MoneyListeners",
        "com.andrei1058.bedwars.levels.internal.LevelListeners"));
    final PrivateMatches games;
    boolean complete;
    BwRewardShield(PrivateMatches games){this.games=games;}
    int install(){
        int count=0;Set<String> found=new HashSet<>();
        for(HandlerList list:HandlerList.getHandlerLists())for(RegisteredListener original:list.getRegisteredListeners()){
            if(!TARGETS.contains(original.getListener().getClass().getName()))continue;
            found.add(original.getListener().getClass().getName());
            if(original instanceof Filtered)continue;
            list.unregister(original);list.register(new Filtered(original));count++;
        }
        complete=com.andrei1058.bedwars.BedWars.getEconomy()!=null&&found.contains("com.andrei1058.bedwars.stats.StatsListener")&&found.contains("com.andrei1058.bedwars.levels.internal.LevelListeners")
            &&(!com.andrei1058.bedwars.BedWars.getEconomy().isEconomy()||found.contains("com.andrei1058.bedwars.money.internal.MoneyListeners"));
        return count;
    }
    static IArena arena(Event event){
        if(!event.getClass().getName().startsWith("com.andrei1058.bedwars.api.events."))return null;
        try{return (IArena)event.getClass().getMethod("getArena").invoke(event);}catch(Exception ex){return null;}
    }
    final class Filtered extends RegisteredListener{
        final RegisteredListener original;
        Filtered(RegisteredListener original){super(original.getListener(),(listener,event)->{},original.getPriority(),original.getPlugin(),original.isIgnoringCancelled());this.original=original;}
        @Override public void callEvent(Event event)throws EventException{
            IArena a=arena(event);if(a!=null&&games.privateArena(a))return;
            original.callEvent(event);
        }
    }
}
