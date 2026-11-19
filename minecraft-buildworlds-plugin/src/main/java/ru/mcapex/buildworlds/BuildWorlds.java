package ru.mcapex.buildworlds;

import org.bukkit.*;
import org.bukkit.block.Block;
import org.bukkit.command.*;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.block.*;
import org.bukkit.event.entity.*;
import org.bukkit.event.player.*;
import org.bukkit.generator.ChunkGenerator;
import org.bukkit.plugin.Plugin;
import org.bukkit.plugin.java.JavaPlugin;
import org.spigotmc.event.player.PlayerSpawnLocationEvent;
import java.io.File;
import java.nio.file.*;
import java.util.*;

/** Isolated, persistent construction worlds. Never registers playable arenas or changes native jars. */
public final class BuildWorlds extends JavaPlugin implements Listener,CommandExecutor,TabCompleter {
    public static final String PERMISSION="apex.buildworlds.admin";
    public static final List<String> MAPS=Arrays.asList("stickfight","fireballfight","thebridge");
    final Map<String,World> worlds=new LinkedHashMap<>();
    YamlConfiguration data;File file;boolean ready;
    public static final class VoidGenerator extends ChunkGenerator {
        @Override public byte[][] generateBlockSections(World w,Random random,int x,int z,BiomeGrid biomes){return new byte[16][];}
        @Override public Location getFixedSpawnLocation(World w,Random random){return new Location(w,.5,81,-47.5);}
    }
    @Override public ChunkGenerator getDefaultWorldGenerator(String world,String id){return new VoidGenerator();}
    @Override public void onEnable(){
        getDataFolder().mkdirs();file=new File(getDataFolder(),"state.yml");data=YamlConfiguration.loadConfiguration(file);
        ready=false;getServer().getPluginManager().registerEvents(this,this);
        try { for(String map:MAPS)loadWorld(map);ready=true; }
        catch(Exception ex){getLogger().log(java.util.logging.Level.SEVERE,"Строительные миры не готовы; вход закрыт",ex);}
        getCommand("buildmap").setExecutor(this);getCommand("buildmap").setTabCompleter(this);
        Bukkit.getScheduler().runTaskTimer(this,()->{
            for(Player p:Bukkit.getOnlinePlayers()){
                if(isBuild(p.getWorld())){
                    if(!allowed(p)){evacuate(p);continue;}
                    if(p.getGameMode()!=GameMode.CREATIVE)p.setGameMode(GameMode.CREATIVE);
                    p.setAllowFlight(true);
                    if(p.getLocation().getY()<5)p.teleport(p.getWorld().getSpawnLocation());
                }else if(data.contains(path(p)+"mode")&&authenticated(p))restore(p);
            }
        },10L,10L);
        getLogger().info("Готовы строительные миры: "+worlds.keySet()+"; вход: /buildmap <карта>, только "+PERMISSION);
    }
    void loadWorld(String map)throws Exception{
        String name="build_"+map;File folder=new File(Bukkit.getWorldContainer(),name);
        boolean owned=data.getBoolean("worlds."+name,false),fresh=!folder.exists()&&Bukkit.getWorld(name)==null;
        if(!owned&&!fresh)throw new IllegalStateException("Refusing to overwrite an unmanaged world: "+name);
        if(!owned){data.set("worlds."+name,true);saveState();}
        World w=new WorldCreator(name).environment(World.Environment.NORMAL).type(WorldType.FLAT).generator(new VoidGenerator()).generateStructures(false).createWorld();
        if(w==null)throw new IllegalStateException("World did not load: "+name);
        w.setAutoSave(true);w.setDifficulty(Difficulty.PEACEFUL);w.setPVP(false);w.setSpawnFlags(false,false);
        w.setGameRuleValue("doDaylightCycle","false");w.setGameRuleValue("doMobSpawning","false");w.setGameRuleValue("mobGriefing","false");w.setGameRuleValue("doFireTick","false");w.setGameRuleValue("keepInventory","true");
        w.setTime(6000);w.setStorm(false);w.setThundering(false);
        if(fresh){template(w,map);w.setSpawnLocation(0,81,-48);w.save();}
        worlds.put(map,w);
    }
    void template(World w,String map){
        if(map.equals("stickfight"))platform(w,-16,16,-16,16,64,(byte)3);
        else{platform(w,-52,-28,-12,12,64,(byte)14);platform(w,28,52,-12,12,64,(byte)11);}
        for(int x=-3;x<=3;x++)for(int z=-51;z<=-45;z++)w.getBlockAt(x,80,z).setType(Material.GLASS,false);
        w.getBlockAt(0,80,-48).setType(Material.GLOWSTONE,false);
    }
    void platform(World w,int x1,int x2,int z1,int z2,int y,byte color){
        for(int x=x1;x<=x2;x++)for(int z=z1;z<=z2;z++){
            Block b=w.getBlockAt(x,y,z);boolean edge=x==x1||x==x2||z==z1||z==z2;
            b.setType(edge?Material.STAINED_CLAY:Material.SMOOTH_BRICK,false);if(edge)b.setData(color,false);
        }
        w.getBlockAt((x1+x2)/2,y,(z1+z2)/2).setType(Material.GOLD_BLOCK,false);
    }
    public boolean isBuild(World w){return w!=null&&MAPS.contains(w.getName().replaceFirst("^build_",""))&&w.getName().startsWith("build_");}
    public boolean authenticated(Player p){
        try{Plugin auth=Bukkit.getPluginManager().getPlugin("ApexAuth");if(auth==null||!auth.isEnabled())return false;
            java.lang.reflect.Field f=auth.getClass().getDeclaredField("manager");f.setAccessible(true);Object manager=f.get(auth);
            java.lang.reflect.Method method=manager.getClass().getDeclaredMethod("isAuthenticated",Player.class);method.setAccessible(true);return Boolean.TRUE.equals(method.invoke(manager,p));
        }catch(Exception ex){return false;}
    }
    public boolean allowed(Player p){return ready&&p.hasPermission(PERMISSION)&&authenticated(p);}
    boolean busy(Player p){
        try{Plugin alley=Bukkit.getPluginManager().getPlugin("Alley");if(alley==null)return false;
            Class<?> type=Class.forName("dev.revere.alley.core.profile.ProfileService",true,alley.getClass().getClassLoader());
            Object service=alley.getClass().getMethod("getService",Class.class).invoke(alley,type);
            Object profile=type.getMethod("getProfile",UUID.class).invoke(service,p.getUniqueId());
            if(profile==null)return true;
            return profile.getClass().getMethod("getMatch").invoke(profile)!=null||!"LOBBY".equals(String.valueOf(profile.getClass().getMethod("getState").invoke(profile)));
        }catch(Exception ex){return true;}
    }
    String path(Player p){return "sessions."+p.getUniqueId()+".";}
    void remember(Player p,Location from){
        if(data.contains(path(p)+"mode"))return;
        String key=path(p);data.set(key+"mode",p.getGameMode().name());data.set(key+"flight",p.getAllowFlight());data.set(key+"flying",p.isFlying());
        data.set(key+"return",from);saveState();
    }
    void restore(Player p){
        String key=path(p);if(!data.contains(key+"mode"))return;
        GameMode mode;try{mode=GameMode.valueOf(data.getString(key+"mode"));}catch(Exception ex){mode=GameMode.ADVENTURE;}
        boolean flight=data.getBoolean(key+"flight"),flying=data.getBoolean(key+"flying");
        p.setGameMode(mode);p.setAllowFlight(flight||mode==GameMode.CREATIVE||mode==GameMode.SPECTATOR);p.setFlying(flying&&p.getAllowFlight());
        data.set("sessions."+p.getUniqueId(),null);saveState();
    }
    Location outside(Player p){
        Object saved=data.get(path(p)+"return");if(saved instanceof Location&&((Location)saved).getWorld()!=null&&!isBuild(((Location)saved).getWorld()))return ((Location)saved).clone();
        World hub=Bukkit.getWorld("world");if(hub!=null&&!isBuild(hub))return hub.getSpawnLocation();
        for(World w:Bukkit.getWorlds())if(!isBuild(w))return w.getSpawnLocation();return null;
    }
    void evacuate(Player p){Location to=outside(p);if(to==null||!p.teleport(to))p.kickPlayer("Строительный мир доступен только авторизованным администраторам.");}
    void saveState(){
        try{File temp=new File(getDataFolder(),"state.yml.tmp");data.save(temp);try{Files.move(temp.toPath(),file.toPath(),StandardCopyOption.ATOMIC_MOVE,StandardCopyOption.REPLACE_EXISTING);}catch(AtomicMoveNotSupportedException ex){Files.move(temp.toPath(),file.toPath(),StandardCopyOption.REPLACE_EXISTING);}}
        catch(Exception ex){throw new IllegalStateException("Could not save build-world state",ex);}
    }
    @Override public boolean onCommand(CommandSender sender,Command command,String label,String[] args){
        if(!(sender instanceof Player)){sender.sendMessage("Миры: build_stickfight, build_fireballfight, build_thebridge. /buildmap <карта>|exit|setspawn");return true;}
        Player p=(Player)sender;if(!allowed(p)){p.sendMessage("§cТолько для авторизованных администраторов.");return true;}
        if(args.length==0){p.sendMessage("§d/buildmap stickfight | fireballfight | thebridge §7- открыть заготовку");p.sendMessage("§d/buildmap exit §7- вернуться; §d/buildmap setspawn §7- сохранить точку входа");return true;}
        String map=args[0].toLowerCase(Locale.ROOT);if(map.equals("exit")){if(isBuild(p.getWorld()))evacuate(p);else p.sendMessage("§7Ты уже вне строительных миров.");return true;}
        if(map.equals("setspawn")){if(!isBuild(p.getWorld()))p.sendMessage("§cСначала войди в строительный мир.");else{Location at=p.getLocation();p.getWorld().setSpawnLocation(at.getBlockX(),at.getBlockY(),at.getBlockZ());p.getWorld().save();p.sendMessage("§aТочка входа сохранена.");}return true;}
        World w=worlds.get(map);if(w==null){p.sendMessage("§cКарты: stickfight, fireballfight, thebridge.");return true;}
        if(!isBuild(p.getWorld())&&busy(p)){p.sendMessage("§cСначала выйди из матча, очереди или режима игры в лобби.");return true;}
        if(p.teleport(w.getSpawnLocation()))p.sendMessage("§aСтроительный мир: §f"+w.getName()+"§a. Строй свободно; выход: §f/buildmap exit");return true;
    }
    @Override public List<String> onTabComplete(CommandSender s,Command c,String a,String[] args){if(!s.hasPermission(PERMISSION)||args.length!=1)return Collections.emptyList();List<String> list=new ArrayList<>(MAPS);list.add("exit");list.add("setspawn");list.removeIf(v->!v.startsWith(args[0].toLowerCase(Locale.ROOT)));return list;}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void teleport(PlayerTeleportEvent e){
        if(e.getTo()!=null&&isBuild(e.getTo().getWorld())&&(!allowed(e.getPlayer())||(!isBuild(e.getFrom().getWorld())&&busy(e.getPlayer())))){e.setCancelled(true);e.getPlayer().sendMessage("§cВход в строительные миры доступен только свободным авторизованным администраторам.");}
    }
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void portal(PlayerPortalEvent e){teleport(e);}
    @EventHandler(priority=EventPriority.MONITOR,ignoreCancelled=true) public void rememberPortal(PlayerPortalEvent e){rememberEntry(e);}
    @EventHandler(priority=EventPriority.MONITOR,ignoreCancelled=true) public void rememberEntry(PlayerTeleportEvent e){if(e.getTo()!=null&&isBuild(e.getTo().getWorld())&&!isBuild(e.getFrom().getWorld()))remember(e.getPlayer(),e.getFrom());}
    @EventHandler(priority=EventPriority.HIGHEST) public void changed(PlayerChangedWorldEvent e){Player p=e.getPlayer();if(isBuild(p.getWorld())){if(allowed(p)){p.setGameMode(GameMode.CREATIVE);p.setAllowFlight(true);p.setFlying(true);}else evacuate(p);}else if(authenticated(p))restore(p);}
    @EventHandler(priority=EventPriority.HIGHEST) public void spawn(PlayerSpawnLocationEvent e){if(isBuild(e.getSpawnLocation().getWorld())){Location to=outside(e.getPlayer());if(to!=null)e.setSpawnLocation(to);}}
    @EventHandler(priority=EventPriority.HIGHEST) public void respawn(PlayerRespawnEvent e){if(isBuild(e.getRespawnLocation().getWorld())&&!allowed(e.getPlayer())){Location to=outside(e.getPlayer());if(to!=null)e.setRespawnLocation(to);}}
    @EventHandler(priority=EventPriority.HIGHEST) public void join(PlayerJoinEvent e){if(isBuild(e.getPlayer().getWorld())&&!allowed(e.getPlayer()))evacuate(e.getPlayer());}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void place(BlockPlaceEvent e){if(isBuild(e.getBlock().getWorld())){boolean ok=allowed(e.getPlayer());e.setCancelled(!ok);e.setBuild(ok);}}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void breakBlock(BlockBreakEvent e){if(isBuild(e.getBlock().getWorld()))e.setCancelled(!allowed(e.getPlayer()));}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void interact(PlayerInteractEvent e){if(isBuild(e.getPlayer().getWorld()))e.setCancelled(!allowed(e.getPlayer()));}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void emptyBucket(PlayerBucketEmptyEvent e){if(isBuild(e.getPlayer().getWorld()))e.setCancelled(!allowed(e.getPlayer()));}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=false) public void fillBucket(PlayerBucketFillEvent e){if(isBuild(e.getPlayer().getWorld()))e.setCancelled(!allowed(e.getPlayer()));}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=true) public void damage(EntityDamageEvent e){if(e.getEntity() instanceof Player&&isBuild(e.getEntity().getWorld()))e.setCancelled(true);}
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=true) public void explosion(EntityExplodeEvent e){if(isBuild(e.getLocation().getWorld()))e.setCancelled(true);}
    @Override public void onDisable(){for(Player p:Bukkit.getOnlinePlayers())if(isBuild(p.getWorld())){evacuate(p);if(!isBuild(p.getWorld()))restore(p);}for(World w:worlds.values())w.save();}
}
