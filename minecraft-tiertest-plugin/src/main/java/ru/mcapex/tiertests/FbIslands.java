package ru.mcapex.tiertests;

import java.io.*;
import java.util.*;
import org.bukkit.World;
import org.bukkit.Material;
import org.bukkit.block.Block;
import org.bukkit.plugin.java.JavaPlugin;

/** Cropped/rotated Akina islands. No builder world, ground, entities or playerdata. */
final class FbIslands {
    static final String AKINA = "akina-v1";
    static final String LEGACY = "legacy";
    private final List<int[]> start = new ArrayList<int[]>(), finish = new ArrayList<int[]>();
    private final String[] installed = new String[FastBridge.LANES];

    FbIslands(JavaPlugin plugin) {
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

    void build(World world, int lane, FbMap map, String layout) {
        boolean modern = AKINA.equals(layout);
        String key = (modern ? AKINA : LEGACY) + ":" + map.id;
        if (key.equals(installed[lane])) return;
        int z = lane * FastBridge.SPACING;
        // Remove exact footprints for every supported layout. Also repairs persisted
        // previous-map islands on startup; never clears the entire world or player data.
        paste(world, start, 0, z, true);
        for (int gap : new int[]{16, 24, 32, 64}) paste(world, finish, 5 + gap, z, true);
        for (int x = 0; x < 76; x++) for (int dz = -2; dz <= 2; dz++)
            put(world, x, FastBridge.FLOOR_Y, z + dz, 0, 0);
        if (modern) {
            paste(world, start, 0, z, false);
            if (!map.endless()) paste(world, finish, 5 + map.gap, z, false);
        } else {
            for (int x = 0; x < 5; x++) for (int dz = -2; dz <= 2; dz++) {
                put(world, x, FastBridge.FLOOR_Y, z + dz, Material.QUARTZ_BLOCK.getId(), 0);
                if (!map.endless()) put(world, 5 + map.gap + x, FastBridge.FLOOR_Y, z + dz, Material.EMERALD_BLOCK.getId(), 0);
            }
        }
        installed[lane] = key;
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
