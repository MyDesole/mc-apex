package ru.mcapex.tiertests;

import org.bukkit.Bukkit;
import org.bukkit.GameMode;
import org.bukkit.Location;
import org.bukkit.Material;
import org.bukkit.Sound;
import org.bukkit.World;
import org.bukkit.WorldCreator;
import org.bukkit.block.Block;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.ArmorStand;
import org.bukkit.entity.Player;
import org.bukkit.event.Event;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.block.Action;
import org.bukkit.event.block.BlockBreakEvent;
import org.bukkit.event.block.BlockCanBuildEvent;
import org.bukkit.event.block.BlockDamageEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import org.bukkit.event.entity.CreatureSpawnEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.entity.FoodLevelChangeEvent;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.PlayerChangedWorldEvent;
import org.bukkit.event.player.PlayerDropItemEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.event.weather.WeatherChangeEvent;
import org.bukkit.generator.ChunkGenerator;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.inventory.meta.SkullMeta;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitTask;
import org.bukkit.util.EulerAngle;

import java.io.File;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.Comparator;
import java.util.Date;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Random;
import java.util.Set;
import java.util.UUID;
import java.util.LinkedHashMap;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import org.bukkit.Effect;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerTeleportEvent;
import org.bukkit.event.inventory.InventoryDragEvent;
import java.util.concurrent.ConcurrentHashMap;
import java.util.function.Consumer;

/**
 * Тренировка быстрого строительства (fastbridge).
 *
 * Отдельный пустой мир, у каждого игрока своя дорожка: стартовый островок, пропасть
 * выбранной длины и дальний островок. Нужно как можно быстрее построить мост. Таймер идёт
 * с первого блока, упал - дорожка очищается и всё заново. Есть карты на 16, 24, 32, 64 блока
 * и бесконечная (считается дальность моста).
 *
 * Каждая попытка пишется (позиции игрока и все блоки) и проигрывается как реплей: «призрак»
 * повторяет движения, блоки появляются в те же моменты. Рекорды, рекорд за 24 часа и число
 * попыток видны в скорборде, скорость - над инвентарём.
 */
final class FastBridge implements Listener, CommandExecutor, FbHud.Source {
    static final String WORLD = "fastbridge";

    static final int LANES = 20;
    static final int PUBLIC_LANES = 16;
    static final int SPACING = 30;     // шаг дорожек по z: между соседями есть запас на сносы в сторону
    static final int FLOOR_Y = 70;
    static final int ISLAND = 5;       // сторона островка; пропасть начинается с x = ISLAND
    private static final int HALF_WIDTH = 12;
    private static final int SAMPLE_TICKS = 2;
    private static final int MAX_FRAMES = 6000;      // 10 минут записи
    private static final double[] SPEEDS = {0.25, 0.5, 1, 2, 4};

    /** Зачёркнутые пробелы: пустая линия постоянной ширины. Две разные, потому что строки доски не повторяются. */
    static final String SEP_TOP = "§8§m                      ";
    static final String SEP_BOTTOM = "§7§m                      ";

    private static final String T_MAP = "§8Fastbridge: карта";
    private static final String T_REPLAYS = "§8Fastbridge: реплеи";

    private static final String I_MAP = "§bКарта §7(ПКМ)";
    private static final String I_REPLAYS = "§dМои реплеи §7(ПКМ)";
    private static final String I_RESTART = "§eЗаново §7(ПКМ)";
    private static final String I_EXIT = "§cВыход §7(ПКМ)";
    private static final String W_SLOW = "§eМедленнее §7(ПКМ)";
    private static final String W_PAUSE = "§aПауза / дальше §7(ПКМ)";
    private static final String W_FAST = "§eБыстрее §7(ПКМ)";
    private static final String W_AGAIN = "§bСначала §7(ПКМ)";
    private static final String W_CAMERA = "§bКамера: первое лицо / свободная §7(ПКМ)";
    private static final String W_BACK = "§cНазад к тренировке §7(ПКМ)";

    private final JavaPlugin plugin;
    private final Consumer<Player> toHub;
    private World world;
    private FbStats stats;
    private FbStats legacyStats;
    private FbIslands islands;
    private FbHud hud;
    private File replayDir;
    private FbPerks perks;
    private BattleStyle battleStyle;
    private final ExecutorService replayWriter = Executors.newSingleThreadExecutor();
    private final Map<UUID, Set<UUID>> hidden = new HashMap<UUID, Set<UUID>>();
    private final Map<UUID, Long> replayRequests = new HashMap<UUID, Long>();
    private long nextReplayRequest;
    private static final String T_PERKS = "§8Fastbridge: настройки";
    private static final String T_HISTORY = "§8Fastbridge: история";
    private static final String I_PERKS = "§6Настройки §7(ПКМ)";
    private static final String W_FAVORITE = "§eВ избранное / убрать §7(ПКМ)";

    private final Map<UUID, Sess> sessions = new ConcurrentHashMap<UUID, Sess>();
    private final Map<UUID, Watch> watches = new ConcurrentHashMap<UUID, Watch>();
    private final Object[] laneOwner = new Object[LANES];
    private final Map<UUID, String> lastReplay = new HashMap<UUID, String>();
    private final Map<UUID, Map<Integer, String>> menuRefs = new HashMap<UUID, Map<Integer, String>>();
    private boolean ghostSpawn;
    private int tickCounter;
    private BukkitTask sampler;
    private BukkitTask flusher;

    FastBridge(JavaPlugin plugin, Consumer<Player> toHub) {
        this.plugin = plugin;
        this.toHub = toHub;
    }

    /* ============================== запуск ============================== */

    void start() {
        replayDir = new File(plugin.getDataFolder(), "replays");
        replayDir.mkdirs();
        perks = new FbPerks(plugin.getDataFolder(), plugin.getLogger());
        legacyStats = new FbStats(new File(plugin.getDataFolder(), "fastbridge-stats.yml"), plugin.getLogger());
        stats = new FbStats(new File(plugin.getDataFolder(), "fastbridge-akina-v1-stats.yml"), plugin.getLogger());
        islands = new FbIslands(plugin);

        world = Bukkit.getWorld(WORLD);
        if (world == null) {
            world = new WorldCreator(WORLD).generator(new VoidGen()).generateStructures(false).createWorld();
        }
        if (world == null) {
            plugin.getLogger().warning("FastBridge: мир не создан - режим выключен");
            return;
        }
        world.setTime(6000);
        world.setStorm(false);
        world.setGameRuleValue("doDaylightCycle", "false");
        world.setGameRuleValue("doMobSpawning", "false");
        world.setSpawnFlags(false, false);
        for (int i = 0; i < LANES; i++) build(i, FbMap.B32);

        hud = new FbHud(plugin.getLogger(), this);

        Bukkit.getPluginManager().registerEvents(this, plugin);
        plugin.getCommand("fastbridge").setExecutor(this);
        battleStyle = new BattleStyle(plugin);
        battleStyle.start();
        sampler = Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                sample();
            }
        }, SAMPLE_TICKS, SAMPLE_TICKS);
        flusher = Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                stats.flush();
                perks.flush();
            }
        }, 600L, 600L);
        plugin.getLogger().info("FastBridge включён: " + LANES + " дорожек, мир " + WORLD);
    }

    void stop() {
        if (world == null) return;
        if (battleStyle != null) battleStyle.stop();
        for (Sess s : new ArrayList<Sess>(sessions.values())) detach(s, false);
        for (Watch w : new ArrayList<Watch>(watches.values())) w.cleanup(false);
        if (sampler != null) sampler.cancel();
        if (flusher != null) flusher.cancel();
        stats.flush();
        replayWriter.shutdown();
        try {
            if (!replayWriter.awaitTermination(15, TimeUnit.SECONDS)) plugin.getLogger().warning("FastBridge: replay writer is still finishing saves");
        } catch (InterruptedException e) { Thread.currentThread().interrupt(); }
        perks.flush();
        restoreVisibility();
    }

    boolean enabled() {
        return world != null;
    }

    /** Мир без земли: пропасть под островками. */
    static final class VoidGen extends ChunkGenerator {
        @Override
        public byte[][] generateBlockSections(World w, Random random, int x, int z, BiomeGrid biomes) {
            return new byte[w.getMaxHeight() / 16][];
        }

        @Override
        public Location getFixedSpawnLocation(World w, Random random) {
            return new Location(w, 2.5, FLOOR_Y + 1, 0.5);
        }
    }

    /* ============================== дорожки ============================== */

    private void build(int lane, FbMap map) {
        build(lane, map, FbIslands.AKINA);
    }

    private void build(int lane, FbMap map, String layout) {
        islands.build(world, lane, map, layout);
    }

    private int endX(FbMap map) {
        return map.endless() ? ISLAND + FbMap.INF_LIMIT : ISLAND + map.gap;
    }

    private Location spawn(int lane) {
        return new Location(world, 2.5, FLOOR_Y + 1, lane * SPACING + 0.5, -90f, 0f);
    }

    private int freeLane(boolean personal) {
        for (int i = personal ? PUBLIC_LANES : 0; i < (personal ? LANES : PUBLIC_LANES); i++) if (laneOwner[i] == null) return i;
        return -1;
    }

    private static String key(Block b) {
        return b.getX() + "," + b.getY() + "," + b.getZ();
    }

    /* ============================== сеанс игрока ============================== */

    /** Попытка: запись и состояние текущего прохода. */
    static final class Run {
        long recStart = System.currentTimeMillis();
        long timerStart = -1;
        boolean finished;
        boolean historyRecorded;
        int count;
        int maxX = -1;
        final List<double[]> frames = new ArrayList<double[]>();
        final List<int[]> blocks = new ArrayList<int[]>();
        final List<int[]> clicks = new ArrayList<int[]>();
        final Set<String> placed = new HashSet<String>();
    }

    /** Игрок в режиме тренировки. */
    static final class Sess {
        final Player p;
        final int lane;
        FbMap map;
        boolean personal;
        Run run = new Run();
        double speed;
        long finishedMs;
        volatile List<String> hud = Collections.emptyList();

        Sess(Player p, int lane, FbMap map) {
            this.p = p;
            this.lane = lane;
            this.map = map;
        }
    }

    void join(Player p) { join(p, false); }

    private void join(Player p, boolean personal) {
        replayRequests.remove(p.getUniqueId());
        if (personal && !perks.privateAllowed(rank(p))) {
            p.sendMessage("§cПриватная тренировка доступна от APEX.");
            return;
        }
        if (!enabled()) {
            p.sendMessage("§cFastBridge сейчас недоступен.");
            return;
        }
        Sess ex = sessions.get(p.getUniqueId());
        if (ex != null && ex.personal == personal) {
            restart(ex, null);
            return;
        }
        Watch w = watches.get(p.getUniqueId());
        int lane = freeLane(personal);
        if (lane < 0 && w != null && (w.lane >= PUBLIC_LANES) == personal) lane = w.lane;
        if (lane < 0) {
            p.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
            return;
        }
        if (ex != null) detach(ex, false);
        if (w != null) w.cleanup(false);
        Sess s = new Sess(p, lane, stats.lastMap(p.getUniqueId()));
        s.personal = personal;
        sessions.put(p.getUniqueId(), s);
        laneOwner[lane] = s;
        p.setGameMode(GameMode.SURVIVAL);
        p.setAllowFlight(false);
        p.setFlying(false);
        build(lane, s.map);
        kit(s);
        p.teleport(spawn(lane));
        refreshHud(s);
        refreshVisibility();
        if (personal) p.sendMessage("§dПриватная дорожка: без зрителей и общего рейтинга. Реплеи доступны только тебе.");
        p.sendMessage("§7Akina: отдельный рейтинг. Старые рекорды: §f/fb oldtop§7; записи сохранены.");
        p.sendMessage("§6Fastbridge: §7карта §f" + s.map.title + "§7. Построй мост до зелёного островка, таймер пойдёт с первого блока.");
        p.sendMessage("§7Предметы: §bкарта§7, §dреплеи§7, §eзаново§7, §cвыход§7. Команды: §f/fb help");
    }

    private void kit(Sess s) {
        Player p = s.p;
        p.getInventory().clear();
        p.getInventory().setArmorContents(null);
        p.getInventory().setItem(0, new ItemStack(blockType(p), 64));
        p.getInventory().setItem(6, item(Material.NETHER_STAR, 0, I_PERKS, "§7Пресеты, история, избранное и эффекты"));
        p.getInventory().setItem(2, item(Material.EMPTY_MAP, 0, I_MAP, "§7Выбрать длину моста"));
        p.getInventory().setItem(3, item(Material.BOOK, 0, I_REPLAYS, "§7Записи твоих попыток"));
        p.getInventory().setItem(4, item(Material.WATCH, 0, I_RESTART, "§7Сбросить попытку"));
        p.getInventory().setItem(8, item(Material.BARRIER, 0, I_EXIT, "§7Вернуться в хаб"));
        p.getInventory().setHeldItemSlot(0);
        p.setHealth(p.getMaxHealth());
        p.setFoodLevel(20);
        p.setFireTicks(0);
        p.setFallDistance(0);
        p.updateInventory();
    }

    private ItemStack item(Material m, int data, String name, String... lore) {
        ItemStack it = new ItemStack(m, 1, (short) data);
        ItemMeta im = it.getItemMeta();
        im.setDisplayName(name);
        if (lore.length > 0) im.setLore(Arrays.asList(lore));
        it.setItemMeta(im);
        return it;
    }

    /** Убрать построенное и вернуть островки. */
    private void cleanLane(Sess s) {
        for (String key : s.run.placed) {
            String[] c = key.split(",");
            world.getBlockAt(Integer.parseInt(c[0]), Integer.parseInt(c[1]), Integer.parseInt(c[2])).setType(Material.AIR);
        }
        s.run.placed.clear();
        build(s.lane, s.map);
    }

    private void restart(Sess s, String why) {
        logAttempt(s, why != null && why.contains("Упал") ? "падение" : why != null && why.contains("долго") ? "лимит времени" : "сброс", -1, "");
        cleanLane(s);
        s.run = new Run();
        s.speed = 0;
        kit(s);
        s.p.teleport(spawn(s.lane));
        refreshHud(s);
        if (why != null) s.p.sendMessage(why);
    }

    private void selectMap(Sess s, FbMap map) {
        replayRequests.remove(s.p.getUniqueId());
        logAttempt(s, "смена карты", -1, "");
        s.map = map;
        stats.setLastMap(s.p.getUniqueId(), s.p.getName(), map);
        restart(s, "§6Fastbridge: §7карта §f" + map.title);
    }

    /** Выйти из режима. toHub - ещё и перенести игрока в хаб. */
    private void detach(Sess s, boolean sendToHub) {
        logAttempt(s, "выход", -1, "");
        cleanLane(s);
        sessions.remove(s.p.getUniqueId());
        laneOwner[s.lane] = null;
        if (hud != null) hud.release(s.p);
        refreshVisibility();
        if (sendToHub && s.p.isOnline()) toHub.accept(s.p);
    }

    void leave(Player p) {
        replayRequests.remove(p.getUniqueId());
        Sess s = sessions.get(p.getUniqueId());
        if (s != null) {
            detach(s, true);
            return;
        }
        Watch w = watches.get(p.getUniqueId());
        if (w != null) w.cleanup(true);
    }

    /* ============================== запись и HUD ============================== */

    private void sample() {
        tickCounter++;
        boolean slow = tickCounter % 3 == 0;
        for (Sess s : sessions.values()) {
            if (s.personal && !perks.privateAllowed(rank(s.p))) {
                s.p.sendMessage("§cСрок доступа к приватной тренировке закончился.");
                detach(s, true);
                continue;
            }
            Run r = s.run;
            if (s.p.getGameMode() != GameMode.SURVIVAL && s.p.getGameMode() != GameMode.CREATIVE) {
                s.p.setGameMode(GameMode.SURVIVAL);
            }
            if (r.finished) continue;
            if (r.frames.size() >= MAX_FRAMES) {
                restart(s, "§cСлишком долго - попытка сброшена.");
                continue;
            }
            Location l = s.p.getLocation();
            int flags = (s.p.isSneaking() ? Replay.SNEAK : 0) | (s.p.isSprinting() ? Replay.SPRINT : 0)
                    | (s.p.isOnGround() ? 0 : Replay.AIR);
            double nowT = System.currentTimeMillis() - r.recStart;
            // клавиши движения выводим из смещения относительно взгляда
            if (!r.frames.isEmpty()) {
                double[] pv = r.frames.get(r.frames.size() - 1);
                double dt = nowT - pv[0];
                if (dt > 0) {
                    double dx = l.getX() - pv[1], dz = (l.getZ() - s.lane * SPACING) - pv[3];
                    double dist = Math.sqrt(dx * dx + dz * dz);
                    if (dist / dt * 1000 > 0.4) {
                        double yaw = Math.toRadians(l.getYaw());
                        double fwd = (-Math.sin(yaw) * dx + Math.cos(yaw) * dz) / dist;
                        double right = (-Math.cos(yaw) * dx - Math.sin(yaw) * dz) / dist;
                        if (fwd > 0.35) flags |= Replay.KEY_W;
                        if (fwd < -0.35) flags |= Replay.KEY_S;
                        if (right > 0.35) flags |= Replay.KEY_D;
                        if (right < -0.35) flags |= Replay.KEY_A;
                    }
                }
            }
            r.frames.add(new double[]{nowT,
                    l.getX(), l.getY() - FLOOR_Y, l.getZ() - s.lane * SPACING, l.getYaw(), l.getPitch(), flags});
            s.speed = s.speed * 0.5 + speedOf(r) * 0.5;
            long elapsed = r.timerStart >= 0 ? System.currentTimeMillis() - r.timerStart : 0;
            FbHud.actionBar(s.p, "§b" + String.format("%.1f", s.speed).replace(',', '.') + " §7б/с   §8|   §e" + r.count
                    + " §7бл.   §8|   §a" + (s.map.endless() ? Math.max(0, r.maxX - ISLAND + 1) + " §7бл. вперёд" : fmt(elapsed)));
            if (slow) refreshHud(s);
        }
        if (slow) {
            for (Watch w : watches.values()) w.refreshHud();
        }
    }

    /** Горизонтальная скорость за последние ~0,6 с, блоков в секунду. */
    private double speedOf(Run r) {
        int n = r.frames.size();
        if (n < 2) return 0;
        double[] a = r.frames.get(n - 1);
        double[] b = r.frames.get(Math.max(0, n - 4));
        double dt = a[0] - b[0];
        if (dt <= 0) return 0;
        double dx = a[1] - b[1], dz = a[3] - b[3];
        return Math.sqrt(dx * dx + dz * dz) / dt * 1000;
    }

    static String fmt(long ms) {
        return String.format("%d.%02d", ms / 1000, (ms % 1000) / 10);
    }

    private String fmtVal(FbMap m, long v) {
        if (v < 0) return "-";
        return m.endless() ? v + " бл." : fmt(v) + " с";
    }

    private void refreshHud(Sess s) {
        UUID u = s.p.getUniqueId();
        Run r = s.run;
        List<String> l = new ArrayList<String>();
        // линия фиксированной длины держит ширину доски: без неё доска «пляшет» при смене цифр
        l.add(SEP_TOP);
        l.add((s.personal ? "§dПриватно: §f" : "§7Карта: §f") + s.map.title);
        l.add("§a ");
        if (s.map.endless()) {
            l.add("§fДальность: §a" + Math.max(0, r.maxX - ISLAND + 1) + " бл.");
        } else {
            l.add("§fВремя: §a" + fmt(r.timerStart >= 0 && !r.finished ? System.currentTimeMillis() - r.timerStart : lastShown(s)));
        }
        l.add("§fБлоков: §e" + r.count);
        l.add("§fСкорость: §b" + String.format("%.1f", s.speed).replace(',', '.') + " б/с");
        l.add("§b ");
        l.add("§fРекорд: §6" + fmtVal(s.map, stats.best(u, s.map)));
        l.add("§fРекорд 24ч: §d" + fmtVal(s.map, stats.best24h(u, s.map)));
        l.add("§fПопыток: §e" + stats.attempts24h(u, s.map) + " §7(24ч)");
        l.add(SEP_BOTTOM);
        l.add("§emc-apex.ru");
        s.hud = Collections.unmodifiableList(l);
        if (hud != null) hud.refresh(s.p);
    }

    private long lastShown(Sess s) {
        return s.run.finished && s.run.timerStart >= 0 ? s.finishedMs : 0;
    }

    // FbHud.Source (вызывается и из потока Alley, поэтому только читаем готовые строки)
    public String title(Player p) {
        return sessions.containsKey(p.getUniqueId()) || watches.containsKey(p.getUniqueId()) ? "§6§lFASTBRIDGE" : null;
    }

    public List<String> lines(Player p) {
        Sess s = sessions.get(p.getUniqueId());
        if (s != null) return s.hud;
        Watch w = watches.get(p.getUniqueId());
        return w != null ? w.hud : null;
    }

    /* ============================== установка блоков ============================== */

    /** Сколько слоёв можно строить над островками: без ограничения, пока не упрёмся в потолок мира (fastbridge.max-height). */
    private int maxHeight() {
        return Math.max(0, plugin.getConfig().getInt("fastbridge.max-height", 250 - FLOOR_Y));
    }

    private boolean inLane(Sess s, Block b) {
        int lz = s.lane * SPACING;
        return b.getWorld().equals(world) && !islands.protectedBlock(s.map, b.getX(), b.getY() - FLOOR_Y, b.getZ() - lz)
                && Math.abs(b.getZ() - lz) <= HALF_WIDTH
                && b.getX() >= -2 && b.getX() <= endX(s.map) + ISLAND + 2
                && b.getY() >= FLOOR_Y - 2 && b.getY() <= FLOOR_Y + maxHeight();
    }

    /** Блок пересекается с телом игрока (0,6 x 1,8): ставить его нельзя, как и в обычной игре. */
    private boolean intersectsPlayer(Player p, Block b) {
        Location l = p.getLocation();
        double h = p.isSneaking() ? 1.65 : 1.8;
        return l.getX() - 0.3 < b.getX() + 1 && l.getX() + 0.3 > b.getX()
                && l.getY() < b.getY() + 1 && l.getY() + h > b.getY()
                && l.getZ() - 0.3 < b.getZ() + 1 && l.getZ() + 0.3 > b.getZ();
    }

    private void limitHint(Player p, Block b) {
        if (b.getY() > FLOOR_Y + maxHeight()) FbHud.actionBar(p, "§cВыше строить нельзя: максимум " + maxHeight() + " бл. над островком");
    }

    private boolean holdingBlocks(Player p) {
        ItemStack it = p.getItemInHand();
        return it != null && it.getType() == Material.SANDSTONE;
    }

    /**
     * Клики: только кнопки в хотбаре; блоки ставятся и ломаются штатно.
     */
    @EventHandler(priority = EventPriority.HIGHEST)
    public void onInteract(PlayerInteractEvent e) {
        final Player p = e.getPlayer();
        if (!p.getWorld().equals(world)) return;
        final Sess s = sessions.get(p.getUniqueId());
        Watch w = watches.get(p.getUniqueId());
        Action a = e.getAction();
        boolean right = a == Action.RIGHT_CLICK_AIR || a == Action.RIGHT_CLICK_BLOCK;
        ItemStack it = e.getItem();
        String name = it != null && it.hasItemMeta() && it.getItemMeta().hasDisplayName() ? it.getItemMeta().getDisplayName() : null;

        if (right && name != null) {
            if (s != null && buttonSess(s, name)) {
                e.setCancelled(true);
                return;
            }
            if (w != null && buttonWatch(w, name)) {
                e.setCancelled(true);
                return;
            }
        }
        if (s == null || s.run.finished) return;
        if (right) {
            int t = (int) (System.currentTimeMillis() - s.run.recStart);
            // клиент машет рукой и при постановке блока: этот взмах не считаем ЛКМ
            List<int[]> cl = s.run.clicks;
            if (!cl.isEmpty()) {
                int[] last = cl.get(cl.size() - 1);
                if (last[1] == 1 && t - last[0] <= 80) cl.remove(cl.size() - 1);
            }
            cl.add(new int[]{t, 2});
            lastRight.put(p.getUniqueId(), System.currentTimeMillis());
        }
    }

    private final Map<UUID, Long> lastRight = new HashMap<UUID, Long>();

    @EventHandler(priority = EventPriority.MONITOR)
    public void onSwing(org.bukkit.event.player.PlayerAnimationEvent e) {
        Player p = e.getPlayer();
        if (!p.getWorld().equals(world)) return;
        Sess s = sessions.get(p.getUniqueId());
        if (s == null || s.run.finished) return;
        Long r = lastRight.get(p.getUniqueId());
        if (r != null && System.currentTimeMillis() - r <= 80) return;
        s.run.clicks.add(new int[]{(int) (System.currentTimeMillis() - s.run.recStart), 1});
    }

    /** Установка блока - обычная, ванильная; мы только запрещаем лишнее (вне дорожки, выше лимита, в себя). */
    @EventHandler(priority = EventPriority.HIGHEST)
    public void onPlace(BlockPlaceEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s == null) {
            if (e.getPlayer().getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
            return;
        }
        boolean ok = !s.run.finished && inLane(s, e.getBlock()) && e.getBlock().getType() == blockType(e.getPlayer())
                && !intersectsPlayer(e.getPlayer(), e.getBlock());
        if (!ok) {
            limitHint(e.getPlayer(), e.getBlock());
            e.setCancelled(true);
        }
    }

    @EventHandler(priority = EventPriority.MONITOR, ignoreCancelled = true)
    public void onPlaced(BlockPlaceEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s != null) record(s, e.getBlock());
    }

    /** Учёт поставленного блока и пополнение стака. */
    private void record(final Sess s, Block b) {
        Run r = s.run;
        if (r.placed.contains(key(b))) return;
        long now = System.currentTimeMillis();
        if (r.timerStart < 0) {
            r.timerStart = now;
            if (!s.personal) stats.attempt(s.p.getUniqueId(), s.map);
        }
        r.placed.add(key(b));
        r.count++;
        r.maxX = Math.max(r.maxX, b.getX());
        r.blocks.add(new int[]{(int) (now - r.recStart), b.getX(), b.getY() - FLOOR_Y, b.getZ() - s.lane * SPACING,
                b.getTypeId(), b.getData(), 1});
        Bukkit.getScheduler().runTask(plugin, new Runnable() {
            public void run() {
                Player p = s.p;
                if (sessions.get(p.getUniqueId()) != s || s.run != r) return;
                for (int i = 0; i < 9; i++) {
                    ItemStack st = p.getInventory().getItem(i);
                    if (st != null && st.getType() == blockType(p) && st.getAmount() < 64) st.setAmount(64);
                }
                if (p.getInventory().getItem(0) == null) p.getInventory().setItem(0, new ItemStack(blockType(p), 64));
            }
        });
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void onDamageBlock(BlockDamageEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s != null && s.run.placed.contains(key(e.getBlock()))) {
            e.setCancelled(false);
            e.setInstaBreak(true);
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void onBreak(BlockBreakEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s == null && e.getPlayer().getGameMode() == GameMode.CREATIVE) return;
        e.setCancelled(true);
        if (s != null && s.run.placed.contains(key(e.getBlock()))) removeBlock(s, e.getBlock());
    }

    /** Ломаем свой блок без выпадения предмета. */
    private void removeBlock(Sess s, Block b) {
        s.run.placed.remove(key(b));
        b.setType(Material.AIR);
        s.run.blocks.add(new int[]{(int) (System.currentTimeMillis() - s.run.recStart), b.getX(), b.getY() - FLOOR_Y,
                b.getZ() - s.lane * SPACING, 0, 0, 0});
    }

    /* ============================== движение, финиш ============================== */

    @EventHandler
    public void onMove(PlayerMoveEvent e) {
        if (!guardPrivate(e)) return;
        Watch watch = watches.get(e.getPlayer().getUniqueId());
        if (watch != null && watch.firstPerson && !(e instanceof PlayerTeleportEvent)) {
            e.setTo(e.getFrom());
            return;
        }
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s == null || s.run.finished) return;
        Location to = e.getTo();
        if (!to.getWorld().equals(world)) return;
        if (to.getY() < FLOOR_Y - 12) {
            fell(s);
            return;
        }
        int lz = s.lane * SPACING;
        if (s.run.timerStart < 0) return;
        if (s.map.endless()) {
            if (to.getX() >= endX(s.map)) finishRun(s, Math.max(0, s.run.maxX - ISLAND + 1), true);
        } else if (to.getX() >= endX(s.map) && to.getX() <= endX(s.map) + ISLAND && Math.abs(to.getZ() - lz) <= 3
                && to.getY() >= FLOOR_Y + 0.9 && to.getY() <= FLOOR_Y + 2) {
            finishRun(s, System.currentTimeMillis() - s.run.timerStart, false);
        }
    }

    private void fell(Sess s) {
        Run r = s.run;
        if (s.map.endless() && r.timerStart >= 0 && r.maxX - ISLAND + 1 >= 5) {
            finishRun(s, r.maxX - ISLAND + 1, false);
            return;
        }
        restart(s, "§cУпал. Ещё раз!");
    }

    private void finishRun(final Sess s, long value, boolean limit) {
        final Run r = s.run;
        r.finished = true;
        s.finishedMs = System.currentTimeMillis() - (r.timerStart >= 0 ? r.timerStart : System.currentTimeMillis());
        final Player p = s.p;
        UUID u = p.getUniqueId();
        long prevBest = stats.best(u, s.map);
        long prevDay = stats.best24h(u, s.map);
        String id = saveReplay(s, value);
        lastReplay.put(u, id);
        boolean record = !s.personal && stats.finish(u, p.getName(), s.map, value, id);
        logAttempt(s, "финиш", value, id);
        finishEffect(p);
        if (s.map.endless()) {
            p.sendMessage("§6Fastbridge: §aдальность " + value + " бл." + (limit ? " §7(предел дорожки)" : "")
                    + (record ? " §e- новый рекорд!" : " §7(рекорд " + fmtVal(s.map, prevBest) + ")"));
        } else {
            p.sendMessage("§6Fastbridge: §a" + fmt(value) + " с" + (record ? " §e- новый рекорд!" : " §7(рекорд " + fmtVal(s.map, prevBest) + ")"));
            if (!record && (prevDay < 0 || s.map.better(value, prevDay))) p.sendMessage("§dЭто лучший результат за 24 часа!");
        }
        p.playSound(p.getLocation(), record ? Sound.LEVEL_UP : Sound.NOTE_PLING, 1f, 1f);
        p.sendMessage("§7Запись сохранена: §f/fb replay last §7или предмет «Мои реплеи».");
        if ("on".equals(perks.setting(u, "auto", "on"))) {
            Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
                public void run() {
                    if (sessions.get(p.getUniqueId()) == s && s.run == r && p.isOnline()) restart(s, null);
                }
            }, 50L);
        }
    }

    /* ============================== реплеи: сохранение ============================== */

    private String saveReplay(Sess s, long value) {
        Run r = s.run;
        final Replay rep = new Replay();
        String stamp = new SimpleDateFormat("yyyyMMdd-HHmmssSSS").format(new Date());
        rep.id = stamp + "_" + s.p.getName() + "_" + s.map.id + "_" + value;
        rep.player = s.p.getName();
        rep.uuid = s.p.getUniqueId().toString();
        rep.map = s.map.id;
        rep.value = value;
        rep.created = System.currentTimeMillis();
        rep.personal = s.personal;
        rep.material = blockType(s.p).name();
        rep.layout = FbIslands.AKINA;
        if (s.personal) { perks.markPrivate(rep.id, s.p.getUniqueId()); perks.flush(); }
        rep.timerStartMs = r.timerStart >= 0 ? r.timerStart - r.recStart : 0;
        rep.frames = r.frames.toArray(new double[r.frames.size()][]);
        rep.blocks = r.blocks.toArray(new int[r.blocks.size()][]);
        rep.clicks = r.clicks.toArray(new int[r.clicks.size()][]);
        final String nick = s.p.getName();
        final Set<String> keep = new HashSet<String>(stats.recordReplays());
        keep.addAll(legacyStats.recordReplays());
        keep.add(rep.id);
        final int retentionRank = rank(s.p);
        replayWriter.submit(new Runnable() {
            public void run() {
                try {
                    rep.write(new File(replayDir, rep.id + ".json"));
                } catch (Exception ex) {
                    plugin.getLogger().warning("FastBridge: запись не сохранена: " + ex);
                    return;
                }
                prune(nick, keep, retentionRank);
            }
        });
        return rep.id;
    }

    /** Файлы записей игрока, свежие первыми. */
    private List<File> filesOf(String nick) {
        List<File> out = new ArrayList<File>();
        File[] all = replayDir.listFiles();
        if (all == null) return out;
        for (File f : all) {
            String[] meta = Replay.parseName(f.getName());
            if (meta != null && meta[1].equalsIgnoreCase(nick)) out.add(f);
        }
        Collections.sort(out, new Comparator<File>() {
            public int compare(File a, File b) {
                return b.getName().compareTo(a.getName());
            }
        });
        return out;
    }

    private void prune(String nick, Set<String> keep, int rank) {
        List<File> files = filesOf(nick);
        int ordinary = 0;
        for (File file : files) {
            if (!perks.protectedReplay(file, keep)) perks.deleteReplay(file, keep, ordinary++, rank, System.currentTimeMillis());
        }
    }

    /* ============================== реплеи: проигрывание ============================== */

    final class Watch {
        final Player viewer;
        final int lane;
        final Replay rep;
        final FbMap map;
        int speedIdx;
        boolean paused;
        boolean ended;
        boolean firstPerson;
        Location freePosition;
        double t;
        int blockIdx;
        Ghost ghost;
        int clickIdx;
        BukkitTask task;
        final List<int[]> shown = new ArrayList<int[]>();
        volatile List<String> hud = Collections.emptyList();
        int ticks;

        Watch(Player viewer, int lane, Replay rep, double speed) {
            this.viewer = viewer;
            this.lane = lane;
            this.rep = rep;
            FbMap m = FbMap.of(rep.map);
            this.map = m != null ? m : FbMap.B32;
            int idx = 2;
            for (int i = 0; i < SPEEDS.length; i++) if (Math.abs(SPEEDS[i] - speed) < 0.01) idx = i;
            this.speedIdx = idx;
        }

        double speed() {
            return SPEEDS[speedIdx];
        }

        boolean begin() {
            int lz = lane * SPACING;
            build(lane, map, rep.layout);
            viewer.setGameMode(GameMode.ADVENTURE);
            viewer.getInventory().clear();
            viewer.getInventory().setArmorContents(null);
            viewer.getInventory().setItem(0, item(Material.STICK, 0, W_SLOW, "§7Скорость меньше"));
            viewer.getInventory().setItem(1, item(Material.REDSTONE_TORCH_ON, 0, W_PAUSE, "§7Пауза и продолжить"));
            viewer.getInventory().setItem(2, item(Material.BLAZE_ROD, 0, W_FAST, "§7Скорость больше"));
            viewer.getInventory().setItem(3, item(Material.COMPASS, 0, W_CAMERA, "§7Или /fb camera [first|free]"));
            viewer.getInventory().setItem(4, item(Material.EYE_OF_ENDER, 0, W_AGAIN, "§7Проиграть заново"));
            viewer.getInventory().setItem(6, item(Material.NETHER_STAR, 0, W_FAVORITE, "§7Закрепить свою запись от удаления"));
            viewer.getInventory().setItem(8, item(Material.BARRIER, 0, W_BACK, "§7Закрыть запись и вернуться на дорожку"));
            viewer.getInventory().setHeldItemSlot(4);
            viewer.setAllowFlight(true);
            viewer.teleport(new Location(world, -3.5, FLOOR_Y + 4, lz + 0.5, -90f, 25f));
            viewer.setFlying(true);
            if (!spawnStand()) return false;
            viewer.sendMessage("§6Fastbridge: §7запись игрока §e" + rep.player + " §7на карте §f" + map.title + "§7, результат §a" + fmtVal(map, rep.value));
            viewer.sendMessage("§7Предметы: скорость, пауза, сначала. §cБарьер §7- назад на дорожку, §f/fb leave §7- в хаб.");
            task = Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
                public void run() {
                    tick();
                }
            }, 1L, 1L);
            return true;
        }

        boolean spawnStand() {
            Location at = spawn(lane);
            at.getChunk().load();
            try {
                ghost = new Ghost(plugin, viewer, world, rep.player, rep.uuid);
                ghost.spawn(at.getX(), at.getY(), at.getZ(), at.getYaw(), new ItemStack(replayMaterial(rep)));
            } catch (Throwable ex) {
                plugin.getLogger().warning("FastBridge: бот-игрок не создан: " + ex);
                ghost = null;
                return false;
            }
            clickIdx = 0;
            if (firstPerson) FbCamera.attach(viewer, ghost.id());
            return true;
        }

        void setFirstPerson(boolean on) {
            if (on == firstPerson) return;
            if (on && ended) {
                viewer.sendMessage("§7Запись закончилась. Нажми «Сначала», затем включи камеру.");
                return;
            }
            if (on && (ghost == null || !ghost.alive())) return;
            if (on) {
                freePosition = viewer.getLocation().clone();
                firstPerson = true;
                FbCamera.attach(viewer, ghost.id());
            } else {
                firstPerson = false;
                if (viewer.isOnline()) {
                    FbCamera.reset(viewer);
                    if (freePosition != null && viewer.getWorld().equals(world)) viewer.teleport(freePosition);
                }
                freePosition = null;
            }
            viewer.sendMessage(on ? "§bКамера: от первого лица. §7/fb camera free — свободный вид."
                    : "§bКамера: свободный вид.");
        }

        void again() {
            for (int[] c : shown) world.getBlockAt(c[0], c[1], c[2]).setType(Material.AIR);
            shown.clear();
            build(lane, map, rep.layout);
            blockIdx = 0;
            clickIdx = 0;
            t = 0;
            ended = false;
            paused = false;
        }

        void tick() {
            ticks++;
            if (!viewer.isOnline()) {
                cleanup(false);
                return;
            }
            if (ghost == null || !ghost.alive()) {
                if (!spawnStand()) {
                    viewer.sendMessage("§cНе удалось показать призрака, запись закрыта.");
                    cleanup(true);
                    return;
                }
            }
            if (!paused && !ended) t += 50 * speed();
            if (!viewer.getWorld().equals(world)) { cleanup(false); return; }
            int lz = lane * SPACING;
            // блоки до текущего момента
            while (blockIdx < rep.blocks.length && rep.blocks[blockIdx][0] <= t) {
                int[] b = rep.blocks[blockIdx++];
                Block blk = world.getBlockAt(b[1], FLOOR_Y + b[2], lz + b[3]);
                if (b[6] == 1) {
                    blk.setTypeIdAndData(b[4], (byte) b[5], false);
                    shown.add(new int[]{blk.getX(), blk.getY(), blk.getZ()});
                    if (ticks % 2 == 0) blk.getWorld().playSound(blk.getLocation(), Sound.DIG_STONE, 0.4f, 1f);
                } else {
                    blk.setType(Material.AIR);
                }
            }
            double[] pose = rep.poseAt(t);
            int fl = (int) pose[5];
            ghost.move(pose[0], FLOOR_Y + pose[1], lz + pose[2], (float) pose[3], (float) pose[4],
                    (fl & Replay.SNEAK) != 0, (fl & Replay.SPRINT) != 0);
            if (firstPerson) {
                // Keep chunks around the camera loaded, including long/endless recordings.
                Location anchor = new Location(world, pose[0], FLOOR_Y + pose[1] + 3, lz + pose[2]);
                if (viewer.getLocation().distanceSquared(anchor) > 64) {
                    if (!viewer.teleport(anchor)) { setFirstPerson(false); }
                    else FbCamera.attach(viewer, ghost.id());
                }
            }
            while (clickIdx < rep.clicks.length && rep.clicks[clickIdx][0] <= t) {
                if (!paused && !ended) ghost.swing();
                clickIdx++;
            }
            if (!ended && t >= rep.duration() + 1200) {
                ended = true;
                setFirstPerson(false);
                viewer.sendMessage("§6Fastbridge: §7запись закончилась. «Сначала» - ещё раз, барьер - назад на дорожку.");
            }
            if (ticks % 2 == 0) {
                long shownT = (long) Math.min(t, rep.duration());
                FbHud.actionBar(viewer, keysLine(fl) + " §8| §fЛКМ §e" + rep.cps(t, 1) + " §8| §fПКМ §e" + rep.cps(t, 2)
                        + " §8| §f" + fmt(shownT) + "/" + fmt(rep.duration()) + " §8| §bx" + trim(speed()) + (paused ? " §c(пауза)" : ""));
            }
        }

        private String key(boolean on, String name) {
            return (on ? "§a§l" : "§8") + name;
        }

        /** Нажатые клавиши: W A S D, Shift, бег, прыжок (по смещению игрока; клавиши выводятся приблизительно). */
        String keysLine(int fl) {
            return key((fl & Replay.KEY_W) != 0, "W") + " " + key((fl & Replay.KEY_A) != 0, "A") + " "
                    + key((fl & Replay.KEY_S) != 0, "S") + " " + key((fl & Replay.KEY_D) != 0, "D") + " §8| "
                    + key((fl & Replay.SNEAK) != 0, "SHIFT") + " " + key((fl & Replay.SPRINT) != 0, "БЕГ") + " "
                    + key((fl & Replay.AIR) != 0, "ПРЫЖОК");
        }

        void refreshHud() {
            List<String> l = new ArrayList<String>();
            l.add(SEP_TOP);
            l.add("§7Реплей: §e" + rep.player);
            l.add("§7Карта: §f" + map.title);
            l.add("§a ");
            l.add("§fРезультат: §a" + fmtVal(map, rep.value));
            l.add("§fПрошло: §e" + fmt((long) Math.min(t, rep.duration())) + "/" + fmt(rep.duration()));
            l.add("§fСкорость: §bx" + trim(speed()) + (paused ? " §c||" : ""));
            l.add("§a  ");
            l.add("§fКПС ЛКМ: §e" + rep.cps(t, 1) + " §7(макс " + rep.maxCps(1) + ")");
            l.add("§fКПС ПКМ: §e" + rep.cps(t, 2) + " §7(макс " + rep.maxCps(2) + ")");
            l.add("§fСр. КПС: §e" + String.format("%.1f", rep.avgCps(1)).replace(',', '.') + " §7/ §e"
                    + String.format("%.1f", rep.avgCps(2)).replace(',', '.'));
            l.add("§fКликов: §e" + rep.total(1) + " §7/ §e" + rep.total(2));
            l.add("§fБлоков: §e" + rep.blocks.length);
            l.add(SEP_BOTTOM);
            l.add("§emc-apex.ru");
            hud = Collections.unmodifiableList(l);
            if (FastBridge.this.hud != null) FastBridge.this.hud.refresh(viewer);
        }

        void cleanup(boolean sendToHub) {
            // Reset before destroying the target, even on world changes and disable.
            if (viewer.isOnline()) FbCamera.reset(viewer);
            firstPerson = false;
            freePosition = null;
            if (task != null) task.cancel();
            task = null;
            if (ghost != null) ghost.remove();
            ghost = null;
            for (int[] c : shown) world.getBlockAt(c[0], c[1], c[2]).setType(Material.AIR);
            shown.clear();
            build(lane, map, rep.layout);
            watches.remove(viewer.getUniqueId());
            refreshVisibility();
            if (laneOwner[lane] == this) laneOwner[lane] = null;
            if (FastBridge.this.hud != null) FastBridge.this.hud.release(viewer);
            if (viewer.isOnline()) {
                viewer.setAllowFlight(false);
                viewer.setFlying(false);
                if (sendToHub) toHub.accept(viewer);
            }
        }
    }

    private static String trim(double v) {
        return v == Math.floor(v) ? String.valueOf((int) v) : String.valueOf(v);
    }

    private void startWatch(Player viewer, Replay rep, double speed) {
        if ((rep.personal && !viewer.getUniqueId().toString().equals(rep.uuid)) || !perks.mayWatch(rep.id, viewer.getUniqueId())) {
            viewer.sendMessage("§cЭто приватная запись."); return;
        }
        Sess mine = sessions.get(viewer.getUniqueId());
        Watch old = watches.get(viewer.getUniqueId());
        int lane = freeLane(rep.personal);
        if (lane < 0 && mine != null && mine.personal == rep.personal) lane = mine.lane;
        if (lane < 0 && old != null && (old.lane >= PUBLIC_LANES) == rep.personal) lane = old.lane;
        if (lane < 0) {
            viewer.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
            return;
        }
        if (mine != null) detach(mine, false);
        if (old != null) old.cleanup(false);
        Watch w = new Watch(viewer, lane, rep, speed);
        watches.put(viewer.getUniqueId(), w);
        laneOwner[lane] = w;
        if (!w.begin()) {
            viewer.sendMessage("§cНе удалось запустить запись.");
            w.cleanup(true);
            return;
        }
        w.refreshHud();
        refreshVisibility();
    }

    /** Читаем файл не в основном потоке, показываем в основном. */
    private void startWatchFile(final Player viewer, final File f, final double speed) {
        final long request = ++nextReplayRequest;
        replayRequests.put(viewer.getUniqueId(), request);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                Replay r;
                try {
                    r = Replay.read(f);
                } catch (Exception ex) {
                    plugin.getLogger().warning("FastBridge: запись " + f.getName() + " не прочитана: " + ex);
                    Bukkit.getScheduler().runTask(plugin, new Runnable() {
                        public void run() { if (viewer.isOnline()) viewer.sendMessage("§cЗапись повреждена."); }
                    });
                    return;
                }
                final Replay rep = r;
                Bukkit.getScheduler().runTask(plugin, new Runnable() {
                    public void run() {
                        Long active = replayRequests.get(viewer.getUniqueId());
                        if (viewer.isOnline() && active != null && active == request) startWatch(viewer, rep, speed);
                    }
                });
            }
        });
    }

    private boolean buttonSess(Sess s, String name) {
        if (name.equals(I_PERKS)) {
            openPerks(s.p);
        } else if (name.equals(I_MAP)) {
            openMapMenu(s);
        } else if (name.equals(I_REPLAYS)) {
            openReplayMenu(s.p, s.p.getName());
        } else if (name.equals(I_RESTART)) {
            restart(s, null);
        } else if (name.equals(I_EXIT)) {
            detach(s, true);
        } else {
            return false;
        }
        return true;
    }

    /** Закрыть запись, но остаться в режиме: игрок снова на своей дорожке. */
    private void backToTraining(Watch w) {
        Player p = w.viewer;
        boolean personal = w.rep.personal && perks.privateAllowed(rank(p));
        w.cleanup(false);
        if (p.isOnline()) join(p, personal);
    }

    private boolean buttonWatch(Watch w, String name) {
        if (name.equals(W_CAMERA)) {
            w.setFirstPerson(!w.firstPerson);
        } else if (name.equals(W_FAVORITE)) {
            favorite(w.viewer, w.rep.id);
        } else if (name.equals(W_SLOW)) {
            w.speedIdx = Math.max(0, w.speedIdx - 1);
        } else if (name.equals(W_FAST)) {
            w.speedIdx = Math.min(SPEEDS.length - 1, w.speedIdx + 1);
        } else if (name.equals(W_PAUSE)) {
            w.paused = !w.paused;
        } else if (name.equals(W_AGAIN)) {
            w.again();
        } else if (name.equals(W_BACK)) {
            backToTraining(w);
        } else {
            return false;
        }
        return true;
    }

    /* ============================== меню ============================== */

    private void openMapMenu(Sess s) {
        Inventory inv = Bukkit.createInventory(null, 27, T_MAP);
        Map<Integer, String> refs = new HashMap<Integer, String>();
        UUID u = s.p.getUniqueId();
        FbMap[] maps = FbMap.values();
        for (int i = 0; i < maps.length; i++) {
            FbMap m = maps[i];
            boolean cur = m == s.map;
            ItemStack it = item(m.icon, m.iconData, (cur ? "§a" : "§e") + m.title,
                    "§7Рекорд: §6" + fmtVal(m, stats.best(u, m)),
                    "§7Рекорд 24ч: §d" + fmtVal(m, stats.best24h(u, m)),
                    "§7Попыток (24ч): §e" + stats.attempts24h(u, m),
                    "",
                    cur ? "§aСейчас выбрана" : "§7Нажми, чтобы выбрать");
            inv.setItem(11 + i, it);
            refs.put(11 + i, "map:" + m.id);
        }
        menuRefs.put(u, refs);
        s.p.openInventory(inv);
    }

    private void openReplayMenu(Player viewer, String nick) { openReplayMenu(viewer, nick, 0, false); }

    private void openReplayMenu(Player viewer, String nick, int page, boolean favorites) {
        Inventory inv = Bukkit.createInventory(null, 54, T_REPLAYS);
        Map<Integer, String> refs = new HashMap<Integer, String>();
        List<File> files = new ArrayList<File>();
        Set<String> starred = new HashSet<String>(perks.favorites(viewer.getUniqueId()));
        for (File f : filesOf(nick)) {
            String id = f.getName().replace(".json", "");
            if (perks.mayWatch(id, viewer.getUniqueId()) && (!favorites || starred.contains(id))) files.add(f);
        }
        int pages = Math.max(1, (files.size() + 35) / 36);
        page = Math.max(0, Math.min(page, pages - 1));
        Player online = Bukkit.getPlayerExact(nick);
        UUID target = online != null ? online.getUniqueId() : stats.uuidByName(nick);
        for (int i = 0; i < FbMap.values().length; i++) {
            FbMap m = FbMap.values()[i];
            String id = target == null ? null : stats.bestReplay(target, m);
            if (id != null && perks.mayWatch(id, viewer.getUniqueId()) && new File(replayDir, id + ".json").isFile()) {
                inv.setItem(2 + i, item(Material.GOLD_INGOT, 0, "§6Рекорд: " + m.title,
                        "§7Результат: §a" + fmtVal(m, stats.best(target, m)), "§eЛКМ §7— смотреть", "§eShift+ЛКМ §7— медленно"));
                refs.put(2 + i, "watch:" + id);
            }
        }
        for (int i = page * 36; i < files.size() && i < (page + 1) * 36; i++) {
            File f = files.get(i);
            String[] meta = Replay.parseName(f.getName());
            FbMap m = FbMap.of(meta[2]);
            if (m == null) continue;
            String id = f.getName().replace(".json", "");
            String d = meta[0];
            String when = d.substring(6, 8) + "." + d.substring(4, 6) + " " + d.substring(9, 11) + ":" + d.substring(11, 13);
            int slot = 9 + i % 36;
            inv.setItem(slot, item(starred.contains(id) ? Material.NETHER_STAR : Material.PAPER, 0,
                    (starred.contains(id) ? "§e★ " : "§f") + m.title + " §7— §a" + fmtVal(m, Long.parseLong(meta[3])),
                    "§7" + when + (perks.privateReplay(id) ? " §dПриватная" : ""),
                    "§eЛКМ §7— смотреть; Shift — медленно", "§eПКМ §7— в избранное / убрать (свои записи)"));
            refs.put(slot, "watch:" + id);
        }
        if (files.isEmpty()) inv.setItem(22, item(Material.BARRIER, 0, "§7Записей пока нет"));
        String nav = "replays:" + nick + ":" + favorites + ":";
        if (page > 0) { inv.setItem(45, item(Material.ARROW, 0, "§eНазад")); refs.put(45, nav + (page - 1)); }
        if (page + 1 < pages) { inv.setItem(53, item(Material.ARROW, 0, "§eДальше")); refs.put(53, nav + (page + 1)); }
        inv.setItem(49, item(Material.NETHER_STAR, 0, "§6Страница " + (page + 1) + "/" + pages, "§7Нажми: настройки и лимиты хранения"));
        refs.put(49, "settings");
        menuRefs.put(viewer.getUniqueId(), refs);
        viewer.openInventory(inv);
    }

    private boolean fbMenu(String title) {
        return T_MAP.equals(title) || T_REPLAYS.equals(title) || T_PERKS.equals(title) || T_HISTORY.equals(title);
    }

    @EventHandler
    public void onDrag(InventoryDragEvent e) {
        if (fbMenu(e.getInventory().getTitle())) e.setCancelled(true);
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        if (!(e.getWhoClicked() instanceof Player)) return;
        final Player p = (Player) e.getWhoClicked();
        String title = e.getInventory() != null ? e.getInventory().getTitle() : null;
        if (fbMenu(title)) {
            e.setCancelled(true);
            if (e.getRawSlot() < 0 || e.getRawSlot() >= e.getInventory().getSize()) return;
            Map<Integer, String> refs = menuRefs.get(p.getUniqueId());
            final String ref = refs == null ? null : refs.get(e.getRawSlot());
            if (ref == null) return;
            final boolean right = e.isRightClick(), slow = e.isShiftClick();
            Bukkit.getScheduler().runTask(plugin, new Runnable() {
                public void run() { if (p.isOnline()) menuAction(p, ref, right, slow); }
            });
            return;
        }
        if (p.getWorld().equals(world) && p.getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
    }

    private void menuAction(Player p, String ref, boolean right, boolean slow) {
        replayRequests.remove(p.getUniqueId());
        p.closeInventory();
        String[] bits = ref.split(":");
        if (ref.startsWith("map:")) {
            Sess s = sessions.get(p.getUniqueId());
            FbMap m = FbMap.of(bits[1]);
            if (s != null && m != null) selectMap(s, m);
        } else if (ref.startsWith("watch:")) {
            if (right) favorite(p, bits[1]);
            else {
                File f = new File(replayDir, bits[1] + ".json");
                if (f.isFile()) startWatchFile(p, f, slow ? 0.5 : 1);
                else p.sendMessage("§7Запись ещё сохраняется или уже удалена.");
            }
        } else if (ref.startsWith("replays:")) {
            openReplayMenu(p, bits[1], Integer.parseInt(bits[3]), Boolean.parseBoolean(bits[2]));
        } else if (ref.startsWith("history:")) {
            openHistory(p, Integer.parseInt(bits[1]));
        } else if (ref.startsWith("preset:")) {
            perksCommand(p, "preset", new String[]{"preset", right ? "delete" : "load", bits[1]});
        } else if (ref.equals("block")) {
            String[] types = {"sandstone", "wool", "clay", "planks"};
            String current = perks.setting(p.getUniqueId(), "block", "sandstone");
            int i = Arrays.asList(types).indexOf(current);
            changeBlock(p, types[(i + 1) % types.length]);
            openPerks(p);
        } else if (ref.startsWith("effect:")) {
            chooseEffect(p, bits[1]); openPerks(p);
        } else if (ref.equals("auto")) {
            perks.putSetting(p.getUniqueId(), "auto", "on".equals(perks.setting(p.getUniqueId(), "auto", "on")) ? "off" : "on");
            openPerks(p);
        } else if (ref.equals("private")) {
            Sess session = sessions.get(p.getUniqueId());
            join(p, session == null || !session.personal);
        } else if (ref.equals("favorites")) {
            openReplayMenu(p, p.getName(), 0, true);
        } else if (ref.equals("settings")) openPerks(p);
    }

    private int rank(Player p) {
        for (int r = 5; r >= 1; r--) if (p.hasPermission("apex.donor." + FbPerks.RANKS[r])) return r;
        return 0;
    }

    private Material blockType(Player p) {
        String key = perks.setting(p.getUniqueId(), "block", "sandstone");
        if (key.equals("wool")) return Material.WOOL;
        if (key.equals("clay")) return Material.STAINED_CLAY;
        if (key.equals("planks")) return Material.WOOD;
        return Material.SANDSTONE;
    }

    private Material replayMaterial(Replay rep) {
        try {
            Material m = Material.valueOf(rep.material);
            if (m == Material.WOOL || m == Material.STAINED_CLAY || m == Material.WOOD) return m;
        } catch (IllegalArgumentException ignored) { }
        return Material.SANDSTONE;
    }

    private void changeBlock(Player p, String block) {
        if (!Arrays.asList("sandstone", "wool", "clay", "planks").contains(block)) {
            p.sendMessage("§7/fb block sandstone|wool|clay|planks"); return;
        }
        Sess session = sessions.get(p.getUniqueId());
        if (session != null) logAttempt(session, "смена блока", -1, "");
        perks.putSetting(p.getUniqueId(), "block", block);
        if (session != null) restart(session, "§aБлок изменён: " + block);
    }

    private void logAttempt(Sess s, String outcome, long value, String replay) {
        Run r = s.run;
        if (r.timerStart < 0 || r.historyRecorded) return;
        r.historyRecorded = true;
        Replay metrics = new Replay();
        metrics.clicks = r.clicks.toArray(new int[r.clicks.size()][]);
        Map<String, Object> entry = new LinkedHashMap<String, Object>();
        entry.put("at", System.currentTimeMillis());
        entry.put("map", s.map.id);
        entry.put("outcome", outcome);
        entry.put("elapsed", Math.max(0, System.currentTimeMillis() - r.timerStart));
        entry.put("value", value);
        entry.put("blocks", r.count);
        entry.put("distance", Math.max(0, r.maxX - ISLAND + 1));
        entry.put("left_cps", metrics.maxCps(1));
        entry.put("right_cps", metrics.maxCps(2));
        entry.put("private", s.personal);
        entry.put("replay", replay);
        perks.history(s.p.getUniqueId(), rank(s.p), entry);
    }

    private static final String[] EFFECTS = {"none", "hearts", "notes", "sparks", "flame", "crown"};
    private static final String[] EFFECT_NAMES = {"Без эффекта", "Сердца", "Ноты", "Искры", "Огненное кольцо", "Корона"};

    private void chooseEffect(Player p, String id) {
        int required = Arrays.asList(EFFECTS).indexOf(id);
        if (required < 0) { p.sendMessage("§7/fb effect none|hearts|notes|sparks|flame|crown"); return; }
        if (rank(p) < required) { p.sendMessage("§cДля этого эффекта нужен более высокий ранг."); return; }
        perks.putSetting(p.getUniqueId(), "effect", id);
        p.sendMessage("§aЭффект финиша: " + EFFECT_NAMES[required]);
    }

    private void finishEffect(Player p) {
        int effect = Arrays.asList(EFFECTS).indexOf(perks.setting(p.getUniqueId(), "effect", "none"));
        if (effect <= 0 || effect > rank(p)) return;
        Effect particle = effect == 1 ? Effect.HEART : effect == 2 ? Effect.NOTE : effect == 3 ? Effect.FIREWORKS_SPARK : effect == 4 ? Effect.FLAME : Effect.VILLAGER_THUNDERCLOUD;
        Location at = p.getLocation().clone().add(0, effect == 5 ? 2.2 : 0.7, 0);
        for (int i = 0; i < 16; i++) {
            double angle = 2 * Math.PI * i / 16;
            Location l = at.clone().add(Math.cos(angle) * 0.8, 0, Math.sin(angle) * 0.8);
            // Cosmetic packets only to the finisher: no damage or effect leakage into private sessions.
            p.spigot().playEffect(l, particle, 0, 0, 0.03f, 0.03f, 0.03f, 0.01f, 2, 32);
        }
    }

    private void favorite(final Player p, String id) {
        if (id.equalsIgnoreCase("last")) {
            id = lastReplay.get(p.getUniqueId());
            if (id == null) { List<File> files = filesOf(p.getName()); if (!files.isEmpty()) id = files.get(0).getName().replace(".json", ""); }
        }
        if (id == null || !id.matches("[A-Za-z0-9_-]+")) { p.sendMessage("§7/fb favorite last|id"); return; }
        final String replayId = id;
        final File f = new File(replayDir, id + ".json");
        if (!f.isFile()) { p.sendMessage("§7Запись ещё сохраняется или уже удалена."); return; }
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    final Replay rep = Replay.read(f);
                    Bukkit.getScheduler().runTask(plugin, new Runnable() {
                        public void run() {
                            if (!p.isOnline()) return;
                            if (!p.getUniqueId().toString().equals(rep.uuid)) { p.sendMessage("§cДобавлять в избранное можно только свои записи."); return; }
                            synchronized (perks) {
                                if (!f.isFile()) { p.sendMessage("§7Запись уже удалена."); return; }
                                if (!perks.toggleFavorite(p.getUniqueId(), replayId, rank(p))) {
                                    p.sendMessage("§cЛимит избранного: " + perks.limit(rank(p), "favorites") + ". Удали старую запись или повысь ранг."); return;
                                }
                            }
                            p.sendMessage(perks.favorite(p.getUniqueId(), replayId) ? "§e★ Запись в избранном и защищена от автоудаления." : "§7Запись убрана из избранного.");
                        }
                    });
                } catch (Exception error) {
                    Bukkit.getScheduler().runTask(plugin, new Runnable() {
                        public void run() { if (p.isOnline()) p.sendMessage("§cНе удалось прочитать запись."); }
                    });
                }
            }
        });
    }

    private void openPerks(Player p) {
        Inventory inv = Bukkit.createInventory(null, 54, T_PERKS);
        Map<Integer, String> refs = new HashMap<Integer, String>();
        UUID u = p.getUniqueId(); int r = rank(p);
        inv.setItem(4, item(Material.BOOK, 0, "§6Твои лимиты",
                "§7Пресеты: §f" + perks.presets(u).size() + "/" + perks.limit(r, "presets"),
                "§7Избранное: §f" + perks.favorites(u).size() + "/" + perks.limit(r, "favorites"),
                "§7Обычные реплеи: до §f" + perks.limit(r, "replays") + " §7за §f" + perks.limit(r, "days") + " дн.",
                "§7Рекорды и избранное защищены отдельно", "§7История: §f" + perks.limit(r, "history") + " попыток"));
        inv.setItem(10, item(blockType(p), 0, "§eБлок: " + perks.setting(u, "block", "sandstone"), "§7Нажми для следующего материала")); refs.put(10, "block");
        inv.setItem(11, item(Material.WATCH, 0, "§eАвтоповтор: " + perks.setting(u, "auto", "on"), "§7Возврат через 2,5 секунды после финиша")); refs.put(11, "auto");
        inv.setItem(12, item(Material.PAPER, 0, "§eИстория попыток", "§7Финиши, падения, сбросы и выходы")); refs.put(12, "history:0");
        inv.setItem(14, item(Material.NETHER_STAR, 0, "§eИзбранные реплеи", "§7Защищены от автоудаления")); refs.put(14, "favorites");
        inv.setItem(15, item(Material.ENDER_PEARL, 0, "§dПриватная / общая тренировка", "§7От APEX; 4 отдельные дорожки", "§7Без зрителей и общего рейтинга")); refs.put(15, "private");
        inv.setItem(16, item(Material.NAME_TAG, 0, "§eСохранить пресет", "§f/fb preset save имя", "§7ЛКМ по пресету — загрузить; ПКМ — удалить"));
        for (int i = 0; i < EFFECTS.length; i++) {
            inv.setItem(19 + i, item(Material.FIREWORK, 0, (r >= i ? "§a" : "§c") + EFFECT_NAMES[i],
                    "§7Эффект финиша", perks.setting(u, "effect", "none").equals(EFFECTS[i]) ? "§aВыбран" : r >= i ? "§7Нажми для выбора" : "§7Нужен ранг " + FbPerks.RANKS[i]));
            refs.put(19 + i, "effect:" + EFFECTS[i]);
        }
        List<String> presets = perks.presets(u);
        for (int i = 0; i < presets.size() && i < 12; i++) {
            inv.setItem(27 + i, item(Material.EMPTY_MAP, 0, "§e" + presets.get(i), "§7ЛКМ — загрузить; ПКМ — удалить")); refs.put(27 + i, "preset:" + presets.get(i));
        }
        menuRefs.put(u, refs); p.openInventory(inv);
    }

    private void openHistory(Player p, int page) {
        List<Map<?, ?>> rows = perks.history(p.getUniqueId());
        int pages = Math.max(1, (rows.size() + 44) / 45); page = Math.max(0, Math.min(page, pages - 1));
        Inventory inv = Bukkit.createInventory(null, 54, T_HISTORY);
        Map<Integer, String> refs = new HashMap<Integer, String>();
        for (int i = page * 45; i < rows.size() && i < (page + 1) * 45; i++) {
            Map<?, ?> row = rows.get(i);
            String replay = String.valueOf(row.get("replay"));
            String when = new SimpleDateFormat("dd.MM HH:mm:ss").format(new Date(((Number) row.get("at")).longValue()));
            int slot = i % 45;
            inv.setItem(slot, item(Material.PAPER, 0, "§e" + row.get("map") + " §7— §f" + row.get("outcome"),
                    "§7" + when + (Boolean.TRUE.equals(row.get("private")) ? " §dПриватно" : ""),
                    "§7Время: §f" + fmt(((Number) row.get("elapsed")).longValue()) + " с",
                    "§7Поставлено: §f" + row.get("blocks") + " §7Дальность: §f" + row.get("distance"),
                    "§7Макс. КПС ЛКМ/ПКМ: §f" + row.get("left_cps") + "/" + row.get("right_cps"),
                    replay.length() > 0 && new File(replayDir, replay + ".json").isFile() ? "§eНажми — реплей финиша" : "§8Без доступного реплея"));
            if (!replay.isEmpty() && new File(replayDir, replay + ".json").isFile()) refs.put(slot, "watch:" + replay);
        }
        if (rows.isEmpty()) inv.setItem(22, item(Material.BARRIER, 0, "§7История пока пуста", "§7Начни попытку: поставь первый блок"));
        if (page > 0) { inv.setItem(45, item(Material.ARROW, 0, "§eНазад")); refs.put(45, "history:" + (page - 1)); }
        if (page + 1 < pages) { inv.setItem(53, item(Material.ARROW, 0, "§eДальше")); refs.put(53, "history:" + (page + 1)); }
        inv.setItem(49, item(Material.BOOK, 0, "§6Страница " + (page + 1) + "/" + pages)); refs.put(49, "settings");
        menuRefs.put(p.getUniqueId(), refs); p.openInventory(inv);
    }

    private boolean perksCommand(Player p, String sub, String[] args) {
        UUID u = p.getUniqueId();
        if (sub.equals("settings") || sub.equals("perks")) openPerks(p);
        else if (sub.equals("history")) openHistory(p, 0);
        else if (sub.equals("favorites")) openReplayMenu(p, p.getName(), 0, true);
        else if (sub.equals("favorite")) favorite(p, args.length > 1 ? args[1] : "last");
        else if (sub.equals("private")) join(p, true);
        else if (sub.equals("public")) join(p, false);
        else if (sub.equals("block")) changeBlock(p, args.length > 1 ? args[1] : "");
        else if (sub.equals("effect")) chooseEffect(p, args.length > 1 ? args[1] : "");
        else if (sub.equals("preset")) {
            if (args.length < 3 || !FbPerks.validName(args[2])) {
                p.sendMessage("§7/fb preset save|load|delete имя §8(латиница, цифры, _ и -, до 16 символов)");
                p.sendMessage("§7Сохранены: §f" + perks.presets(u)); return true;
            }
            String action = args[1], name = args[2];
            if (action.equalsIgnoreCase("delete")) { perks.deletePreset(u, name); p.sendMessage("§7Пресет удалён: " + name); }
            else if (action.equalsIgnoreCase("save")) {
                Sess session = sessions.get(u);
                if (session == null) { p.sendMessage("§7Сначала зайди на дорожку: /fb"); return true; }
                p.sendMessage(perks.savePreset(u, name, session.map.id, rank(p)) ? "§aПресет сохранён: " + name : "§cНет доступа или достигнут лимит пресетов: " + perks.limit(rank(p), "presets"));
            } else if (action.equalsIgnoreCase("load")) {
                Map<String, String> preset = perks.preset(u, name, rank(p));
                if (preset == null) { p.sendMessage("§cПресет не найден или недоступен для текущего ранга."); return true; }
                Sess session = sessions.get(u);
                if (session == null) { join(p); session = sessions.get(u); }
                if (session == null) return true;
                logAttempt(session, "загрузка пресета", -1, "");
                for (String key : new String[]{"block", "effect", "auto"}) perks.putSetting(u, key, preset.get(key));
                FbMap map = FbMap.of(preset.get("map"));
                selectMap(session, map == null ? FbMap.B32 : map);
                p.sendMessage("§aПресет загружен: " + name);
            } else p.sendMessage("§7/fb preset save|load|delete имя");
        } else return false;
        return true;
    }

    private boolean privatePlayer(Player p) {
        Sess session = sessions.get(p.getUniqueId()); Watch watch = watches.get(p.getUniqueId());
        return session != null && session.personal || watch != null && watch.rep.personal;
    }

    private void refreshVisibility() {
        for (Player viewer : Bukkit.getOnlinePlayers()) for (Player subject : Bukkit.getOnlinePlayers()) {
            if (viewer.equals(subject)) continue;
            Set<UUID> ours = hidden.get(viewer.getUniqueId());
            boolean conceal = privatePlayer(subject) && !viewer.hasPermission("apex.fastbridge.private.bypass")
                    || privatePlayer(viewer) && !subject.hasPermission("apex.fastbridge.private.bypass");
            if (conceal && viewer.canSee(subject)) {
                viewer.hidePlayer(subject);
                if (ours == null) { ours = new HashSet<UUID>(); hidden.put(viewer.getUniqueId(), ours); }
                ours.add(subject.getUniqueId());
            } else if (!conceal && ours != null && ours.remove(subject.getUniqueId())) viewer.showPlayer(subject);
        }
    }

    private void restoreVisibility() {
        for (Map.Entry<UUID, Set<UUID>> pair : hidden.entrySet()) {
            Player viewer = Bukkit.getPlayer(pair.getKey());
            if (viewer != null) for (UUID id : pair.getValue()) { Player subject = Bukkit.getPlayer(id); if (subject != null) viewer.showPlayer(subject); }
        }
        hidden.clear();
    }

    private boolean guardPrivate(PlayerMoveEvent e) {
        Location to = e.getTo(); Player p = e.getPlayer();
        if (to == null || !to.getWorld().equals(world) || p.hasPermission("apex.fastbridge.private.bypass")) return true;
        Sess session = sessions.get(p.getUniqueId()); Watch watch = watches.get(p.getUniqueId());
        int own = session != null && session.personal ? session.lane : watch != null && watch.rep.personal ? watch.lane : -1;
        boolean inPrivate = to.getZ() >= PUBLIC_LANES * SPACING - 14;
        if (own >= 0) {
            FbMap map = session != null ? session.map : watch.map;
            if (Math.abs(to.getZ() - own * SPACING) <= 14 && to.getX() >= -12 && to.getX() <= endX(map) + ISLAND + 12) return true;
        } else if (!inPrivate) return true;
        e.setCancelled(true); return false;
    }

    @EventHandler(priority = EventPriority.HIGHEST, ignoreCancelled = true)
    public void onPrivateTeleport(PlayerTeleportEvent e) { guardPrivate(e); }

    @EventHandler
    public void onPrivateJoin(final PlayerJoinEvent e) {
        Bukkit.getScheduler().runTask(plugin, new Runnable() { public void run() {
            Player p = e.getPlayer();
            if (p.isOnline() && p.getWorld().equals(world) && !sessions.containsKey(p.getUniqueId()) && !watches.containsKey(p.getUniqueId())) toHub.accept(p);
            refreshVisibility();
        } });
    }

    /* ============================== команды ============================== */

    @Override
    public boolean onCommand(CommandSender sender, Command c, String label, String[] a) {
        if (!(sender instanceof Player)) {
            sender.sendMessage("Только для игрока.");
            return true;
        }
        Player p = (Player) sender;
        if (!enabled()) {
            p.sendMessage("§cFastBridge сейчас недоступен.");
            return true;
        }
        String sub = a.length == 0 ? "play" : a[0].toLowerCase();
        replayRequests.remove(p.getUniqueId());
        if (perksCommand(p, sub, a)) return true;
        if (sub.equals("camera")) {
            Watch watch = watches.get(p.getUniqueId());
            if (watch == null) p.sendMessage("§7Сначала открой запись: §f/fb replay");
            else if (a.length < 2) watch.setFirstPerson(!watch.firstPerson);
            else if (a[1].equalsIgnoreCase("first")) watch.setFirstPerson(true);
            else if (a[1].equalsIgnoreCase("free")) watch.setFirstPerson(false);
            else p.sendMessage("§7/fb camera [first|free]");
        } else if (sub.equals("oldtop")) {
            FbMap m = a.length > 1 ? FbMap.of(a[1]) : null;
            p.sendMessage("§6Fastbridge: старые платформы, архивный топ §f" + (m != null ? m.title : "32 блока"));
            List<String[]> rows = legacyStats.top(m != null ? m : FbMap.B32, 10);
            if (rows.isEmpty()) p.sendMessage("§7В архивном топе пока нет записей.");
            for (int i = 0; i < rows.size(); i++) p.sendMessage("§e" + (i + 1) + ". §f" + rows.get(i)[0] + " §7— §a" + fmtVal(m != null ? m : FbMap.B32, Long.parseLong(rows.get(i)[1])));
        } else if (sub.equals("play") || sub.equals("join")) {
            join(p);
        } else if (sub.equals("leave") || sub.equals("exit")) {
            leave(p);
        } else if (sub.equals("map")) {
            Sess s = sessions.get(p.getUniqueId());
            if (s == null) {
                join(p);
                s = sessions.get(p.getUniqueId());
                if (s == null) return true;
            }
            FbMap m = a.length > 1 ? FbMap.of(a[1]) : null;
            if (m != null) selectMap(s, m);
            else openMapMenu(s);
        } else if (sub.equals("top")) {
            FbMap m = a.length > 1 ? FbMap.of(a[1]) : null;
            Sess s = sessions.get(p.getUniqueId());
            top(p, m != null ? m : s != null ? s.map : stats.lastMap(p.getUniqueId()));
        } else if (sub.equals("replays") || sub.equals("records")) {
            openReplayMenu(p, a.length > 1 ? a[1] : p.getName());
        } else if (sub.equals("replay") || sub.equals("watch")) {
            replayCommand(p, a);
        } else {
            p.sendMessage("§6Камера реплея: §f/fb camera [first|free]§7. Архивный рейтинг: §f/fb oldtop [карта]");
            p.sendMessage("§6Новые функции: §f/fb settings, /fb preset, /fb favorites, /fb history, /fb private, /fb public");
            p.sendMessage("§6Fastbridge: §f/fb §7- играть, §f/fb map [16|24|32|64|inf]§7, §f/fb top [карта]§7,");
            p.sendMessage("§f/fb replays [ник] §7- список записей, §f/fb replay [last|best|ник|id] [скорость] §7- смотреть, §f/fb leave");
        }
        return true;
    }

    private void replayCommand(Player p, String[] a) {
        String arg = a.length > 1 ? a[1] : "last";
        double speed = 1;
        if (a.length > 2) {
            try {
                speed = Double.parseDouble(a[2]);
            } catch (NumberFormatException ignored) {
            }
        }
        File f = null;
        UUID u = p.getUniqueId();
        if (arg.equalsIgnoreCase("last")) {
            String id = lastReplay.get(u);
            if (id == null) {
                List<File> mine = filesOf(p.getName());
                if (!mine.isEmpty()) f = mine.get(0);
            } else {
                f = new File(replayDir, id + ".json");
            }
        } else if (arg.equalsIgnoreCase("best")) {
            Sess s = sessions.get(u);
            String id = stats.bestReplay(u, s != null ? s.map : stats.lastMap(u));
            if (id != null) f = new File(replayDir, id + ".json");
        } else {
            String clean = arg.replaceAll("[^A-Za-z0-9_\\-]", "");
            File exact = new File(replayDir, clean + ".json");
            if (exact.exists()) {
                f = exact;
            } else {
                List<File> theirs = filesOf(clean);
                if (!theirs.isEmpty()) f = theirs.get(0);
            }
        }
        if (f == null || !f.exists()) {
            p.sendMessage("§cЗапись не найдена. Закончи попытку или открой список: §f/fb replays");
            return;
        }
        startWatchFile(p, f, speed);
    }

    private void top(Player p, FbMap m) {
        p.sendMessage("§6Fastbridge: лучшие на карте §f" + m.title);
        List<String[]> rows = stats.top(m, 10);
        if (rows.isEmpty()) p.sendMessage("§7Пока никто не финишировал.");
        for (int i = 0; i < rows.size(); i++) {
            p.sendMessage("§e" + (i + 1) + ". §f" + rows.get(i)[0] + " §7- §a" + fmtVal(m, Long.parseLong(rows.get(i)[1])));
        }
    }

    /* ============================== защита мира ============================== */

    private boolean mine(org.bukkit.entity.Entity e) {
        return e instanceof Player && e.getWorld().equals(world);
    }

    @EventHandler
    public void onDamage(EntityDamageEvent e) {
        if (mine(e.getEntity())) e.setCancelled(true);
    }

    @EventHandler
    public void onFood(FoodLevelChangeEvent e) {
        if (mine(e.getEntity())) e.setCancelled(true);
    }

    @EventHandler
    public void onDrop(PlayerDropItemEvent e) {
        if (e.getPlayer().getWorld().equals(world)) e.setCancelled(true);
    }

    @EventHandler
    public void onWeather(WeatherChangeEvent e) {
        if (e.getWorld().equals(world) && e.toWeatherState()) e.setCancelled(true);
    }

    /** Призрака реплея создаём мы сами; чужие отмены спавна ему не помеха. */
    @EventHandler(priority = EventPriority.HIGHEST)
    public void onSpawn(CreatureSpawnEvent e) {
        if (ghostSpawn && e.getEntity() instanceof ArmorStand) e.setCancelled(false);
    }

    @EventHandler
    public void onWorldChange(PlayerChangedWorldEvent e) {
        if (!e.getFrom().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s != null) detach(s, false);
        Watch w = watches.get(e.getPlayer().getUniqueId());
        if (w != null) w.cleanup(false);
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        replayRequests.remove(e.getPlayer().getUniqueId());
        UUID id = e.getPlayer().getUniqueId();
        Sess s = sessions.get(id);
        if (s != null) detach(s, false);
        Watch w = watches.get(id);
        if (w != null) w.cleanup(false);
        menuRefs.remove(id);
        hidden.remove(id);
        for (Set<UUID> set : hidden.values()) set.remove(id);
        lastRight.remove(id);
    }
}
