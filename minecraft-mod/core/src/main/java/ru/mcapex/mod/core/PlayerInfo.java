package ru.mcapex.mod.core;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;

import java.util.ArrayList;
import java.util.List;

/** Карточка игрока из GET /api/mod/player/{query}. Без зависимостей от Minecraft. */
public final class PlayerInfo {
    public static final class Aspect {
        public final String label;
        public final double value;

        Aspect(String label, double value) {
            this.label = label;
            this.value = value;
        }
    }

    public static final class AspectGroup {
        public String mode;
        public double percent;
        public final List<Aspect> items = new ArrayList<Aspect>();
    }

    public static final class Technique {
        public String label;
        public int stability, speed, difficulty;
    }

    public String username;
    public String minecraftUsername;
    public String tier;
    public double tierScore;
    public Badge badge;
    public String profileUrl;
    public boolean visible;
    public final List<AspectGroup> aspects = new ArrayList<AspectGroup>();
    public int bridgeCount = -1;
    public int bridgeAspectsTotal;
    public final List<Technique> techniques = new ArrayList<Technique>();
    public String clanName, clanTag, clanRole, clanTitle;
    public int friendsCount = -1;
    public int achievementsCount = -1;

    static String str(JsonObject o, String key) {
        JsonElement e = o.get(key);
        return e == null || e.isJsonNull() ? null : e.getAsString();
    }

    static int num(JsonObject o, String key, int def) {
        JsonElement e = o.get(key);
        return e == null || e.isJsonNull() ? def : e.getAsInt();
    }

    static JsonObject obj(JsonObject o, String key) {
        JsonElement e = o.get(key);
        return e != null && e.isJsonObject() ? e.getAsJsonObject() : null;
    }

    public static PlayerInfo parse(JsonObject p) {
        PlayerInfo r = new PlayerInfo();
        r.username = str(p, "username");
        r.minecraftUsername = str(p, "minecraft_username");
        r.tier = str(p, "tier");
        r.tierScore = p.has("tier_score") && !p.get("tier_score").isJsonNull() ? p.get("tier_score").getAsDouble() : 0;
        r.badge = Badge.parse(obj(p, "badge"));
        r.profileUrl = str(p, "profile_url");
        r.visible = p.has("visible") && p.get("visible").getAsBoolean();

        JsonElement aspects = p.get("aspects");
        if (aspects != null && aspects.isJsonArray()) {
            for (JsonElement el : aspects.getAsJsonArray()) {
                JsonObject g = el.getAsJsonObject();
                AspectGroup group = new AspectGroup();
                group.mode = str(g, "mode");
                group.percent = g.get("percent").getAsDouble();
                for (JsonElement it : g.getAsJsonArray("items")) {
                    JsonObject i = it.getAsJsonObject();
                    group.items.add(new Aspect(str(i, "label"), i.get("value").getAsDouble()));
                }
                r.aspects.add(group);
            }
        }

        JsonObject bridge = obj(p, "bridge");
        if (bridge != null) {
            r.bridgeCount = num(bridge, "confirmed_count", 0);
            r.bridgeAspectsTotal = num(bridge, "aspects_total", 0);
            JsonArray list = bridge.getAsJsonArray("techniques");
            if (list != null) {
                for (JsonElement el : list) {
                    JsonObject t = el.getAsJsonObject();
                    Technique tech = new Technique();
                    tech.label = str(t, "label");
                    tech.stability = num(t, "stability", 0);
                    tech.speed = num(t, "speed", 0);
                    tech.difficulty = num(t, "difficulty", 0);
                    r.techniques.add(tech);
                }
            }
        }

        JsonObject clan = obj(p, "clan");
        if (clan != null) {
            r.clanName = str(clan, "name");
            r.clanTag = str(clan, "tag");
            r.clanRole = str(clan, "role");
            r.clanTitle = str(clan, "title");
        }
        r.friendsCount = num(p, "friends_count", -1);
        r.achievementsCount = num(p, "achievements_count", -1);
        return r;
    }
}
