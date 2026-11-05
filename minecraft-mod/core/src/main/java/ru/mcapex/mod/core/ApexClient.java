package ru.mcapex.mod.core;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.ArrayList;
import java.util.Collection;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.ThreadFactory;

/**
 * Клиент публичного API сайта. Все запросы идут в фоне, из игрового потока
 * вызываются только неблокирующие методы с кэшем.
 */
public final class ApexClient {
    public interface Callback<T> {
        /** Вызывается в фоновом потоке; value == null: игрока нет, error != null: сбой сети. */
        void done(T value, String error);
    }

    private static final long TTL_MS = 60_000L;
    private static final long ERROR_BACKOFF_MS = 30_000L;
    private static final int BATCH_LIMIT = 60;

    private static final class Entry {
        final Badge badge;
        final long at;

        Entry(Badge badge) {
            this.badge = badge;
            this.at = System.currentTimeMillis();
        }

        boolean fresh() {
            return System.currentTimeMillis() - at < TTL_MS;
        }
    }

    private final String baseUrl;
    private final Map<String, Entry> badges = new ConcurrentHashMap<String, Entry>();
    private final Set<String> pending = java.util.Collections.newSetFromMap(new ConcurrentHashMap<String, Boolean>());
    private volatile long backoffUntil;
    private final ExecutorService pool = Executors.newFixedThreadPool(2, new ThreadFactory() {
        public Thread newThread(Runnable r) {
            Thread t = new Thread(r, "apex-mod-http");
            t.setDaemon(true);
            return t;
        }
    });

    public ApexClient(String baseUrl) {
        this.baseUrl = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;
    }

    /** Значок из кэша; null, если игрока нет или ещё не загружен. */
    public Badge cachedBadge(String nick) {
        Entry e = badges.get(key(nick));
        return e == null ? null : e.badge;
    }

    /** Ставит в очередь ники, которых нет в кэше или он устарел; запрос уйдёт одним пакетом. */
    public void request(Collection<String> nicks) {
        if (System.currentTimeMillis() < backoffUntil) {
            return;
        }
        final List<String> need = new ArrayList<String>();
        for (String nick : nicks) {
            String k = key(nick);
            Entry e = badges.get(k);
            if ((e == null || !e.fresh()) && pending.add(k)) {
                need.add(nick);
                if (need.size() >= BATCH_LIMIT) {
                    break;
                }
            }
        }
        if (need.isEmpty()) {
            return;
        }
        pool.execute(new Runnable() {
            public void run() {
                try {
                    fetchBatch(need);
                } catch (Exception ex) {
                    backoffUntil = System.currentTimeMillis() + ERROR_BACKOFF_MS;
                } finally {
                    for (String n : need) {
                        pending.remove(key(n));
                    }
                }
            }
        });
    }

    private void fetchBatch(List<String> nicks) throws Exception {
        JsonArray arr = new JsonArray();
        for (String n : nicks) {
            arr.add(new com.google.gson.JsonPrimitive(n));
        }
        JsonObject body = new JsonObject();
        body.add("nicks", arr);
        String json = http("POST", "/tiers", body.toString());

        Set<String> found = new HashSet<String>();
        JsonElement players = new JsonParser().parse(json).getAsJsonObject().get("players");
        if (players != null && players.isJsonArray()) {
            for (JsonElement el : players.getAsJsonArray()) {
                JsonObject p = el.getAsJsonObject();
                Badge b = Badge.parse(p.getAsJsonObject("badge"));
                String nick = PlayerInfo.str(p, "nick");
                if (nick != null) {
                    badges.put(key(nick), new Entry(b != null && b.hasLabel() ? b : null));
                    found.add(key(nick));
                }
            }
        }
        for (String n : nicks) {
            if (!found.contains(key(n))) {
                badges.put(key(n), new Entry(null));
            }
        }
    }

    /** Полная карточка по нику Minecraft или нику на сайте. */
    public void lookup(final String query, final Callback<PlayerInfo> cb) {
        pool.execute(new Runnable() {
            public void run() {
                try {
                    String json = http("GET", "/player/" + URLEncoder.encode(query, "UTF-8").replace("+", "%20"), null);
                    JsonObject p = new JsonParser().parse(json).getAsJsonObject().getAsJsonObject("player");
                    cb.done(PlayerInfo.parse(p), null);
                } catch (NotFound nf) {
                    cb.done(null, null);
                } catch (Exception ex) {
                    cb.done(null, ex.getMessage() == null ? ex.getClass().getSimpleName() : ex.getMessage());
                }
            }
        });
    }

    private static final class NotFound extends Exception {
    }

    private String http(String method, String path, String body) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(baseUrl + path).openConnection();
        c.setRequestMethod(method);
        c.setConnectTimeout(5000);
        c.setReadTimeout(7000);
        c.setRequestProperty("Accept", "application/json");
        c.setRequestProperty("User-Agent", "ApexMod/1.0");
        if (body != null) {
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json");
            OutputStream out = c.getOutputStream();
            out.write(body.getBytes("UTF-8"));
            out.close();
        }
        int code = c.getResponseCode();
        if (code == 404) {
            throw new NotFound();
        }
        if (code == 429) {
            backoffUntil = System.currentTimeMillis() + ERROR_BACKOFF_MS;
            throw new Exception("слишком много запросов, повторите позже");
        }
        if (code >= 400) {
            throw new Exception("сайт ответил кодом " + code);
        }
        InputStream in = c.getInputStream();
        ByteArrayOutputStream buf = new ByteArrayOutputStream();
        byte[] chunk = new byte[4096];
        int n;
        while ((n = in.read(chunk)) > 0) {
            buf.write(chunk, 0, n);
        }
        in.close();
        return buf.toString("UTF-8");
    }

    static String key(String nick) {
        return nick.toLowerCase(Locale.ROOT);
    }

    /** Для тестов и отладки. */
    Map<String, Entry> cacheView() {
        return new HashMap<String, Entry>(badges);
    }
}
