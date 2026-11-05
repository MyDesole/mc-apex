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

    static final int LANES = 16;
    static final int SPACING = 30;     // шаг дорожек по z: между соседями есть запас на сносы в сторону
    static final int FLOOR_Y = 70;
    static final int ISLAND = 5;       // сторона островка; пропасть начинается с x = ISLAND
    private static final int HALF_WIDTH = 12;
    private static final int SAMPLE_TICKS = 2;
    private static final int MAX_FRAMES = 6000;      // 10 минут записи
    private static final int KEEP_PER_PLAYER = 40;
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
    private static final String W_BACK = "§cНазад к тренировке §7(ПКМ)";

    private final JavaPlugin plugin;
    private final Consumer<Player> toHub;
    private World world;
    private FbStats stats;
    private FbHud hud;
    private File replayDir;

    private final Map<UUID, Sess> sessions = new ConcurrentHashMap<UUID, Sess>();
    private final Map<UUID, Watch> watches = new ConcurrentHashMap<UUID, Watch>();
    private final Object[] laneOwner = new Object[LANES];
    private final Map<UUID, String> lastReplay = new HashMap<UUID, String>();
    private final Map<UUID, Map<Integer, String>> menuRefs = new HashMap<UUID, Map<Integer, String>>();
    private boolean ghostSpawn;
    private boolean warnedFallback;
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
        stats = new FbStats(new File(plugin.getDataFolder(), "fastbridge-stats.yml"), plugin.getLogger());

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
        sampler = Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                sample();
            }
        }, SAMPLE_TICKS, SAMPLE_TICKS);
        flusher = Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                stats.flush();
            }
        }, 600L, 600L);
        plugin.getLogger().info("FastBridge включён: " + LANES + " дорожек, мир " + WORLD);
    }

    void stop() {
        if (world == null) return;
        for (Sess s : new ArrayList<Sess>(sessions.values())) detach(s, false);
        for (Watch w : new ArrayList<Watch>(watches.values())) w.cleanup(false);
        if (sampler != null) sampler.cancel();
        if (flusher != null) flusher.cancel();
        stats.flush();
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
        int lz = lane * SPACING;
        // чистим всю полосу под любую карту, затем рисуем нужные островки
        for (int x = ISLAND; x < ISLAND + 64 + ISLAND + 2; x++) {
            for (int dz = -2; dz <= 2; dz++) world.getBlockAt(x, FLOOR_Y, lz + dz).setType(Material.AIR);
        }
        for (int dx = 0; dx < ISLAND; dx++) {
            for (int dz = -2; dz <= 2; dz++) {
                world.getBlockAt(dx, FLOOR_Y, lz + dz).setType(Material.QUARTZ_BLOCK);
                if (!map.endless()) world.getBlockAt(ISLAND + map.gap + dx, FLOOR_Y, lz + dz).setType(Material.EMERALD_BLOCK);
            }
        }
    }

    private int endX(FbMap map) {
        return map.endless() ? ISLAND + FbMap.INF_LIMIT : ISLAND + map.gap;
    }

    private Location spawn(int lane) {
        return new Location(world, 2.5, FLOOR_Y + 1, lane * SPACING + 0.5, -90f, 0f);
    }

    private int freeLane() {
        for (int i = 0; i < LANES; i++) if (laneOwner[i] == null) return i;
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
        int count;
        int maxX = -1;
        final List<double[]> frames = new ArrayList<double[]>();
        final List<int[]> blocks = new ArrayList<int[]>();
        final Set<String> placed = new HashSet<String>();
    }

    /** Игрок в режиме тренировки. */
    static final class Sess {
        final Player p;
        final int lane;
        FbMap map;
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

    void join(Player p) {
        if (!enabled()) {
            p.sendMessage("§cFastBridge сейчас недоступен.");
            return;
        }
        Sess ex = sessions.get(p.getUniqueId());
        if (ex != null) {
            restart(ex, null);
            return;
        }
        Watch w = watches.get(p.getUniqueId());
        if (w != null) w.cleanup(false);
        int lane = freeLane();
        if (lane < 0) {
            p.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
            return;
        }
        Sess s = new Sess(p, lane, stats.lastMap(p.getUniqueId()));
        sessions.put(p.getUniqueId(), s);
        laneOwner[lane] = s;
        p.setGameMode(GameMode.SURVIVAL);
        p.setAllowFlight(false);
        p.setFlying(false);
        build(lane, s.map);
        kit(s);
        p.teleport(spawn(lane));
        refreshHud(s);
        p.sendMessage("§6Fastbridge: §7карта §f" + s.map.title + "§7. Построй мост до зелёного островка, таймер пойдёт с первого блока.");
        p.sendMessage("§7Предметы: §bкарта§7, §dреплеи§7, §eзаново§7, §cвыход§7. Команды: §f/fb help");
    }

    private void kit(Sess s) {
        Player p = s.p;
        p.getInventory().clear();
        p.getInventory().setArmorContents(null);
        p.getInventory().setItem(0, new ItemStack(Material.SANDSTONE, 64));
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
        cleanLane(s);
        s.run = new Run();
        s.speed = 0;
        kit(s);
        s.p.teleport(spawn(s.lane));
        refreshHud(s);
        if (why != null) s.p.sendMessage(why);
    }

    private void selectMap(Sess s, FbMap map) {
        s.map = map;
        stats.setLastMap(s.p.getUniqueId(), s.p.getName(), map);
        restart(s, "§6Fastbridge: §7карта §f" + map.title);
    }

    /** Выйти из режима. toHub - ещё и перенести игрока в хаб. */
    private void detach(Sess s, boolean sendToHub) {
        cleanLane(s);
        sessions.remove(s.p.getUniqueId());
        laneOwner[s.lane] = null;
        if (hud != null) hud.release(s.p);
        if (sendToHub && s.p.isOnline()) toHub.accept(s.p);
    }

    void leave(Player p) {
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
            Run r = s.run;
            if (r.finished) continue;
            if (r.frames.size() >= MAX_FRAMES) {
                restart(s, "§cСлишком долго - попытка сброшена.");
                continue;
            }
            Location l = s.p.getLocation();
            int flags = (s.p.isSneaking() ? 1 : 0) | (s.p.isSprinting() ? 2 : 0);
            r.frames.add(new double[]{System.currentTimeMillis() - r.recStart,
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
        l.add("§7Карта: §f" + s.map.title);
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
        return b.getWorld().equals(world) && Math.abs(b.getZ() - lz) <= HALF_WIDTH
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

    private void allow(PlayerInteractEvent e) {
        e.setCancelled(false);
        e.setUseInteractedBlock(Event.Result.ALLOW);
        e.setUseItemInHand(Event.Result.ALLOW);
    }

    private boolean holdingBlocks(Player p) {
        ItemStack it = p.getItemInHand();
        return it != null && it.getType() == Material.SANDSTONE;
    }

    /**
     * Клики. Alley в лобби отменяет почти всё, поэтому для игроков дорожки мы отменяем эту отмену,
     * чтобы блоки ставились и ломались как в обычной игре. Если кто-то всё же заблокирует
     * (например, отменит позже нас), через пару тиков ставим блок сами.
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

        if (a == Action.RIGHT_CLICK_BLOCK && holdingBlocks(p)) {
            allow(e);
            final Block target = e.getClickedBlock().getRelative(e.getBlockFace());
            Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
                public void run() {
                    fallbackPlace(s, target);
                }
            }, 2L);
        } else if (a == Action.LEFT_CLICK_BLOCK && s.run.placed.contains(key(e.getClickedBlock()))) {
            allow(e);
            final Block b = e.getClickedBlock();
            Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
                public void run() {
                    if (sessions.get(p.getUniqueId()) == s && s.run.placed.contains(key(b)) && b.getType() != Material.AIR) {
                        warnFallback();
                        removeBlock(s, b);
                    }
                }
            }, 3L);
        }
    }

    private int fallbackUsed;

    /** Сколько раз пришлось ставить или ломать блок запасным путём (для проверки и логов). */
    int fallbackUsed() {
        return fallbackUsed;
    }

    private void warnFallback() {
        fallbackUsed++;
        if (!warnedFallback) {
            warnedFallback = true;
            plugin.getLogger().info("FastBridge: чужой плагин блокирует блоки на дорожке, включён запасной путь");
        }
    }

    /** Блок так и не поставился штатно - ставим сами. */
    private void fallbackPlace(Sess s, Block target) {
        if (sessions.get(s.p.getUniqueId()) != s || s.run.finished) return;
        if (target.getType() != Material.AIR || s.run.placed.contains(key(target)) || !inLane(s, target)) return;
        if (intersectsPlayer(s.p, target)) return;
        if (s.p.getEyeLocation().distance(target.getLocation().add(0.5, 0.5, 0.5)) > 6.5) return;
        if (!holdingBlocks(s.p)) return;
        warnFallback();
        target.setTypeIdAndData(Material.SANDSTONE.getId(), (byte) 0, false);
        record(s, target);
        target.getWorld().playSound(target.getLocation(), Sound.DIG_STONE, 0.8f, 1f);
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void onPlace(BlockPlaceEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Sess s = sessions.get(e.getPlayer().getUniqueId());
        if (s == null) {
            if (e.getPlayer().getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
            return;
        }
        boolean ok = !s.run.finished && inLane(s, e.getBlock()) && e.getBlock().getType() == Material.SANDSTONE
                && !intersectsPlayer(e.getPlayer(), e.getBlock());
        if (!ok) limitHint(e.getPlayer(), e.getBlock());
        e.setCancelled(!ok);
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
            stats.attempt(s.p.getUniqueId(), s.map);
        }
        r.placed.add(key(b));
        r.count++;
        r.maxX = Math.max(r.maxX, b.getX());
        r.blocks.add(new int[]{(int) (now - r.recStart), b.getX(), b.getY() - FLOOR_Y, b.getZ() - s.lane * SPACING,
                b.getTypeId(), b.getData(), 1});
        Bukkit.getScheduler().runTask(plugin, new Runnable() {
            public void run() {
                Player p = s.p;
                for (int i = 0; i < 9; i++) {
                    ItemStack st = p.getInventory().getItem(i);
                    if (st != null && st.getType() == Material.SANDSTONE && st.getAmount() < 64) st.setAmount(64);
                }
                if (p.getInventory().getItem(0) == null) p.getInventory().setItem(0, new ItemStack(Material.SANDSTONE, 64));
                p.updateInventory();
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
        boolean record = stats.finish(u, p.getName(), s.map, value, id);
        if (s.map.endless()) {
            p.sendMessage("§6Fastbridge: §aдальность " + value + " бл." + (limit ? " §7(предел дорожки)" : "")
                    + (record ? " §e- новый рекорд!" : " §7(рекорд " + fmtVal(s.map, prevBest) + ")"));
        } else {
            p.sendMessage("§6Fastbridge: §a" + fmt(value) + " с" + (record ? " §e- новый рекорд!" : " §7(рекорд " + fmtVal(s.map, prevBest) + ")"));
            if (!record && (prevDay < 0 || s.map.better(value, prevDay))) p.sendMessage("§dЭто лучший результат за 24 часа!");
        }
        p.playSound(p.getLocation(), record ? Sound.LEVEL_UP : Sound.NOTE_PLING, 1f, 1f);
        p.sendMessage("§7Запись сохранена: §f/fb replay last §7или предмет «Мои реплеи».");
        Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
            public void run() {
                if (sessions.get(p.getUniqueId()) == s && s.run == r && p.isOnline()) restart(s, null);
            }
        }, 50L);
    }

    /* ============================== реплеи: сохранение ============================== */

    private String saveReplay(Sess s, long value) {
        Run r = s.run;
        final Replay rep = new Replay();
        String stamp = new SimpleDateFormat("yyyyMMdd-HHmmss").format(new Date());
        rep.id = stamp + "_" + s.p.getName() + "_" + s.map.id + "_" + value;
        rep.player = s.p.getName();
        rep.uuid = s.p.getUniqueId().toString();
        rep.map = s.map.id;
        rep.value = value;
        rep.created = System.currentTimeMillis();
        rep.timerStartMs = r.timerStart >= 0 ? r.timerStart - r.recStart : 0;
        rep.frames = r.frames.toArray(new double[r.frames.size()][]);
        rep.blocks = r.blocks.toArray(new int[r.blocks.size()][]);
        final String nick = s.p.getName();
        final Set<String> keep = new HashSet<String>(stats.recordReplays());
        keep.add(rep.id);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    rep.write(new File(replayDir, rep.id + ".json"));
                } catch (Exception ex) {
                    plugin.getLogger().warning("FastBridge: запись не сохранена: " + ex);
                    return;
                }
                prune(nick, keep);
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

    private void prune(String nick, Set<String> keep) {
        List<File> files = filesOf(nick);
        for (int i = KEEP_PER_PLAYER; i < files.size(); i++) {
            String id = files.get(i).getName().replace(".json", "");
            if (!keep.contains(id)) files.get(i).delete();
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
        double t;
        int blockIdx;
        ArmorStand stand;
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
            build(lane, map);
            viewer.setGameMode(GameMode.ADVENTURE);
            viewer.getInventory().clear();
            viewer.getInventory().setArmorContents(null);
            viewer.getInventory().setItem(0, item(Material.STICK, 0, W_SLOW, "§7Скорость меньше"));
            viewer.getInventory().setItem(1, item(Material.REDSTONE_TORCH_ON, 0, W_PAUSE, "§7Пауза и продолжить"));
            viewer.getInventory().setItem(2, item(Material.BLAZE_ROD, 0, W_FAST, "§7Скорость больше"));
            viewer.getInventory().setItem(4, item(Material.EYE_OF_ENDER, 0, W_AGAIN, "§7Проиграть заново"));
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
            ghostSpawn = true;
            try {
                stand = world.spawn(at, ArmorStand.class);
            } catch (Throwable ex) {
                plugin.getLogger().warning("FastBridge: призрак не создан: " + ex);
                stand = null;
            } finally {
                ghostSpawn = false;
            }
            if (stand == null || !stand.isValid()) return false;
            stand.setGravity(false);
            stand.setBasePlate(false);
            stand.setArms(true);
            stand.setCustomName("§e" + rep.player);
            stand.setCustomNameVisible(true);
            ItemStack head = new ItemStack(Material.SKULL_ITEM, 1, (short) 3);
            SkullMeta sm = (SkullMeta) head.getItemMeta();
            sm.setOwner(rep.player);
            head.setItemMeta(sm);
            stand.setHelmet(head);
            stand.setItemInHand(new ItemStack(Material.SANDSTONE));
            stand.setRightArmPose(new EulerAngle(Math.toRadians(-60), 0, 0));
            return true;
        }

        void again() {
            for (int[] c : shown) world.getBlockAt(c[0], c[1], c[2]).setType(Material.AIR);
            shown.clear();
            build(lane, map);
            blockIdx = 0;
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
            if (stand == null || !stand.isValid()) {
                if (!spawnStand()) {
                    viewer.sendMessage("§cНе удалось показать призрака, запись закрыта.");
                    cleanup(true);
                    return;
                }
            }
            if (!paused && !ended) t += 50 * speed();
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
            Location l = new Location(world, pose[0], FLOOR_Y + pose[1], lz + pose[2], (float) pose[3], 0f);
            stand.teleport(l);
            stand.setHeadPose(new EulerAngle(Math.toRadians(pose[4]), 0, 0));
            if (!ended && t >= rep.duration() + 1200) {
                ended = true;
                viewer.sendMessage("§6Fastbridge: §7запись закончилась. «Сначала» - ещё раз, барьер - назад на дорожку.");
            }
            if (ticks % 4 == 0) {
                long shownT = (long) Math.min(t, rep.duration());
                int filled = rep.duration() == 0 ? 20 : (int) (20 * shownT / Math.max(1, rep.duration()));
                StringBuilder bar = new StringBuilder("§a");
                for (int i = 0; i < 20; i++) {
                    if (i == filled) bar.append("§7");
                    bar.append("|");
                }
                FbHud.actionBar(viewer, "§e" + rep.player + " §8| " + bar + " §8| §f" + fmt(shownT) + "/" + fmt(rep.duration())
                        + " §8| §bx" + trim(speed()) + (paused ? " §c(пауза)" : ""));
            }
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
            l.add(SEP_BOTTOM);
            l.add("§emc-apex.ru");
            hud = Collections.unmodifiableList(l);
            if (FastBridge.this.hud != null) FastBridge.this.hud.refresh(viewer);
        }

        void cleanup(boolean sendToHub) {
            if (task != null) task.cancel();
            task = null;
            if (stand != null) stand.remove();
            stand = null;
            for (int[] c : shown) world.getBlockAt(c[0], c[1], c[2]).setType(Material.AIR);
            shown.clear();
            build(lane, map);
            watches.remove(viewer.getUniqueId());
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
        Sess mine = sessions.get(viewer.getUniqueId());
        if (mine != null) detach(mine, false);
        Watch old = watches.get(viewer.getUniqueId());
        if (old != null) old.cleanup(false);
        int lane = freeLane();
        if (lane < 0) {
            viewer.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
            return;
        }
        Watch w = new Watch(viewer, lane, rep, speed);
        watches.put(viewer.getUniqueId(), w);
        laneOwner[lane] = w;
        if (!w.begin()) {
            viewer.sendMessage("§cНе удалось запустить запись.");
            w.cleanup(true);
            return;
        }
        w.refreshHud();
    }

    /** Читаем файл не в основном потоке, показываем в основном. */
    private void startWatchFile(final Player viewer, final File f, final double speed) {
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                Replay r;
                try {
                    r = Replay.read(f);
                } catch (Exception ex) {
                    plugin.getLogger().warning("FastBridge: запись " + f.getName() + " не прочитана: " + ex);
                    viewer.sendMessage("§cЗапись повреждена.");
                    return;
                }
                final Replay rep = r;
                Bukkit.getScheduler().runTask(plugin, new Runnable() {
                    public void run() {
                        if (viewer.isOnline()) startWatch(viewer, rep, speed);
                    }
                });
            }
        });
    }

    private boolean buttonSess(Sess s, String name) {
        if (name.equals(I_MAP)) {
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
        w.cleanup(false);
        if (p.isOnline()) join(p);
    }

    private boolean buttonWatch(Watch w, String name) {
        if (name.equals(W_SLOW)) {
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

    private void openReplayMenu(Player viewer, String nick) {
        Inventory inv = Bukkit.createInventory(null, 54, T_REPLAYS);
        Map<Integer, String> refs = new HashMap<Integer, String>();
        List<File> files = filesOf(nick);
        // верхняя строка: рекорды по картам
        FbMap[] maps = FbMap.values();
        Player online = Bukkit.getPlayerExact(nick);
        UUID target = online != null ? online.getUniqueId() : stats.uuidByName(nick);
        for (int i = 0; i < maps.length; i++) {
            FbMap m = maps[i];
            String rid = target == null ? null : stats.bestReplay(target, m);
            if (rid == null || !new File(replayDir, rid + ".json").exists()) {
                inv.setItem(2 + i, item(Material.COAL, 0, "§8" + m.title, "§7Рекорда пока нет"));
                continue;
            }
            inv.setItem(2 + i, item(Material.GOLD_INGOT, 0, "§6Рекорд: " + m.title,
                    "§7Результат: §a" + fmtVal(m, stats.best(target, m)), "", "§eЛКМ §7- смотреть", "§eShift+ЛКМ §7- медленно"));
            refs.put(2 + i, rid);
        }
        int slot = 9;
        for (File f : files) {
            if (slot >= 54) break;
            String[] meta = Replay.parseName(f.getName());
            FbMap m = FbMap.of(meta[2]);
            if (m == null) continue;
            long val = Long.parseLong(meta[3]);
            String d = meta[0];
            String when = d.substring(6, 8) + "." + d.substring(4, 6) + " " + d.substring(9, 11) + ":" + d.substring(11, 13);
            inv.setItem(slot, item(Material.PAPER, 0, "§e" + m.title + " §7- §a" + fmtVal(m, val),
                    "§7" + when, "", "§eЛКМ §7- смотреть", "§eShift+ЛКМ §7- медленно"));
            refs.put(slot, f.getName().replace(".json", ""));
            slot++;
        }
        if (files.isEmpty()) {
            inv.setItem(22, item(Material.BARRIER, 0, "§cЗаписей нет", "§7Закончи попытку, и она появится здесь"));
        }
        menuRefs.put(viewer.getUniqueId(), refs);
        viewer.openInventory(inv);
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        if (!(e.getWhoClicked() instanceof Player)) return;
        Player p = (Player) e.getWhoClicked();
        String title = e.getInventory() != null ? e.getInventory().getTitle() : null;
        if (T_MAP.equals(title) || T_REPLAYS.equals(title)) {
            e.setCancelled(true);
            if (e.getRawSlot() < 0 || e.getRawSlot() >= e.getInventory().getSize()) return;
            Map<Integer, String> refs = menuRefs.get(p.getUniqueId());
            String ref = refs == null ? null : refs.get(e.getRawSlot());
            if (ref == null) return;
            p.closeInventory();
            if (ref.startsWith("map:")) {
                Sess s = sessions.get(p.getUniqueId());
                FbMap m = FbMap.of(ref.substring(4));
                if (s != null && m != null) selectMap(s, m);
            } else {
                File f = new File(replayDir, ref + ".json");
                if (f.exists()) startWatchFile(p, f, e.isShiftClick() ? 0.5 : 1);
                else p.sendMessage("§cЗапись не найдена.");
            }
            return;
        }
        if (p.getWorld().equals(world) && p.getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
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
        if (sub.equals("play") || sub.equals("join")) {
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
        UUID id = e.getPlayer().getUniqueId();
        Sess s = sessions.get(id);
        if (s != null) detach(s, false);
        Watch w = watches.get(id);
        if (w != null) w.cleanup(false);
        menuRefs.remove(id);
    }
}
