package ru.mcapex.auth;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * Обращения к сайту Apex.
 *
 * Ключ — ник игрока: он заявлен на сайте под аккаунтом, и плагин пускает
 * только того, чей ник совпадает с заявленным.
 *
 * Пароль передаётся только в login() и только телом POST по HTTPS. Он
 * нигде не логируется: ни в сообщениях об ошибке, ни в трейсах.
 */
public final class ApiClient {

    private final HttpClient http;
    private final String baseUrl;
    private final String serverKey;
    private final String configuredProxy;
    private final Gson gson = new Gson();
    private final Logger logger;

    public ApiClient(String baseUrl, String serverKey, int timeoutSeconds, Logger logger) {
        this(baseUrl, serverKey, timeoutSeconds, logger, "");
    }

    public ApiClient(String baseUrl, String serverKey, int timeoutSeconds, Logger logger, String proxy) {
        this.baseUrl = baseUrl.endsWith("/")
                ? baseUrl.substring(0, baseUrl.length() - 1)
                : baseUrl;
        this.serverKey = serverKey;
        this.configuredProxy = proxy;
        this.logger = logger;

        Duration timeout = Duration.ofSeconds(Math.max(3, timeoutSeconds));

        HttpClient.Builder builder = HttpClient.newBuilder()
                .connectTimeout(timeout)
                .followRedirects(HttpClient.Redirect.NORMAL);

        // Прокси нужен там, где хостинг закрывает исходящие наружу
        if (proxy != null && !proxy.isBlank()) {
            String[] parts = proxy.trim().split(":");
            int proxyPort = parts.length > 1 ? Integer.parseInt(parts[1].trim()) : 8080;

            builder.proxy(java.net.ProxySelector.of(
                    new java.net.InetSocketAddress(parts[0].trim(), proxyPort)
            ));

            logger.info("Использую прокси " + parts[0].trim() + ":" + proxyPort);
        }

        this.http = builder.build();
    }

    /** Заявлен ли ник на сайте и на какой аккаунт. */
    public ResolveResult resolve(String nickname) throws ApiException {
        JsonObject body = new JsonObject();
        body.addProperty("nickname", nickname);

        JsonObject json = post("/api/minecraft/nickname/resolve", body);

        return new ResolveResult(
                json.has("claimed") && json.get("claimed").getAsBoolean(),
                optString(json, "account_username")
        );
    }

    /**
     * Вход по нику и паролю сайта.
     *
     * Пароль не попадает ни в лог, ни в текст исключения.
     */
    public LoginResult login(String nickname, String password, String uuid) throws ApiException {
        JsonObject body = new JsonObject();
        body.addProperty("nickname", nickname);
        body.addProperty("password", password);
        body.addProperty("uuid", uuid);

        Response response = postRaw("/api/minecraft/auth/login", body, true);
        JsonObject json = parse(response.body());

        LoginResult result = new LoginResult(
                json.has("ok") && json.get("ok").getAsBoolean(),
                optString(json, "reason"),
                json.has("attempts_left") ? json.get("attempts_left").getAsInt() : 0,
                json.has("retry_after") ? json.get("retry_after").getAsInt() : 0
        );

        if (result.ok() && json.has("player") && json.get("player").isJsonObject()) {
            JsonObject player = json.getAsJsonObject("player");

            result.setProfile(
                    optString(player, "username"),
                    optString(player, "tier"),
                    optString(player, "clan_tag"),
                    player.has("bridge_rank") && player.get("bridge_rank").isJsonObject()
                            ? optString(player.getAsJsonObject("bridge_rank"), "label")
                            : null
            );
        }

        return result;
    }

    /** На какой аккаунт заявлен ник. */
    public LookupResult lookup(String nickname) throws ApiException {
        JsonObject body = new JsonObject();
        body.addProperty("nickname", nickname);

        JsonObject json = post("/api/minecraft/admin/lookup", body);

        return new LookupResult(
                json.has("claimed") && json.get("claimed").getAsBoolean(),
                optString(json, "nickname"),
                optString(json, "account_username"),
                optString(json, "claimed_at")
        );
    }

    /** Освободить ник от аккаунта. */
    public boolean adminUnlink(String nickname) throws ApiException {
        JsonObject body = new JsonObject();
        body.addProperty("nickname", nickname);

        JsonObject json = post("/api/minecraft/admin/unlink", body);

        return json.has("ok") && json.get("ok").getAsBoolean();
    }

    /* ----------------------------- Транспорт ----------------------------- */

    private JsonObject post(String path, JsonObject body) throws ApiException {
        Response response = postRaw(path, body, false);

        if (response.status() < 200 || response.status() >= 300) {
            throw new ApiException("Сайт ответил кодом " + response.status());
        }

        return parse(response.body());
    }

    private Response postRaw(String path, JsonObject body, boolean allowErrorCodes) throws ApiException {
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(baseUrl + path))
                .timeout(Duration.ofSeconds(15))
                .header("Content-Type", "application/json; charset=utf-8")
                .header("Accept", "application/json")
                .header("X-Minecraft-Server-Key", serverKey)
                .POST(HttpRequest.BodyPublishers.ofString(gson.toJson(body), StandardCharsets.UTF_8))
                .build();

        try {
            HttpResponse<String> httpResponse = http.send(
                    request,
                    HttpResponse.BodyHandlers.ofString(StandardCharsets.UTF_8)
            );

            int status = httpResponse.statusCode();

            /*
             * 401 и 429 — это осмысленные ответы входа, их разбирает
             * вызывающий. Всё остальное вне 2xx считаем сбоем.
             */
            boolean acceptable = status >= 200 && status < 300
                    || (allowErrorCodes && (status == 401 || status == 429 || status == 422));

            if (!acceptable) {
                throw new ApiException("Сайт ответил кодом " + status);
            }

            return new Response(status, httpResponse.body());
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new ApiException("Запрос к сайту прерван");
        } catch (Exception e) {
            /*
             * В сообщении исключения может быть адрес, но не тело запроса:
             * пароль сюда попасть не может, он уходит в другом месте.
             */
            String detail = describe(e);

            logger.log(Level.WARNING, "Не удалось обратиться к сайту: " + detail);
            logger.log(Level.WARNING, "Проверь связь командой /apexauth test");

            throw new ApiException(detail);
        }
    }

    /** Человеческое описание причины сбоя. */
    private static String describe(Throwable e) {
        String name = e.getClass().getSimpleName();
        String message = e.getMessage();

        if (e instanceof java.net.UnknownHostException) {
            return "не удалось разрешить имя сайта (DNS): " + message;
        }

        if (e instanceof java.net.ConnectException) {
            return "сайт отклонил соединение: " + message;
        }

        if (e instanceof java.net.http.HttpConnectTimeoutException) {
            return "истекло время подключения: соединение не устанавливается "
                    + "(обычно блокирует firewall или хостинг)";
        }

        if (e instanceof java.net.http.HttpTimeoutException) {
            return "сайт не ответил вовремя";
        }

        if (e instanceof javax.net.ssl.SSLException) {
            return "ошибка TLS: " + message;
        }

        return name + (message == null ? "" : ": " + message);
    }

    /**
     * Проверка связи с сайтом по шагам: DNS, TCP, HTTPS.
     *
     * Нужна, когда непонятно, на каком именно шаге рвётся соединение.
     */
    public java.util.List<String> diagnose() {
        java.util.List<String> out = new java.util.ArrayList<>();

        java.net.URI uri;

        try {
            uri = java.net.URI.create(baseUrl);
        } catch (Exception e) {
            out.add("§cАдрес сайта не разбирается: " + baseUrl);

            return out;
        }

        String host = uri.getHost();
        int port = uri.getPort() > 0
                ? uri.getPort()
                : ("https".equalsIgnoreCase(uri.getScheme()) ? 443 : 80);

        out.add("§7Адрес: §f" + baseUrl);
        out.add("§7Хост: §f" + host + "§7, порт: §f" + port);

        out.add("§7Прокси: §f" + (configuredProxy == null || configuredProxy.isBlank()
                ? "не задан"
                : configuredProxy));

        // Шаг 1: DNS
        java.net.InetAddress[] addresses;

        try {
            addresses = java.net.InetAddress.getAllByName(host);
        } catch (Exception e) {
            out.add("§cDNS: не удалось разрешить имя — " + describe(e));
            out.add("§cПроверь, что сервер вообще видит интернет.");

            return out;
        }

        for (java.net.InetAddress address : addresses) {
            out.add("§7DNS: §f" + address.getHostAddress());
        }

        // Шаг 2: TCP до каждого адреса
        for (java.net.InetAddress address : addresses) {
            long started = System.currentTimeMillis();

            try (java.net.Socket socket = new java.net.Socket()) {
                socket.connect(new java.net.InetSocketAddress(address, port), 5000);

                out.add("§aTCP " + address.getHostAddress() + ":" + port
                        + " — открыт (" + (System.currentTimeMillis() - started) + " мс)");
            } catch (Exception e) {
                out.add("§cTCP " + address.getHostAddress() + ":" + port
                        + " — НЕ открыт: " + describe(e));
            }
        }

        // Шаг 3: настоящий запрос к сайту
        long started = System.currentTimeMillis();

        try {
            resolve("ApexConnectionTest");

            out.add("§aЗапрос к сайту — прошёл (" + (System.currentTimeMillis() - started) + " мс)");
        } catch (ApiException e) {
            out.add("§cЗапрос к сайту — ошибка: " + e.getMessage());
        }

        return out;
    }

    private JsonObject parse(String raw) throws ApiException {
        try {
            return JsonParser.parseString(raw == null ? "{}" : raw).getAsJsonObject();
        } catch (Exception e) {
            throw new ApiException("Сайт вернул неожиданный ответ");
        }
    }

    private static String optString(JsonObject object, String key) {
        return object.has(key) && !object.get(key).isJsonNull()
                ? object.get(key).getAsString()
                : null;
    }

    private record Response(int status, String body) {
    }

    /* ----------------------------- Результаты ----------------------------- */

    public record ResolveResult(boolean claimed, String accountUsername) {
    }

    public record LookupResult(boolean claimed, String nickname,
                               String accountUsername, String claimedAt) {
    }

    public static final class LoginResult {
        private final boolean ok;
        private final String reason;
        private final int attemptsLeft;
        private final int retryAfter;

        private String siteUsername;
        private String tier;
        private String clanTag;
        private String bridgeRank;

        public LoginResult(boolean ok, String reason, int attemptsLeft, int retryAfter) {
            this.ok = ok;
            this.reason = reason;
            this.attemptsLeft = attemptsLeft;
            this.retryAfter = retryAfter;
        }

        void setProfile(String siteUsername, String tier, String clanTag, String bridgeRank) {
            this.siteUsername = siteUsername;
            this.tier = tier;
            this.clanTag = clanTag;
            this.bridgeRank = bridgeRank;
        }

        public boolean ok() {
            return ok;
        }

        public String reason() {
            return reason;
        }

        public int attemptsLeft() {
            return attemptsLeft;
        }

        public int retryAfter() {
            return retryAfter;
        }

        public String siteUsername() {
            return siteUsername;
        }

        public String tier() {
            return tier;
        }

        public String clanTag() {
            return clanTag;
        }

        public String bridgeRank() {
            return bridgeRank;
        }
    }

    /** Сбой обращения к сайту: пароль сюда не попадает. */
    public static final class ApiException extends Exception {
        public ApiException(String message) {
            super(message);
        }
    }
}
