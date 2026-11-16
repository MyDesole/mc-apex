package ru.mcapex.tiertests;

import org.bukkit.*;
import org.bukkit.command.*;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.inventory.*;
import org.bukkit.inventory.*;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitTask;
import java.io.*;
import java.nio.file.*;
import java.util.*;

/** Cosmetics only: client particles/sounds and private victory cards, no gameplay mutations. */
final class BattleStyle implements Listener, CommandExecutor {
    static final String TITLE = "§8Стиль боя";
    static final String DONOR_TITLE = "§0Донат-меню";
    static final String ENTRY = "§6Стиль боя: дуэли и BedWars";
    static final String[] RANK_NAMES={"Игрок","VIP","MVP","MVP+","APEX","LEGEND"};
    static final String[] FX = {"none", "hearts", "notes", "sparks", "aurora", "crown"};
    static final String[] FX_NAMES = {"Без эффекта", "Сердца", "Музыкальный круг", "Звёздный след", "Северное сияние", "Корона победителя"};
    static final String[] MESSAGES = {"none", "gg", "clean", "champion", "apex", "legend"};
    static final String[] MESSAGE_NAMES = {"Без подписи", "GG, хорошая игра!", "Чистая победа", "Чемпион арены", "На вершине APEX", "Легендарный финал"};
    static final String[] SOUNDS = {"none", "pling", "note", "levelup", "firework", "dragon"};
    static final String[] SOUND_NAMES = {"Без звука", "Звон победы", "Музыкальная нота", "Новый уровень", "Салют", "Голос дракона"};
    final JavaPlugin plugin;
    final File file;
    final YamlConfiguration data;
    private final Map<UUID, BukkitTask> animations = new HashMap<UUID, BukkitTask>();
    private final Map<UUID, Long> previews = new HashMap<UUID, Long>();
    private final Map<UUID, Long> kills = new HashMap<UUID, Long>();
    private final Set<String> wins = new LinkedHashSet<String>();
    private BukkitTask saveTask;
    private BattleStyleAlley alley;
    private BattleStyleBw bw;
    private boolean dirty;

    BattleStyle(JavaPlugin plugin) {
        this.plugin = plugin;
        file = new File(plugin.getDataFolder(), "battle-styles.yml");
        data = YamlConfiguration.loadConfiguration(file);
    }

    void start() {
        if (plugin.getCommand("style") == null) { plugin.getLogger().warning("BattleStyle: command missing from plugin.yml"); return; }
        plugin.getCommand("style").setExecutor(this);
        Bukkit.getPluginManager().registerEvents(this, plugin);
        if (Bukkit.getPluginManager().isPluginEnabled("Alley")) { alley = new BattleStyleAlley(this); alley.start(); }
        if (Bukkit.getPluginManager().isPluginEnabled("BedWars1058")) { bw = new BattleStyleBw(this); bw.start(); }
        saveTask = Bukkit.getScheduler().runTaskTimer(plugin, () -> flush(), 600, 600);
        plugin.getLogger().info("BattleStyle: /style, victory cosmetics and client-only effects enabled; Alley=" + (alley != null) + ", BedWars=" + (bw != null));
    }

    void stop() {
        if (saveTask != null) saveTask.cancel();
        if (alley != null) alley.stop();
        if (bw != null) HandlerList.unregisterAll(bw);
        for (BukkitTask task : animations.values()) task.cancel();
        animations.clear(); previews.clear(); kills.clear(); wins.clear();
        HandlerList.unregisterAll(this); flush();
    }

    static int rank(Player p) {
        for (int i = 5; i >= 1; i--) if (p.hasPermission("apex.donor." + FbPerks.RANKS[i])) return i;
        return 0;
    }
    String key(UUID u, String category) { return "players." + u + "." + category; }
    static String[] options(String category) { return category.equals("message") ? MESSAGES : category.equals("sound") ? SOUNDS : FX; }
    static String[] names(String category) { return category.equals("message") ? MESSAGE_NAMES : category.equals("sound") ? SOUND_NAMES : FX_NAMES; }
    boolean select(UUID u, int rank, String category, String id) {
        if (!Arrays.asList("victory", "kill", "message", "sound").contains(category)) return false;
        int i = Arrays.asList(options(category)).indexOf(id);
        if (i < 0 || i > rank) return false;
        if(id.equals(data.getString(key(u,category))))return true;
        data.set(key(u, category), id); dirty = true; flush(); return true;
    }
    String selected(UUID u, int rank, String category) {
        String id = data.getString(key(u, category), "none");
        int i = Arrays.asList(options(category)).indexOf(id);
        return i >= 0 && i <= rank ? id : "none";
    }
    boolean visible(UUID u) { return data.getBoolean(key(u, "visible"), true); }
    void toggle(UUID u) { data.set(key(u, "visible"), !visible(u)); dirty = true; flush(); }
    void reset(UUID u) { for (String category : new String[]{"victory", "kill", "message", "sound"}) data.set(key(u, category), null); dirty = true; flush(); }
    void flush() {
        if (!dirty) return;
        try {
            File temp = new File(file.getParentFile(), file.getName() + ".tmp"); data.save(temp);
            try { Files.move(temp.toPath(), file.toPath(), StandardCopyOption.REPLACE_EXISTING, StandardCopyOption.ATOMIC_MOVE); }
            catch (AtomicMoveNotSupportedException e) { Files.move(temp.toPath(), file.toPath(), StandardCopyOption.REPLACE_EXISTING); }
            dirty = false;
        } catch (IOException e) { plugin.getLogger().warning("BattleStyle: settings not saved: " + e.getMessage()); }
    }

    void kill(Player killer, Player victim) {
        if (killer == null || victim == null || killer.equals(victim) || !killer.isOnline() || !visible(killer.getUniqueId())) return;
        String effect = selected(killer.getUniqueId(), rank(killer), "kill");
        if (effect.equals("none")) return;
        long now = System.currentTimeMillis(); Long previous = kills.put(victim.getUniqueId(), now);
        if (previous != null && now - previous < 1000) return;
        if (kills.size() > 512) kills.clear();
        draw(effect, killer.getLocation(), Collections.singleton(killer.getUniqueId()), 0, true);
    }

    boolean once(String id) {
        if (!wins.add(id)) return false;
        if (wins.size() > 256) wins.remove(wins.iterator().next());
        return true;
    }
    void victory(String eventId, Collection<UUID> winners, Collection<UUID> audience, String mode) {
        if (!once(eventId)) return;
        for (UUID id : new LinkedHashSet<UUID>(winners)) {
            Player winner = Bukkit.getPlayer(id);
            if (winner == null || !winner.isOnline()) continue;
            int r = rank(winner);
            if (visible(id)) {
                String message = selected(id, r, "message");
                if (!message.equals("none")) winner.sendMessage("§8§m------------------------\n§6★ §f" + winner.getName() + " §7· §b" + mode + "\n§e" + MESSAGE_NAMES[Arrays.asList(MESSAGES).indexOf(message)] + "\n§8§m------------------------");
                playJingle(winner, selected(id, r, "sound"));
            }
            animate(winner, selected(id, r, "victory"), audience);
        }
    }
    private void playJingle(Player p, String id) {
        if (id.equals("none")) return;
        Sound sound = id.equals("pling") ? Sound.NOTE_PLING : id.equals("note") ? Sound.NOTE_PIANO : id.equals("levelup") ? Sound.LEVEL_UP : id.equals("firework") ? Sound.FIREWORK_TWINKLE : Sound.ENDERDRAGON_GROWL;
        p.playSound(p.getLocation(), sound, 0.35f, id.equals("dragon") ? 1.5f : 1.1f);
    }
    private void animate(final Player winner, final String effect, Collection<UUID> audience) {
        if (effect.equals("none")) return;
        final UUID id = winner.getUniqueId(); final Location at = winner.getLocation().clone();
        final Set<UUID> viewers = new LinkedHashSet<UUID>(audience); viewers.add(id);
        // Never include players outside the finished match, even in a shared arena world.
        BukkitTask previous = animations.remove(id); if (previous != null) previous.cancel();
        final int[] step = {0};
        BukkitTask task = Bukkit.getScheduler().runTaskTimer(plugin, () -> {
            if (!winner.isOnline() || !winner.getWorld().equals(at.getWorld()) || winner.getLocation().distanceSquared(at) > 1024 || step[0] >= 8) {
                BukkitTask done = animations.remove(id); if (done != null) done.cancel(); return;
            }
            String current = selected(id, rank(winner), "victory");
            if (!current.equals(effect)) { BukkitTask done = animations.remove(id); if (done != null) done.cancel(); return; }
            Set<UUID> permitted = new LinkedHashSet<UUID>();
            for (UUID u : viewers) { Player p = Bukkit.getPlayer(u); if (p != null && (p.equals(winner) || p.canSee(winner))) permitted.add(u); }
            draw(effect, at, permitted, step[0]++, false);
        }, 1, 4);
        animations.put(id, task);
    }
    private void draw(String effect, Location origin, Collection<UUID> audience, int step, boolean small) {
        Effect particle = effect.equals("hearts") ? Effect.HEART : effect.equals("notes") ? Effect.NOTE : effect.equals("sparks") ? Effect.FIREWORKS_SPARK : effect.equals("aurora") ? Effect.WITCH_MAGIC : Effect.FIREWORKS_SPARK;
        int points = small ? 8 : 16;
        for (UUID id : audience) {
            Player viewer = Bukkit.getPlayer(id);
            if (viewer == null || !viewer.isOnline() || !visible(id) || !viewer.getWorld().equals(origin.getWorld()) || viewer.getLocation().distanceSquared(origin) > 1024) continue;
            for (int i = 0; i < points; i++) {
                double angle = 2 * Math.PI * i / points + step * 0.28;
                double height = effect.equals("crown") ? 2.0 + (i % 2 == 0 ? 0.35 : 0) : 0.3 + step * 0.15;
                double radius = small ? 0.45 : effect.equals("crown") ? 0.65 : 1.0;
                Location at = origin.clone().add(Math.cos(angle) * radius, height, Math.sin(angle) * radius);
                viewer.spigot().playEffect(at, particle, 0, 0, 0.02f, 0.02f, 0.02f, 0.01f, 1, 32);
            }
        }
    }

    private ItemStack item(Material material, String title, String... lore) {
        ItemStack it = new ItemStack(material); ItemMeta meta = it.getItemMeta();meta.setDisplayName(title);meta.setLore(Arrays.asList(lore));it.setItemMeta(meta);return it;
    }
    private static final class Menu implements InventoryHolder {
        final UUID owner; Inventory inventory; final Map<Integer,String> actions=new HashMap<Integer,String>();
        Menu(UUID owner) { this.owner=owner; } public Inventory getInventory(){ return inventory; }
    }
    void open(Player p) {
        Menu menu=new Menu(p.getUniqueId()); Inventory inv=Bukkit.createInventory(menu,54,TITLE);menu.inventory=inv;
        inv.setItem(4,item(Material.DIAMOND,"§6Твой стиль: " + RANK_NAMES[rank(p)],"§7Только оформление, без боевых бонусов","§7Нажми вариант для выбора"));
        String[] categories={"victory","kill","message","sound"};String[] labels={"Победа","Личный эффект убийства","Личная карточка победы","Личный звук победы"};
        for(int row=0;row<4;row++)for(int i=0;i<6;i++) {
            String category=categories[row],id=options(category)[i];int slot=10+9*row+i;
            boolean allowed=rank(p)>=i,chosen=selected(p.getUniqueId(),rank(p),category).equals(id);
            inv.setItem(slot,item(allowed?chosen?Material.EMERALD:Material.FIREWORK:Material.COAL,(allowed?"§a":"§c")+names(category)[i],"§7"+labels[row],chosen?"§aВыбрано":allowed?"§eНажми для выбора":"§7Нужен ранг "+RANK_NAMES[i]));
            menu.actions.put(slot,category+":"+id);
        }
        inv.setItem(47,item(Material.EYE_OF_ENDER,"§bПредпросмотр","§7Только тебе, вне активного матча","§7Не чаще раза в 15 секунд"));menu.actions.put(47,"preview");
        inv.setItem(49,item(Material.REDSTONE_COMPARATOR,"§eПоказывать эффекты: "+(visible(p.getUniqueId())?"да":"нет"),"§7Бесплатный переключатель для всех","§7Отключает новые частицы, карточки и звуки для тебя"));menu.actions.put(49,"toggle");
        inv.setItem(51,item(Material.BARRIER,"§cСбросить выбранное"));menu.actions.put(51,"reset");
        p.openInventory(inv);
    }
    private boolean inCombat(Player p) { return alley!=null&&alley.inCombat(p)||bw!=null&&bw.inCombat(p); }
    void preview(Player p) {
        if(inCombat(p)){p.sendMessage("§7Предпросмотр недоступен во время матча.");return;}
        long now=System.currentTimeMillis();Long last=previews.get(p.getUniqueId());
        if(last!=null&&now-last<15000){p.sendMessage("§7Предпросмотр можно повторить через 15 секунд.");return;}
        previews.put(p.getUniqueId(),now);
        victory("preview:"+p.getUniqueId()+":"+now,Collections.singleton(p.getUniqueId()),Collections.singleton(p.getUniqueId()),"Предпросмотр");
    }
    @EventHandler public void onOpen(InventoryOpenEvent e) {
        if(DONOR_TITLE.equals(e.getInventory().getTitle())&&e.getInventory().getSize()>=27&&e.getInventory().getItem(22)==null)
            e.getInventory().setItem(22,item(Material.FIREWORK,ENTRY,"§7Нажми: эффекты победы и стиль боя","§7Дуэли и BedWars · /style"));
    }
    @EventHandler(priority=EventPriority.HIGH) public void onClick(InventoryClickEvent e) {
        if(!(e.getWhoClicked() instanceof Player))return;final Player p=(Player)e.getWhoClicked();
        Inventory top=e.getView().getTopInventory();
        if(DONOR_TITLE.equals(top.getTitle())&&e.getRawSlot()==22){
            ItemStack it=top.getItem(22);if(it!=null&&it.hasItemMeta()&&ENTRY.equals(it.getItemMeta().getDisplayName())){e.setCancelled(true);Bukkit.getScheduler().runTask(plugin,()->open(p));}return;
        }
        if(!(top.getHolder() instanceof Menu))return;e.setCancelled(true);
        Menu menu=(Menu)top.getHolder();if(!menu.owner.equals(p.getUniqueId()))return;
        final String action=menu.actions.get(e.getRawSlot());if(action==null)return;
        Bukkit.getScheduler().runTask(plugin,()->{if(!p.isOnline())return;execute(p,action);open(p);});
    }
    @EventHandler public void onDrag(InventoryDragEvent e){if(e.getView().getTopInventory().getHolder() instanceof Menu)e.setCancelled(true);}
    @EventHandler public void onQuit(org.bukkit.event.player.PlayerQuitEvent e){UUID u=e.getPlayer().getUniqueId();BukkitTask t=animations.remove(u);if(t!=null)t.cancel();previews.remove(u);kills.remove(u);}
    private void execute(Player p,String action){
        if(action.equals("toggle"))toggle(p.getUniqueId());
        else if(action.equals("reset"))reset(p.getUniqueId());
        else if(action.equals("preview"))preview(p);
        else {String[] bits=action.split(":");if(bits.length==2&&!select(p.getUniqueId(),rank(p),bits[0],bits[1]))p.sendMessage("§cЭтот вариант недоступен для твоего ранга.");}
    }
    public boolean onCommand(CommandSender sender,Command cmd,String label,String[] args){
        if(!(sender instanceof Player)){sender.sendMessage("Оформление выбирает игрок: /style");return true;}
        Player p=(Player)sender;
        if(args.length==0)open(p);
        else if(args.length==1&&Arrays.asList("toggle","reset","preview").contains(args[0]))execute(p,args[0]);
        else if(args.length==2&&Arrays.asList("victory","kill","message","sound").contains(args[0]))execute(p,args[0]+":"+args[1]);
        else p.sendMessage("§7/style — меню; /style preview|toggle|reset");
        return true;
    }
}
