package ru.mcapex.auth;
import java.lang.reflect.Proxy;
import java.net.InetAddress;
import java.util.UUID;
import org.bukkit.Bukkit;
import org.bukkit.Server;
import org.bukkit.entity.Player;
import org.bukkit.plugin.Plugin;
import org.bukkit.plugin.PluginManager;
import org.bukkit.event.player.AsyncPlayerPreLoginEvent;
import org.bukkit.event.player.PlayerLoginEvent;
import ru.mcapex.tiertests.AdmissionGuard;

/** Native Bukkit API regression checks; no production accounts or passwords. */
public final class AdmissionChecks {
    static int count;
    static boolean present,enabled;
    static void check(boolean ok,String message){count++;if(!ok)throw new AssertionError(message);}
    static Object defaultValue(Class<?> type){
        if(!type.isPrimitive())return null;
        if(type==boolean.class)return false;
        if(type==int.class)return 0;
        if(type==long.class)return 0L;
        return null;
    }
    @SuppressWarnings("unchecked") static <T> T proxy(Class<T> type,java.lang.reflect.InvocationHandler handler){
        return (T)Proxy.newProxyInstance(type.getClassLoader(),new Class<?>[]{type},handler);
    }
    public static void main(String[] args)throws Exception{
        Plugin auth=proxy(Plugin.class,(o,m,a)->m.getName().equals("isEnabled")?enabled:defaultValue(m.getReturnType()));
        PluginManager pm=proxy(PluginManager.class,(o,m,a)->m.getName().equals("getPlugin")?(present?auth:null):defaultValue(m.getReturnType()));
        Server server=proxy(Server.class,(o,m,a)->{
            switch(m.getName()){
                case "getPluginManager":return pm;
                case "getLogger":return java.util.logging.Logger.getLogger("admission-test");
                case "getVersion":case "getBukkitVersion":case "getName":return "native-test";
                default:return defaultValue(m.getReturnType());
            }
        });
        Bukkit.setServer(server);
        UUID id=UUID.randomUUID();InetAddress ip=InetAddress.getLoopbackAddress();
        Player player=proxy(Player.class,(o,m,a)->m.getName().equals("getUniqueId")?id:m.getName().equals("getName")?"ValidName":defaultValue(m.getReturnType()));
        AdmissionGuard guard=new AdmissionGuard();
        for(int state=0;state<3;state++){
            present=state>0;enabled=state==2;
            AsyncPlayerPreLoginEvent pre=new AsyncPlayerPreLoginEvent("ValidName",ip,id);guard.pre(pre);
            PlayerLoginEvent login=new PlayerLoginEvent(player,"localhost",ip);guard.login(login);
            check((pre.getLoginResult()==AsyncPlayerPreLoginEvent.Result.ALLOWED)==enabled,"guard pre-login state "+state);
            check((login.getResult()==PlayerLoginEvent.Result.ALLOWED)==enabled,"guard login state "+state);
        }
        AuthListeners listeners=new AuthListeners(null,null);
        for(String name:new String[]{"шайтан","ab","a".repeat(17),"name space","Name-1","Ａpex","a\n"}){
            check(!AuthListeners.validNickname(name),"invalid name "+name);
            AsyncPlayerPreLoginEvent pre=new AsyncPlayerPreLoginEvent(name,ip,id);listeners.preLogin(pre);
            check(pre.getLoginResult()!=AsyncPlayerPreLoginEvent.Result.ALLOWED,"invalid name admitted");
        }
        for(String name:new String[]{"abc","MyDesole","A_b123","a".repeat(16)})check(AuthListeners.validNickname(name),"valid name "+name);
        check(!AuthListeners.validNickname(null),"null name");
        PlayerLoginEvent early=new PlayerLoginEvent(player,"localhost",ip);listeners.login(early);
        check(early.getResult()!=PlayerLoginEvent.Result.ALLOWED,"early startup without pre-login token");
        check(!new AuthManager(null).canAdmit("ValidName"),"unconfigured manager fails closed");
        java.lang.reflect.Field field=AuthListeners.class.getDeclaredField("admitted");field.setAccessible(true);
        @SuppressWarnings("unchecked") java.util.Map<UUID,Long> admitted=(java.util.Map<UUID,Long>)field.get(listeners);
        admitted.put(id,System.currentTimeMillis()-61000);
        PlayerLoginEvent stale=new PlayerLoginEvent(player,"localhost",ip);listeners.login(stale);
        check(stale.getResult()!=PlayerLoginEvent.Result.ALLOWED,"expired pre-login token");
        admitted.put(id,System.currentTimeMillis());
        PlayerLoginEvent fresh=new PlayerLoginEvent(player,"localhost",ip);listeners.login(fresh);
        check(fresh.getResult()==PlayerLoginEvent.Result.ALLOWED,"fresh verified token");
        PlayerLoginEvent replay=new PlayerLoginEvent(player,"localhost",ip);listeners.login(replay);
        check(replay.getResult()!=PlayerLoginEvent.Result.ALLOWED,"token reused");
        System.out.println(count+" admission checks passed against native Bukkit API");
    }
}
