package ru.mcapex.clanwars;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** HTTP-клиент к интерфейсу сайта для сервера (/api/minecraft/...). Вызывать вне основного потока. */
final class Api {
    private final String base;
    private final String key;

    Api(String base, String key) {
        this.base = base.replaceAll("/+$", "");
        this.key = key;
    }

    JsonObject get(String path) throws Exception {
        return call("GET", path, null);
    }

    JsonObject post(String path, JsonObject body) throws Exception {
        return call("POST", path, body == null ? new JsonObject() : body);
    }

    private JsonObject call(String method, String path, JsonObject body) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(base + "/api/minecraft" + path).openConnection();
        c.setRequestMethod(method);
        c.setConnectTimeout(8000);
        c.setReadTimeout(10000);
        c.setRequestProperty("X-Minecraft-Server-Key", key);
        c.setRequestProperty("Accept", "application/json");
        if (body != null) {
            c.setDoOutput(true);
            c.setRequestProperty("Content-Type", "application/json");
            try (OutputStream o = c.getOutputStream()) {
                o.write(body.toString().getBytes(StandardCharsets.UTF_8));
            }
        }
        int code = c.getResponseCode();
        InputStream in = code >= 400 ? c.getErrorStream() : c.getInputStream();
        String text = "";
        if (in != null) {
            ByteArrayOutputStream buf = new ByteArrayOutputStream();
            byte[] b = new byte[4096];
            int n;
            while ((n = in.read(b)) > 0) buf.write(b, 0, n);
            text = new String(buf.toByteArray(), StandardCharsets.UTF_8);
        }
        JsonObject json = text.isEmpty() ? new JsonObject() : new JsonParser().parse(text).getAsJsonObject();
        if (code >= 400) {
            String msg = json.has("message") ? json.get("message").getAsString() : ("HTTP " + code);
            throw new IllegalStateException(msg);
        }
        return json;
    }
}
