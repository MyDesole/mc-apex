package ru.mcapex.auth;

import org.bukkit.ChatColor;
import org.bukkit.configuration.file.FileConfiguration;

import java.util.ArrayList;
import java.util.List;

/**
 * Настройки плагина.
 *
 * Читаются один раз при включении и при /apexauth reload.
 */
public final class PluginConfig {

    private final String apiUrl;
    private final String serverKey;
    private final int httpTimeoutSeconds;
    private final String proxy;
    private final boolean failClosed;

    private final int authTimeoutSeconds;
    private final boolean hideUnauthenticated;
    private final boolean kickOnTimeout;

    private final boolean spawnOnJoin;
    private final int remindSeconds;
    private final int sessionMinutes;
    private final boolean sessionBindIp;
    private final boolean opBypass;

    private final String prefix;
    private final String claimPrompt;
    private final String nicknameClaimed;
    private final String throttled;
    private final String attemptsUsedUp;
    private final String passwordPrompt;
    private final String welcome;
    private final String wrongPassword;
    private final String timeoutKick;
    private final String siteDownKick;
    private final String alreadyLinked;
    private final String sessionRestored;

    public PluginConfig(FileConfiguration config) {
        this.apiUrl = trim(config.getString("api.url", "https://mc-apex.ru"));
        this.serverKey = trim(config.getString("api.server-key", ""));
        this.httpTimeoutSeconds = config.getInt("api.timeout-seconds", 10);
        this.proxy = trim(config.getString("api.proxy", ""));
        this.failClosed = config.getBoolean("api.fail-closed", true);

        this.authTimeoutSeconds = config.getInt("auth.timeout-seconds", 120);
        this.hideUnauthenticated = config.getBoolean("auth.hide-unauthenticated", true);
        this.kickOnTimeout = config.getBoolean("auth.kick-on-timeout", true);

        // Телепорт на спавн: игрок не должен оказаться там, где вышел, пока
        // не доказал, что он это он.
        this.spawnOnJoin = config.getBoolean("auth.spawn-on-join", true);

        // Напоминание о входе: без него игрок стоит и не понимает, чего ждут
        this.remindSeconds = Math.max(3, config.getInt("auth.remind-seconds", 10));

        // Сколько помнить удачный вход. 0 — спрашивать пароль каждый раз
        this.sessionMinutes = Math.max(0, config.getInt("auth.session-minutes", 30));
        this.sessionBindIp = config.getBoolean("auth.session-bind-ip", true);

        // Операторы получают все права, включая apexauth.bypass, поэтому
        // по умолчанию проверку проходят наравне со всеми
        this.opBypass = config.getBoolean("auth.op-bypass", false);

        this.prefix = color(config.getString("messages.prefix", "&8[&6Apex&8] &r"));
        this.claimPrompt = color(config.getString("messages.claim-prompt",
                "&eНик &b%nickname%&e не заявлен на сайте.\n&eОткрой &bhttps://mc-apex.ru/link-minecraft?nick=%nickname%\n&eи нажми «Подтвердить»."));
        this.nicknameClaimed = color(config.getString("messages.nickname-claimed",
                "&7Ник заявлен аккаунтом &b%account%&7."));
        this.throttled = color(config.getString("messages.throttled",
                "&cСлишком много попыток. Подожди %seconds% секунд."));
        this.attemptsUsedUp = color(config.getString("messages.attempts-used-up",
                "&cПопытки исчерпаны. Зайди позже."));
        this.passwordPrompt = color(config.getString("messages.password-prompt",
                "&eВведи пароль от сайта &7mc-apex.ru &eв чат"));
        this.welcome = color(config.getString("messages.welcome",
                "&aВход выполнен. Добро пожаловать, &b%player%&a!"));
        this.wrongPassword = color(config.getString("messages.wrong-password",
                "&cНеверный пароль. Осталось попыток: &e%attempts%"));
        this.timeoutKick = color(config.getString("messages.timeout-kick",
                "&cВремя на вход истекло. Зайди снова."));
        this.siteDownKick = color(config.getString("messages.site-down-kick",
                "&cСайт недоступен, вход невозможен. Попробуй позже."));
        this.alreadyLinked = color(config.getString("messages.already-linked",
                "&aАккаунт привязан к &b%account%&a. Введи пароль сайта."));
        this.sessionRestored = color(config.getString("messages.session-restored",
                "&aС возвращением! Вход подтверждён, пароль не нужен."));
    }

    private static String trim(String value) {
        return value == null ? "" : value.trim();
    }

    private static String color(String value) {
        return value == null ? "" : ChatColor.translateAlternateColorCodes('&', value);
    }

    /** Подставляет переменные вида %code% и снимает цветовые коды. */
    public String message(String template, String... replacements) {
        String result = template;

        for (int i = 0; i + 1 < replacements.length; i += 2) {
            result = result.replace("%" + replacements[i] + "%", replacements[i + 1]);
        }

        return result;
    }

    public List<String> asList(String message) {
        List<String> lines = new ArrayList<>();
        lines.add(message);

        return lines;
    }

    public String apiUrl() {
        return apiUrl;
    }

    public String serverKey() {
        return serverKey;
    }

    public int httpTimeoutSeconds() {
        return httpTimeoutSeconds;
    }

    /** Прокси вида host:port. Пусто — ходим напрямую. */
    public String proxy() {
        return proxy;
    }

    public boolean failClosed() {
        return failClosed;
    }

    public int authTimeoutSeconds() {
        return authTimeoutSeconds;
    }

    public boolean hideUnauthenticated() {
        return hideUnauthenticated;
    }

    public boolean kickOnTimeout() {
        return kickOnTimeout;
    }

    public String prefix() {
        return prefix;
    }

    public String claimPrompt() {
        return claimPrompt;
    }

    public String nicknameClaimed() {
        return nicknameClaimed;
    }

    public String throttled() {
        return throttled;
    }

    public String attemptsUsedUp() {
        return attemptsUsedUp;
    }

    public String passwordPrompt() {
        return passwordPrompt;
    }

    public String welcome() {
        return welcome;
    }

    public String wrongPassword() {
        return wrongPassword;
    }

    public String timeoutKick() {
        return timeoutKick;
    }

    public String siteDownKick() {
        return siteDownKick;
    }

    public String alreadyLinked() {
        return alreadyLinked;
    }

    public String sessionRestored() {
        return sessionRestored;
    }

    public boolean spawnOnJoin() {
        return spawnOnJoin;
    }

    public int remindSeconds() {
        return remindSeconds;
    }

    public int sessionMinutes() {
        return sessionMinutes;
    }

    public boolean sessionBindIp() {
        return sessionBindIp;
    }

    public boolean opBypass() {
        return opBypass;
    }

    /** Настройки выглядят рабочими? */
    public boolean isUsable() {
        return !apiUrl.isEmpty()
                && !serverKey.isEmpty()
                && !serverKey.equals("CHANGE_ME")
                && apiUrl.startsWith("https://");
    }
}
