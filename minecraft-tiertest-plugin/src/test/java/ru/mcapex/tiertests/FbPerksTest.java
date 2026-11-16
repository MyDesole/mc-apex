package ru.mcapex.tiertests;

import java.io.File;
import java.nio.file.Files;
import java.util.*;
import java.util.logging.Logger;
import org.bukkit.configuration.file.YamlConfiguration;

/** Run with assertions enabled; real Bukkit YAML and real temporary replay files. */
public final class FbPerksTest {
    private static int checks;
    private static void check(boolean value, String message) {
        checks++; if (!value) throw new AssertionError(message);
    }
    private static File touch(File directory, String name) throws Exception {
        File f = new File(directory, name + ".json"); Files.write(f.toPath(), new byte[]{1}); return f;
    }

    public static void main(String[] args) throws Exception {
        File directory = Files.createTempDirectory("fb-perks-test-").toFile();
        Logger log = Logger.getLogger("fb-test"); UUID u = UUID.randomUUID(), other = UUID.randomUUID();
        FbPerks p = new FbPerks(directory, log);
        check(p.limit(0, "replays") == 40 && p.limit(5, "replays") == 400, "replay quotas");
        check(!p.privateAllowed(3) && p.privateAllowed(4), "private rank gate");
        check(!p.savePreset(u, "bad.name", "32", 1), "YAML path injection rejected");
        check(!p.savePreset(u, "basic", "32", 0), "default cannot save donor presets");
        p.putSetting(u, "block", "wool"); p.putSetting(u, "effect", "hearts"); p.putSetting(u, "auto", "off");
        check(p.savePreset(u, "one", "16", 1), "first preset");
        check(p.savePreset(u, "two", "64", 1), "second preset");
        check(!p.savePreset(u, "three", "32", 1), "preset quota");
        check(p.savePreset(u, "one", "24", 1), "overwrite does not consume slot");
        check(p.preset(u, "one", 1).get("block").equals("wool"), "preset captures material");
        check(p.preset(u, "one", 0) == null, "expired rank cannot load preset");
        p.flush(); p = new FbPerks(directory, log);
        check(p.preset(u, "one", 1).get("map").equals("24"), "preset persists restart");
        for (int i=0;i<5;i++) check(p.toggleFavorite(u, "favorite" + i, 1), "favorite slot " + i);
        check(!p.toggleFavorite(u, "over-limit", 1), "favorite limit");
        p = new FbPerks(directory, log);
        check(p.favorite(u, "favorite0"), "favorite acknowledgement is durable");
        check(p.toggleFavorite(u, "favorite0", 0), "downgraded user can remove existing favorite");
        check(!p.toggleFavorite(u, "new", 0), "downgraded user cannot add favorite");
        File favorite = touch(directory, "favorite1");
        check(!p.deleteReplay(favorite, Collections.<String>emptySet(), 1000, 0, System.currentTimeMillis()), "favorite protected from deletion");
        File record = touch(directory, "record");
        check(!p.deleteReplay(record, Collections.singleton("record"), 1000, 0, System.currentTimeMillis()), "record protected");
        File old = touch(directory, "ordinary");
        check(p.deleteReplay(old, Collections.<String>emptySet(), 40, 0, System.currentTimeMillis()), "ordinary count pruning");
        File legacy = touch(directory, "legacy"); legacy.setLastModified(1);
        check(!p.deleteReplay(legacy, Collections.<String>emptySet(), 0, 0, System.currentTimeMillis()), "old archive is not newly TTL-expired at rollout");
        File expired = touch(directory, "expired");
        check(p.deleteReplay(expired, Collections.<String>emptySet(), 0, 0, System.currentTimeMillis()+8*86400000L), "new files expire at retention age");
        p.markPrivate("private", u); p.flush(); p = new FbPerks(directory, log);
        check(p.mayWatch("private", u) && !p.mayWatch("private", other), "private ACL persists restart");
        check(p.mayWatch("public", other), "public replay remains accessible");
        for (int i=0;i<55;i++) {
            Map<String,Object> entry = new HashMap<String,Object>(); entry.put("at", i); entry.put("outcome", "fall"); p.history(u,0,entry);
        }
        p.flush(); p = new FbPerks(directory,log);
        check(p.history(u).size()==50 && ((Number)p.history(u).get(0).get("at")).intValue()==54, "bounded newest-first history survives restart");
        Replay r = new Replay(); r.id="20261116-210000123_Test_32_2000"; r.uuid=u.toString(); r.player="Test"; r.map="32"; r.personal=true; r.material="WOOL";
        r.frames=new double[][]{{0,2.5,1,0.5,179,0,0},{100,3.5,1,0.5,-179,0,0}};
        r.blocks=new int[][]{{10,5,0,0,35,0,1}}; r.clicks=new int[][]{{10,2},{20,2}};
        File replay=new File(directory,r.id+".json"); r.write(replay); Replay read=Replay.read(replay);
        check(read.personal && read.material.equals("WOOL") && read.uuid.equals(u.toString()), "v3 private/material roundtrip");
        check(Math.abs(read.poseAt(50)[3]-180)<0.01, "yaw interpolation crosses boundary safely");
        check(Replay.parseName(replay.getName())!=null, "millisecond filename supported");
        check(Replay.parseName("20261116-210000_Test_With_Underscore_32_2000.json")[1].equals("Test_With_Underscore"), "old names and underscores supported");
        com.google.gson.JsonObject v1=new com.google.gson.JsonParser().parse("{\"id\":\"legacy\",\"time_ms\":2000,\"frames\":[],\"blocks\":[]}").getAsJsonObject();
        Replay compatible=Replay.fromJson(v1);
        check(!compatible.personal && compatible.material.equals("SANDSTONE") && compatible.map.equals("32") && compatible.value==2000, "v1 replay compatibility");
        File cfg=new File(directory,"fastbridge-perks.yml"); YamlConfiguration y=YamlConfiguration.loadConfiguration(cfg);
        y.set("limits.vip.presets",3); y.save(cfg); p=new FbPerks(directory,log);
        check(p.limit(1,"presets")==3,"server quotas configurable");
        System.out.println("PASS: " + checks + " storage, access, retention and replay compatibility checks");
    }
}
