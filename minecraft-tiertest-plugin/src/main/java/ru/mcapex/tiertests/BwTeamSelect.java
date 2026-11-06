package ru.mcapex.tiertests;

import com.andrei1058.bedwars.api.arena.GameState;
import com.andrei1058.bedwars.api.arena.IArena;
import com.andrei1058.bedwars.api.arena.team.ITeam;
import com.andrei1058.bedwars.api.events.gameplay.TeamAssignEvent;
import com.andrei1058.bedwars.api.events.player.PlayerJoinArenaEvent;
import com.andrei1058.bedwars.api.events.player.PlayerLeaveArenaEvent;
import com.andrei1058.bedwars.arena.Arena;
import org.bukkit.Bukkit;
import org.bukkit.Material;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.block.Action;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.PlayerInteractEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.Plugin;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;

/**
 * Выбор команды в ожидании арены BedWars.
 *
 * В комнате ожидания игрок получает предмет «Выбор команды» (слот 4). ПКМ открывает меню команд
 * арены; выбранную команду мы запоминаем и в момент, когда BedWars сам распределяет игроков
 * на старте (TeamAssignEvent), сажаем игрока именно туда, если там ещё есть место. Кто ничего
 * не выбрал, распределяется как обычно.
 */
final class BwTeamSelect implements Listener {
    private static final String ITEM = "§aВыбор команды §7(ПКМ)";
    private static final String TITLE = "§8Выбор команды";
    private static final int ITEM_SLOT = 4;

    private final Plugin plugin;
    /** Игрок -> имя выбранной команды. */
    private final Map<UUID, String> choice = new HashMap<UUID, String>();
    /** Слоты меню -> имя команды, пока меню открыто. */
    private final Map<UUID, Map<Integer, String>> slots = new HashMap<UUID, Map<Integer, String>>();

    BwTeamSelect(Plugin plugin) {
        this.plugin = plugin;
    }

    void start() {
        Bukkit.getPluginManager().registerEvents(this, plugin);
        plugin.getLogger().info("BwTeamSelect: выбор команды в ожидании арены BedWars включён");
    }

    private ItemStack named(Material m, int data, String name, String... lore) {
        ItemStack it = new ItemStack(m, 1, (short) data);
        ItemMeta im = it.getItemMeta();
        im.setDisplayName(name);
        if (lore.length > 0) im.setLore(Arrays.asList(lore));
        it.setItemMeta(im);
        return it;
    }

    /** Предмет выдаём чуть позже входа: BedWars сначала сам очищает инвентарь и выдаёт свои предметы. */
    @EventHandler(priority = EventPriority.MONITOR)
    public void onJoinArena(final PlayerJoinArenaEvent e) {
        if (e.isSpectator()) return;
        final Player p = e.getPlayer();
        choice.remove(p.getUniqueId());
        Bukkit.getScheduler().runTaskLater(plugin, new Runnable() {
            public void run() {
                IArena a = Arena.getArenaByPlayer(p);
                if (!p.isOnline() || a == null || !a.isPlayer(p)) return;
                if (a.getStatus() != GameState.waiting && a.getStatus() != GameState.starting) return;
                p.getInventory().setItem(ITEM_SLOT, named(Material.NETHER_STAR, 0, ITEM, "§7Выбери команду до старта"));
                p.updateInventory();
            }
        }, 4L);
    }

    @EventHandler(priority = EventPriority.MONITOR)
    public void onLeaveArena(PlayerLeaveArenaEvent e) {
        choice.remove(e.getPlayer().getUniqueId());
        slots.remove(e.getPlayer().getUniqueId());
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        choice.remove(e.getPlayer().getUniqueId());
        slots.remove(e.getPlayer().getUniqueId());
    }

    @EventHandler
    public void onInteract(PlayerInteractEvent e) {
        if (e.getAction() != Action.RIGHT_CLICK_AIR && e.getAction() != Action.RIGHT_CLICK_BLOCK) return;
        ItemStack it = e.getItem();
        if (it == null || !it.hasItemMeta() || !ITEM.equals(it.getItemMeta().getDisplayName())) return;
        e.setCancelled(true);
        Player p = e.getPlayer();
        IArena a = Arena.getArenaByPlayer(p);
        if (a == null || !a.isPlayer(p) || (a.getStatus() != GameState.waiting && a.getStatus() != GameState.starting)) {
            p.sendMessage("§cВыбирать команду можно только пока арена ждёт игроков.");
            return;
        }
        open(p, a);
    }

    private int chosenCount(IArena a, String team) {
        int n = 0;
        for (Player q : a.getPlayers()) {
            if (team.equals(choice.get(q.getUniqueId()))) n++;
        }
        return n;
    }

    private void open(Player p, IArena a) {
        List<ITeam> teams = a.getTeams();
        int rows = Math.max(1, (teams.size() + 1 + 8) / 9);
        Inventory inv = Bukkit.createInventory(null, rows * 9, TITLE);
        Map<Integer, String> map = new HashMap<Integer, String>();
        String mine = choice.get(p.getUniqueId());
        int slot = 0;
        for (ITeam t : teams) {
            int chosen = chosenCount(a, t.getName());
            boolean full = chosen >= a.getMaxInTeam();
            boolean selected = t.getName().equals(mine);
            List<String> lore = new ArrayList<String>();
            lore.add("§7Мест выбрано: §f" + chosen + "§7/§f" + a.getMaxInTeam());
            lore.add(selected ? "§aТы выбрал эту команду" : full ? "§cМеста закончились" : "§eНажми, чтобы выбрать");
            ItemStack wool = new ItemStack(Material.WOOL, 1, (short) t.getColor().itemByte());
            ItemMeta im = wool.getItemMeta();
            im.setDisplayName(t.getColor().chat() + "§l" + t.getName() + (selected ? " §a✔" : ""));
            im.setLore(lore);
            wool.setItemMeta(im);
            inv.setItem(slot, wool);
            map.put(slot, t.getName());
            slot++;
        }
        inv.setItem(rows * 9 - 1, named(Material.BARRIER, 0, "§7Случайная команда", mine == null ? "§aСейчас так" : "§eНажми, чтобы сбросить выбор"));
        map.put(rows * 9 - 1, "");
        slots.put(p.getUniqueId(), map);
        p.openInventory(inv);
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        if (!(e.getWhoClicked() instanceof Player) || e.getInventory() == null || !TITLE.equals(e.getInventory().getTitle())) return;
        e.setCancelled(true);
        Player p = (Player) e.getWhoClicked();
        Map<Integer, String> map = slots.get(p.getUniqueId());
        if (map == null || !map.containsKey(e.getRawSlot())) return;
        IArena a = Arena.getArenaByPlayer(p);
        if (a == null || !a.isPlayer(p) || (a.getStatus() != GameState.waiting && a.getStatus() != GameState.starting)) {
            p.closeInventory();
            return;
        }
        String team = map.get(e.getRawSlot());
        if (team.isEmpty()) {
            choice.remove(p.getUniqueId());
            p.sendMessage("§6Команда: §7выбор сброшен, будешь в случайной.");
            open(p, a);
            return;
        }
        if (!team.equals(choice.get(p.getUniqueId())) && chosenCount(a, team) >= a.getMaxInTeam()) {
            p.sendMessage("§cВ этой команде уже нет мест.");
            return;
        }
        choice.put(p.getUniqueId(), team);
        p.sendMessage("§6Команда: §7ты выбрал §f" + team + "§7. Тебя посадят туда на старте.");
        open(p, a);
    }

    /** Момент, когда BedWars сам расселяет игроков: сажаем выбравших туда, куда они хотели. */
    @EventHandler(priority = EventPriority.NORMAL)
    public void onAssign(TeamAssignEvent e) {
        String name = choice.get(e.getPlayer().getUniqueId());
        if (name == null || e.isCancelled()) return;
        IArena a = e.getArena();
        ITeam wanted = a.getTeam(name);
        if (wanted == null || wanted.isMember(e.getPlayer())) return;
        if (wanted.getMembers().size() >= a.getMaxInTeam()) return;
        e.setCancelled(true);
        wanted.addPlayers(e.getPlayer());
    }
}
