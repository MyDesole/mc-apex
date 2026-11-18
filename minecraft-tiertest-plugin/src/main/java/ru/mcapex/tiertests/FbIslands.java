package ru.mcapex.tiertests;

import java.io.*;
import java.util.*;
import org.bukkit.World;
import org.bukkit.Material;
import org.bukkit.Chunk;
import org.bukkit.craftbukkit.v1_8_R3.CraftChunk;
import net.minecraft.server.v1_8_R3.ChunkSection;
import org.bukkit.block.Block;
import org.bukkit.plugin.java.JavaPlugin;

/** Cropped/rotated Akina islands. No builder world, ground, entities or playerdata. */
final class FbIslands {
    static final String AKINA = "akina-v1";
    static final String LEGACY = "legacy";
    private final List<int[]> start = new ArrayList<int[]>(), finish = new ArrayList<int[]>();
    private final JavaPlugin plugin;
    private final String[] installed = new String[FastBridge.LANES];

    FbIslands(JavaPlugin plugin) {
        this.plugin = plugin;
        try (InputStream in = plugin.getResource("fastbridge/akina-v1.blocks")) {
            if (in == null) throw new IOException("Missing Akina island resource");
            BufferedReader reader = new BufferedReader(new InputStreamReader(in, "UTF-8"));
            String line;
            while ((line = reader.readLine()) != null) {
                if (line.isEmpty() || line.startsWith("#")) continue;
                String[] v = line.split(" ");
                int[] b = new int[5];
                for (int i = 0; i < b.length; i++) b[i] = Integer.parseInt(v[i + 1]);
                (v[0].equals("S") ? start : finish).add(b);
            }
            if (start.size() != 234 || finish.size() != 254) throw new IOException("Incomplete Akina template");
        } catch (IOException ex) { throw new IllegalStateException("FastBridge island resource cannot be loaded", ex); }
    }

    void build(World world, int lane, FbMap map) {
        String key = AKINA + ":" + map.id;
        if (key.equals(installed[lane])) return;
        int z = lane * FastBridge.SPACING;
        // Remove exact footprints for every supported layout. Also repairs persisted
        // previous-map islands on startup; never clears the entire world or player data.
        paste(world, start, 0, z, true);
        for (int gap : new int[]{16, 24, 32, 64}) paste(world, finish, 5 + gap, z, true);
        for (int x = 0; x < 76; x++) for (int dz = -2; dz <= 2; dz++)
            put(world, x, FastBridge.FLOOR_Y, z + dz, 0, 0);
        paste(world, start, 0, z, false);
        if (!map.endless()) paste(world, finish, 5 + map.gap, z, false);
        installed[lane] = key;
    }

    /** All saved chunks, including old platforms outside today's lane coordinates. */
    int removeLegacyPlatforms(World world) {
        Set<Long> chunks = new LinkedHashSet<Long>();
        for (Chunk c : world.getLoadedChunks()) chunks.add(chunkKey(c.getX(), c.getZ()));
        File[] files = new File(world.getWorldFolder(), "region").listFiles();
        if (files != null) for (File file : files) {
            String[] name = file.getName().split("\\.");
            if (name.length != 4 || !name[0].equals("r") || !name[3].equals("mca")) continue;
            int rx = Integer.parseInt(name[1]), rz = Integer.parseInt(name[2]);
            try (RandomAccessFile region = new RandomAccessFile(file, "r")) {
                if (region.length() < 4096) throw new IOException("Truncated region header: " + file);
                for (int i = 0; i < 1024; i++) if ((region.readInt() >>> 8) != 0)
                    chunks.add(chunkKey(rx * 32 + i % 32, rz * 32 + i / 32));
            } catch (IOException ex) { throw new IllegalStateException("Cannot inspect FastBridge region", ex); }
        }
        int removed = 0;
        for (long key : chunks) {
            int cx = (int) (key >> 32), cz = (int) key;
            Chunk c = world.getChunkAt(cx, cz);
            for (ChunkSection section : ((CraftChunk)c).getHandle().getSections()) {
                if (section == null) continue;
                char[] states = section.getIdArray();
                for (int i = 0; i < states.length; i++) {
                    if (states[i] == 0) continue;
                    int x = i & 15, y = i >> 8, z = (i >> 4) & 15;
                    int id = net.minecraft.server.v1_8_R3.Block.getId(section.getType(x, y, z).getBlock());
                    if (id == Material.QUARTZ_BLOCK.getId() || id == Material.EMERALD_BLOCK.getId()) {
                        world.getBlockAt(cx * 16 + x, section.getYPosition() + y, cz * 16 + z).setType(Material.AIR, false);
                        removed++;
                    }
                }
            }
        }
        plugin.getLogger().info("FastBridge cleanup: removed " + removed + " legacy quartz/emerald blocks in " + chunks.size() + " chunks");
        return removed;
    }
    private long chunkKey(int x, int z) { return ((long)x << 32) | (z & 0xffffffffL); }

    boolean finishPlate(FbMap map, int x, int y, int z) {
        if (map.endless()) return false;
        for (int[] b : finish) if (b[3] == Material.GOLD_PLATE.getId()
                && b[0] + 5 + map.gap == x && b[1] == y && b[2] == z) return true;
        return false;
    }

    boolean protectedBlock(FbMap map, int x, int y, int z) {
        return contains(start, x, y, z) || !map.endless() && contains(finish, x - 5 - map.gap, y, z);
    }
    private boolean contains(List<int[]> cells, int x, int y, int z) {
        for (int[] b : cells) if (b[0] == x && b[1] == y && b[2] == z) return true;
        return false;
    }
    private void paste(World world, List<int[]> cells, int x, int z, boolean clear) {
        for (int[] b : cells) put(world, x + b[0], FastBridge.FLOOR_Y + b[1], z + b[2], clear ? 0 : b[3], clear ? 0 : b[4]);
    }
    private void put(World world, int x, int y, int z, int id, int data) {
        Block b = world.getBlockAt(x, y, z);
        if (b.getTypeId() != id || b.getData() != (byte) data) b.setTypeIdAndData(id, (byte) data, false);
    }
}
