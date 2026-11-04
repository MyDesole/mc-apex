package ru.mcapex.tiertests;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.feature.hotbar.HotbarService;
import dev.revere.alley.feature.spawn.SpawnService;
import org.bukkit.Bukkit;
import org.bukkit.GameMode;
import org.bukkit.Location;
import org.bukkit.Material;
import org.bukkit.World;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.block.BlockBreakEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import org.bukkit.event.entity.EntityDamageEvent;
import org.bukkit.event.entity.FoodLevelChangeEvent;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.PlayerDropItemEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;

import java.util.Arrays;

/**
 * Нейтральный хаб: все заходят сюда и выбирают лобби дуэлей или BedWars.
 * Точки в config.yml: hub.hub / hub.duels / hub.bedwars ("мир:x:y:z:yaw:pitch"), hub.radius.
 */
final class Hub implements Listener, CommandExecutor {
    private static final String MENU = "§8Выбор лобби";
    private static final String COMPASS = "§aВыбор лобби §7(ПКМ)";

    private final ApexTierTests plugin;
    private Location hub, duels, bedwars;
    private double radius;

    Hub(ApexTierTests plugin) {
        this.plugin = plugin;
    }

    void start() {
        hub = loc("hub.hub");
        duels = loc("hub.duels");
        bedwars = loc("hub.bedwars");
        radius = plugin.getConfig().getDouble("hub.radius", 200);
        if (hub == null) {
            plugin.getLogger().info("Хаб не настроен (hub.hub в config.yml) - отключён");
            return;
        }
        Bukkit.getPluginManager().registerEvents(this, plugin);
        plugin.getCommand("hub").setExecutor(this);
        plugin.getLogger().info("Хаб включён");
    }

    private Location loc(String path) {
        String s = plugin.getConfig().getString(path);
        if (s == null) return null;
        String[] p = s.split(":");
        World w = Bukkit.getWorld(p[0]);
        if (w == null || p.length < 4) return null;
        return new Location(w, Double.parseDouble(p[1]), Double.parseDouble(p[2]), Double.parseDouble(p[3]),
                p.length > 4 ? Float.parseFloat(p[4]) : 0f, p.length > 5 ? Float.parseFloat(p[5]) : 0f);
    }

    /** Игрок в хабе или в лобби BedWars (там нет строительства и урона). */
    private boolean inZone(Location l) {
        return near(l, hub) || near(l, bedwars);
    }

    private boolean near(Location l, Location c) {
        if (c == null || !l.getWorld().equals(c.getWorld())) return false;
        double dx = l.getX() - c.getX(), dz = l.getZ() - c.getZ();
        return dx * dx + dz * dz <= radius * radius;
    }

    private ItemStack item(Material m, String name, String... lore) {
        ItemStack it = new ItemStack(m);
        ItemMeta im = it.getItemMeta();
        im.setDisplayName(name);
        im.setLore(Arrays.asList(lore));
        it.setItemMeta(im);
        return it;
    }

    private void reset(Player p) {
        p.getInventory().clear();
        p.getInventory().setArmorContents(null);
        p.setHealth(p.getMaxHealth());
        p.setFoodLevel(20);
        p.setFireTicks(0);
        p.setFallDistance(0);
        p.setGameMode(GameMode.ADVENTURE);
    }

    void toHub(Player p) {
        reset(p);
        p.teleport(hub);
        p.getInventory().setItem(4, item(Material.COMPASS, COMPASS, "§7Дуэли или BedWars"));
        p.getInventory().setHeldItemSlot(4);
    }

    private void toDuels(Player p) {
        reset(p);
        AlleyPlugin ap = AlleyPlugin.getInstance();
        if (duels != null) ap.getService(SpawnService.class).updateSpawnLocation(duels);
        ap.getService(SpawnService.class).teleportToSpawn(p);
        ap.getService(HotbarService.class).applyHotbarItems(p);
    }

    private void toBedwars(Player p) {
        reset(p);
        p.teleport(bedwars != null ? bedwars : hub);
        p.getInventory().setItem(2, item(Material.IRON_SWORD, "§e2 команды", "§7ПКМ - арены на 2 команды"));
        p.getInventory().setItem(3, item(Material.GOLD_SWORD, "§e4 команды", "§7ПКМ - арены на 4 команды"));
        p.getInventory().setItem(4, item(Material.CHEST, "§eВсе арены", "§7ПКМ - список всех арен"));
        p.getInventory().setItem(5, item(Material.DIAMOND_SWORD, "§e8 команд", "§7ПКМ - арены на 8 команд"));
        p.getInventory().setItem(8, item(Material.COMPASS, COMPASS, "§7Вернуться в хаб"));
        p.getInventory().setHeldItemSlot(4);
    }

    private void openMenu(Player p) {
        Inventory inv = Bukkit.createInventory(null, 27, MENU);
        inv.setItem(11, item(Material.IRON_SWORD, "§6Лобби дуэлей", "§7Дуэли, рейтинг, киты"));
        inv.setItem(15, item(Material.BED, "§6Лобби BedWars", "§7Арены на 2, 4 и 8 команд"));
        p.openInventory(inv);
    }

    @Override
    public boolean onCommand(CommandSender s, Command c, String l, String[] a) {
        if (s instanceof Player) toHub((Player) s);
        return true;
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onJoin(PlayerJoinEvent e) {
        final Player p = e.getPlayer();
        // после Alley, который сам переносит игрока в свой спавн
        Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
            public void run() {
                if (p.isOnline()) toHub(p);
            }
        }, 25L);
    }

    @EventHandler
    public void onInteract(PlayerInteractEvent e) {
        ItemStack it = e.getItem();
        if (it == null || !it.hasItemMeta() || !it.getItemMeta().hasDisplayName()) return;
        Player p = e.getPlayer();
        if (!inZone(p.getLocation())) return;
        String n = it.getItemMeta().getDisplayName();
        if (n.equals(COMPASS)) {
            e.setCancelled(true);
            if (near(p.getLocation(), bedwars)) toHub(p); else openMenu(p);
        } else if (near(p.getLocation(), bedwars)) {
            String cmd = n.equals("§e2 команды") ? "bw gui T2" : n.equals("§e4 команды") ? "bw gui T4"
                    : n.equals("§e8 команд") ? "bw gui T8" : n.equals("§eВсе арены") ? "bw gui" : null;
            if (cmd != null) {
                e.setCancelled(true);
                p.performCommand(cmd);
            }
        }
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        if (!(e.getWhoClicked() instanceof Player)) return;
        Player p = (Player) e.getWhoClicked();
        if (e.getInventory() != null && MENU.equals(e.getInventory().getTitle())) {
            e.setCancelled(true);
            if (e.getRawSlot() == 11) { p.closeInventory(); toDuels(p); }
            else if (e.getRawSlot() == 15) { p.closeInventory(); toBedwars(p); }
        } else if (inZone(p.getLocation()) && p.getGameMode() != GameMode.CREATIVE) {
            e.setCancelled(true);
        }
    }

    @EventHandler
    public void onBreak(BlockBreakEvent e) {
        if (inZone(e.getBlock().getLocation()) && e.getPlayer().getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
    }

    @EventHandler
    public void onPlace(BlockPlaceEvent e) {
        if (inZone(e.getBlock().getLocation()) && e.getPlayer().getGameMode() != GameMode.CREATIVE) e.setCancelled(true);
    }

    @EventHandler
    public void onDrop(PlayerDropItemEvent e) {
        if (inZone(e.getPlayer().getLocation())) e.setCancelled(true);
    }

    @EventHandler
    public void onDamage(EntityDamageEvent e) {
        if (e.getEntity() instanceof Player && inZone(e.getEntity().getLocation())) e.setCancelled(true);
    }

    @EventHandler
    public void onFood(FoodLevelChangeEvent e) {
        if (inZone(e.getEntity().getLocation())) e.setCancelled(true);
    }

    @EventHandler
    public void onMove(PlayerMoveEvent e) {
        // упал в бездну - вернуть на точку своего лобби
        if (e.getTo().getY() < 10 && inZone(e.getTo())) {
            e.getPlayer().teleport(near(e.getTo(), bedwars) && bedwars != null ? bedwars : hub);
        }
    }
}
