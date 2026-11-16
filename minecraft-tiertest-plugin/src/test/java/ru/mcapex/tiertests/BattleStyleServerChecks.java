package ru.mcapex.tiertests;

import org.bukkit.*;
import org.bukkit.entity.Player;
import org.bukkit.inventory.*;
import org.bukkit.plugin.java.JavaPlugin;
import com.andrei1058.bedwars.api.arena.*;
import com.andrei1058.bedwars.api.events.gameplay.GameEndEvent;
import com.andrei1058.bedwars.api.events.player.PlayerKillEvent;
import dev.revere.alley.feature.match.*;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
import dev.revere.alley.feature.match.model.GameParticipant;
import dev.revere.alley.feature.match.model.internal.MatchGamePlayer;
import java.lang.reflect.*;
import java.util.*;

final class BattleStyleServerChecks {
    static int checks;
    static void check(boolean value,String description){checks++;if(!value)throw new AssertionError(description);}
    static Object field(Object value,String name)throws Exception{Field f=BattleStyle.class.getDeclaredField(name);f.setAccessible(true);return f.get(value);}
    static void set(Object value,Class<?> owner,String name,Object data)throws Exception{Field f=owner.getDeclaredField(name);f.setAccessible(true);f.set(value,data);}
    static Object allocate(Class<?> type)throws Exception{
        Class<?> unsafe=Class.forName("sun.misc.Unsafe");Field f=unsafe.getDeclaredField("theUnsafe");f.setAccessible(true);
        return unsafe.getMethod("allocateInstance",Class.class).invoke(f.get(null),type);
    }
    static int wins(BattleStyle style)throws Exception{return ((Set<?>)field(style,"wins")).size();}
    static void run(JavaPlugin plugin,BattleStyle style,Player owner,Player other)throws Exception{
        UUID id=owner.getUniqueId();int rank=BattleStyle.rank(owner);
        double health=owner.getHealth(),speed=owner.getWalkSpeed();Location position=owner.getLocation().clone();
        ItemStack[] inventory=owner.getInventory().getContents().clone();
        check(rank==4,"fixture has APEX rank");
        check(style.select(id,rank,"victory","aurora"),"APEX victory option");
        check(style.select(id,rank,"kill","sparks"),"kill option");
        check(style.select(id,rank,"message","apex"),"victory card");
        check(style.select(id,rank,"sound","firework"),"jingle");
        check(!style.select(id,rank,"victory","crown"),"locked LEGEND option rejected");
        check(!style.select(id,0,"kill","hearts"),"default rank cannot equip donor FX");
        check(!style.select(id,rank,"../../hack","none"),"invalid settings category rejected");
        check(!style.select(id,rank,"victory","bad.value"),"invalid option rejected");
        check(style.selected(id,0,"victory").equals("none"),"expiry suppresses formerly equipped FX");
        check(new BattleStyle(plugin).selected(id,rank,"victory").equals("aurora"),"settings persisted immediately");
        style.toggle(id);check(!new BattleStyle(plugin).visible(id),"free opt-out persists");style.toggle(id);
        owner.performCommand("style");check(owner.getOpenInventory().getTitle().equals(BattleStyle.TITLE),"style command opens menu");
        check(owner.getOpenInventory().getTopInventory().getItem(15).getType()==Material.COAL,"locked rank shown in menu");owner.closeInventory();
        Inventory donor=Bukkit.createInventory(null,27,BattleStyle.DONOR_TITLE);owner.openInventory(donor);
        check(donor.getItem(22)!=null&&BattleStyle.ENTRY.equals(donor.getItem(22).getItemMeta().getDisplayName()),"perks menu gains style entry");owner.closeInventory();
        List<UUID> winners=Collections.singletonList(id),losers=Collections.singletonList(other.getUniqueId());
        final IArena arena=(IArena)Proxy.newProxyInstance(IArena.class.getClassLoader(),new Class<?>[]{IArena.class},(self,m,args)->{
            if(m.getName().equals("getPlayers"))return Arrays.asList(owner,other);
            if(m.getName().equals("getSpectators"))return Collections.emptyList();
            if(m.getName().equals("getWorld"))return owner.getWorld();
            if(m.getName().equals("getStatus"))return GameState.restarting;
            Class<?> r=m.getReturnType();if(r==boolean.class)return false;if(r==int.class)return 0;return null;
        });
        BattleStyleBw bw=new BattleStyleBw(style);GameEndEvent end=new GameEndEvent(arena,winners,losers,null,winners);
        int before=wins(style);bw.ended(end);check(wins(style)==before+1,"native BW end event produces one celebration");
        bw.ended(end);check(wins(style)==before+1,"repeated BW event deduplicated");
        PlayerKillEvent kill=new PlayerKillEvent(arena,owner,other,p->"unchanged",PlayerKillEvent.PlayerKillCause.values()[0]);
        java.util.function.Function<Player,String> message=kill.getMessage();bw.killed(kill);
        check(kill.getMessage()==message,"native BW kill message unchanged");
        MatchGamePlayer a=(MatchGamePlayer)allocate(MatchGamePlayer.class),b=(MatchGamePlayer)allocate(MatchGamePlayer.class);
        set(a,dev.revere.alley.feature.match.model.GamePlayer.class,"uuid",id);set(b,dev.revere.alley.feature.match.model.GamePlayer.class,"uuid",other.getUniqueId());
        set(a,dev.revere.alley.feature.match.model.GamePlayer.class,"username",owner.getName());set(b,dev.revere.alley.feature.match.model.GamePlayer.class,"username",other.getName());
        set(a,MatchGamePlayer.class,"data",allocate(dev.revere.alley.feature.match.model.MatchGamePlayerData.class));set(b,MatchGamePlayer.class,"data",allocate(dev.revere.alley.feature.match.model.MatchGamePlayerData.class));
        GameParticipant<MatchGamePlayer> pa=new GameParticipant<MatchGamePlayer>(a),pb=new GameParticipant<MatchGamePlayer>(b);
        DefaultMatch match=(DefaultMatch)allocate(DefaultMatch.class);
        match.setParticipantA(pa);match.setParticipantB(pb);match.setWinner(pa);match.setState(MatchState.RUNNING);
        set(match,Match.class,"spectators",new ArrayList<UUID>());
        BattleStyleAlley alley=new BattleStyleAlley(style);before=wins(style);
        alley.scan(Collections.<Match>singletonList(match));check(wins(style)==before,"no victory during running match");
        ((Map<?,?>)field(style,"kills")).clear();a.getData().setKills(1);alley.scan(Collections.<Match>singletonList(match));
        check(((Map<?,?>)field(style,"kills")).containsKey(other.getUniqueId()),"virtual Alley kill detected without PlayerDeathEvent");
        match.setState(MatchState.ENDING_ROUND);alley.scan(Collections.<Match>singletonList(match));check(wins(style)==before,"no premature victory at round end");
        match.setState(MatchState.ENDING_MATCH);alley.scan(Collections.<Match>singletonList(match));check(wins(style)==before+1,"native Alley final winner produces celebration");
        alley.scan(Collections.<Match>singletonList(match));check(wins(style)==before+1,"Alley match celebration once");alley.stop();
        style.kill(owner,other);
        Method draw=BattleStyle.class.getDeclaredMethod("draw",String.class,Location.class,Collection.class,int.class,boolean.class);draw.setAccessible(true);
        for(String effect:BattleStyle.FX)if(!effect.equals("none"))draw.invoke(style,effect,position,Arrays.asList(id,other.getUniqueId()),2,false);
        check(owner.getHealth()==health&&owner.getWalkSpeed()==speed,"health and movement speed unchanged");
        check(owner.getLocation().equals(position),"no player teleport by cosmetics");
        check(Arrays.equals(owner.getInventory().getContents(),inventory),"inventory unchanged");
        check(owner.getActivePotionEffects().isEmpty(),"no potion effects granted");
        style.reset(id);check(style.selected(id,rank,"victory").equals("none"),"reset disables equipped cosmetics");
        plugin.getLogger().info("BATTLE_STYLE_TEST_PASS "+checks+" native BW/Alley and no-gameplay-change checks");
    }
}
