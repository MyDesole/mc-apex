package ru.mcapex.tiertests;

import org.bukkit.Bukkit;
import org.bukkit.World;
import org.bukkit.entity.Entity;
import org.bukkit.entity.Player;
import org.bukkit.event.Event;
import org.bukkit.event.EventException;
import org.bukkit.event.HandlerList;
import org.bukkit.event.block.BlockBreakEvent;
import org.bukkit.event.block.BlockCanBuildEvent;
import org.bukkit.event.block.BlockDamageEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import org.bukkit.event.entity.CreatureSpawnEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.entity.EntityRegainHealthEvent;
import org.bukkit.event.entity.EntityShootBowEvent;
import org.bukkit.event.entity.FoodLevelChangeEvent;
import org.bukkit.event.entity.ProjectileLaunchEvent;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.inventory.InventoryDragEvent;
import org.bukkit.event.inventory.InventoryOpenEvent;
import org.bukkit.event.player.PlayerBucketEmptyEvent;
import org.bukkit.event.player.PlayerBucketFillEvent;
import org.bukkit.event.player.PlayerDropItemEvent;
import org.bukkit.event.player.PlayerEvent;
import org.bukkit.event.player.PlayerInteractEntityEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerItemConsumeEvent;
import org.bukkit.event.player.PlayerItemHeldEvent;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.event.player.PlayerPickupItemEvent;
import org.bukkit.event.weather.WeatherChangeEvent;
import org.bukkit.plugin.Plugin;
import org.bukkit.plugin.RegisteredListener;

import java.lang.reflect.Method;
import java.util.ArrayList;
import java.util.List;
import java.util.function.Predicate;

/**
 * «Alley работает только в лобби дуэлей и в самих дуэлях».
 *
 * Alley рассчитан на сервер целиком: его обработчики событий работают в любом мире,
 * а скорборд он возвращает игроку каждый тик. Здесь мы:
 *  - оборачиваем обработчики Alley для событий взаимодействия (клики, блоки, урон, голод,
 *    инвентарь, движение, погода, спавн): вне зоны Alley о них просто не узнаёт;
 *  - убираем у игроков вне зоны доску скорборда Alley и возвращаем её, когда они вернулись.
 *
 * Не трогаем вход, выход, смерть, телепорты и смену миров: профиль игрока
 * в Alley должен оставаться в порядке, чтобы возврат в дуэли работал.
 */
final class AlleyGate {
    private final Plugin host;
    private final Plugin target;
    private final Predicate<Player> inZone;
    private final Predicate<World> zoneWorld;
    private int wrapped;
    private boolean boardsOn = true;
    private Predicate<Player> showable;

    // скорборд Alley (через рефлексию: у харнесса Alley нет)
    private Object assemble;
    private Method createBoard;
    private Method removeBoard;
    private Method getBoards;

    /** Какие слушатели плагина глушить (все, если не задано). Нужно тестам, где цель и хозяин - один плагин. */
    private Predicate<Object> listenerFilter = new Predicate<Object>() {
        public boolean test(Object o) {
            return true;
        }
    };

    AlleyGate(Plugin host, Plugin target, Predicate<Player> inZone, Predicate<World> zoneWorld) {
        this.host = host;
        this.target = target;
        this.inZone = inZone;
        this.zoneWorld = zoneWorld;
    }

    /** Только глушить события, доски не трогать (для плагинов, у которых досок Assemble нет). */
    AlleyGate withoutBoards() {
        this.boardsOn = false;
        return this;
    }

    /** Кого игрок вне зоны Alley должен видеть (в 1.8 спрятанный игрок пропадает и из таба). */
    AlleyGate keepVisible(Predicate<Player> showable) {
        this.showable = showable;
        return this;
    }

    AlleyGate onlyListeners(Predicate<Object> filter) {
        this.listenerFilter = filter;
        return this;
    }

    int start() {
        wrap();
        if (showable != null) startVisibility();
        if (!boardsOn) return wrapped;
        bindBoards();
        Bukkit.getScheduler().runTaskTimer(host, new Runnable() {
            public void run() {
                syncBoards();
            }
        }, 20L, 4L);
        // сразу при входе и смене мира: иначе доска Alley успевает мигнуть
        Bukkit.getPluginManager().registerEvents(new org.bukkit.event.Listener() {
            @org.bukkit.event.EventHandler
            public void onWorld(org.bukkit.event.player.PlayerChangedWorldEvent e) {
                Bukkit.getScheduler().runTask(host, new Runnable() {
                    public void run() {
                        syncBoards();
                    }
                });
            }

            @org.bukkit.event.EventHandler
            public void onJoin(org.bukkit.event.player.PlayerJoinEvent e) {
                Bukkit.getScheduler().runTaskLater(host, new Runnable() {
                    public void run() {
                        syncBoards();
                    }
                }, 2L);
            }
        }, host);
        return wrapped;
    }

    /* ----------------------------- обработчики событий ----------------------------- */

    private void wrap() {
        for (HandlerList list : HandlerList.getHandlerLists()) {
            for (RegisteredListener rl : list.getRegisteredListeners()) {
                if (rl.getPlugin() != target || rl instanceof Muted || !listenerFilter.test(rl.getListener())) continue;
                list.unregister(rl);
                list.register(new Muted(rl));
                wrapped++;
            }
        }
    }

    final class Muted extends RegisteredListener {
        private final RegisteredListener inner;

        Muted(RegisteredListener inner) {
            super(inner.getListener(), null, inner.getPriority(), inner.getPlugin(), inner.isIgnoringCancelled());
            this.inner = inner;
        }

        @Override
        public void callEvent(Event event) throws EventException {
            if (muted(event)) return;
            inner.callEvent(event);
        }
    }

    /**
     * Событие происходит вне зоны Alley и относится к тем, что мы глушим: любые события игроков, блоков,
     * существ, инвентаря, транспорта и погоды. Не глушим жизненный цикл игрока (вход, выход, кик, смена мира,
     * телепорт, возрождение, смерть), чат и команды: профиль игрока в Alley должен оставаться
     * в порядке, чтобы возврат в дуэли работал.
     */
    boolean muted(Event e) {
        if (e instanceof org.bukkit.event.player.PlayerJoinEvent || e instanceof org.bukkit.event.player.PlayerQuitEvent
                || e instanceof org.bukkit.event.player.PlayerLoginEvent || e instanceof org.bukkit.event.player.PlayerKickEvent
                || e instanceof org.bukkit.event.player.PlayerChangedWorldEvent || e instanceof org.bukkit.event.player.PlayerTeleportEvent
                || e instanceof org.bukkit.event.player.PlayerRespawnEvent || e instanceof org.bukkit.event.player.PlayerCommandPreprocessEvent
                || e instanceof org.bukkit.event.player.AsyncPlayerChatEvent || e instanceof org.bukkit.event.player.PlayerResourcePackStatusEvent
                || e instanceof org.bukkit.event.entity.EntityDeathEvent) {
            return false;
        }
        boolean candidate = e instanceof PlayerEvent || e instanceof org.bukkit.event.block.BlockEvent
                || e instanceof org.bukkit.event.entity.EntityEvent || e instanceof org.bukkit.event.inventory.InventoryEvent
                || e instanceof org.bukkit.event.vehicle.VehicleEvent || e instanceof org.bukkit.event.hanging.HangingEvent
                || e instanceof WeatherChangeEvent;
        if (!candidate) return false;
        Player p = playerOf(e);
        if (p != null) return !inZone.test(p);
        World w = worldOf(e);
        return w != null && !zoneWorld.test(w);
    }

    private Player playerOf(Event e) {
        if (e instanceof PlayerEvent) return ((PlayerEvent) e).getPlayer();
        if (e instanceof BlockPlaceEvent) return ((BlockPlaceEvent) e).getPlayer();
        if (e instanceof BlockBreakEvent) return ((BlockBreakEvent) e).getPlayer();
        if (e instanceof BlockDamageEvent) return ((BlockDamageEvent) e).getPlayer();
        if (e instanceof org.bukkit.event.inventory.InventoryEvent) {
            org.bukkit.entity.HumanEntity h = ((org.bukkit.event.inventory.InventoryEvent) e).getView().getPlayer();
            return h instanceof Player ? (Player) h : null;
        }
        if (e instanceof org.bukkit.event.entity.EntityEvent) {
            Entity en = ((org.bukkit.event.entity.EntityEvent) e).getEntity();
            return en instanceof Player ? (Player) en : null;
        }
        return null;
    }

    private World worldOf(Event e) {
        if (e instanceof WeatherChangeEvent) return ((WeatherChangeEvent) e).getWorld();
        if (e instanceof org.bukkit.event.block.BlockEvent) return ((org.bukkit.event.block.BlockEvent) e).getBlock().getWorld();
        if (e instanceof org.bukkit.event.entity.EntityEvent) return ((org.bukkit.event.entity.EntityEvent) e).getEntity().getWorld();
        if (e instanceof org.bukkit.event.vehicle.VehicleEvent) return ((org.bukkit.event.vehicle.VehicleEvent) e).getVehicle().getWorld();
        if (e instanceof org.bukkit.event.hanging.HangingEvent) return ((org.bukkit.event.hanging.HangingEvent) e).getEntity().getWorld();
        return null;
    }

    /* ----------------------------- видимость игроков ----------------------------- */

    /**
     * Alley прячет игроков друг от друга по состоянию их профилей (в лобби, в очереди, в матче). Вне его
     * зоны это только мешает: новый игрок заходит и не видит других, а те не видят его, ни в мире,
     * ни в табе. Поэтому тем, кто вне зоны, возвращаем всех, кого им можно показывать.
     */
    private void startVisibility() {
        Bukkit.getScheduler().runTaskTimer(host, new Runnable() {
            public void run() {
                syncVisibility();
            }
        }, 40L, 20L);
        Bukkit.getPluginManager().registerEvents(new org.bukkit.event.Listener() {
            @org.bukkit.event.EventHandler
            public void onJoin(org.bukkit.event.player.PlayerJoinEvent e) {
                for (long delay : new long[]{10L, 40L, 100L}) {
                    Bukkit.getScheduler().runTaskLater(host, new Runnable() {
                        public void run() {
                            syncVisibility();
                        }
                    }, delay);
                }
            }

            @org.bukkit.event.EventHandler
            public void onWorld(org.bukkit.event.player.PlayerChangedWorldEvent e) {
                Bukkit.getScheduler().runTaskLater(host, new Runnable() {
                    public void run() {
                        syncVisibility();
                    }
                }, 5L);
            }
        }, host);
    }

    private void syncVisibility() {
        List<Player> players = new ArrayList<Player>(Bukkit.getOnlinePlayers());
        for (Player viewer : players) {
            if (inZone.test(viewer)) continue;
            for (Player target : players) {
                if (target == viewer || viewer.canSee(target)) continue;
                if (showable.test(target)) viewer.showPlayer(target);
            }
        }
    }

    /* ----------------------------- скорборд ----------------------------- */

    private void bindBoards() {
        try {
            Class<?> alley = Class.forName("dev.revere.alley.AlleyPlugin");
            Object plugin = alley.getMethod("getInstance").invoke(null);
            Class<?> svc = Class.forName("dev.revere.alley.library.assemble.AssembleService");
            assemble = alley.getMethod("getService", Class.class).invoke(plugin, svc);
            createBoard = svc.getMethod("createBoard", Player.class);
            removeBoard = svc.getMethod("removeBoard", Player.class);
            getBoards = svc.getMethod("getBoards");
        } catch (Throwable t) {
            assemble = null;
        }
    }

    /** Имя боковой цели Alley на его доске (по существующей доске). */
    private String objectiveName(Object board) {
        try {
            Object obj = board.getClass().getMethod("getObjective").invoke(board);
            return obj == null ? null : (String) obj.getClass().getMethod("getName").invoke(obj);
        } catch (Throwable t) {
            return null;
        }
    }

    /**
     * Alley цепляет свою цель к любому скорборду игрока, в том числе к чужому (BedWars): getScoreboard()
     * отдаёт текущий скорборд, если он не главный. После удаления доски эта цель остаётся и перекрывает
     * боковую панель владельца. Убираем её и возвращаем владельцу его панель и скорборд.
     */
    private void releaseForeign(Player p, org.bukkit.scoreboard.Scoreboard sb, String alleyObjective) {
        try {
            org.bukkit.scoreboard.Objective alley = alleyObjective == null ? null : sb.getObjective(alleyObjective);
            if (alley != null) alley.unregister();
            int others = sb.getObjectives().size();
            if (others == 0) return;   // это была собственная доска Alley: игроку остаётся главный скорборд
            if (sb.getObjective(org.bukkit.scoreboard.DisplaySlot.SIDEBAR) == null) {
                for (org.bukkit.scoreboard.Objective o : sb.getObjectives()) {
                    if (o.getDisplaySlot() == null && !"health".equalsIgnoreCase(o.getCriteria())) {
                        o.setDisplaySlot(org.bukkit.scoreboard.DisplaySlot.SIDEBAR);
                        break;
                    }
                }
            }
            if (p.getScoreboard() != sb) p.setScoreboard(sb);
        } catch (IllegalStateException ignored) {
        }
    }

    private void syncBoards() {
        if (assemble == null) return;
        try {
            java.util.Map<?, ?> boards = (java.util.Map<?, ?>) getBoards.invoke(assemble);
            List<Player> players = new ArrayList<Player>(Bukkit.getOnlinePlayers());
            org.bukkit.scoreboard.Scoreboard main = Bukkit.getScoreboardManager().getMainScoreboard();
            for (Player p : players) {
                Object board = boards.get(p.getUniqueId());
                boolean zone = inZone.test(p);
                if (!zone && board != null) {
                    // чужую доску (BedWars, наш скорборд) Alley при удалении сбрасывает на главную: возвращаем её
                    org.bukkit.scoreboard.Scoreboard before = p.getScoreboard();
                    String alleyObj = objectiveName(board);
                    removeBoard.invoke(assemble, p);
                    if (before != main) releaseForeign(p, before, alleyObj);
                } else if (zone && board == null) {
                    // доска Alley цепляется к текущему скорборду игрока, если он не главный (например, к нашему):
                    // сначала главный, чтобы Alley завёл свой, иначе наш потом сбросит ему скорборд и доска сломается
                    if (p.getScoreboard() != main) p.setScoreboard(main);
                    createBoard.invoke(assemble, p);
                } else if (zone && p.getScoreboard() == main) {
                    // доска есть, а у игрока главный скорборд (его сбросили): Alley из-за этого сыплет ошибками. Пересоздаём.
                    removeBoard.invoke(assemble, p);
                    createBoard.invoke(assemble, p);
                }
            }
        } catch (Throwable t) {
            host.getLogger().warning("AlleyGate: скорборд Alley не синхронизирован: " + t);
            assemble = null;
        }
    }
}
