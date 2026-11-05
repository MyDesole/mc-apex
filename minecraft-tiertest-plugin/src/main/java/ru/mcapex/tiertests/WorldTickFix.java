package ru.mcapex.tiertests;

import org.bukkit.Bukkit;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.world.WorldLoadEvent;
import org.bukkit.event.world.WorldUnloadEvent;
import org.bukkit.plugin.Plugin;

import java.lang.reflect.Field;
import java.util.List;

/**
 * Защита от падения WindSpigot при смене миров.
 *
 * WindSpigot тикает миры своим списком WorldTickManager.worldTickers и пересобирает его, только если
 * изменилось количество миров. BedWars на /bw disableArena выгружает мир арены и тут же поднимает
 * его заново из архива: число миров то же, список не пересобирается, и следующий тик идёт по
 * выгруженному миру: «NullPointerException: chunkProvider is null», сервер падает.
 *
 * Мы сбрасываем этот список при каждой выгрузке и загрузке мира и, кроме того, каждый тик проверяем,
 * что в нём нет миров, которых уже нет у сервера. Список пересоберётся на ближайшем тике.
 */
final class WorldTickFix implements Listener {
    private final Plugin host;
    private Object server;
    private Object manager;
    private Field managerField;
    private Field tickers;
    private Field serverWorlds;
    private Field tickerWorld;
    private int fixed;

    WorldTickFix(Plugin host) {
        this.host = host;
    }

    boolean start() {
        try {
            Class<?> mc = Class.forName("net.minecraft.server.v1_8_R3.MinecraftServer");
            server = mc.getMethod("getServer").invoke(null);
            managerField = mc.getDeclaredField("worldTickerManager");
            managerField.setAccessible(true);
            Class<?> mgr = Class.forName("com.windpvp.windspigot.world.WorldTickManager");
            tickers = mgr.getDeclaredField("worldTickers");
            tickers.setAccessible(true);
            serverWorlds = mc.getField("worlds");
            tickerWorld = Class.forName("com.windpvp.windspigot.world.WorldTicker").getField("worldserver");
        } catch (Throwable t) {
            host.getLogger().info("WorldTickFix: защита не включена (" + t.getClass().getSimpleName() + ": " + t.getMessage() + ")");
            return false;
        }
        Bukkit.getPluginManager().registerEvents(this, host);
        Bukkit.getScheduler().runTaskTimer(host, new Runnable() {
            public void run() {
                verify();
            }
        }, 20L, 1L);
        return true;
    }

    int fixed() {
        return fixed;
    }

    /** Менеджер тиков миров WindSpigot создаёт позже, чем грузятся плагины: берём, когда он появился. */
    private boolean ready() {
        if (manager == null) {
            try {
                manager = managerField.get(server);
            } catch (Throwable t) {
                return false;
            }
        }
        return manager != null;
    }

    private void reset() {
        if (!ready()) return;
        try {
            ((List<?>) tickers.get(manager)).clear();
            fixed++;
        } catch (Throwable t) {
            host.getLogger().warning("WorldTickFix: список миров не сброшен: " + t);
        }
    }

    /** В списке тика остался мир, которого у сервера уже нет: сбрасываем, пока он не упал. */
    private void verify() {
        if (!ready()) return;
        try {
            List<?> list = (List<?>) tickers.get(manager);
            if (list.isEmpty()) return;
            List<?> worlds = (List<?>) serverWorlds.get(server);
            for (Object t : list.toArray()) {
                if (!worlds.contains(tickerWorld.get(t))) {
                    reset();
                    host.getLogger().info("WorldTickFix: убрал выгруженный мир из тика (падения не будет)");
                    return;
                }
            }
        } catch (Throwable ignored) {
        }
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onUnload(WorldUnloadEvent e) {
        reset();
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onLoad(WorldLoadEvent e) {
        reset();
    }
}
