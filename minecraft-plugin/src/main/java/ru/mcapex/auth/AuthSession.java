package ru.mcapex.auth;

import org.bukkit.Location;

import java.util.UUID;

/**
 * Состояние входа одного игрока.
 *
 * Пока состояние не AUTHENTICATED, игрок заморожен: ни ходить, ни писать,
 * ни ломать блоки.
 */
public final class AuthSession {

    public enum State {
        /** Ник не заявлен на сайте: игроку нужно сделать это в профиле. */
        NOT_CLAIMED,
        /** Ник заявлен, ждём пароль сайта. */
        PASSWORD,
        /** Вход выполнен. */
        AUTHENTICATED
    }

    private final UUID uuid;
    private final String name;

    private volatile State state = State.NOT_CLAIMED;
    private volatile int attemptsLeft = 5;
    private volatile String siteUsername;

    /** Задача опроса сайта: заявлен ли ник. */
    private volatile Integer pollTaskId;
    /** Задача на кик по времени. */
    private volatile Integer timeoutTaskId;

    /** Задача периодического напоминания о входе. */
    private volatile Integer reminderTaskId;

    /** Точка, к которой возвращаем игрока, пока он не вошёл. */
    private volatile Location frozenAt;

    public AuthSession(UUID uuid, String name) {
        this.uuid = uuid;
        this.name = name;
    }

    public UUID uuid() {
        return uuid;
    }

    public String name() {
        return name;
    }

    public State state() {
        return state;
    }

    public void state(State state) {
        this.state = state;
    }

    public boolean isAuthenticated() {
        return state == State.AUTHENTICATED;
    }

    public int attemptsLeft() {
        return attemptsLeft;
    }

    public void attemptsLeft(int value) {
        this.attemptsLeft = value;
    }

    public String siteUsername() {
        return siteUsername;
    }

    public void siteUsername(String value) {
        this.siteUsername = value;
    }

    public Integer pollTaskId() {
        return pollTaskId;
    }

    public void pollTaskId(Integer id) {
        this.pollTaskId = id;
    }

    public Integer timeoutTaskId() {
        return timeoutTaskId;
    }

    public Location frozenAt() {
        return frozenAt;
    }

    public void frozenAt(Location location) {
        this.frozenAt = location;
    }

    public Integer reminderTaskId() {
        return reminderTaskId;
    }

    public void reminderTaskId(Integer id) {
        this.reminderTaskId = id;
    }

    public void timeoutTaskId(Integer id) {
        this.timeoutTaskId = id;
    }
}
