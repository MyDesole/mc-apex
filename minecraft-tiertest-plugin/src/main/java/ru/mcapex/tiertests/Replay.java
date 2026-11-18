package ru.mcapex.tiertests;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.google.gson.JsonPrimitive;

import java.io.File;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Запись одной попытки: позиции игрока и каждый поставленный или сломанный блок.
 * Координаты относительны дорожки (x от начала островка, y от его уровня, z от оси дорожки),
 * поэтому запись можно проиграть на любой дорожке. Здесь только данные и математика, без Bukkit.
 */
final class Replay {
    /** Имя файла: дата_ник_карта_результат.json. Ник может содержать подчёркивания, поэтому разбираем с обоих концов. */
    static final Pattern FILE = Pattern.compile("^(\\d{8}-\\d{6}(?:\\d{3})?)_(.+?)(?:_(16|24|32|64|inf))?_(\\d+)\\.json$");

    String id;
    String player;
    String uuid;
    String map;
    /** Результат: время в мс для конечных карт, дистанция в блоках для бесконечной. */
    long value;
    long created;
    boolean personal;
    String material = "SANDSTONE";
    /** Missing in old files: replay those on the original quartz platforms. */
    String layout = "legacy";
    /** Когда пошёл таймер (мс от начала записи). */
    long timerStartMs;
    /** t, x, y, z, yaw, pitch, флаги (см. SNEAK, SPRINT, KEY_*, AIR). */
    double[][] frames = new double[0][];
    /** t, x, y, z, id, data, 1 - поставил / 0 - сломал. */
    int[][] blocks = new int[0][];
    /** t, тип клика (1 - ЛКМ, 2 - ПКМ). */
    int[][] clicks = new int[0][];

    /** Биты флагов кадра. */
    static final int SNEAK = 1, SPRINT = 2, KEY_W = 4, KEY_A = 8, KEY_S = 16, KEY_D = 32, AIR = 64;

    /** Кликов указанного типа за секунду, заканчивающуюся в момент t. */
    int cps(double t, int type) {
        int n = 0;
        for (int[] c : clicks) if (c[1] == type && c[0] <= t && c[0] > t - 1000) n++;
        return n;
    }

    int total(int type) {
        int n = 0;
        for (int[] c : clicks) if (c[1] == type) n++;
        return n;
    }

    /** Максимум кликов за любую секунду. */
    int maxCps(int type) {
        int best = 0, lo = 0, count = 0;
        for (int i = 0; i < clicks.length; i++) {
            while (lo < i && clicks[lo][0] <= clicks[i][0] - 1000) {
                if (clicks[lo++][1] == type) count--;
            }
            if (clicks[i][1] == type) count++;
            best = Math.max(best, count);
        }
        return best;
    }

    /** Среднее число кликов в секунду от первого клика до последнего. */
    double avgCps(int type) {
        int first = -1, last = -1, n = 0;
        for (int[] c : clicks) {
            if (c[1] != type) continue;
            if (first < 0) first = c[0];
            last = c[0];
            n++;
        }
        return n < 2 || last <= first ? n : n * 1000.0 / (last - first);
    }

    long duration() {
        return frames.length == 0 ? 0 : (long) frames[frames.length - 1][0];
    }

    /** Поза в момент t (мс от начала записи): x, y, z, yaw, pitch, флаги. */
    double[] poseAt(double t) {
        int n = frames.length;
        if (n == 0) return new double[]{2.5, 1, 0.5, -90, 0, 0};
        if (t <= frames[0][0]) return pose(frames[0]);
        if (t >= frames[n - 1][0]) return pose(frames[n - 1]);
        int lo = 0, hi = n - 1;
        while (hi - lo > 1) {
            int mid = (lo + hi) >>> 1;
            if (frames[mid][0] <= t) lo = mid;
            else hi = mid;
        }
        double[] a = frames[lo], b = frames[hi];
        double span = b[0] - a[0];
        double k = span > 0 ? (t - a[0]) / span : 0;
        double dyaw = ((b[4] - a[4] + 540) % 360) - 180;
        return new double[]{
                a[1] + (b[1] - a[1]) * k,
                a[2] + (b[2] - a[2]) * k,
                a[3] + (b[3] - a[3]) * k,
                a[4] + dyaw * k,
                a[5] + (b[5] - a[5]) * k,
                a[6]};
    }

    private static double[] pose(double[] f) {
        return new double[]{f[1], f[2], f[3], f[4], f[5], f[6]};
    }

    /* ----------------------------- файл ----------------------------- */

    JsonObject toJson() {
        JsonObject o = new JsonObject();
        o.addProperty("v", 4);
        o.addProperty("layout", layout);
        o.addProperty("private", personal);
        o.addProperty("material", material);
        o.addProperty("id", id);
        o.addProperty("player", player);
        o.addProperty("uuid", uuid);
        o.addProperty("map", map);
        o.addProperty("value", value);
        o.addProperty("created", created);
        o.addProperty("timer_start_ms", timerStartMs);
        JsonArray fr = new JsonArray();
        for (double[] f : frames) {
            JsonArray a = new JsonArray();
            for (double v : f) a.add(new JsonPrimitive(Math.round(v * 100) / 100.0));
            fr.add(a);
        }
        o.add("frames", fr);
        JsonArray bl = new JsonArray();
        for (int[] b : blocks) {
            JsonArray a = new JsonArray();
            for (int v : b) a.add(new JsonPrimitive(v));
            bl.add(a);
        }
        o.add("blocks", bl);
        JsonArray cl = new JsonArray();
        for (int[] c : clicks) {
            JsonArray a = new JsonArray();
            for (int v : c) a.add(new JsonPrimitive(v));
            cl.add(a);
        }
        o.add("clicks", cl);
        return o;
    }

    static Replay fromJson(JsonObject o) {
        Replay r = new Replay();
        r.id = str(o, "id", "");
        r.player = str(o, "player", "?");
        r.uuid = str(o, "uuid", "");
        // v1 писал time_ms без карты: считаем, что это 32 блока
        r.map = str(o, "map", "32");
        r.value = o.has("value") ? o.get("value").getAsLong() : o.has("time_ms") ? o.get("time_ms").getAsLong() : 0;
        r.personal = o.has("private") && o.get("private").getAsBoolean();
        r.material = str(o, "material", "SANDSTONE");
        r.layout = str(o, "layout", "legacy");
        r.created = o.has("created") ? o.get("created").getAsLong() : 0;
        r.timerStartMs = o.has("timer_start_ms") ? o.get("timer_start_ms").getAsLong() : 0;
        JsonArray fr = o.getAsJsonArray("frames");
        r.frames = new double[fr.size()][];
        for (int i = 0; i < fr.size(); i++) {
            JsonArray a = fr.get(i).getAsJsonArray();
            double[] f = new double[7];
            for (int k = 0; k < 7 && k < a.size(); k++) f[k] = a.get(k).getAsDouble();
            r.frames[i] = f;
        }
        JsonArray bl = o.getAsJsonArray("blocks");
        r.blocks = new int[bl.size()][];
        for (int i = 0; i < bl.size(); i++) {
            JsonArray a = bl.get(i).getAsJsonArray();
            int[] b = new int[7];
            for (int k = 0; k < 7 && k < a.size(); k++) b[k] = a.get(k).getAsInt();
            r.blocks[i] = b;
        }
        if (o.has("clicks")) {
            JsonArray cl = o.getAsJsonArray("clicks");
            r.clicks = new int[cl.size()][];
            for (int i = 0; i < cl.size(); i++) {
                JsonArray a = cl.get(i).getAsJsonArray();
                r.clicks[i] = new int[]{a.get(0).getAsInt(), a.get(1).getAsInt()};
            }
        }
        return r;
    }

    private static String str(JsonObject o, String k, String d) {
        return o.has(k) && !o.get(k).isJsonNull() ? o.get(k).getAsString() : d;
    }

    void write(File f) throws IOException {
        File temp = new File(f.getParentFile(), f.getName() + ".tmp");
        java.io.Writer w = new java.io.OutputStreamWriter(new java.io.FileOutputStream(temp), "UTF-8");
        try { w.write(toJson().toString()); } finally { w.close(); }
        try { java.nio.file.Files.move(temp.toPath(), f.toPath(), java.nio.file.StandardCopyOption.REPLACE_EXISTING, java.nio.file.StandardCopyOption.ATOMIC_MOVE); }
        catch (java.nio.file.AtomicMoveNotSupportedException e) { java.nio.file.Files.move(temp.toPath(), f.toPath(), java.nio.file.StandardCopyOption.REPLACE_EXISTING); }
    }

    static Replay read(File f) throws IOException {
        FileReader rd = new FileReader(f);
        try {
            return fromJson(new JsonParser().parse(rd).getAsJsonObject());
        } finally {
            rd.close();
        }
    }

    /** Разбор имени файла: {дата, ник, карта, результат} или null. */
    static String[] parseName(String fileName) {
        Matcher m = FILE.matcher(fileName);
        // записи первой версии шли без карты - это были 32 блока
        return m.matches() ? new String[]{m.group(1), m.group(2), m.group(3) == null ? "32" : m.group(3), m.group(4)} : null;
    }

    static String fileName(String stamp, String nick, String map, long value) {
        return stamp + "_" + nick + "_" + map + "_" + value + ".json";
    }
}
