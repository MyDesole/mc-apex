package ru.mcapex.tiertests;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.ProfileService;
import dev.revere.alley.feature.match.*;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
import dev.revere.alley.feature.match.model.GameParticipant;
import dev.revere.alley.feature.match.model.internal.MatchGamePlayer;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.entity.PlayerDeathEvent;
import org.bukkit.scheduler.BukkitTask;
import java.util.*;

final class BattleStyleAlley implements Listener {
    private final BattleStyle styles;
    private BukkitTask task;
    private final Map<Match,Map<UUID,Integer>> killCounts=new IdentityHashMap<Match,Map<UUID,Integer>>();
    private final Set<Match> finished=Collections.newSetFromMap(new IdentityHashMap<Match,Boolean>());
    BattleStyleAlley(BattleStyle styles){this.styles=styles;}
    private List<Match> matches(){return AlleyPlugin.getInstance().getService(MatchService.class).getMatches();}
    void start(){
        Bukkit.getPluginManager().registerEvents(this,styles.plugin);
        task=Bukkit.getScheduler().runTaskTimer(styles.plugin,()->scan(new ArrayList<Match>(matches())),5,5);
    }
    void stop(){if(task!=null)task.cancel();HandlerList.unregisterAll(this);finished.clear();killCounts.clear();}
    boolean inCombat(Player p){for(Match match:matches())for(GameParticipant<MatchGamePlayer> part:match.getParticipants())if(part.containsPlayer(p.getUniqueId())&&match.getState()!=MatchState.ENDING_MATCH)return true;return false;}
    void scan(List<Match> active){
        finished.retainAll(active);killCounts.keySet().retainAll(active);
        for(Match match:active){
            scanKills(match);
            if(!(match instanceof DefaultMatch)||match.getState()!=MatchState.ENDING_MATCH||finished.contains(match))continue;
            GameParticipant<MatchGamePlayer> winner=((DefaultMatch)match).getWinner();if(winner==null)continue;
            finished.add(match);Set<UUID> winners=new LinkedHashSet<UUID>(),audience=new LinkedHashSet<UUID>(match.getSpectators());
            for(GameParticipant<MatchGamePlayer> part:match.getParticipants())for(MatchGamePlayer p:part.getAllPlayers())audience.add(p.getUuid());
            for(MatchGamePlayer p:winner.getAllPlayers())winners.add(p.getUuid());
            styles.victory("duel:"+System.identityHashCode(match),winners,audience,"Дуэль");
        }
    }
    @EventHandler(priority=EventPriority.MONITOR) public void killed(PlayerDeathEvent e){
        Player victim=e.getEntity(),killer=victim.getKiller();if(killer==null)return;
        boolean same=false;
        for(Match m:matches()){boolean a=false,b=false;for(GameParticipant<MatchGamePlayer> part:m.getParticipants()){a|=part.containsPlayer(victim.getUniqueId());b|=part.containsPlayer(killer.getUniqueId());}if(a&&b){same=true;break;}}
        if(!same)return;
        if(!nativeEffect(killer))styles.kill(killer,victim);
    }

    private boolean nativeEffect(Player killer){
        if(AlleyPlugin.getInstance()==null)return false;
        Profile profile=AlleyPlugin.getInstance().getService(ProfileService.class).getProfile(killer.getUniqueId());
        if(profile==null)return false;
        String effect=profile.getProfileData().getCosmeticData().getSelectedKillEffect();
        return effect!=null&&!effect.equalsIgnoreCase("none");
    }
    private void scanKills(Match match){
        Map<UUID,Integer> seen=killCounts.get(match);
        if(seen==null){seen=new HashMap<UUID,Integer>();killCounts.put(match,seen);}
        List<GameParticipant<MatchGamePlayer>> parts=match.getParticipants();
        for(GameParticipant<MatchGamePlayer> part:parts)for(MatchGamePlayer player:part.getAllPlayers()){
            int count=player.getData().getKills();Integer previous=seen.put(player.getUuid(),count);
            if(count<=(previous==null?0:previous)||parts.size()!=2)continue;
            Player killer=Bukkit.getPlayer(player.getUuid());if(killer==null||nativeEffect(killer))continue;
            GameParticipant<MatchGamePlayer> opponent=parts.get(0)==part?parts.get(1):parts.get(0);
            // Attribution is unambiguous only for 1v1. Native PlayerDeathEvent handles team deaths.
            if(part.getAllPlayers().size()==1&&opponent.getAllPlayers().size()==1){
                Player victim=Bukkit.getPlayer(opponent.getLeader().getUuid());styles.kill(killer,victim);
            }
        }
    }
}
