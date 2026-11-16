package ru.mcapex.tiertests;

import org.bukkit.*;
import org.bukkit.block.BlockState;
import org.bukkit.entity.Entity;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.inventory.*;
import org.bukkit.inventory.*;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.java.JavaPlugin;
import java.util.*;

/** Empty frame cells only. Existing buttons, identifiers, slots and inventory titles stay authoritative. */
final class ApexUi implements Listener {
    private final JavaPlugin plugin;
    private final Map<UUID,String> titles=new java.util.concurrent.ConcurrentHashMap<UUID,String>();
    private ApexUiPackets packets;
    private final Map<Inventory,Map<Integer,ItemStack>> frames=new WeakHashMap<Inventory,Map<Integer,ItemStack>>();
    ApexUi(JavaPlugin plugin){this.plugin=plugin;}
    void start(){Bukkit.getPluginManager().registerEvents(this,plugin);if(Bukkit.getPluginManager().isPluginEnabled("ProtocolLib")){packets=new ApexUiPackets(plugin,titles);packets.start();}plugin.getLogger().info("ApexUi: APEX Violet menu frames enabled");}
    void stop(){
        HandlerList.unregisterAll(this);
        if(packets!=null)packets.stop();titles.clear();
        for(Map.Entry<Inventory,Map<Integer,ItemStack>> entry:frames.entrySet())for(Map.Entry<Integer,ItemStack> frame:entry.getValue().entrySet())
            if(matches(entry.getKey().getItem(frame.getKey()),frame.getValue()))entry.getKey().setItem(frame.getKey(),null);
        frames.clear();
    }
    static boolean suitable(Inventory inv){
        if(inv==null||inv.getType()!=InventoryType.CHEST||inv.getSize()<27)return false;
        InventoryHolder owner=inv.getHolder();
        if(owner instanceof BlockState||owner instanceof Entity)return false;
        String title=ChatColor.stripColor(inv.getTitle()).toLowerCase(Locale.ROOT);

        for(String editable:new String[]{"редактор","расклад","инвентарь","layout","edit","магазин","shop","покуп","улучшен","buy","upgrade","хранилищ","сундук","storage","backpack"})if(title.contains(editable))return false;
        return true;
    }
    static String caption(String title){
        String plain=ChatColor.stripColor(title).replaceFirst("(?i)^APEX\\s*[/·:]?\\s*","");
        String prefix="§8APEX §5/ §8";
        return prefix+plain.substring(0,Math.min(plain.length(),32-prefix.length()));
    }
    @EventHandler(priority=EventPriority.MONITOR,ignoreCancelled=true) public void caption(InventoryOpenEvent e){
        Inventory inv=e.getInventory();InventoryHolder holder=inv.getHolder();
        if(!(holder instanceof BlockState)&&!(holder instanceof Entity)&&inv.getType()!=InventoryType.PLAYER&&inv.getType()!=InventoryType.CRAFTING)
            titles.put(e.getPlayer().getUniqueId(),caption(inv.getTitle()));
        else titles.remove(e.getPlayer().getUniqueId());
    }
    @EventHandler public void closed(InventoryCloseEvent e){titles.remove(e.getPlayer().getUniqueId());}
    @EventHandler public void quit(org.bukkit.event.player.PlayerQuitEvent e){titles.remove(e.getPlayer().getUniqueId());}
    private static boolean matches(ItemStack current,ItemStack expected){return current!=null&&current.isSimilar(expected);}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=true) public void opened(InventoryOpenEvent e){decorate(e.getInventory());}
    void decorate(Inventory inv){
        if(!suitable(inv))return;
        Map<Integer,ItemStack> added=frames.get(inv);if(added==null){added=new HashMap<Integer,ItemStack>();frames.put(inv,added);}
        int size=inv.getSize();
        for(int slot=0;slot<size;slot++){
            boolean rim=slot<9||slot>=size-9||slot%9==0||slot%9==8;
            if(!rim)continue;
            ItemStack current=inv.getItem(slot);if(current!=null&&current.getType()!=Material.AIR)continue;
            boolean corner=slot==0||slot==8||slot==size-9||slot==size-1;
            ItemStack pane=new ItemStack(Material.STAINED_GLASS_PANE,1,(short)(corner?10:15));
            ItemMeta meta=pane.getItemMeta();meta.setDisplayName("§8 ");pane.setItemMeta(meta);
            inv.setItem(slot,pane);added.put(slot,pane);
        }
    }
    @EventHandler(priority=EventPriority.LOWEST) public void click(InventoryClickEvent e){
        Inventory inv=e.getView().getTopInventory();Map<Integer,ItemStack> cells=frames.get(inv);if(cells==null)return;
        ItemStack expected=cells.get(e.getRawSlot());if(expected!=null&&matches(inv.getItem(e.getRawSlot()),expected))e.setCancelled(true);
    }
    @EventHandler(priority=EventPriority.LOWEST) public void drag(InventoryDragEvent e){
        Inventory inv=e.getView().getTopInventory();Map<Integer,ItemStack> cells=frames.get(inv);if(cells==null)return;
        for(Integer slot:e.getRawSlots())if(cells.containsKey(slot)&&matches(inv.getItem(slot),cells.get(slot))){e.setCancelled(true);return;}
    }
}
