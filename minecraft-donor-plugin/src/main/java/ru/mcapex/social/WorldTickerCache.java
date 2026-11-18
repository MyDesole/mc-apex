package ru.mcapex.social;

import org.bukkit.Bukkit;
import org.bukkit.World;
import java.lang.reflect.*;
import java.util.*;

/** WindSpigot caches worlds before Bukkit scheduler tasks. BW unloads its world inside those tasks. */
final class WorldTickerCache {
    private Method instance;
    private Field tickers,worldHandle;
    boolean ready=true;
    WorldTickerCache(){
        try{
            Class<?> manager=Class.forName("com.windpvp.windspigot.world.WorldTickManager");
            instance=manager.getMethod("getInstance");tickers=manager.getDeclaredField("worldTickers");tickers.setAccessible(true);
            worldHandle=Class.forName("com.windpvp.windspigot.world.WorldTicker").getField("worldserver");
        }catch(ClassNotFoundException otherServer){if(instance!=null)ready=false;instance=null;}
        catch(ReflectiveOperationException ex){ready=false;}
    }
    void beforeUnload(World world)throws ReflectiveOperationException{
        if(instance==null)return;
        if(!Bukkit.isPrimaryThread())throw new IllegalStateException("World unload must run on the main thread");
        Object handle=world.getClass().getMethod("getHandle").invoke(world);
        @SuppressWarnings("unchecked") List<Object> cached=(List<Object>)tickers.get(instance.invoke(null));
        // This private BW unload runs in the scheduler, before WorldTickManager obtains its iterator.
        for(Iterator<Object> it=cached.iterator();it.hasNext();)if(worldHandle.get(it.next())==handle)it.remove();
    }
}
