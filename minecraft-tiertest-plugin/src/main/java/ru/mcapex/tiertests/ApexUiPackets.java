package ru.mcapex.tiertests;

import com.comphenix.protocol.ProtocolLibrary;
import com.comphenix.protocol.PacketType;
import com.comphenix.protocol.events.*;
import com.comphenix.protocol.wrappers.WrappedChatComponent;
import org.bukkit.plugin.java.JavaPlugin;
import java.util.*;

/** Client-only window captions: server titles used by existing click handlers never change. */
final class ApexUiPackets {
    private final JavaPlugin plugin;
    private final Map<UUID,String> titles;
    private PacketListener listener;
    ApexUiPackets(JavaPlugin plugin,Map<UUID,String> titles){this.plugin=plugin;this.titles=titles;}
    void start(){
        listener=new PacketAdapter(plugin,ListenerPriority.NORMAL,PacketType.Play.Server.OPEN_WINDOW){
            @Override public void onPacketSending(PacketEvent e){
                String title=titles.get(e.getPlayer().getUniqueId());if(title==null)return;
                PacketContainer copy=e.getPacket().shallowClone();
                if(copy.getChatComponents().size()==0)return;
                copy.getChatComponents().write(0,WrappedChatComponent.fromText(title));e.setPacket(copy);
            }
        };
        ProtocolLibrary.getProtocolManager().addPacketListener(listener);
        plugin.getLogger().info("ApexUi: ProtocolLib client window titles enabled");
    }
    void stop(){if(listener!=null)ProtocolLibrary.getProtocolManager().removePacketListener(listener);}
}
