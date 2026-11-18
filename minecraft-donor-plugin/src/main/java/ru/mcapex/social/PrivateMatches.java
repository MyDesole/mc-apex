package ru.mcapex.social;

import com.andrei1058.bedwars.api.arena.*;
import com.andrei1058.bedwars.api.events.player.PlayerJoinArenaEvent;
import com.andrei1058.bedwars.api.events.gameplay.GameStateChangeEvent;
import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.feature.kit.*;
import dev.revere.alley.feature.arena.ArenaService;
import dev.revere.alley.feature.match.*;
import dev.revere.alley.feature.match.model.*;
import dev.revere.alley.feature.match.model.internal.MatchGamePlayer;
import org.bukkit.*;
import org.bukkit.entity.Player;
import org.bukkit.event.*;
import org.bukkit.event.inventory.*;
import org.bukkit.event.player.*;
import org.bukkit.event.world.WorldUnloadEvent;
import java.util.*;

final class PrivateMatches implements Listener {
    final ApexSocial plugin;
    final Map<UUID,Session> sessions=new HashMap<>();
    final Map<UUID,Invite> invites=new HashMap<>();
    final Set<IArena> reserved=Collections.newSetFromMap(new IdentityHashMap<>());
    final Map<IArena,Long> releasing=new IdentityHashMap<>();
    final Set<UUID> knownBwWorlds=new HashSet<>();
    boolean bwSafe;
    final WorldTickerCache worldTicks=new WorldTickerCache();
    static final class Session{
        final UUID host;final Set<UUID> members=new LinkedHashSet<>();final String type,target;final long created=System.currentTimeMillis();
        IArena bw;Match duel;boolean started;
        Session(Player host,String type,String target){this.host=host.getUniqueId();members.add(this.host);this.type=type;this.target=target;}
    }
    static final class Invite{final Session game;final long expires=System.currentTimeMillis()+60000;Invite(Session game){this.game=game;}}
    PrivateMatches(ApexSocial plugin){this.plugin=plugin;}
    void installRewardShield(){
        boolean before=bwSafe;BwRewardShield shield=new BwRewardShield(this);int count=shield.install();bwSafe=shield.complete&&worldTicks.ready;
        if(count>0||before!=bwSafe)plugin.getLogger().info("Приватные BW: защищено новых обработчиков статистики/наград: "+count+", допуск: "+bwSafe);
    }
    boolean privateArena(IArena a){return reserved.contains(a);}
    private Session session(Player p){return sessions.get(p.getUniqueId());}
    private boolean hostEligible(Player p){return ApexSocial.rank(p)>=4;}
    void open(Player p){
        ApexSocial.Menu m=ApexSocial.menu(p,"private-home",27,"APEX • Приватные игры");Session s=session(p);
        if(s==null){m.inv.setItem(11,ApexSocial.item(Material.BED,"§eПриватный BedWars","§7Выбери свободную карту","§7Хозяин от APEX, гости бесплатно"));m.inv.setItem(15,ApexSocial.item(Material.DIAMOND_SWORD,"§eПриватная дуэль","§7Два игрока, стандартные наборы","§7Без статистики, ELO и наград"));}
        else{m.inv.setItem(11,ApexSocial.item(Material.PAPER,"§e"+s.type+" • "+s.target,"§7Игроков: §f"+s.members.size(),"§7Пригласить: /private invite ник","§7Принять: /private accept ник_хозяина"));m.inv.setItem(15,ApexSocial.item(Material.EMERALD,"§aНачать матч","§7Начать может только хозяин"));m.inv.setItem(22,ApexSocial.item(Material.BARRIER,"§cВыйти / закрыть","§7Выход хозяина закроет сессию"));}p.openInventory(m.inv);
    }
    private boolean freeBw(IArena a){return bwSafe&&a.getStatus()==GameState.waiting&&a.getPlayers().isEmpty()&&a.getSpectators().isEmpty()&&!reserved.contains(a)&&a.getTeams().size()>=2&&!Objects.toString(a.getGroup(),"").toLowerCase(Locale.ROOT).contains("clan");}
    private void choose(Player p,String type){
        if(!hostEligible(p)){ApexSocial.tell(p,"Создавать приватные матчи могут APEX и LEGEND. Участие по приглашению бесплатно.");return;}
        ApexSocial.Menu m=ApexSocial.menu(p,"private-choose-"+type,54,"Приватный матч • Выбор");int at=0;
        if(type.equals("bw")){
            for(IArena a:com.andrei1058.bedwars.arena.Arena.getArenas())if(freeBw(a)&&at<45){m.inv.setItem(at,ApexSocial.item(Material.BED,"§e"+a.getArenaName(),"§7До "+a.getMaxPlayers()+" игроков, "+a.getMaxInTeam()+" в команде"));m.values.put(at++,a.getArenaName());}
        }else for(Kit k:AlleyPlugin.getInstance().getService(KitService.class).getKits())if(k.isEnabled()&&at<45){m.inv.setItem(at,ApexSocial.item(k.getIcon(),"§e"+k.getName(),"§7Стандартная дуэль без статистики"));m.values.put(at++,k.getName());}
        if(at==0)m.inv.setItem(22,ApexSocial.item(Material.BARRIER,"§7Сейчас нет свободных карт"));p.openInventory(m.inv);
    }
    void command(Player p,String[] args){
        if(args.length==0){open(p);return;}
        String cmd=args[0].toLowerCase(Locale.ROOT);
        if(cmd.equals("leave")||cmd.equals("close")){Session s=session(p);if(s!=null)leave(p,s);else ApexSocial.tell(p,"Ты не в приватной сессии.");return;}
        if(cmd.equals("start")){start(p);return;}
        if(cmd.equals("invite")&&args.length==2){invite(p,args[1]);return;}
        if(cmd.equals("accept")&&args.length==2){accept(p,args[1]);return;}
        if(cmd.equals("create")&&args.length==3){create(p,args[1],args[2]);return;}
        ApexSocial.tell(p,"/private — меню; /private create bw карта; /private create duel набор");
        ApexSocial.tell(p,"/private invite ник; /private accept хозяин; /private start; /private leave");
    }
    private void create(Player p,String type,String target){
        if(!hostEligible(p)||!ApexSocial.ready(p)||ApexSocial.profile(p).getParty()!=null){ApexSocial.tell(p,"Создание доступно от APEX в лобби, вне обычной пати.");return;}
        if(session(p)!=null){ApexSocial.tell(p,"Сначала закрой текущую сессию: /private leave");return;}
        if(!type.equals("bw")&&!type.equals("duel")){ApexSocial.tell(p,"Режим: bw или duel");return;}
        Session s=new Session(p,type,target);
        if(type.equals("bw")){
            IArena a=com.andrei1058.bedwars.arena.Arena.getArenaByName(target);
            if(a==null||!freeBw(a)){ApexSocial.tell(p,"Эта карта занята или недоступна. Выбери другую в /private.");return;}
            s.bw=a;reserved.add(a);if(a.getWorld()!=null)knownBwWorlds.add(a.getWorld().getUID());
        }else{Kit k=AlleyPlugin.getInstance().getService(KitService.class).getKit(target);if(k==null||!k.isEnabled()){ApexSocial.tell(p,"Этот набор недоступен.");return;}}
        sessions.put(s.host,s);p.closeInventory();ApexSocial.tell(p,"Сессия создана. /private invite ник, затем /private start. Гостям донат не нужен.");
    }
    private void invite(Player p,String name){
        Session s=session(p);if(s==null||!s.host.equals(p.getUniqueId())||s.started){ApexSocial.tell(p,"Приглашать может хозяин сессии до старта.");return;}
        Player guest=Bukkit.getPlayerExact(name);if(guest==null||guest.equals(p)||!ApexSocial.ready(guest)||session(guest)!=null||ApexSocial.profile(guest).getParty()!=null){ApexSocial.tell(p,"Игрок должен быть в сети, авторизован и свободен от матча, пати и приватной сессии.");return;}
        invites.put(guest.getUniqueId(),new Invite(s));ApexSocial.tell(guest,"§e"+p.getName()+" §7приглашает в приватный "+s.type+". Бесплатно, без наград. Принять за 60 с: §e/private accept "+p.getName());ApexSocial.tell(p,"Приглашение отправлено.");
    }
    private void accept(Player p,String hostName){
        Invite invite=invites.get(p.getUniqueId());Player host=Bukkit.getPlayerExact(hostName);
        if(invite==null||host==null||!invite.game.host.equals(host.getUniqueId())||invite.expires<System.currentTimeMillis()||invite.game.started||sessions.get(invite.game.host)!=invite.game){ApexSocial.tell(p,"Приглашение отсутствует или истекло.");return;}
        Session s=invite.game;
        if(!hostEligible(host)||!ApexSocial.ready(p)||ApexSocial.profile(p).getParty()!=null||session(p)!=null){ApexSocial.tell(p,"Сейчас присоединиться нельзя: вернись в лобби вне пати и матча.");return;}
        if(s.members.size()>=(s.bw==null?2:s.bw.getMaxPlayers())){ApexSocial.tell(p,"Сессия заполнена.");return;}
        invites.remove(p.getUniqueId());s.members.add(p.getUniqueId());sessions.put(p.getUniqueId(),s);ApexSocial.tell(p,"Ты в приватной сессии "+hostName+". Ожидай старта.");ApexSocial.tell(host,p.getName()+" присоединился. Игроков: "+s.members.size());
    }
    private void start(Player p){
        Session s=session(p);if(s==null||!s.host.equals(p.getUniqueId())||s.started){ApexSocial.tell(p,"Начать может хозяин ожидающей сессии.");return;}
        if(!hostEligible(p)){close(s,"Привилегия хозяина больше не активна.");return;}
        int min=s.bw==null?2:Math.max(2,s.bw.getConfig().getInt("minPlayers"));
        if(s.members.size()<min){ApexSocial.tell(p,"Нужно игроков: "+min+". Сейчас: "+s.members.size());return;}
        List<Player> members=new ArrayList<>();
        for(UUID id:s.members){Player member=Bukkit.getPlayer(id);if(member==null||!ApexSocial.ready(member)||ApexSocial.profile(member).getParty()!=null){ApexSocial.tell(p,"Все участники должны быть в лобби вне пати и матча.");return;}members.add(member);}
        for(Player member:members){member.closeInventory();ApexSocial.tell(member,"§eПриватная тренировка: общая статистика, ELO и награды отключены.");}
        try{
            s.started=true;
            if(s.bw!=null){
                if(s.bw.getStatus()!=GameState.waiting||!s.bw.getPlayers().isEmpty())throw new IllegalStateException("Arena no longer empty");
                for(Player member:members)if(!s.bw.addPlayer(member,true))throw new IllegalStateException("Join rejected");
                if(s.bw.getStartingTask()==null)throw new IllegalStateException("Native countdown did not start");
                s.bw.getStartingTask().setCountdown(5);
            }else{
                Kit k=AlleyPlugin.getInstance().getService(KitService.class).getKit(s.target);ArenaService arenas=AlleyPlugin.getInstance().getService(ArenaService.class);
                dev.revere.alley.feature.arena.Arena selected=arenas.getRandomArena(k);
                if(selected==null)throw new IllegalStateException("No native arena for kit");selected=arenas.selectArenaWithPotentialTemporaryCopy(selected);
                if(selected==null)throw new IllegalStateException("Arena copy unavailable");
                if(selected.getPos1()==null||selected.getPos2()==null||selected.getPos1().getWorld()==null||selected.getPos2().getWorld()==null)throw new IllegalStateException("Arena spawn points are not configured");
                Player a=members.get(0),b=members.get(1);
                AlleyPlugin.getInstance().getService(MatchService.class).createAndStartMatch(k,selected,new GameParticipant<>(new MatchGamePlayer(a.getUniqueId(),a.getName())),new GameParticipant<>(new MatchGamePlayer(b.getUniqueId(),b.getName())),false,false,false);
                s.duel=ApexSocial.profile(p).getMatch();
                if(s.duel==null)throw new IllegalStateException("Match not created");s.duel.setAffectStatistics(false);plugin.privateDuels.add(s.duel);
            }
        }catch(Exception ex){
            if(s.bw==null&&s.duel==null)s.duel=ApexSocial.profile(p).getMatch();
            plugin.getLogger().warning("Приватный матч не запущен: "+ex.getMessage());close(s,"Не удалось начать матч. Попробуй другую карту.");
        }
    }
    private void leave(Player p,Session s){
        if(s.host.equals(p.getUniqueId())||s.started){close(s,"Приватная сессия закрыта после выхода участника.");return;}
        s.members.remove(p.getUniqueId());sessions.remove(p.getUniqueId());ApexSocial.tell(p,"Ты вышел из приватной сессии.");
    }
    private void close(Session s,String message){
        for(UUID id:new ArrayList<>(s.members)){sessions.remove(id,s);Player p=Bukkit.getPlayer(id);if(p!=null)ApexSocial.tell(p,message);}
        invites.entrySet().removeIf(e->e.getValue().game==s);
        if(s.duel!=null&&AlleyPlugin.getInstance().getService(MatchService.class).getMatches().contains(s.duel))s.duel.endMatch();
        if(s.bw!=null){
            if(s.started||!s.bw.getPlayers().isEmpty()){
                releasing.put(s.bw,System.currentTimeMillis());
                if(s.bw.getStatus()!=GameState.restarting)s.bw.changeStatus(GameState.restarting);
                // Use native leave/restore, then let its restarting task unload the empty world.
                if(s.bw.getPlayers()!=null)for(Player member:new ArrayList<>(s.bw.getPlayers()))s.bw.removePlayer(member,false);
                if(s.bw.getSpectators()!=null)for(Player spectator:new ArrayList<>(s.bw.getSpectators()))s.bw.removeSpectator(spectator,false);
            }
            else reserved.remove(s.bw);
        }
    }
    void maintenance(){
        if(!bwSafe)installRewardShield();
        for(IArena a:com.andrei1058.bedwars.arena.Arena.getArenas())if(a.getWorld()!=null)knownBwWorlds.add(a.getWorld().getUID());
        invites.entrySet().removeIf(e->e.getValue().expires<System.currentTimeMillis());
        for(Session s:new HashSet<>(sessions.values())){
            Player host=Bukkit.getPlayer(s.host);
            if(host==null||!hostEligible(host)||(!s.started&&System.currentTimeMillis()-s.created>600000)){close(s,"Приватная сессия закрыта: хозяин вышел, срок ранга изменился или истекло ожидание.");continue;}
            if(s.started&&s.duel!=null&&!AlleyPlugin.getInstance().getService(MatchService.class).getMatches().contains(s.duel))close(s,"Приватная тренировка завершена.");
            else if(s.started&&s.bw!=null&&s.bw.getStatus()==GameState.restarting)close(s,"Приватная тренировка завершена.");
            else if(s.started&&s.bw!=null&&s.bw.getStatus()==GameState.playing)stopPeriodic(s.bw);
        }
        for(Iterator<Map.Entry<IArena,Long>> it=releasing.entrySet().iterator();it.hasNext();){Map.Entry<IArena,Long> e=it.next();IArena a=e.getKey();IArena current=com.andrei1058.bedwars.arena.Arena.getArenaByName(a.getArenaName());
            if(current!=null&&current.getStatus()==GameState.waiting&&current.getPlayers()!=null&&current.getPlayers().isEmpty()&&System.currentTimeMillis()-e.getValue()>5000){reserved.remove(a);it.remove();}
        }
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void join(PlayerJoinArenaEvent e){
        if(!privateArena(e.getArena()))return;Session s=sessions.get(e.getPlayer().getUniqueId());
        if(s==null||s.bw!=e.getArena()||!s.started||e.isSpectator()){e.setCancelled(true);ApexSocial.tell(e.getPlayer(),"Это приватная арена. Нужны приглашение и подтверждение хозяина.");}
    }
    @EventHandler(priority=EventPriority.HIGHEST,ignoreCancelled=true) public void teleport(PlayerTeleportEvent e){
        if(e.getTo()==null||e.getTo().getWorld()==null)return;
        for(IArena a:reserved)if(a.getWorld()!=null&&e.getTo().getWorld().equals(a.getWorld())){
            Session s=session(e.getPlayer());if(s==null||s.bw!=a||!s.started)e.setCancelled(true);
        }
    }
    @EventHandler(priority=EventPriority.MONITOR) public void state(GameStateChangeEvent e){
        if(privateArena(e.getArena())&&e.getNewState()==GameState.playing)Bukkit.getScheduler().runTask(plugin,()->stopPeriodic(e.getArena()));
    }
    private void stopPeriodic(IArena arena){
        try{for(String name:new String[]{"perMinuteTask","moneyperMinuteTask"}){
            java.lang.reflect.Field f=arena.getClass().getDeclaredField(name);f.setAccessible(true);Object task=f.get(arena);if(task!=null)task.getClass().getMethod("cancel").invoke(task);
        }}catch(Exception ex){plugin.getLogger().severe("Не удалось отключить периодические награды приватного BW; матч закрывается");for(Session s:new HashSet<>(sessions.values()))if(s.bw==arena)close(s,"Тренировка отменена: защита наград недоступна.");}
    }
    @EventHandler(priority=EventPriority.MONITOR) public void quit(PlayerQuitEvent e){Session s=session(e.getPlayer());if(s!=null)leave(e.getPlayer(),s);invites.remove(e.getPlayer().getUniqueId());}
    @EventHandler(priority=EventPriority.MONITOR,ignoreCancelled=true) public void unload(WorldUnloadEvent e){
        if(knownBwWorlds.remove(e.getWorld().getUID())){
            try{worldTicks.beforeUnload(e.getWorld());}catch(ReflectiveOperationException ex){plugin.getLogger().severe("Не удалось обновить кэш миров WindSpigot: "+ex.getClass().getSimpleName());}
        }
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void click(InventoryClickEvent e){
        if(!(e.getView().getTopInventory().getHolder() instanceof ApexSocial.Menu))return;ApexSocial.Menu m=(ApexSocial.Menu)e.getView().getTopInventory().getHolder();if(!m.kind.startsWith("private-"))return;
        e.setCancelled(true);Player p=(Player)e.getWhoClicked();if(!m.owner.equals(p.getUniqueId())||!ApexSocial.authenticated(p))return;
        int at=e.getRawSlot();if(at<0||at>=m.inv.getSize()||!e.isLeftClick())return;
        if(m.kind.startsWith("private-choose-")){if(!ApexSocial.ready(p))return;String target=m.values.get(at);if(target!=null)create(p,m.kind.substring("private-choose-".length()),target);return;}
        Session s=session(p);if(s==null){if(!ApexSocial.ready(p))return;if(at==11)choose(p,"bw");if(at==15)choose(p,"duel");}else{if(at==15)start(p);if(at==22)leave(p,s);}
    }
    void shutdown(){for(Session s:new HashSet<>(sessions.values()))close(s,"Сервер перезапускается, приватная сессия закрыта.");}
}
