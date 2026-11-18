package ru.mcapex.tiertests;

import net.minecraft.server.v1_8_R3.PacketPlayOutCamera;
import org.bukkit.craftbukkit.v1_8_R3.entity.CraftPlayer;
import org.bukkit.entity.Player;

/** Native camera packet: the replay ghost exists only on this viewer's client. */
final class FbCamera {
    static PacketPlayOutCamera packet(int entityId) {
        PacketPlayOutCamera packet = new PacketPlayOutCamera();
        packet.a = entityId;
        return packet;
    }
    static void attach(Player viewer, int entityId) {
        ((CraftPlayer) viewer).getHandle().playerConnection.sendPacket(packet(entityId));
    }
    static void reset(Player viewer) { attach(viewer, viewer.getEntityId()); }
}
