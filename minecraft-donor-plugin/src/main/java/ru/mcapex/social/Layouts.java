package ru.mcapex.social;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.enums.ProfileState;
import dev.revere.alley.feature.kit.*;
import dev.revere.alley.feature.layout.data.LayoutData;
import org.bukkit.Material;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.inventory.*;
import org.bukkit.inventory.ItemStack;
import java.io.*;
import java.nio.file.*;
import java.util.*;

/** Stores only permutations: items, amounts, armor and metadata come from the current native kit. */
final class Layouts implements Listener {
    final ApexSocial plugin;final File file;final YamlConfiguration data;
    final Map<UUID,Map<String,int[]>> editingNative=new HashMap<>();
    static final class Editor extends ApexSocial.Menu {
        final Kit kit;final int slot;final int[] order;int selected=-1;
        Editor(Player p,Kit kit,int slot,int[] order){super(p,"layout-editor");this.kit=kit;this.slot=slot;this.order=order;}
    }
    Layouts(ApexSocial plugin){this.plugin=plugin;file=new File(plugin.getDataFolder(),"layouts.yml");data=YamlConfiguration.loadConfiguration(file);}
    static int[] identity(){int[] p=new int[36];for(int i=0;i<36;i++)p[i]=i;return p;}
    static boolean valid(int[] order){if(order==null||order.length!=36)return false;boolean[] seen=new boolean[36];for(int i:order){if(i<0||i>=36||seen[i])return false;seen[i]=true;}return true;}
    static ItemStack[] arrange(ItemStack[] base,int[] order){
        if(!valid(order))throw new IllegalArgumentException("Invalid inventory permutation");
        ItemStack[] out=new ItemStack[36];for(int i=0;i<36;i++){int at=order[i];if(at<base.length&&base[at]!=null)out[i]=base[at].clone();}return out;
    }
    static int[] permutation(ItemStack[] base,ItemStack[] layout){
        int[] out=new int[36];boolean[] used=new boolean[36];
        for(int i=0;i<36;i++){
            ItemStack want=i<layout.length?layout[i]:null;if(want!=null&&want.getType()==Material.AIR)want=null;
            int found=-1;
            for(int j=0;j<36;j++){
                ItemStack candidate=j<base.length?base[j]:null;if(candidate!=null&&candidate.getType()==Material.AIR)candidate=null;
                if(!used[j]&&Objects.equals(want,candidate)){found=j;break;}
            }
            if(found<0)return null;used[found]=true;out[i]=found;
        }
        return out;
    }
    private String key(Player p,Kit k){return "players."+p.getUniqueId()+"."+k.getName().replace('.','_');}
    private Kit kit(String name){return AlleyPlugin.getInstance().getService(KitService.class).getKit(name);}
    private int[] read(String path){List<Integer> list=data.getIntegerList(path);if(list.size()!=36)return identity();int[] out=new int[36];for(int i=0;i<36;i++)out[i]=list.get(i);return valid(out)?out:identity();}
    private void write(String path,int[] order){List<Integer> list=new ArrayList<>();for(int n:order)list.add(n);data.set(path,list);}
    private boolean persist(){
        File tmp=new File(file.getPath()+".tmp");
        try{data.save(tmp);try{Files.move(tmp.toPath(),file.toPath(),StandardCopyOption.REPLACE_EXISTING,StandardCopyOption.ATOMIC_MOVE);}catch(AtomicMoveNotSupportedException ex){Files.move(tmp.toPath(),file.toPath(),StandardCopyOption.REPLACE_EXISTING);}return true;}
        catch(IOException ex){plugin.getLogger().severe("Не удалось сохранить раскладки: "+ex.getClass().getSimpleName());return false;}
    }
    private List<LayoutData> nativeLayouts(Player p,Kit k){
        return ApexSocial.profile(p).getProfileData().getLayoutData().getLayouts().computeIfAbsent(k.getName(),n->new ArrayList<>());
    }
    private void captureBase(Player p,Kit k){
        List<LayoutData> list=nativeLayouts(p,k);int[] order=list.isEmpty()?identity():permutation(k.getItems(),list.get(0).getItems());
        write(key(p,k)+".base",order==null?identity():order);
    }
    private void apply(Player p,Kit k,int[] order){
        List<LayoutData> list=nativeLayouts(p,k);ItemStack[] items=arrange(k.getItems(),order);
        if(list.isEmpty())list.add(new LayoutData("default",k.getDisplayName(),items));else list.get(0).setItems(items);
        ApexSocial.profile(p).save();
    }
    void openKits(Player p){
        ApexSocial.Menu m=ApexSocial.menu(p,"layout-kits",54,"APEX • Раскладки дуэлей");int at=0;
        for(Kit k:AlleyPlugin.getInstance().getService(KitService.class).getKits())if(k.isEnabled()&&k.isEditable()&&at<45){
            m.inv.setItem(at,ApexSocial.item(k.getIcon(),"§e"+k.getName(),"§7Дополнительных профилей: §f"+ApexSocial.LAYOUTS[ApexSocial.rank(p)],"§7Базовый редактор остаётся бесплатным"));m.values.put(at++,k.getName());
        }
        m.inv.setItem(49,ApexSocial.item(Material.BOOK,"§6Только расположение предметов","§7Первый ряд — слоты хотбара","§7Урон, броня и количество не меняются"));p.openInventory(m.inv);
    }
    private void profiles(Player p,Kit k){
        ApexSocial.Menu m=ApexSocial.menu(p,"layout-profiles",36,"Раскладки • "+k.getName());m.values.put(-1,k.getName());
        int limit=ApexSocial.LAYOUTS[ApexSocial.rank(p)];String root=key(p,k);
        for(int i=1;i<=12;i++){
            boolean unlocked=i<=limit,exists=data.contains(root+".slots."+i);
            m.inv.setItem(i+8,ApexSocial.item(unlocked?Material.CHEST:Material.BARRIER,"§eПрофиль "+i,
                unlocked?(exists?"§aСохранён":"§7Ещё не сохранён"):"§8Нужен старший ранг",unlocked?"§7ЛКМ — выбрать, ПКМ — изменить":"§7/privileges",data.getInt(root+".active")==i?"§aСейчас выбран":"§8Только перестановка предметов"));m.values.put(i+8,String.valueOf(i));
        }
        m.inv.setItem(31,ApexSocial.item(Material.PAPER,"§fОсновная раскладка","§7Вернуться к своей бесплатной раскладке"));p.openInventory(m.inv);
    }
    private void edit(Player p,Kit k,int slot){
        Editor m=new Editor(p,k,slot,read(key(p,k)+".slots."+slot));m.inv=org.bukkit.Bukkit.createInventory(m,54,"Профиль "+slot+" • "+k.getName());draw(m);p.openInventory(m.inv);
    }
    private void draw(Editor m){
        ItemStack[] items=arrange(m.kit.getItems(),m.order);
        for(int i=0;i<36;i++)m.inv.setItem(i,items[i]);
        m.inv.setItem(45,ApexSocial.item(Material.ARROW,"§eНазад","§7Изменения не сохранятся"));
        m.inv.setItem(49,ApexSocial.item(Material.EMERALD,"§aСохранить и выбрать","§7Нажми два слота, чтобы поменять их местами","§7Предметы нельзя забрать из этого меню",m.selected<0?"§7Первый ряд — хотбар":"§eВыбран слот "+(m.selected+1)));
        m.inv.setItem(53,ApexSocial.item(Material.REDSTONE,"§cСбросить черновик","§7Вернуть исходное расположение набора"));
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void click(InventoryClickEvent e){
        if(!(e.getView().getTopInventory().getHolder() instanceof ApexSocial.Menu))return;
        ApexSocial.Menu m=(ApexSocial.Menu)e.getView().getTopInventory().getHolder();if(!m.kind.startsWith("layout-"))return;
        e.setCancelled(true);Player p=(Player)e.getWhoClicked();
        if(!m.owner.equals(p.getUniqueId())||!ApexSocial.ready(p)){p.closeInventory();return;}
        int at=e.getRawSlot();if(at<0||at>=m.inv.getSize()||!(e.getClick()==ClickType.LEFT||e.getClick()==ClickType.RIGHT))return;
        if(m.kind.equals("layout-kits")){String name=m.values.get(at);if(name!=null)profiles(p,kit(name));return;}
        if(m instanceof Editor){
            Editor ed=(Editor)m;if(ed.slot>ApexSocial.LAYOUTS[ApexSocial.rank(p)]){p.closeInventory();ApexSocial.tell(p,"Эта раскладка недоступна для текущего ранга.");return;}
            if(at<36){if(ed.selected<0)ed.selected=at;else{int value=ed.order[ed.selected];ed.order[ed.selected]=ed.order[at];ed.order[at]=value;ed.selected=-1;}draw(ed);}
            else if(at==45)profiles(p,ed.kit);
            else if(at==53){System.arraycopy(identity(),0,ed.order,0,36);ed.selected=-1;draw(ed);}
            else if(at==49){String root=key(p,ed.kit);if(data.getInt(root+".active")==0)captureBase(p,ed.kit);write(root+".slots."+ed.slot,ed.order);data.set(root+".active",ed.slot);if(persist()){apply(p,ed.kit,ed.order);ApexSocial.tell(p,"Раскладка сохранена и выбрана.");profiles(p,ed.kit);}else ApexSocial.tell(p,"Не удалось сохранить. Попробуй позже.");}
            return;
        }
        Kit k=kit(m.values.get(-1));if(k==null)return;String root=key(p,k);
        if(at==31){if(data.getInt(root+".active")>0){data.set(root+".active",0);if(persist())apply(p,k,read(root+".base"));}profiles(p,k);return;}
        String value=m.values.get(at);if(value==null)return;int slot=Integer.parseInt(value);
        if(slot>ApexSocial.LAYOUTS[ApexSocial.rank(p)]){ApexSocial.tell(p,"О возможностях рангов: /privileges");return;}
        if(e.isRightClick()||!data.contains(root+".slots."+slot))edit(p,k,slot);
        else{if(data.getInt(root+".active")==0)captureBase(p,k);data.set(root+".active",slot);if(persist()){apply(p,k,read(root+".slots."+slot));ApexSocial.tell(p,"Выбран профиль "+slot+".");profiles(p,k);}}
    }
    void maintenance(Player p){
        Profile pr=ApexSocial.profile(p);if(pr==null)return;
        if(pr.getState()==ProfileState.EDITING){
            if(!editingNative.containsKey(p.getUniqueId())){
                Map<String,int[]> before=new HashMap<>();
                for(Kit k:AlleyPlugin.getInstance().getService(KitService.class).getKits()){
                    List<LayoutData> list=nativeLayouts(p,k);before.put(k.getName(),list.isEmpty()?identity():permutation(k.getItems(),list.get(0).getItems()));
                }
                editingNative.put(p.getUniqueId(),before);
            }
            return;
        }
        if(!ApexSocial.ready(p))return;
        Map<String,int[]> before=editingNative.remove(p.getUniqueId());boolean changed=false;
        for(Kit k:AlleyPlugin.getInstance().getService(KitService.class).getKits()){
            String root=key(p,k);int active=data.getInt(root+".active");if(active<=0)continue;
            List<LayoutData> list=nativeLayouts(p,k);
            int[] current=list.isEmpty()?identity():permutation(k.getItems(),list.get(0).getItems());
            if(before!=null&&!Arrays.equals(before.get(k.getName()),current)){captureBase(p,k);data.set(root+".active",0);changed=true;}
            else if(active>ApexSocial.LAYOUTS[ApexSocial.rank(p)]){apply(p,k,read(root+".base"));data.set(root+".active",0);changed=true;ApexSocial.tell(p,"Срок привилегии изменился: восстановлена основная раскладка "+k.getName()+". Сохранённые профили остались.");}
        }
        if(changed)persist();
    }
}
