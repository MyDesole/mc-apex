package ru.mcapex.tiertests;

import com.google.gson.JsonObject;
import org.bukkit.*;
import org.bukkit.entity.Player;
import org.bukkit.event.player.PlayerMoveEvent;
import org.bukkit.event.block.BlockPlaceEvent;
import java.util.*;

/** Real WindSpigot checks; test-only harness, never shipped in the production JAR. */
final class FbMapCameraServerChecks {
    static void maps(FbServerTest h) throws Exception {
        World w = (World) h.field(h.fb, "world");
        for (FbMap map : FbMap.values()) {
            h.call(h.fb, "build", new Class<?>[]{int.class,FbMap.class}, 0, map);
            h.check(w.getBlockAt(2,70,0).getType().isSolid(), "Akina safe floor " + map);
            h.check(!w.getBlockAt(2,71,0).getType().isSolid() && !w.getBlockAt(2,72,0).getType().isSolid(), "Akina spawn headroom " + map);
            h.check(w.getBlockAt(0,3,0).getType()==Material.AIR,"builder ground excluded");
            if (!map.endless()) {
                h.check(w.getBlockAt(5+map.gap,70,0).getType().isSolid(), "finish edge matches gap " + map);
                for(int x=5;x<5+map.gap;x++) h.check(w.getBlockAt(x,70,0).getType()==Material.AIR,"exact empty gap " + map + " at " + x);
            } else h.check(w.getBlockAt(69,70,0).getType()==Material.AIR,"endless has no finish");
        }
        h.call(h.fb,"build",new Class<?>[]{int.class,FbMap.class,String.class},0,FbMap.B16,"legacy");
        h.check(w.getBlockAt(2,70,0).getType()==Material.QUARTZ_BLOCK,"old replay quartz start");
        h.check(w.getBlockAt(21,70,0).getType()==Material.EMERALD_BLOCK,"old replay emerald finish");
        h.check(w.getBlockAt(2,69,0).getType()==Material.AIR,"new island removed for legacy");
        h.call(h.fb,"build",new Class<?>[]{int.class,FbMap.class},0,FbMap.B32);
        h.check(w.getBlockAt(21,70,0).getType()==Material.AIR,"old finish removed when map changes");
        h.check(w.getBlockAt(37,70,0).getType().isSolid(),"new finish rebuilt");
        Replay old = new Replay();old.frames=new double[][]{{0,2.5,1,0.5,-90,0,0}};
        JsonObject json=old.toJson();json.remove("layout");json.addProperty("v",1);
        h.check(Replay.fromJson(json).layout.equals("legacy"),"old records default to original geometry");
        old.layout=FbIslands.AKINA;
        h.check(Replay.fromJson(old.toJson()).layout.equals(FbIslands.AKINA),"new layout round-trip");
        h.check(FbCamera.packet(4321).a==4321,"camera packet targets exact entity");
    }

    @SuppressWarnings("unchecked") static void camera(FbServerTest h, Player owner) throws Exception {
        Map<UUID,FastBridge.Watch> watches=(Map<UUID,FastBridge.Watch>)h.field(h.fb,"watches");
        FastBridge.Watch watch=watches.get(owner.getUniqueId());
        h.check(watch.rep.layout.equals(FbIslands.AKINA),"new replay marked Akina");
        watch.paused=true;
        owner.performCommand("fb camera first");
        h.check(watch.firstPerson,"first-person command active");
        double t=watch.t;watch.tick();h.check(watch.t==t,"pause preserved with camera");
        Location from=owner.getLocation(),to=from.clone().add(1,0,0);
        PlayerMoveEvent move=new PlayerMoveEvent(owner,from,to);h.fb.onMove(move);
        h.check(move.getTo().distanceSquared(from)==0,"camera input cannot move body");
        watch.again();h.check(watch.firstPerson && !watch.paused && watch.t==0,"restart preserves first person and restarts timeline");
        watch.tick();owner.performCommand("fb camera free");
        h.check(!watch.firstPerson,"free camera command detaches");
        owner.performCommand("fb camera");h.check(watch.firstPerson,"camera toggle command");
        watch.t=watch.rep.duration()+1300;watch.tick();h.check(watch.ended&&!watch.firstPerson,"end releases camera");
        watch.again();owner.performCommand("fb camera first");
        // The original harness will now return to training: cleanup must reset the wire camera.
    }
    static void legacyAndExit(FbServerTest h, Player owner, Replay rep) throws Exception {
        JsonObject data=rep.toJson();data.remove("layout");
        Replay legacy=Replay.fromJson(data);
        h.call(h.fb,"startWatch",new Class<?>[]{Player.class,Replay.class,double.class},owner,legacy,1.0);
        FastBridge.Watch watch=(FastBridge.Watch)((Map<?,?>)h.field(h.fb,"watches")).get(owner.getUniqueId());
        h.check(watch!=null && watch.rep.layout.equals("legacy"),"legacy file opens full replay session");
        h.check(owner.getWorld().getBlockAt(2,70,watch.lane*30).getType()==Material.QUARTZ_BLOCK,"legacy session uses original platforms");
        owner.performCommand("fb camera first");h.check(watch.firstPerson,"legacy first-person available");
        h.check(owner.teleport(Bukkit.getWorlds().get(0).getSpawnLocation()),"external world teleport succeeds");
        h.check(!watch.firstPerson && !((Map<?,?>)h.field(h.fb,"watches")).containsKey(owner.getUniqueId()),"world change cleans target and camera");
    }

}
