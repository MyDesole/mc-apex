package ru.mcapex.tiertests;
import org.bukkit.Bukkit;
import org.bukkit.event.*;
import org.bukkit.event.player.AsyncPlayerPreLoginEvent;
import org.bukkit.event.player.PlayerLoginEvent;
import org.bukkit.plugin.Plugin;
import org.bukkit.plugin.java.JavaPlugin;

/** Independent fail-closed guard if the required authentication plugin is missing. */
public final class AdmissionGuard implements Listener {
    public static void register(JavaPlugin owner) { Bukkit.getPluginManager().registerEvents(new AdmissionGuard(), owner); }
    private boolean available() { Plugin p=Bukkit.getPluginManager().getPlugin("ApexAuth");return p!=null&&p.isEnabled(); }
    @EventHandler(priority=EventPriority.HIGHEST) public void pre(AsyncPlayerPreLoginEvent e) {
        if(!available())e.disallow(AsyncPlayerPreLoginEvent.Result.KICK_OTHER,"Авторизация временно недоступна. Попробуй позже.");
    }
    @EventHandler(priority=EventPriority.HIGHEST) public void login(PlayerLoginEvent e) {
        if(!available())e.disallow(PlayerLoginEvent.Result.KICK_OTHER,"Авторизация временно недоступна. Попробуй позже.");
    }
}
