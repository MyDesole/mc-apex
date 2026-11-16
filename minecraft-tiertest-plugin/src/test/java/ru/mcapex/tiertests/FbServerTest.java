package ru.mcapex.tiertests;

import org.bukkit.*;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.player.*;
import org.bukkit.plugin.java.JavaPlugin;
import java.lang.reflect.*;
import java.io.File;
import java.util.*;

/** Integration harness packaged separately; never installed on production. */
public final class FbServerTest extends JavaPlugin implements Listener {
    FastBridge fb; int checks; boolean started;
    void check(boolean yes,String text) { checks++; if(!yes)throw new AssertionError(text); }
    Object field(Object o,String name)throws Exception { Field f=o.getClass().getDeclaredField(name);f.setAccessible(true);return f.get(o); }
    Object call(Object o,String name,Class<?>[] types,Object...args)throws Exception { Method m=o.getClass().getDeclaredMethod(name,types);m.setAccessible(true);return m.invoke(o,args); }
    public void onEnable() {
        fb=new FastBridge(this,p->p.teleport(Bukkit.getWorlds().get(0).getSpawnLocation()));
        fb.start(); Bukkit.getPluginManager().registerEvents(this,this);
        getLogger().info("FB_TEST_READY");
    }
    public void onDisable() { if(fb!=null)fb.stop(); }
    @EventHandler public void joined(PlayerJoinEvent e) {
        if(started||Bukkit.getOnlinePlayers().size()<2)return;started=true;
        Bukkit.getScheduler().runTaskLater(this,()->{
            try { first(); } catch(Throwable error){fail(error);}
        },20);
    }
    void fail(Throwable error) { getLogger().log(java.util.logging.Level.SEVERE,"FB_TEST_FAIL",error); }
    @SuppressWarnings("unchecked") void first()throws Exception {
        Player owner=Bukkit.getPlayerExact("FBTestOwner"), outsider=Bukkit.getPlayerExact("FBTestOther");
        check(owner!=null&&outsider!=null,"both test clients connected");
        owner.addAttachment(this,"apex.donor.apex",true);
        call(fb,"join",new Class<?>[]{Player.class,boolean.class},owner,true);
        fb.join(outsider);
        check(owner.getWorld().getName().equals("fastbridge")&&owner.getLocation().getZ()>470,"private spawn allocated");
        check(!outsider.teleport(owner.getLocation()),"outsider teleport blocked");
        check(!owner.teleport(new Location(owner.getWorld(),2.5,71,510.5)),"neighbor private lane blocked");
        check(!owner.canSee(outsider)&&!outsider.canSee(owner),"private visibility isolated in both directions");
        outsider.performCommand("fb private");
        Map<UUID,FastBridge.Sess> sessions=(Map<UUID,FastBridge.Sess>)field(fb,"sessions");
        check(!sessions.get(outsider.getUniqueId()).personal,"default rank cannot open private session");
        owner.performCommand("fb block wool"); owner.performCommand("fb effect flame"); owner.performCommand("fb settings");
        check(owner.getOpenInventory().getTitle().toLowerCase(java.util.Locale.ROOT).contains("настройки"),"settings menu opens");
        check(owner.getOpenInventory().getTopInventory().getItem(10).getType()==Material.WOOL,"selected material displayed");owner.closeInventory();
        owner.performCommand("fb preset save test");owner.performCommand("fb block clay");owner.performCommand("fb preset load test");
        check(owner.getInventory().getItem(0).getType()==Material.WOOL,"preset restores block kit");
        FastBridge.Sess session=sessions.get(owner.getUniqueId());
        org.bukkit.block.Block block=owner.getWorld().getBlockAt(5,70,session.lane*30);block.setType(Material.WOOL);
        call(fb,"record",new Class<?>[]{FastBridge.Sess.class,org.bukkit.block.Block.class},session,block);
        PlayerMoveEvent finish=new PlayerMoveEvent(owner,owner.getLocation(),new Location(owner.getWorld(),37.5,71,session.lane*30+0.5));
        fb.onMove(finish);
        check(session.run.finished,"private attempt completes");
        FbStats stats=(FbStats)field(fb,"stats");check(stats.best(owner.getUniqueId(),FbMap.B32)<0,"private finish excluded from leaderboard");
        FbPerks profiles=(FbPerks)field(fb,"perks");check(profiles.history(owner.getUniqueId()).size()==1,"private finish history recorded once");
        String id=((Map<UUID,String>)field(fb,"lastReplay")).get(owner.getUniqueId());
        Bukkit.getScheduler().runTaskLater(this,()->{
            try { second(owner,outsider,id); }catch(Throwable error){fail(error);}
        },20);
    }
    @SuppressWarnings("unchecked") void second(Player owner,Player outsider,String id)throws Exception {
        File file=new File((File)field(fb,"replayDir"),id+".json");
        check(file.isFile(),"replay writer completed");Replay rep=Replay.read(file);
        check(rep.personal&&rep.material.equals("WOOL"),"private/material metadata persisted");
        call(fb,"startWatch",new Class<?>[]{Player.class,Replay.class,double.class},outsider,rep,1.0);
        Map<UUID,?> watches=(Map<UUID,?>)field(fb,"watches");check(!watches.containsKey(outsider.getUniqueId()),"private replay denied to outsider");
        call(fb,"startWatch",new Class<?>[]{Player.class,Replay.class,double.class},owner,rep,1.0);
        check(watches.containsKey(owner.getUniqueId()),"owner can view private replay and ghost spawns");
        owner.performCommand("fb favorite "+id);
        Bukkit.getScheduler().runTaskLater(this,()->{
            try {
                FbPerks profiles=(FbPerks)field(fb,"perks");check(profiles.favorite(owner.getUniqueId(),id),"favorite command persists ownership-checked replay");
                Object watch=((Map<?,?>)field(fb,"watches")).get(owner.getUniqueId());
                call(fb,"backToTraining",new Class<?>[]{FastBridge.Watch.class},watch);
                check(((FastBridge.Sess)((Map<?,?>)field(fb,"sessions")).get(owner.getUniqueId())).personal,"return from private replay stays private");
                owner.performCommand("fb history");check(owner.getOpenInventory().getTitle().toLowerCase(java.util.Locale.ROOT).contains("история"),"history menu opens");owner.closeInventory();
                owner.performCommand("fb public");check(owner.canSee(outsider)&&outsider.canSee(owner),"visibility restored after private exit");
                BattleStyleServerChecks.run(this,(BattleStyle)field(fb,"battleStyle"),owner,outsider);
                ApexUiServerChecks.run(this,(ApexUi)field(fb,"apexUi"),owner);
                getLogger().info("FB_TEST_PASS "+checks+" live Bukkit integration checks");
            } catch(Throwable error){fail(error);}
        },10);
    }
}
