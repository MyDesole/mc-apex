package ru.mcapex.buildworlds;
import org.bukkit.*;import org.bukkit.entity.*;import org.bukkit.event.*;import org.bukkit.event.block.*;import org.bukkit.event.player.*;import org.bukkit.inventory.ItemStack;import org.bukkit.plugin.java.JavaPlugin;import java.util.*;

/** Runs only in an isolated native server, real Alley/Tier/WorldEdit and protocol-47 players. */
public final class BuildChecks implements Listener {
    static BuildWorlds plugin;static JavaPlugin boot;static Player admin,guest;static int stage,count,ticks;static Location origin;static World edit;static long deadline;
    static void check(boolean ok,String text){count++;if(!ok)throw new AssertionError(text);}
    public static void start(JavaPlugin runner){boot=runner;plugin=(BuildWorlds)Bukkit.getPluginManager().getPlugin("ApexBuildWorlds");deadline=System.currentTimeMillis()+110000;
        Bukkit.getScheduler().runTaskTimer(boot,()->{try{tick();}catch(Throwable ex){stage=99;boot.getLogger().log(java.util.logging.Level.SEVERE,"BUILD_TEST_FAIL",ex);}},20L,20L);
    }
    @SuppressWarnings("unchecked") static Set<UUID> denied()throws Exception{Object auth=Bukkit.getPluginManager().getPlugin("ApexAuth");Object manager=auth.getClass().getField("manager").get(auth);return (Set<UUID>)manager.getClass().getField("denied").get(manager);}
    static void tick()throws Exception{
        if(stage==99)return;if(System.currentTimeMillis()>deadline)throw new AssertionError("timeout at stage "+stage);
        if(stage==0){
            admin=Bukkit.getPlayerExact("BuildAdmin");guest=Bukkit.getPlayerExact("BuildGuest");if(admin==null||guest==null)return;
            if(plugin.busy(admin)||plugin.busy(guest))return;
            check(plugin.isEnabled(),"new plugin enabled with current native dependencies");
            admin.setOp(false);guest.setOp(false);admin.addAttachment(boot,BuildWorlds.PERMISSION,true);guest.addAttachment(boot,BuildWorlds.PERMISSION,false);
            World hub=Bukkit.getWorld("world");for(int x=-4;x<=4;x++)for(int z=-4;z<=4;z++)hub.getBlockAt(x,64,z).setType(Material.STONE);
            admin.teleport(new Location(hub,.5,65,.5));guest.teleport(new Location(hub,1.5,65,.5));admin.setGameMode(GameMode.ADVENTURE);admin.setAllowFlight(false);origin=admin.getLocation();
            check(plugin.worlds.size()==3,"three independent construction worlds");
            for(World w:plugin.worlds.values()){
                check(!guest.teleport(w.getSpawnLocation()),"non-admin direct teleport rejected: "+w.getName());
                check(!w.getPVP()&&w.getDifficulty()==Difficulty.PEACEFUL,"safe creative construction settings");
                check(w.getBlockAt(400,64,400).getType()==Material.AIR,"outside the template is void");
                PlayerPortalEvent portal=new PlayerPortalEvent(guest,guest.getLocation(),w.getSpawnLocation(),null);Bukkit.getPluginManager().callEvent(portal);check(portal.isCancelled(),"portal cannot bypass private access");
                PlayerRespawnEvent respawn=new PlayerRespawnEvent(guest,w.getSpawnLocation(),true);Bukkit.getPluginManager().callEvent(respawn);check(!plugin.isBuild(respawn.getRespawnLocation().getWorld()),"bed/respawn access guarded");
                org.spigotmc.event.player.PlayerSpawnLocationEvent login=new org.spigotmc.event.player.PlayerSpawnLocationEvent(guest,w.getSpawnLocation());Bukkit.getPluginManager().callEvent(login);check(!plugin.isBuild(login.getSpawnLocation().getWorld()),"saved login position cannot enter protected world");
            }
            edit=plugin.worlds.get("stickfight");if(new java.io.File(plugin.getDataFolder(),"fixture-built.marker").isFile())check(edit.getBlockAt(3,90,3).getType()==Material.DIAMOND_BLOCK,"player construction survives a full native server restart");denied().add(admin.getUniqueId());check(!admin.teleport(edit.getSpawnLocation()),"admin without website authentication denied");denied().clear();
            check(!plugin.data.contains(plugin.path(guest)+"mode"),"cancelled entry creates no editor session");
            admin.performCommand("buildmap stickfight");check(admin.getWorld()==edit,"admin command reaches correct world");check(admin.getGameMode()==GameMode.CREATIVE&&admin.getAllowFlight(),"admin can build and fly");
            blockChecks();admin.setOp(true);
            edit.getBlockAt(10,70,10).setType(Material.AIR);command("//pos1 10,70,10");stage=1;ticks=-2;return;
        }
        if(stage==1){
            if(ticks==-2){command("//pos2 11,70,11");ticks=-1;return;}
            if(ticks==-1){command("//set glowstone");ticks=0;return;}
            if(edit.getBlockAt(10,70,10).getType()!=Material.GLOWSTONE){if(++ticks>12)throw new AssertionError("native WorldEdit did not edit build world");return;}
            check(true,"native WorldEdit changes persist in construction world");
            check(admin.getGameMode()==GameMode.CREATIVE&&admin.getAllowFlight(),"native lobby maintenance does not disable editor creative/flight");
            for(String map:BuildWorlds.MAPS){admin.performCommand("buildmap "+map);check(admin.getWorld()==plugin.worlds.get(map),"switch world "+map);}
            admin.performCommand("buildmap exit");check(admin.getWorld()==origin.getWorld(),"exit returns to original world");check(admin.getGameMode()==GameMode.ADVENTURE&&!admin.getAllowFlight(),"original mode/flight restored");
            admin.performCommand("buildmap stickfight");admin.teleport(new Location(edit,9.5,81,-47.5));admin.performCommand("buildmap setspawn");check(edit.getSpawnLocation().getBlockX()==9,"custom edit spawn saved");
            edit.getBlockAt(3,90,3).setType(Material.DIAMOND_BLOCK);Bukkit.getPluginManager().disablePlugin(plugin);check(!plugin.isBuild(admin.getWorld()),"disable evacuates editor safely");Bukkit.getPluginManager().enablePlugin(plugin);
            check(edit.getBlockAt(3,90,3).getType()==Material.DIAMOND_BLOCK,"reloading never regenerates existing user builds");check(edit.getSpawnLocation().getBlockX()==9,"custom spawn survives reload");
            admin.performCommand("buildmap stickfight");denied().add(admin.getUniqueId());stage=2;ticks=0;return;
        }
        if(stage==2){if(plugin.isBuild(admin.getWorld())){if(++ticks>4)throw new AssertionError("permission/auth revocation not enforced");return;}
            check(true,"authentication revocation removes editor from world");denied().clear();
            BlockBreakEvent outside=new BlockBreakEvent(Bukkit.getWorld("world").getBlockAt(3,64,3),guest);outside.setCancelled(true);Bukkit.getPluginManager().callEvent(outside);check(outside.isCancelled(),"ordinary hub protection is unchanged");
            for(World w:plugin.worlds.values())w.save();new java.io.File(plugin.getDataFolder(),"fixture-built.marker").createNewFile();boot.getLogger().info("BUILD_TEST_PASS "+count+" native construction/access/persistence checks");stage=99;
        }
    }
    static void command(String text){PlayerCommandPreprocessEvent e=new PlayerCommandPreprocessEvent(admin,text);Bukkit.getPluginManager().callEvent(e);if(!e.isCancelled())admin.performCommand(e.getMessage().substring(1));}
    static void blockChecks(){
        org.bukkit.block.Block block=edit.getBlockAt(5,66,5);
        BlockPlaceEvent place=new BlockPlaceEvent(block,block.getState(),block.getRelative(0,-1,0),new ItemStack(Material.STONE),admin,false);place.setCancelled(true);Bukkit.getPluginManager().callEvent(place);check(!place.isCancelled()&&place.canBuild(),"admin placing works despite native lobby cancellation");
        BlockBreakEvent breaking=new BlockBreakEvent(block,admin);breaking.setCancelled(true);Bukkit.getPluginManager().callEvent(breaking);check(!breaking.isCancelled(),"admin breaking works despite native lobby cancellation");
        BlockPlaceEvent forbidden=new BlockPlaceEvent(block,block.getState(),block,new ItemStack(Material.STONE),guest,true);Bukkit.getPluginManager().callEvent(forbidden);check(forbidden.isCancelled()&&!forbidden.canBuild(),"non-admin cannot alter protected build blocks");
        BlockBreakEvent forbiddenBreak=new BlockBreakEvent(block,guest);Bukkit.getPluginManager().callEvent(forbiddenBreak);check(forbiddenBreak.isCancelled(),"non-admin block break denied");
    }
}
