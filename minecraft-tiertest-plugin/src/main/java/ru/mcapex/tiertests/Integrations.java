package ru.mcapex.tiertests;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.ProfileService;
import dev.revere.alley.core.profile.enums.ProfileState;
import dev.revere.alley.feature.match.Match;
import dev.revere.alley.feature.match.MatchService;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
import dev.revere.alley.feature.match.model.GameParticipant;
import dev.revere.alley.feature.match.model.internal.MatchGamePlayer;
import org.bukkit.Bukkit;
import org.bukkit.GameMode;
import org.bukkit.entity.Player;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentLinkedQueue;

/**
 * Связь сервера с сайтом: итоги дуэлей Alley, онлайн, выдача лобби-покупок
 * (команды LuckPerms из очереди сайта) и полёт в лобби по праву apex.lobby.fly.
 */
final class Integrations {
    private static final String FLY_PERMISSION = "apex.lobby.fly";

    private final ApexTierTests plugin;
    private final Map<Match, Tracked> tracked = new HashMap<Match, Tracked>();
    private final ConcurrentLinkedQueue<JsonObject> unsent = new ConcurrentLinkedQueue<JsonObject>();
    private boolean grantsBusy;
    private int tick;

    /** Матч 1 на 1, за которым следим до его конца. */
    private static final class Tracked {
        String key = UUID.randomUUID().toString();
        String kit;
        String a;
        String b;
        UUID aId;
        UUID bId;
        boolean ranked;
        long startedAt = System.currentTimeMillis();
        String winner;
        String loser;
    }

    Integrations(ApexTierTests plugin) {
        this.plugin = plugin;
    }

    void start() {
        Bukkit.getScheduler().runTaskTimer(plugin, new Runnable() {
            public void run() {
                tick++;
                try {
                    scanMatches();
                    if (tick % 2 == 0) syncFly();
                    if (tick % 30 == 0) sendPresence();
                    if (tick % 15 == 0) fetchGrants();
                    if (tick % 30 == 0) flushUnsent();
                } catch (Throwable t) {
                    plugin.getLogger().warning("Интеграции: " + t);
                }
            }
        }, 100L, 20L);
    }

    /* ---------- Дуэли ---------- */

    private void scanMatches() {
        List<Match> live = AlleyPlugin.getInstance().getService(MatchService.class).getMatches();

        for (Match m : live) {
            if (!(m instanceof DefaultMatch)) continue;
            List<GameParticipant<MatchGamePlayer>> parts = m.getParticipants();
            if (parts.size() != 2 || parts.get(0).getPlayerSize() != 1 || parts.get(1).getPlayerSize() != 1) continue;

            Tracked t = tracked.get(m);
            if (t == null) {
                t = new Tracked();
                MatchGamePlayer pa = parts.get(0).getLeader();
                MatchGamePlayer pb = parts.get(1).getLeader();
                t.kit = m.getKit().getName();
                t.a = pa.getUsername();
                t.b = pb.getUsername();
                t.aId = pa.getUuid();
                t.bId = pb.getUuid();
                t.ranked = m.isRanked();
                tracked.put(m, t);
            }

            GameParticipant<MatchGamePlayer> w = ((DefaultMatch) m).getWinner();
            if (w != null) {
                boolean aWon = w.containsPlayer(t.aId);
                t.winner = aWon ? t.a : t.b;
                t.loser = aWon ? t.b : t.a;
            }
        }

        for (Iterator<Map.Entry<Match, Tracked>> it = tracked.entrySet().iterator(); it.hasNext(); ) {
            Map.Entry<Match, Tracked> e = it.next();
            if (live.contains(e.getKey())) continue;
            it.remove();
            Tracked t = e.getValue();
            if (t.winner == null) continue; // ничья или матч отменён - в историю не идёт
            JsonObject body = new JsonObject();
            body.addProperty("match_key", t.key);
            body.addProperty("kit", t.kit);
            body.addProperty("winner", t.winner);
            body.addProperty("loser", t.loser);
            body.addProperty("duration_seconds", (int) ((System.currentTimeMillis() - t.startedAt) / 1000L));
            body.addProperty("ranked", t.ranked);
            postDuel(body);
        }
    }

    private void postDuel(final JsonObject body) {
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    plugin.api().post("/duels", body);
                } catch (Exception ex) {
                    // match_key делает повторную отправку безопасной
                    unsent.add(body);
                }
            }
        });
    }

    private void flushUnsent() {
        JsonObject body;
        while ((body = unsent.poll()) != null) postDuel(body);
    }

    /* ---------- Онлайн ---------- */

    private void sendPresence() {
        final JsonArray names = new JsonArray();
        for (Player p : Bukkit.getOnlinePlayers()) names.add(new com.google.gson.JsonPrimitive(p.getName()));
        final JsonObject body = new JsonObject();
        body.add("players", names);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    plugin.api().post("/presence", body);
                } catch (Exception ignored) {
                    // следующая отправка через полминуты
                }
            }
        });
    }

    /* ---------- Выдача покупок ---------- */

    private void fetchGrants() {
        if (grantsBusy) return;
        grantsBusy = true;
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            public void run() {
                try {
                    JsonArray list = plugin.api().get("/grants").getAsJsonArray("grants");
                    for (JsonElement el : list) apply(el.getAsJsonObject());
                } catch (Exception ignored) {
                    // сайт недоступен - повторим на следующем круге
                } finally {
                    grantsBusy = false;
                }
            }
        });
    }

    private void apply(final JsonObject grant) throws Exception {
        final int id = grant.get("id").getAsInt();
        final List<String> commands = new ArrayList<String>();
        for (JsonElement c : grant.getAsJsonArray("commands")) commands.add(c.getAsString());
        final String player = grant.get("player").getAsString();

        // Команды выполняются в основном потоке; ждём результат, чтобы честно подтвердить
        final boolean[] ok = {true};
        final String[] error = {null};
        Bukkit.getScheduler().callSyncMethod(plugin, new java.util.concurrent.Callable<Void>() {
            public Void call() {
                for (String cmd : commands) {
                    // выполняем только команды LuckPerms
                    if (!cmd.startsWith("lp ") || !Bukkit.dispatchCommand(Bukkit.getConsoleSender(), cmd)) {
                        ok[0] = false;
                        error[0] = "команда не выполнена: " + cmd;
                        break;
                    }
                }
                return null;
            }
        }).get();

        JsonObject ack = new JsonObject();
        ack.addProperty("ok", ok[0]);
        if (error[0] != null) ack.addProperty("error", error[0]);
        plugin.api().post("/grants/" + id + "/ack", ack);
        plugin.getLogger().info("Выдача #" + id + " для " + player + ": " + (ok[0] ? "готово" : error[0]));
    }

    /* ---------- Полёт в лобби ---------- */

    private void syncFly() {
        ProfileService profiles = AlleyPlugin.getInstance().getService(ProfileService.class);
        for (Player p : Bukkit.getOnlinePlayers()) {
            if (!p.hasPermission(FLY_PERMISSION)) continue;
            GameMode gm = p.getGameMode();
            if (gm == GameMode.CREATIVE || gm == GameMode.SPECTATOR) continue;
            Profile pr = profiles.getProfile(p.getUniqueId());
            boolean inLobby = pr != null && pr.getState() == ProfileState.LOBBY;
            if (p.getAllowFlight() != inLobby) {
                p.setAllowFlight(inLobby);
                if (!inLobby) p.setFlying(false);
            }
        }
    }
}
