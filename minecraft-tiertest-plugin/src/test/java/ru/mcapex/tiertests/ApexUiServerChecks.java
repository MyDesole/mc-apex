package ru.mcapex.tiertests;

import java.util.*;
import org.bukkit.*;
import org.bukkit.block.Chest;
import org.bukkit.entity.Player;
import org.bukkit.event.inventory.*;
import org.bukkit.inventory.*;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.java.JavaPlugin;

final class ApexUiServerChecks {
    static int checks;
    static void check(boolean value,String reason){checks++;if(!value)throw new AssertionError(reason);}
    static void run(JavaPlugin plugin,ApexUi ui,Player p)throws Exception{
        Inventory menu=Bukkit.createInventory(null,27,"Проверка интерфейса");
        ItemStack button=new ItemStack(Material.EMERALD);ItemMeta meta=button.getItemMeta();meta.setDisplayName("§aГотово");button.setItemMeta(meta);menu.setItem(11,button);
        ItemStack[] playerItems=p.getInventory().getContents().clone();
        p.openInventory(menu);
        check(menu.getTitle().equals("Проверка интерфейса"),"server title preserved for plugin handlers");
        check(menu.getItem(0).getType()==Material.STAINED_GLASS_PANE&&menu.getItem(0).getDurability()==10,"violet corners");
        check(menu.getItem(1).getDurability()==15,"graphite frame");
        check(menu.getItem(11).equals(button),"existing buttons untouched including state colors");
        check(menu.getItem(13)==null,"inner action area left empty");
        InventoryClickEvent click=new InventoryClickEvent(p.getOpenInventory(),InventoryType.SlotType.CONTAINER,0,ClickType.NUMBER_KEY,InventoryAction.HOTBAR_SWAP,0);
        ui.click(click);check(click.isCancelled(),"decorative cells cannot be taken with number keys");
        InventoryClickEvent action=new InventoryClickEvent(p.getOpenInventory(),InventoryType.SlotType.CONTAINER,11,ClickType.LEFT,InventoryAction.PICKUP_ALL);
        ui.click(action);check(!action.isCancelled(),"real button remains available to original handler");
        check(Arrays.equals(playerItems,p.getInventory().getContents()),"frames do not enter player inventory");p.closeInventory();
        Inventory editor=Bukkit.createInventory(null,27,"APEX / Раскладка кита");ui.decorate(editor);check(editor.getItem(0)==null,"kit editor is not framed");
        Inventory shop=Bukkit.createInventory(null,54,"APEX / Магазин");ui.decorate(shop);check(shop.getItem(0)==null,"shop item grid unchanged");
        org.bukkit.block.Block block=p.getWorld().getBlockAt(200,50,200);block.setType(Material.CHEST);Chest chest=(Chest)block.getState();
        ui.decorate(chest.getInventory());check(chest.getInventory().getItem(0)==null,"physical chest untouched");block.setType(Material.AIR);
        check(ApexUi.caption("Очень длинное название игрового меню для теста").length()<=32,"legacy title length limit");
        check(ChatColor.stripColor(ApexUi.caption("§8APEX §5/ §8Реплеи")).equals("APEX / Реплеи"),"brand not duplicated");
        p.openInventory(Bukkit.createInventory(null,27,"Ещё одно меню"));p.closeInventory();
        plugin.getLogger().info("APEX_UI_TEST_PASS "+checks+" GUI safety and presentation checks");
    }
}
