package ru.mcapex.social;

import com.andrei1058.bedwars.BedWars;
import com.andrei1058.bedwars.api.arena.IArena;
import com.andrei1058.bedwars.stats.PlayerStats;
import com.google.gson.*;
import org.bukkit.*;
import org.bukkit.configuration.ConfigurationSection;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.entity.Player;
import org.bukkit.plugin.Plugin;
import java.lang.reflect.*;
import java.util.*;
import java.util.concurrent.atomic.AtomicBoolean;

/** Read-only publication of native statistics. Captures live state on the main thread, does I/O asynchronously. */
final class ModePublisher {
    final ApexSocial plugin;final AtomicBoolean busy=new AtomicBoolean();
    static final String[] MAPS={"16","24","32","64","inf"};
    static final long DAY=86400000L;
    static final class Capture {JsonObject body,bw;Set<UUID> offline=new LinkedHashSet<>();Map<UUID,JsonObject> players=new LinkedHashMap<>();}
    ModePublisher(ApexSocial plugin){this.plugin=plugin;}
    void start(){Bukkit.getScheduler().runTaskTimer(plugin,()->publish(),100L,1200L);}
    void publish(){
        if(!busy.compareAndSet(false,true))return;
        final Capture capture;
        try{capture=capture();}catch(Exception ex){busy.set(false);plugin.getLogger().warning("Снимок статистики не подготовлен: "+ex.getClass().getSimpleName());return;}
        Bukkit.getScheduler().runTaskAsynchronously(plugin,()->{
            try{
                complete(capture);
                Plugin tier=Bukkit.getPluginManager().getPlugin("ApexTierTests");Method api=tier.getClass().getDeclaredMethod("api");api.setAccessible(true);Object client=api.invoke(tier);
                Method post=client.getClass().getDeclaredMethod("post",String.class,JsonObject.class);post.setAccessible(true);post.invoke(client,"/modes/snapshot",capture.body);
            }catch(Exception ex){plugin.getLogger().warning("Статистика сайта будет отправлена повторно: "+ex.getClass().getSimpleName());}
            finally{busy.set(false);}
        });
    }
    static Object field(Object target,String name)throws ReflectiveOperationException{if(target==null)return null;Field f=target.getClass().getDeclaredField(name);f.setAccessible(true);return f.get(target);}
    Capture capture()throws ReflectiveOperationException{
        Capture c=new Capture();c.body=new JsonObject();c.body.addProperty("captured_at",java.time.Instant.ofEpochMilli(System.currentTimeMillis()).toString());c.bw=new JsonObject();
        boolean available=BedWars.getRemoteDatabase()!=null&&plugin.games.bwSafe;c.bw.addProperty("configured",available);JsonArray arenas=new JsonArray();int online=0;
        for(IArena a:com.andrei1058.bedwars.arena.Arena.getArenas()){
            if(plugin.games.privateArena(a)||a.getTeams()==null||a.getTeams().size()<2||arenas.size()>=128)continue;
            JsonObject row=new JsonObject();row.addProperty("id",a.getArenaName());row.addProperty("name",ChatColor.stripColor(a.getDisplayName()));row.addProperty("teams",a.getTeams().size());row.addProperty("team_size",a.getMaxInTeam());row.addProperty("players",a.getPlayers().size());row.addProperty("max_players",a.getMaxPlayers());row.addProperty("state",a.getStatus().name());arenas.add(row);online+=a.getPlayers().size();
        }
        c.bw.add("arenas",arenas);c.bw.addProperty("online",online);
        if(available){
            for(Player p:Bukkit.getOnlinePlayers()){PlayerStats stats=BedWars.getStatsManager().get(p.getUniqueId());if(stats!=null){JsonObject row=bwPlayer(stats);if(row!=null)c.players.put(p.getUniqueId(),row);}}
            for(OfflinePlayer p:Bukkit.getOfflinePlayers()){if(c.offline.size()>=3000)break;if(!c.players.containsKey(p.getUniqueId()))c.offline.add(p.getUniqueId());}
        }
        c.body.add("bedwars",c.bw);c.body.add("fastbridge",fastbridge());return c;
    }
    void complete(Capture c){
        if(c.bw.get("configured").getAsBoolean())for(UUID id:c.offline)if(BedWars.getRemoteDatabase().hasStats(id)){
            PlayerStats stats=BedWars.getRemoteDatabase().fetchStats(id);if(stats!=null){JsonObject row=bwPlayer(stats);if(row!=null)c.players.put(id,row);}
        }
        JsonArray players=new JsonArray();for(JsonObject row:c.players.values())players.add(row);c.bw.add("players",players);
    }
    static JsonObject bwPlayer(PlayerStats s){
        if(s.getName()==null||!s.getName().matches("[A-Za-z0-9_]{3,16}"))return null;
        if(s.getGamesPlayed()+s.getWins()+s.getLosses()+s.getKills()+s.getFinalKills()+s.getBedsDestroyed()==0)return null;
        JsonObject row=new JsonObject();row.addProperty("uuid",s.getUuid().toString());row.addProperty("name",s.getName());
        row.addProperty("wins",s.getWins());row.addProperty("losses",s.getLosses());row.addProperty("games",s.getGamesPlayed());row.addProperty("kills",s.getKills());row.addProperty("final_kills",s.getFinalKills());row.addProperty("deaths",s.getDeaths());row.addProperty("final_deaths",s.getFinalDeaths());row.addProperty("beds",s.getBedsDestroyed());return row;
    }
    JsonObject fastbridge()throws ReflectiveOperationException{
        JsonObject out=new JsonObject();JsonObject tables=new JsonObject();JsonArray history=new JsonArray();Plugin tier=Bukkit.getPluginManager().getPlugin("ApexTierTests");
        Method method=tier.getClass().getDeclaredMethod("fastBridge");method.setAccessible(true);Object fb=method.invoke(tier);Object stats=field(fb,"stats");out.addProperty("configured",stats!=null);
        if(stats!=null){
            tables.add("current",fbTable(stats));tables.add("islands",fbTable(field(fb,"islandStats")));tables.add("classic",fbTable(field(fb,"legacyStats")));
            Object perks=field(fb,"perks");YamlConfiguration profiles=(YamlConfiguration)field(perks,"data");
            if(profiles!=null){ConfigurationSection players=profiles.getConfigurationSection("players");if(players!=null)for(String uuid:players.getKeys(false)){
                int count=0;for(Map<?,?> record:profiles.getMapList("players."+uuid+".history")){
                    if(Boolean.parseBoolean(String.valueOf(record.get("private"))))continue;
                    String map=String.valueOf(record.get("map"));if(!Arrays.asList(MAPS).contains(map))continue;
                    JsonObject row=new JsonObject();row.addProperty("uuid",uuid);row.addProperty("map",map);row.addProperty("public",true);row.addProperty("outcome",String.valueOf(record.get("outcome")));
                    for(String key:new String[]{"at","elapsed","value","blocks","distance","left_cps","right_cps"}){
                        Object value=record.get(key);if(value instanceof Number&&Double.isFinite(((Number)value).doubleValue()))row.addProperty(key,(Number)value);
                    }
                    if(row.has("at")&&row.has("elapsed"))history.add(row);if(++count>=20||history.size()>=20000)break;
                }
                if(history.size()>=20000)break;
            }}
            int online=0;@SuppressWarnings("unchecked") Map<UUID,Object> sessions=(Map<UUID,Object>)field(fb,"sessions");
            @SuppressWarnings("unchecked") Map<UUID,Object> watches=(Map<UUID,Object>)field(fb,"watches");
            if(sessions!=null)for(Map.Entry<UUID,Object> e:sessions.entrySet())if(!Boolean.TRUE.equals(field(e.getValue(),"personal"))&&(watches==null||!watches.containsKey(e.getKey())))online++;
            out.addProperty("online",online);
        }
        out.add("tables",tables);out.add("history",history);return out;
    }
    static JsonArray fbTable(Object stats)throws ReflectiveOperationException{
        JsonArray rows=new JsonArray();if(stats==null)return rows;YamlConfiguration yml=(YamlConfiguration)field(stats,"yml");ConfigurationSection players=yml.getConfigurationSection("players");if(players==null)return rows;
        long now=System.currentTimeMillis();int count=0;
        for(String uuid:players.getKeys(false)){
            if(++count>3000)break;String name=yml.getString("players."+uuid+".name");if(name==null||!name.matches("[A-Za-z0-9_]{3,16}"))continue;
            for(String map:MAPS){String path="players."+uuid+".maps."+map;if(!yml.contains(path))continue;JsonObject row=new JsonObject();row.addProperty("uuid",uuid);row.addProperty("name",name);row.addProperty("map",map);row.addProperty("best",yml.contains(path+".best")?yml.getLong(path+".best"):-1L);
                int attempts=0,finishes=0;for(long at:yml.getLongList(path+".attempts"))if(at<=now&&at>=now-DAY)attempts++;
                for(String finish:yml.getStringList(path+".finishes"))try{long at=Long.parseLong(finish.split(":",2)[0]);if(at<=now&&at>=now-DAY)finishes++;}catch(NumberFormatException ignored){}
                row.addProperty("attempts24",attempts);row.addProperty("finishes24",finishes);rows.add(row);
            }
        }
        return rows;
    }
}
