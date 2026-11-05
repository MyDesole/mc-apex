package ru.mcapex.tiertests;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import org.bukkit.Bukkit;
import org.bukkit.GameMode;
import org.bukkit.Location;
import org.bukkit.Material;
import org.bukkit.World;
import org.bukkit.WorldCreator;
import org.bukkit.block.Block;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.ArmorStand;
import org.bukkit.entity.Player;
import org.bukkit.event.Event;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.block.Action;
import org.bukkit.event.block.BlockBreakEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.entity.FoodLevelChangeEvent;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.PlayerDropItemEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.generator.ChunkGenerator;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.inventory.meta.SkullMeta;

import java.io.File;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Date;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Random;
import java.util.Set;
import java.util.UUID;

/**
 * Тренировка быстрого строительства (fastbridge).
 *
 * Отдельный пустой мир "fastbridge": у каждого игрока своя дорожка из двух
 * островков с пропастью между ними. Нужно как можно быстрее построить мост и
 * дойти до дальнего островка. Таймер идёт с первого поставленного блока.
 * Упал - вернулся на старт, дорожка очищается.
 *
 * Каждая попытка пишется: позиция игрока каждые 2 тика и каждый блок, который
 * он поставил или сломал. Законченные попытки сохраняются в replays/*.json и
 * проигрываются командой /fastbridge replay: «призрак» (стойка для брони с головой
 * игрока) повторяет движения, а блоки появляются в те же моменты.
 */
final class FastBridge implements Listener, CommandExecutor {
    static final String WORLD = "fastbridge";

    private static final int LANES = 16;
    private static final int SPACING = 10;      // расстояние между дорожками по z
    private static final int FLOOR_Y = 70;      // уровень островков
    private static final int GAP = 25;          // пропасть между островками
    private static final int ISLAND = 5;        // сторона островка
    private static final int END_X = ISLAND + GAP;   // начало дальнего островка
    private static final int SAMPLE_TICKS = 2;  // как часто пишем позицию
    private static final int MAX_FRAMES = 3600; // потолок записи: 6 минут
    private static final int KEEP_REPLAYS = 500;

    private static final String ITEM_RESTART = "§eЗаново §7(ПКМ)";
    private static final String ITEM_EXIT = "§cВыход §7(ПКМ)";

    private final ApexTierTests plugin;
    private World world;
    private final Run[] lanes = new Run[LANES];
    private final Map<UUID, Run> runs = new HashMap<UUID, Run>();
    private final Map<UUID, String> lastReplay = new HashMap<UUID, String>();
    private final Map<UUID, Watch> watches = new HashMap<UUID, Watch>();
    private final Watch[] watchLanes = new Watch[LANES];
    private File replayDir;
    private File pbFile;
    private YamlConfiguration pb;

    FastBridge(ApexTierTests plugin) {
        this.plugin = plugin;
    }

    /* ----------------------------- запуск ----------------------------- */

    void start() {
        replayDir = new File(plugin.getDataFolder(), "replays");
        replayDir.mkdirs();
        pbFile = new File(plugin.getDataFolder(), "fastbridge.yml");
        pb = YamlConfiguration.loadConfiguration(pbFile);

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
        for (int i = 0; i < LANES; i++) buildLane(i);

        Bukkit.getPluginManager().registerEvents(this, plugin);
        plugin.getCommand("fastbridge").setExecutor(this);
        Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                sample();
            }
        }, SAMPLE_TICKS, SAMPLE_TICKS);
        plugin.getLogger().info("FastBridge включён: " + LANES + " дорожек, мир " + WORLD);
    }

    void stop() {
        for (Run r : new ArrayList<Run>(runs.values())) cleanLane(r);
        for (Watch w : new ArrayList<Watch>(watches.values())) w.cleanup();
    }

    boolean enabled() {
        return world != null;
    }

    /** Чтобы мир был пустым: пропасть под островками. */
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

    private void buildLane(int lane) {
        int lz = lane * SPACING;
        for (int dx = 0; dx < ISLAND; dx++) {
            for (int dz = -2; dz <= 2; dz++) {
                world.getBlockAt(dx, FLOOR_Y, lz + dz).setType(Material.QUARTZ_BLOCK);
                world.getBlockAt(END_X + dx, FLOOR_Y, lz + dz).setType(Material.EMERALD_BLOCK);
            }
        }
    }

    private Location spawn(int lane) {
        return new Location(world, 2.5, FLOOR_Y + 1, lane * SPACING + 0.5, -90f, 0f);
    }

    /* ----------------------------- попытка ----------------------------- */

    static final class Run {
        final Player player;
        final int lane;
        long recStart;
        long timerStart = -1;
        boolean finished;
        final List<double[]> frames = new ArrayList<double[]>();
        final List<int[]> blocks = new ArrayList<int[]>();   // t, x, y, z, id, data, place(1)/break(0)
        final Set<String> placed = new HashSet<String>();

        Run(Player player, int lane) {
            this.player = player;
            this.lane = lane;
        }
    }

    private int freeLane() {
        for (int i = 0; i < LANES; i++) if (lanes[i] == null && watchLanes[i] == null) return i;
        return -1;
    }

    private ItemStack item(Material m, String name) {
        ItemStack it = new ItemStack(m);
        ItemMeta im = it.getItemMeta();
        im.setDisplayName(name);
        it.setItemMeta(im);
        return it;
    }

    private void kit(Player p) {
        p.getInventory().clear();
        p.getInventory().setArmorContents(null);
        p.getInventory().setItem(0, new ItemStack(Material.SANDSTONE, 64));
        p.getInventory().setItem(7, item(Material.WATCH, ITEM_RESTART));
        p.getInventory().setItem(8, item(Material.REDSTONE, ITEM_EXIT));
        p.getInventory().setHeldItemSlot(0);
        p.setHealth(p.getMaxHealth());
        p.setFoodLevel(20);
        p.setFireTicks(0);
        p.setFallDistance(0);
    }

    void join(Player p) {
        if (!enabled()) {
            p.sendMessage("§cFastBridge сейчас недоступен.");
            return;
        }
        Run old = runs.get(p.getUniqueId());
        if (old != null) {
            restart(old, null);
            return;
        }
        Watch w = watches.get(p.getUniqueId());
        if (w != null) w.cleanup();
        int lane = freeLane();
        if (lane < 0) {
            p.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
            return;
        }
        Run r = new Run(p, lane);
        lanes[lane] = r;
        runs.put(p.getUniqueId(), r);
        p.setGameMode(GameMode.SURVIVAL);
        p.setAllowFlight(false);
        p.setFlying(false);
        kit(p);
        beginRecord(r);
        p.teleport(spawn(lane));
        p.sendMessage("§6Fastbridge: §7построй мост до зелёного островка. Таймер пойдёт с первого блока. §8/fb help");
    }

    private void beginRecord(Run r) {
        r.recStart = System.currentTimeMillis();
        r.timerStart = -1;
        r.finished = false;
        r.frames.clear();
        r.blocks.clear();
    }

    /** Убрать построенное игроком. */
    private void cleanLane(Run r) {
        int lz = r.lane * SPACING;
        for (String key : r.placed) {
            String[] c = key.split(",");
            world.getBlockAt(Integer.parseInt(c[0]), Integer.parseInt(c[1]), Integer.parseInt(c[2])).setType(Material.AIR);
        }
        r.placed.clear();
        buildLane(r.lane);
    }

    private void restart(Run r, String why) {
        cleanLane(r);
        beginRecord(r);
        Player p = r.player;
        kit(p);
        p.teleport(spawn(r.lane));
        if (why != null) p.sendMessage(why);
    }

    void leave(Player p) {
        Run r = runs.remove(p.getUniqueId());
        if (r != null) {
            cleanLane(r);
            lanes[r.lane] = null;
        }
        Watch w = watches.get(p.getUniqueId());
        if (w != null) w.cleanup();
        if (p.isOnline() && p.getWorld().equals(world)) plugin.hub().toHub(p);
    }

    /* ----------------------------- запись ----------------------------- */

    private void sample() {
        for (Run r : runs.values()) {
            if (r.finished) continue;
            if (r.frames.size() >= MAX_FRAMES) {
                restart(r, "§cСлишком долго - попытка сброшена.");
                continue;
            }
            Location l = r.player.getLocation();
            int flags = (r.player.isSneaking() ? 1 : 0) | (r.player.isSprinting() ? 2 : 0);
            r.frames.add(new double[]{System.currentTimeMillis() - r.recStart,
                    l.getX(), l.getY() - FLOOR_Y, l.getZ() - r.lane * SPACING, l.getYaw(), l.getPitch(), flags});
        }
    }

    private boolean inLane(Run r, Block b) {
        int lz = r.lane * SPACING;
        return b.getWorld().equals(world) && Math.abs(b.getZ() - lz) <= 6
                && b.getX() >= -2 && b.getX() <= END_X + ISLAND + 1
                && b.getY() >= FLOOR_Y - 2 && b.getY() <= FLOOR_Y + 3;
    }

    private static String key(Block b) {
        return b.getX() + "," + b.getY() + "," + b.getZ();
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void onPlace(BlockPlaceEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Run r = runs.get(e.getPlayer().getUniqueId());
        if (r == null || r.finished || !inLane(r, e.getBlock())) {
            if (e.getPlayer().getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
            return;
        }
        // Блоки ставим сами в onClick: Alley и другие плагины отменяют события лобби,
        // и штатная установка до этого места не доходит.
        e.setCancelled(true);
    }

    /**
     * Ставим и ломаем блоки руками, минуя чужие отмены. ПКМ по блоку с песчаником - поставить
     * на соседнюю клетку, ЛКМ по своему блоку - убрать. Работает только на своей дорожке.
     */
    @EventHandler(priority = EventPriority.HIGHEST)
    public void onClick(PlayerInteractEvent e) {
        Player p = e.getPlayer();
        if (!p.getWorld().equals(world)) return;
        Run r = runs.get(p.getUniqueId());
        if (r == null || r.finished || e.getClickedBlock() == null) return;
        Action a = e.getAction();
        if (a == Action.RIGHT_CLICK_BLOCK) {
            ItemStack inHand = p.getItemInHand();
            if (inHand == null || inHand.getType() != Material.SANDSTONE) return;
            e.setCancelled(true);
            Block target = e.getClickedBlock().getRelative(e.getBlockFace());
            if (!target.getType().equals(Material.AIR) || !inLane(r, target)) return;
            // как в игре: нельзя ставить блок в себя
            Location pl = p.getLocation();
            if (target.getX() == pl.getBlockX() && target.getZ() == pl.getBlockZ()
                    && (target.getY() == pl.getBlockY() || target.getY() == pl.getBlockY() + 1)) return;
            long now = System.currentTimeMillis();
            if (r.timerStart < 0) r.timerStart = now;
            target.setTypeIdAndData(Material.SANDSTONE.getId(), (byte) 0, false);
            r.placed.add(key(target));
            r.blocks.add(new int[]{(int) (now - r.recStart), target.getX(), target.getY() - FLOOR_Y,
                    target.getZ() - r.lane * SPACING, Material.SANDSTONE.getId(), 0, 1});
            target.getWorld().playSound(target.getLocation(), org.bukkit.Sound.DIG_STONE, 0.6f, 1f);
        } else if (a == Action.LEFT_CLICK_BLOCK) {
            Block b = e.getClickedBlock();
            if (r.placed.remove(key(b))) {
                e.setCancelled(true);
                b.setType(Material.AIR);
                r.blocks.add(new int[]{(int) (System.currentTimeMillis() - r.recStart), b.getX(), b.getY() - FLOOR_Y,
                        b.getZ() - r.lane * SPACING, 0, 0, 0});
            }
        }
    }

    @EventHandler(priority = EventPriority.HIGHEST)
    public void onBreak(BlockBreakEvent e) {
        if (!e.getBlock().getWorld().equals(world)) return;
        Run r = runs.get(e.getPlayer().getUniqueId());
        if (e.getPlayer().getGameMode() == GameMode.CREATIVE && r == null) return;
        e.setCancelled(true);
        if (r == null || r.finished) return;
        Block b = e.getBlock();
        // ломать можно только своё, и без выпадения предметов
        if (r.placed.remove(key(b))) {
            b.setType(Material.AIR);
            r.blocks.add(new int[]{(int) (System.currentTimeMillis() - r.recStart), b.getX(), b.getY() - FLOOR_Y,
                    b.getZ() - r.lane * SPACING, 0, 0, 0});
        }
    }

    @EventHandler
    public void onMove(PlayerMoveEvent e) {
        Player p = e.getPlayer();
        Run r = runs.get(p.getUniqueId());
        if (r == null || r.finished) return;
        Location to = e.getTo();
        if (!to.getWorld().equals(world)) return;
        if (to.getY() < FLOOR_Y - 12) {
            restart(r, "§cУпал. Ещё раз!");
            return;
        }
        int lz = r.lane * SPACING;
        if (r.timerStart >= 0 && to.getX() >= END_X && to.getX() <= END_X + ISLAND
                && Math.abs(to.getZ() - lz) <= 3 && to.getY() >= FLOOR_Y + 0.9 && to.getY() <= FLOOR_Y + 2) {
            finish(r);
        }
    }

    /* ----------------------------- финиш и рекорды ----------------------------- */

    private void finish(final Run r) {
        r.finished = true;
        final Player p = r.player;
        final long ms = System.currentTimeMillis() - r.timerStart;
        String base = "players." + p.getUniqueId();
        long best = pb.getLong(base + ".time", Long.MAX_VALUE);
        boolean record = ms < best;
        String id = saveReplay(r, ms);
        lastReplay.put(p.getUniqueId(), id);
        if (record) {
            pb.set(base + ".time", ms);
            pb.set(base + ".name", p.getName());
            pb.set(base + ".replay", id);
            try {
                pb.save(pbFile);
            } catch (IOException ex) {
                plugin.getLogger().warning("FastBridge: рекорды не сохранены: " + ex.getMessage());
            }
        }
        p.sendMessage("§6Fastbridge: §a" + format(ms) + (record ? " §e- новый рекорд!" : " §7(рекорд " + format(best) + ")"));
        p.sendMessage("§7Запись: §f/fb replay last §7- посмотреть, как ты строил.");
        Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
            public void run() {
                if (runs.get(p.getUniqueId()) == r && p.isOnline()) restart(r, null);
            }
        }, 50L);
    }

    static String format(long ms) {
        return String.format("%d.%02d с", ms / 1000, (ms % 1000) / 10);
    }

    private String saveReplay(Run r, long ms) {
        final String id = new SimpleDateFormat("yyyyMMdd-HHmmss").format(new Date()) + "_" + r.player.getName() + "_" + ms;
        JsonObject o = new JsonObject();
        o.addProperty("v", 1);
        o.addProperty("id", id);
        o.addProperty("player", r.player.getName());
        o.addProperty("uuid", r.player.getUniqueId().toString());
        o.addProperty("time_ms", ms);
        o.addProperty("timer_start_ms", r.timerStart - r.recStart);
        o.addProperty("created", System.currentTimeMillis());
        JsonArray frames = new JsonArray();
        for (double[] f : r.frames) {
            JsonArray a = new JsonArray();
            for (double v : f) a.add(new com.google.gson.JsonPrimitive(Math.round(v * 100) / 100.0));
            frames.add(a);
        }
        o.add("frames", frames);
        JsonArray blocks = new JsonArray();
        for (int[] b : r.blocks) {
            JsonArray a = new JsonArray();
            for (int v : b) a.add(new com.google.gson.JsonPrimitive(v));
            blocks.add(a);
        }
        o.add("blocks", blocks);
        final String json = o.toString();
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    FileWriter w = new FileWriter(new File(replayDir, id + ".json"));
                    try {
                        w.write(json);
                    } finally {
                        w.close();
                    }
                    prune();
                } catch (IOException ex) {
                    plugin.getLogger().warning("FastBridge: запись не сохранена: " + ex.getMessage());
                }
            }
        });
        return id;
    }

    /** Старые записи удаляем, рекордные оставляем. */
    private void prune() {
        File[] files = replayDir.listFiles();
        if (files == null || files.length <= KEEP_REPLAYS) return;
        Set<String> keep = new HashSet<String>();
        if (pb.getConfigurationSection("players") != null) {
            for (String u : pb.getConfigurationSection("players").getKeys(false)) {
                String rid = pb.getString("players." + u + ".replay");
                if (rid != null) keep.add(rid + ".json");
            }
        }
        Arrays.sort(files);
        int extra = files.length - KEEP_REPLAYS;
        for (File f : files) {
            if (extra <= 0) break;
            if (keep.contains(f.getName())) continue;
            if (f.delete()) extra--;
        }
    }

    /* ----------------------------- проигрывание ----------------------------- */

    final class Watch {
        final Player viewer;
        final int lane;
        final JsonArray frames;
        final JsonArray blocks;
        final String nick;
        final double speed;
        ArmorStand stand;
        long startedAt;
        int blockIdx;
        int taskId = -1;
        final List<int[]> shown = new ArrayList<int[]>();
        boolean finishedPlay;

        Watch(Player viewer, int lane, JsonObject replay, double speed) {
            this.viewer = viewer;
            this.lane = lane;
            this.frames = replay.getAsJsonArray("frames");
            this.blocks = replay.getAsJsonArray("blocks");
            this.nick = replay.get("player").getAsString();
            this.speed = speed;
        }

        void begin() {
            int lz = lane * SPACING;
            viewer.setGameMode(GameMode.ADVENTURE);
            viewer.getInventory().clear();
            viewer.getInventory().setItem(8, item(Material.REDSTONE, ITEM_EXIT));
            viewer.setAllowFlight(true);
            viewer.teleport(new Location(world, -3.5, FLOOR_Y + 4, lz + 0.5, -90f, 20f));
            viewer.setFlying(true);
            Location at = spawn(lane);
            stand = world.spawn(at, ArmorStand.class);
            stand.setGravity(false);
            stand.setBasePlate(false);
            stand.setArms(true);
            stand.setCustomName("§e" + nick);
            stand.setCustomNameVisible(true);
            ItemStack head = new ItemStack(Material.SKULL_ITEM, 1, (short) 3);
            SkullMeta sm = (SkullMeta) head.getItemMeta();
            sm.setOwner(nick);
            head.setItemMeta(sm);
            stand.setHelmet(head);
            stand.setItemInHand(new ItemStack(Material.SANDSTONE));
            viewer.sendMessage("§6Fastbridge: §7запись игрока §e" + nick + "§7, скорость x" + speed + ". §8Выход: /fb leave");
            startedAt = System.currentTimeMillis();
            taskId = Bukkit.getScheduler().scheduleSyncRepeatingTask(plugin, new Runnable() {
                public void run() {
                    tick();
                }
            }, 1L, 1L);
        }

        private void tick() {
            if (!viewer.isOnline() || stand == null || stand.isDead()) {
                cleanup();
                return;
            }
            double t = (System.currentTimeMillis() - startedAt) * speed;
            int lz = lane * SPACING;
            // блоки
            while (blockIdx < blocks.size()) {
                JsonArray b = blocks.get(blockIdx).getAsJsonArray();
                if (b.get(0).getAsDouble() > t) break;
                Block blk = world.getBlockAt(b.get(1).getAsInt(), FLOOR_Y + b.get(2).getAsInt(), lz + b.get(3).getAsInt());
                if (b.get(6).getAsInt() == 1) {
                    blk.setTypeIdAndData(b.get(4).getAsInt(), (byte) b.get(5).getAsInt(), false);
                    shown.add(new int[]{blk.getX(), blk.getY(), blk.getZ()});
                } else {
                    blk.setType(Material.AIR);
                }
                blockIdx++;
            }
            // позиция: между двумя ближайшими кадрами
            int n = frames.size();
            if (n == 0) {
                cleanup();
                return;
            }
            int i = 0;
            while (i + 1 < n && frames.get(i + 1).getAsJsonArray().get(0).getAsDouble() <= t) i++;
            JsonArray a = frames.get(i).getAsJsonArray();
            JsonArray b = frames.get(Math.min(i + 1, n - 1)).getAsJsonArray();
            double t0 = a.get(0).getAsDouble(), t1 = b.get(0).getAsDouble();
            double k = t1 > t0 ? Math.max(0, Math.min(1, (t - t0) / (t1 - t0))) : 0;
            double x = a.get(1).getAsDouble() + (b.get(1).getAsDouble() - a.get(1).getAsDouble()) * k;
            double y = a.get(2).getAsDouble() + (b.get(2).getAsDouble() - a.get(2).getAsDouble()) * k;
            double z = a.get(3).getAsDouble() + (b.get(3).getAsDouble() - a.get(3).getAsDouble()) * k;
            float yaw = (float) a.get(4).getAsDouble();
            Location l = new Location(world, x, FLOOR_Y + y, lz + z, yaw, 0f);
            // у стойки точка отсчёта ниже: ноги, а не глаза
            stand.teleport(l);
            if (t > frames.get(n - 1).getAsJsonArray().get(0).getAsDouble() + 1500 && !finishedPlay) {
                finishedPlay = true;
                viewer.sendMessage("§6Fastbridge: §7запись закончена.");
                Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
                    public void run() {
                        if (watches.get(viewer.getUniqueId()) == Watch.this) {
                            cleanup();
                            if (viewer.isOnline()) plugin.hub().toHub(viewer);
                        }
                    }
                }, 40L);
            }
        }

        void cleanup() {
            if (taskId >= 0) Bukkit.getScheduler().cancelTask(taskId);
            taskId = -1;
            if (stand != null) stand.remove();
            for (int[] c : shown) world.getBlockAt(c[0], c[1], c[2]).setType(Material.AIR);
            shown.clear();
            buildLane(lane);
            watches.remove(viewer.getUniqueId());
            if (watchLanes[lane] == this) watchLanes[lane] = null;
            if (viewer.isOnline()) {
                viewer.setAllowFlight(false);
                viewer.setFlying(false);
            }
        }
    }

    private void startReplay(final Player viewer, final String idOrNick, final double speed) {
        if (!enabled()) return;
        final File f = findReplay(viewer, idOrNick);
        if (f == null) {
            viewer.sendMessage("§cЗапись не найдена. Попробуй §f/fb replay last §cили §f/fb replay <ник>");
            return;
        }
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                final JsonObject o;
                try {
                    FileReader rd = new FileReader(f);
                    try {
                        o = new JsonParser().parse(rd).getAsJsonObject();
                    } finally {
                        rd.close();
                    }
                } catch (Exception ex) {
                    viewer.sendMessage("§cЗапись повреждена.");
                    return;
                }
                Bukkit.getScheduler().runTask(plugin, new Runnable() {
                    public void run() {
                        if (!viewer.isOnline()) return;
                        Run mine = runs.get(viewer.getUniqueId());
                        if (mine != null) leaveRunOnly(mine);
                        Watch old = watches.get(viewer.getUniqueId());
                        if (old != null) old.cleanup();
                        int lane = freeLane();
                        if (lane < 0) {
                            viewer.sendMessage("§cВсе дорожки заняты, зайди чуть позже.");
                            return;
                        }
                        Watch w = new Watch(viewer, lane, o, speed);
                        watches.put(viewer.getUniqueId(), w);
                        watchLanes[lane] = w;
                        w.begin();
                    }
                });
            }
        });
    }

    private void leaveRunOnly(Run r) {
        runs.remove(r.player.getUniqueId());
        cleanLane(r);
        lanes[r.lane] = null;
    }

    private File findReplay(Player viewer, String idOrNick) {
        if (idOrNick == null || idOrNick.equals("last")) {
            String id = lastReplay.get(viewer.getUniqueId());
            if (id == null) id = pb.getString("players." + viewer.getUniqueId() + ".replay");
            if (id == null) return null;
            File f = new File(replayDir, id + ".json");
            return f.exists() ? f : null;
        }
        String clean = idOrNick.replaceAll("[^A-Za-z0-9_\\-]", "");
        File exact = new File(replayDir, clean + ".json");
        if (exact.exists()) return exact;
        // ник: рекордная запись, иначе самая свежая
        if (pb.getConfigurationSection("players") != null) {
            for (String u : pb.getConfigurationSection("players").getKeys(false)) {
                String name = pb.getString("players." + u + ".name");
                if (name != null && name.equalsIgnoreCase(clean)) {
                    File f = new File(replayDir, pb.getString("players." + u + ".replay") + ".json");
                    if (f.exists()) return f;
                }
            }
        }
        File[] files = replayDir.listFiles();
        File best = null;
        if (files != null) {
            for (File f : files) {
                if (f.getName().contains("_" + clean + "_") && (best == null || f.getName().compareTo(best.getName()) > 0)) best = f;
            }
        }
        return best;
    }

    /* ----------------------------- команды ----------------------------- */

    @Override
    public boolean onCommand(CommandSender s, Command c, String l, String[] a) {
        if (!(s instanceof Player)) {
            s.sendMessage("Только для игрока.");
            return true;
        }
        Player p = (Player) s;
        String sub = a.length == 0 ? "play" : a[0].toLowerCase();
        if (sub.equals("play") || sub.equals("join")) {
            join(p);
        } else if (sub.equals("leave") || sub.equals("exit")) {
            leave(p);
        } else if (sub.equals("top")) {
            top(p);
        } else if (sub.equals("replay")) {
            double speed = 1.0;
            if (a.length > 2) {
                try {
                    speed = Math.max(0.1, Math.min(3, Double.parseDouble(a[2])));
                } catch (NumberFormatException ignored) {
                }
            }
            startReplay(p, a.length > 1 ? a[1] : "last", speed);
        } else {
            p.sendMessage("§6Fastbridge: §f/fb §7- играть, §f/fb top§7, §f/fb replay [last|ник|id] [скорость]§7, §f/fb leave");
        }
        return true;
    }

    private void top(Player p) {
        List<String[]> rows = new ArrayList<String[]>();
        if (pb.getConfigurationSection("players") != null) {
            for (String u : pb.getConfigurationSection("players").getKeys(false)) {
                rows.add(new String[]{pb.getString("players." + u + ".name", "?"), String.valueOf(pb.getLong("players." + u + ".time"))});
            }
        }
        java.util.Collections.sort(rows, new java.util.Comparator<String[]>() {
            public int compare(String[] x, String[] y) {
                return Long.compare(Long.parseLong(x[1]), Long.parseLong(y[1]));
            }
        });
        p.sendMessage("§6Fastbridge: лучшие времена");
        if (rows.isEmpty()) p.sendMessage("§7Пока никто не финишировал.");
        for (int i = 0; i < Math.min(10, rows.size()); i++) {
            p.sendMessage("§e" + (i + 1) + ". §f" + rows.get(i)[0] + " §7- §a" + format(Long.parseLong(rows.get(i)[1])));
        }
    }

    /* ----------------------------- защита мира ----------------------------- */

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
    public void onClick(InventoryClickEvent e) {
        if (e.getWhoClicked() instanceof Player && e.getWhoClicked().getWorld().equals(world)
                && ((Player) e.getWhoClicked()).getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
    }

    @EventHandler
    public void onInteract(PlayerInteractEvent e) {
        if (e.getAction() != Action.RIGHT_CLICK_AIR && e.getAction() != Action.RIGHT_CLICK_BLOCK) return;
        ItemStack it = e.getItem();
        if (it == null || !it.hasItemMeta() || !it.getItemMeta().hasDisplayName()) return;
        Player p = e.getPlayer();
        if (!p.getWorld().equals(world)) return;
        String n = it.getItemMeta().getDisplayName();
        if (n.equals(ITEM_EXIT)) {
            e.setCancelled(true);
            leave(p);
        } else if (n.equals(ITEM_RESTART)) {
            e.setCancelled(true);
            Run r = runs.get(p.getUniqueId());
            if (r != null) restart(r, null);
        }
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        UUID id = e.getPlayer().getUniqueId();
        Run r = runs.remove(id);
        if (r != null) {
            cleanLane(r);
            lanes[r.lane] = null;
        }
        Watch w = watches.get(id);
        if (w != null) w.cleanup();
    }
}
