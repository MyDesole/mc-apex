package ru.mcapex.social;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.ProfileService;
import dev.revere.alley.core.profile.enums.ProfileState;
import org.bukkit.*;
import org.bukkit.command.*;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.inventory.*;
import org.bukkit.event.player.*;
import org.bukkit.inventory.*;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.Plugin;
import org.bukkit.plugin.java.JavaPlugin;
import java.util.*;

public final class ApexSocial extends JavaPlugin implements Listener {
    static final String[] RANKS={"default","vip","mvp","mvp_plus","apex","legend"};
    static final String[] LABELS={"Игрок","VIP","MVP","MVP+","APEX","LEGEND"};
    static final int[] LAYOUTS={0,2,3,5,8,12};
    static final String MARK="§0apex:privileges";
    final Set<Object> privateDuels=Collections.newSetFromMap(new WeakHashMap<Object,Boolean>());
    public boolean isPrivateDuel(Object match){return privateDuels.contains(match);}
    Layouts layouts;
    PrivateMatches games;

    public void onEnable(){
        getDataFolder().mkdirs();
        layouts=new Layouts(this);games=new PrivateMatches(this);
        Bukkit.getPluginManager().registerEvents(this,this);
        Bukkit.getPluginManager().registerEvents(layouts,this);
        Bukkit.getPluginManager().registerEvents(games,this);
        games.installRewardShield();
        new ModePublisher(this).start();
        Bukkit.getScheduler().runTaskTimer(this,()->{
            for(Player p:Bukkit.getOnlinePlayers()){
                boolean hub=mainHub(p)&&idle(p)&&authenticated(p);
                for(int slot=0;slot<36;slot++)if(marked(p.getInventory().getItem(slot))&&(!hub||slot!=6))p.getInventory().setItem(slot,null);
                ItemStack held=p.getInventory().getItem(6);
                if(hub&&(held==null||held.getType()==Material.AIR))p.getInventory().setItem(6,item(Material.NETHER_STAR,"§6§lПривилегии",MARK,"§7Все ранги и возможности","§eНажми ПКМ"));
                layouts.maintenance(p);
            }
            games.maintenance();
        },20L,20L);
        getLogger().info("ApexSocial: привилегии, раскладки и приватные матчи включены");
    }
    public void onDisable(){if(games!=null)games.shutdown();}
    static int rank(Player p){for(int i=5;i>0;i--)if(p.hasPermission("apex.donor."+RANKS[i]))return i;return 0;}
    static Profile profile(Player p){return AlleyPlugin.getInstance().getService(ProfileService.class).getProfile(p.getUniqueId());}
    static boolean idle(Player p){
        Profile pr=profile(p);
        return lobbyWorld(p)&&pr!=null&&pr.getState()==ProfileState.LOBBY&&pr.getMatch()==null
            &&com.andrei1058.bedwars.arena.Arena.getArenaByPlayer(p)==null;
    }
    static boolean authenticated(Player p){
        try{
            Plugin auth=Bukkit.getPluginManager().getPlugin("ApexAuth");if(auth==null||!auth.isEnabled())return false;
            java.lang.reflect.Field f=auth.getClass().getDeclaredField("manager");f.setAccessible(true);Object m=f.get(auth);
            return (Boolean)m.getClass().getMethod("isAuthenticated",Player.class).invoke(m,p)||(Boolean)m.getClass().getMethod("bypasses",Player.class).invoke(m,p);
        }catch(Exception ex){return false;}
    }
    static boolean ready(Player p){return authenticated(p)&&idle(p);}
    static boolean mainHub(Player p){
        Plugin owner=Bukkit.getPluginManager().getPlugin("ApexTierTests");
        String location=owner instanceof JavaPlugin?((JavaPlugin)owner).getConfig().getString("hub.hub","world:0:64:0"):"world:0:64:0";
        return p.getWorld().getName().equals(location.split(":",2)[0]);
    }
    static boolean lobbyWorld(Player p){
        Plugin owner=Bukkit.getPluginManager().getPlugin("ApexTierTests");
        if(owner instanceof JavaPlugin)for(String key:new String[]{"hub.hub","hub.duels","hub.bedwars"}){
            String location=((JavaPlugin)owner).getConfig().getString(key);
            if(location!=null&&p.getWorld().getName().equals(location.split(":",2)[0]))return true;
        }
        return mainHub(p);
    }
    static ItemStack item(Material material,String name,String... lore){
        ItemStack i=new ItemStack(material);ItemMeta m=i.getItemMeta();m.setDisplayName(name);m.setLore(Arrays.asList(lore));i.setItemMeta(m);return i;
    }
    static boolean marked(ItemStack i){return i!=null&&i.hasItemMeta()&&i.getItemMeta().hasLore()&&i.getItemMeta().getLore().contains(MARK);}
    static void tell(Player p,String text){p.sendMessage("§8[§6APEX§8] §7"+text);}
    static class Menu implements InventoryHolder{
        final UUID owner;final String kind;final Map<Integer,String> values=new HashMap<>();Inventory inv;
        Menu(Player p,String kind){owner=p.getUniqueId();this.kind=kind;}
        public Inventory getInventory(){return inv;}
    }
    static Menu menu(Player p,String kind,int size,String title){Menu m=new Menu(p,kind);m.inv=Bukkit.createInventory(m,size,title);return m;}
    void privileges(Player p){
        Menu m=menu(p,"perks",45,"APEX • Привилегии");
        Material[] icons={Material.EMERALD,Material.DIAMOND,Material.DIAMOND_CHESTPLATE,Material.GOLD_BLOCK,Material.NETHER_STAR};
        org.bukkit.configuration.file.YamlConfiguration fb=org.bukkit.configuration.file.YamlConfiguration.loadConfiguration(new java.io.File(Bukkit.getPluginManager().getPlugin("ApexTierTests").getDataFolder(),"fastbridge-perks.yml"));
        for(int r=1;r<=5;r++){
            String key="limits."+RANKS[r]+".";
            List<String> lore=new ArrayList<>();
            lore.add("§7Раскладок дуэлей: §f"+LAYOUTS[r]);
            lore.add("§7Пресетов FB: §f"+fb.getInt(key+"presets"));
            lore.add("§7Избранных реплеев: §f"+fb.getInt(key+"favorites"));
            lore.add("§7Реплеев: §f"+fb.getInt(key+"replays")+" §7/ §f"+fb.getInt(key+"days")+" дн.");
            lore.add("§7История FB: §f"+fb.getInt(key+"history")+" попыток");
            lore.add("§7Личная витрина на сайте");lore.add("§7Оформление и эффекты финиша");
            if(r>=4){lore.add("§6Приватные BW и дуэли");lore.add("§6Приватные дорожки FastBridge");}
            lore.add("§8Без усиления урона и ресурсов");
            if(rank(p)==r)lore.add("§aТвоя действующая привилегия");
            m.inv.setItem(11+(r-1),item(icons[r-1],"§6§l"+LABELS[r],lore.toArray(new String[0])));
        }
        m.inv.setItem(29,item(Material.CHEST,"§eРаскладки дуэлей","§7Сохрани расположение предметов","§7Состав набора остаётся прежним"));
        m.inv.setItem(31,item(Material.IRON_DOOR,"§eПриватные игры","§7Хозяин от APEX, гости бесплатно","§7Без общей статистики и наград"));
        m.inv.setItem(33,item(Material.BOOK,"§eСайт и привилегии","§fmc-apex.ru/lavka","§7Подробности и личная витрина"));
        m.inv.setItem(40,item(Material.FIREWORK,"§eБоевые эффекты","§7Оформление победы и сообщения GG","§7Настроить через /style"));
        p.openInventory(m.inv);
    }
    public boolean onCommand(CommandSender sender,Command cmd,String label,String[] args){
        if(!(sender instanceof Player)){sender.sendMessage("Команда для игрока");return true;}
        Player p=(Player)sender;if(!authenticated(p)){tell(p,"Сначала пройди авторизацию.");return true;}
        if(cmd.getName().equals("private")){games.command(p,args);return true;}
        if(!idle(p)){tell(p,"Открой меню в лобби, вне пати и матча.");return true;}
        if(cmd.getName().equals("layouts"))layouts.openKits(p);else privileges(p);return true;
    }
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void interact(PlayerInteractEvent e){
        if(!marked(e.getItem()))return;e.setCancelled(true);
        if(e.getAction().name().startsWith("RIGHT_")&&ready(e.getPlayer())&&mainHub(e.getPlayer()))privileges(e.getPlayer());
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void drop(PlayerDropItemEvent e){if(marked(e.getItemDrop().getItemStack()))e.setCancelled(true);}
    @EventHandler(priority=EventPriority.HIGHEST) public void click(InventoryClickEvent e){
        if(marked(e.getCurrentItem())||marked(e.getCursor()))e.setCancelled(true);
        if(e.getHotbarButton()>=0&&marked(e.getWhoClicked().getInventory().getItem(e.getHotbarButton())))e.setCancelled(true);
        if(!(e.getView().getTopInventory().getHolder() instanceof Menu))return;
        e.setCancelled(true);Player p=(Player)e.getWhoClicked();Menu m=(Menu)e.getView().getTopInventory().getHolder();
        if(!m.owner.equals(p.getUniqueId())||!authenticated(p))return;
        if(!m.kind.equals("perks"))return;
        if(!idle(p)){p.closeInventory();return;}
        if(e.getRawSlot()==29)layouts.openKits(p);
        if(e.getRawSlot()==31)games.open(p);
        if(e.getRawSlot()==33){p.closeInventory();tell(p,"§ehttps://mc-apex.ru/lavka §7— привилегии; витрина настраивается в профиле.");}
        if(e.getRawSlot()==40){p.closeInventory();p.performCommand("style");}
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void drag(InventoryDragEvent e){
        if(e.getView().getTopInventory().getHolder() instanceof Menu||marked(e.getOldCursor()))e.setCancelled(true);
    }
}
