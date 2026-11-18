package ru.mcapex.social;

import org.bukkit.*;
import org.bukkit.entity.Player;
import org.bukkit.inventory.*;
import org.bukkit.event.inventory.*;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.permissions.PermissionAttachment;
import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.feature.kit.*;
import dev.revere.alley.feature.arena.*;
import dev.revere.alley.feature.arena.internal.types.SharedArena;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
import com.andrei1058.bedwars.api.arena.*;
import com.andrei1058.bedwars.api.events.player.PlayerBedBreakEvent;
import java.util.*;

/** Runs with real native plugins and protocol-47 clients in an isolated world/database. */
public final class SocialServerChecks {
    static ApexSocial social;static JavaPlugin runner;static Player owner,guest,other;static PermissionAttachment rank;
    static int checks,stage,ticks;static long deadline,nextCheck;static Kit kit;static IArena bw;static String beforeStats;static int xp;static double money;
    static void check(boolean value,String message){checks++;if(!value)throw new AssertionError(message);}
    public static void start(JavaPlugin plugin){
        runner=plugin;social=(ApexSocial)Bukkit.getPluginManager().getPlugin("ApexSocial");deadline=System.currentTimeMillis()+120000;
        Bukkit.getScheduler().runTaskTimer(plugin,()->{try{tick();}catch(Throwable ex){plugin.getLogger().log(java.util.logging.Level.SEVERE,"SOCIAL_TEST_FAIL",ex);stage=99;}},20L,20L);
    }
    static void tick()throws Exception{
        if(stage>=99)return;if(System.currentTimeMillis()>deadline)throw new AssertionError("stage timeout "+stage);
        if(stage==0){
            owner=Bukkit.getPlayerExact("SocialOwner");guest=Bukkit.getPlayerExact("SocialGuest");other=Bukkit.getPlayerExact("SocialOther");
            bw=com.andrei1058.bedwars.arena.Arena.getArenaByName("bw_amazon");
            if(owner==null||guest==null||other==null||bw==null||!social.games.bwSafe||!ApexSocial.ready(owner)||!ApexSocial.ready(guest)||!ApexSocial.ready(other))return;
            if(ticks==0){owner.getInventory().setItem(6,new ItemStack(Material.DIRT,3));ticks=1;nextCheck=System.currentTimeMillis()+1500;return;}
            if(System.currentTimeMillis()<nextCheck)return;
            if(ticks==1){check(owner.getInventory().getItem(6).getType()==Material.DIRT,"occupied hub slot is preserved");owner.getInventory().setItem(6,null);ticks=2;nextCheck=System.currentTimeMillis()+1500;return;}
            initial();stage=1;return;
        }
        if(stage==1){if(!ApexSocial.ready(owner)||!ApexSocial.ready(guest))return;bwStart();stage=2;ticks=0;return;}
        if(stage==2){
            if(bw.getStatus()!=GameState.playing){if(++ticks>20)throw new AssertionError("BW did not reach playing: "+bw.getStatus());return;}
            if(ticks>=0){ticks=-1;nextCheck=System.currentTimeMillis()+1200;return;}
            if(System.currentTimeMillis()<nextCheck)return;
            bwPlaying();stage=3;ticks=0;return;
        }
        if(stage==3){
            if(social.games.privateArena(bw)){if(++ticks>45)throw new AssertionError("BW restart cleanup timeout");return;}
            check(stats().equals(beforeStats),"private BW never changes global counters through cleanup");
            check(com.andrei1058.bedwars.BedWars.getEconomy().getMoney(owner)==money,"private BW never changes money");
            check(levels().getCurrentXp(owner)==xp,"private BW never changes XP");
            check(!social.games.privateArena(bw),"reservation released after restart cleanup");
            check(social.games.sessions.isEmpty(),"all private members removed after close");
            rank.setPermission("apex.donor.apex",false);owner.recalculatePermissions();
            social.games.command(owner,new String[]{"create","duel",kit.getName()});check(social.games.sessions.isEmpty(),"expired rank cannot create host session");
            runner.getLogger().info("SOCIAL_TEST_PASS "+checks+" live native integration checks");stage=99;
        }
    }
    static void click(Player p,int slot,ClickType click){
        InventoryClickEvent e=new InventoryClickEvent(p.getOpenInventory(),InventoryType.SlotType.CONTAINER,slot,click,InventoryAction.PICKUP_ALL);
        Bukkit.getPluginManager().callEvent(e);check(e.isCancelled(),"virtual menu blocks physical item transfer");
    }
    static void initial()throws Exception{
        check(ApexSocial.rank(guest)==0,"guest has no donor permission");rank=owner.addAttachment(runner,"apex.donor.apex",true);
        com.andrei1058.bedwars.BedWars.config.saveConfigLoc("lobbyLoc",new Location(owner.getWorld(),0.5,65,0.5));
        com.andrei1058.bedwars.BedWars.setLobbyWorld(owner.getWorld().getName());
        check(ApexSocial.rank(owner)==4,"host rank recognized");
        owner.performCommand("privileges");Inventory info=owner.getOpenInventory().getTopInventory();
        for(int slot=11;slot<=15;slot++)check(info.getItem(slot)!=null,"rank card present "+slot);
        check(ApexSocial.marked(owner.getInventory().getItem(6)),"main hub has privilege item: world="+owner.getWorld().getName()+", item="+owner.getInventory().getItem(6));
        kit=AlleyPlugin.getInstance().getService(KitService.class).getKits().stream().filter(k->k.isEnabled()&&k.isEditable()).findFirst().get();
        guest.performCommand("layouts");ApexSocial.Menu menu=(ApexSocial.Menu)guest.getOpenInventory().getTopInventory().getHolder();int kitSlot=menu.values.entrySet().stream().filter(e->e.getValue().equals(kit.getName())).findFirst().get().getKey();
        click(guest,kitSlot,ClickType.LEFT);click(guest,9,ClickType.LEFT);check(!(guest.getOpenInventory().getTopInventory().getHolder() instanceof Layouts.Editor),"free player cannot edit paid layout");guest.closeInventory();
        owner.performCommand("layouts");menu=(ApexSocial.Menu)owner.getOpenInventory().getTopInventory().getHolder();kitSlot=menu.values.entrySet().stream().filter(e->e.getValue().equals(kit.getName())).findFirst().get().getKey();
        click(owner,kitSlot,ClickType.LEFT);click(owner,9,ClickType.RIGHT);
        check(owner.getOpenInventory().getTopInventory().getHolder() instanceof Layouts.Editor,"paid editor opens");
        ItemStack[] inventory=owner.getInventory().getContents();click(owner,0,ClickType.LEFT);click(owner,15,ClickType.LEFT);
        check(Arrays.equals(inventory,owner.getInventory().getContents()),"virtual swap never changes carried inventory");
        Layouts.Editor editor=(Layouts.Editor)owner.getOpenInventory().getTopInventory().getHolder();check(Layouts.valid(editor.order),"editor preserves bijection");
        int[] saved=editor.order.clone();click(owner,49,ClickType.LEFT);
        ItemStack[] applied=ApexSocial.profile(owner).getProfileData().getLayoutData().getLayouts().get(kit.getName()).get(0).getItems();
        check(Arrays.equals(applied,Layouts.arrange(kit.getItems(),saved)),"native layout uses current kit items in saved order");
        check(Layouts.valid(Layouts.permutation(kit.getItems(),applied)),"items, amounts and metadata exactly conserved");
        Layouts loaded=new Layouts(social);check(loaded.data.getIntegerList("players."+owner.getUniqueId()+"."+kit.getName()+".slots.1").size()==36,"layout survives disk reload");
        rank.setPermission("apex.donor.apex",false);owner.recalculatePermissions();social.layouts.maintenance(owner);
        check(social.layouts.data.getInt("players."+owner.getUniqueId()+"."+kit.getName()+".active")==0,"expired rank restores basic layout");
        check(social.layouts.data.contains("players."+owner.getUniqueId()+"."+kit.getName()+".slots.1"),"expired rank retains saved paid profile");
        owner.closeInventory();rank.setPermission("apex.donor.apex",true);owner.recalculatePermissions();
        World w=owner.getWorld();for(int x=-25;x<=25;x++)for(int z=-25;z<=25;z++)w.getBlockAt(x,64,z).setType(Material.SANDSTONE);
        SharedArena a=new SharedArena("social-test",new Location(w,-25,60,-25),new Location(w,25,90,25));a.setPos1(new Location(w,-5.5,65,0.5));a.setPos2(new Location(w,5.5,65,0.5));a.setDisplayName("Social test");a.setEnabled(true);a.setKits(Arrays.asList(kit.getName()));a.setCenter(new Location(w,0,65,0));AlleyPlugin.getInstance().getService(ArenaService.class).registerNewArena(a);
        social.games.command(guest,new String[]{"create","duel",kit.getName()});check(social.games.sessions.isEmpty(),"guest cannot host private game");
        social.games.command(owner,new String[]{"create","duel",kit.getName()});check(social.games.sessions.size()==1,"host creates session");
        social.games.command(other,new String[]{"accept",owner.getName()});check(social.games.sessions.size()==1,"unsolicited accept rejected");
        social.games.command(owner,new String[]{"invite",guest.getName()});social.games.command(guest,new String[]{"accept",owner.getName()});check(social.games.sessions.size()==2,"free guest accepts invitation");
        social.games.command(owner,new String[]{"invite",other.getName()});social.games.command(other,new String[]{"accept",owner.getName()});check(social.games.sessions.size()==2,"duel capacity enforced");
        int wins=wins(owner),losses=losses(guest),coins=ApexSocial.profile(owner).getProfileData().getCoins();
        social.games.command(owner,new String[]{"start"});DefaultMatch match=(DefaultMatch)ApexSocial.profile(owner).getMatch();
        check(match!=null&&match==ApexSocial.profile(guest).getMatch(),"native duel starts for both invited players");
        check(!match.isAffectStatistics()&&!match.isRanked()&&!match.isTeamMatch(),"native flags suppress statistics and ELO");
        check(ApexSocial.profile(other).getMatch()==null,"outsider not pulled into duel");
        java.lang.reflect.Method process=DefaultMatch.class.getDeclaredMethod("processStatistics",dev.revere.alley.feature.match.model.GameParticipant.class,dev.revere.alley.feature.match.model.GameParticipant.class);process.setAccessible(true);process.invoke(match,match.getParticipantA(),match.getParticipantB());
        check(wins(owner)==wins&&losses(guest)==losses&&ApexSocial.profile(owner).getProfileData().getCoins()==coins,"native results do not award private stats/coins");
        match.setWinner(match.getParticipantA());match.setLoser(match.getParticipantB());social.games.command(owner,new String[]{"leave"});
    }
    static int wins(Player p){return ApexSocial.profile(p).getProfileData().getUnrankedKitData().values().stream().mapToInt(d->d.getWins()).sum();}
    static int losses(Player p){return ApexSocial.profile(p).getProfileData().getUnrankedKitData().values().stream().mapToInt(d->d.getLosses()).sum();}
    static String stats(){com.andrei1058.bedwars.stats.PlayerStats a=com.andrei1058.bedwars.BedWars.getStatsManager().get(owner.getUniqueId());return a.getWins()+":"+a.getLosses()+":"+a.getGamesPlayed()+":"+a.getBedsDestroyed()+":"+a.getKills()+":"+a.getFinalKills()+":"+a.getDeaths();}
    static com.andrei1058.bedwars.api.levels.Level levels(){return Bukkit.getServicesManager().load(com.andrei1058.bedwars.api.BedWars.class).getLevelsUtil();}
    static PlayerBedBreakEvent bedEvent(){return new PlayerBedBreakEvent(owner,bw.getTeams().get(0),bw.getTeams().get(1),bw,p->"",p->"",p->"");}
    static void nativeRewards(PlayerBedBreakEvent event)throws Exception{
        for(org.bukkit.plugin.RegisteredListener l:PlayerBedBreakEvent.getHandlerList().getRegisteredListeners())if(BwRewardShield.TARGETS.contains(l.getListener().getClass().getName()))l.callEvent(event);
    }
    static void bwStart()throws Exception{
        check(social.games.bwSafe,"BW reward shield installed");
        int beds=com.andrei1058.bedwars.BedWars.getStatsManager().get(owner.getUniqueId()).getBedsDestroyed();nativeRewards(bedEvent());
        check(com.andrei1058.bedwars.BedWars.getStatsManager().get(owner.getUniqueId()).getBedsDestroyed()==beds+1,"ordinary BW still updates native persistent stats");
        beforeStats=stats();xp=levels().getCurrentXp(owner);money=com.andrei1058.bedwars.BedWars.getEconomy().getMoney(owner);
        social.games.command(owner,new String[]{"create","bw","bw_amazon"});check(social.games.privateArena(bw),"empty native map reserved");
        check(!bw.addPlayer(other,true),"uninvited native arena join rejected");
        check(!other.teleport(bw.getWorld().getSpawnLocation()),"outsider teleport into private world rejected");
        nativeRewards(bedEvent());check(stats().equals(beforeStats),"private bed event suppresses persistent counters");
        check(levels().getCurrentXp(owner)==xp&&com.andrei1058.bedwars.BedWars.getEconomy().getMoney(owner)==money,"private bed event suppresses XP/money");
        social.games.command(owner,new String[]{"invite",guest.getName()});social.games.command(guest,new String[]{"accept",owner.getName()});social.games.command(owner,new String[]{"start"});
        check(bw.getPlayers().contains(owner)&&bw.getPlayers().contains(guest),"native BW admits host and free guest");
    }
    static void bwPlaying()throws Exception{
        check(!bw.addSpectator(other,false,bw.getSpectatorLocation()),"uninvited spectator rejected");
        for(int slot=0;slot<36;slot++)check(!ApexSocial.marked(owner.getInventory().getItem(slot)),"hub item absent inside BW slot "+slot);
        for(String name:new String[]{"perMinuteTask","moneyperMinuteTask"}){
            java.lang.reflect.Field field=bw.getClass().getDeclaredField(name);field.setAccessible(true);Object timer=field.get(bw);
            if(timer!=null){java.lang.reflect.Field task=timer.getClass().getDeclaredField("task");task.setAccessible(true);org.bukkit.scheduler.BukkitTask t=(org.bukkit.scheduler.BukkitTask)task.get(timer);check(t==null||!Bukkit.getScheduler().isQueued(t.getTaskId()),"periodic reward timer cancelled: "+name);}
        }
        social.games.command(guest,new String[]{"leave"});
    }
}
