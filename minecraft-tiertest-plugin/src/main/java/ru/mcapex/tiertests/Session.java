package ru.mcapex.tiertests;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.scheduler.BukkitRunnable;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

/** Один тир-тест: приглашение, серия дуэлей, отчёт на сайт. */
final class Session {
    enum State { INVITED, RUNNING, DONE }

    final int testId;
    final String subjectNick;
    final UUID tester;
    final UUID subject;
    final List<String> kits;      // порядок режимов
    final int rounds;             // дуэлей на каждый режим
    State state = State.INVITED;

    private final ApexTierTests plugin;
    private final List<String> queue = new ArrayList<String>();   // режимы по одному на дуэль
    private final JsonArray games = new JsonArray();
    private int testerWins;
    private int subjectWins;
    private int draws;
    private int ticks;
    private Duels.Game current;
    private long nextGameAt;
    private BukkitRunnable task;

    Session(ApexTierTests plugin, int testId, String subjectNick, UUID tester, UUID subject, List<String> kits, int rounds) {
        this.plugin = plugin;
        this.testId = testId;
        this.subjectNick = subjectNick;
        this.tester = tester;
        this.subject = subject;
        this.kits = kits;
        this.rounds = rounds;
        for (String k : kits) for (int i = 0; i < rounds; i++) queue.add(k);
    }

    int totalGames() {
        return queue.size();
    }

    /** Испытуемый принял приглашение. */
    void begin() {
        state = State.RUNNING;
        nextGameAt = System.currentTimeMillis() + 3000;
        task = new BukkitRunnable() {
            @Override
            public void run() {
                tick();
            }
        };
        task.runTaskTimer(plugin, 20L, 20L);
        both("&aТир-тест начался: " + totalGames() + " дуэл. Первая через несколько секунд.");
    }

    private void tick() {
        Player t = Bukkit.getPlayer(tester);
        Player s = Bukkit.getPlayer(subject);

        if (current != null) {
            current.poll();
            if (current.isFinished()) {
                finishGame(current.winner());
                current = null;
                nextGameAt = System.currentTimeMillis() + plugin.getConfig().getInt("test.between-games-seconds", 6) * 1000L;
            } else if (t == null || s == null) {
                // вышел из игры посреди дуэли - Alley сам завершит матч, ждём результат
                if (++ticks > 20) finishGame(null);
            }
            return;
        }

        if (t == null || s == null) {
            abort(t == null ? "тестер вышел" : "испытуемый вышел");
            return;
        }
        if (queue.isEmpty()) {
            finish("завершён");
            return;
        }
        if (System.currentTimeMillis() < nextGameAt) return;
        if (Duels.busy(t) || Duels.busy(s)) {
            nextGameAt = System.currentTimeMillis() + 3000;
            ticks++;
            if (ticks > 40) abort("игроки заняты другим матчем");
            return;
        }
        ticks = 0;
        String kit = queue.remove(0);
        try {
            current = Duels.start(kit, t, s);
            currentKit = kit;
        } catch (RuntimeException e) {
            abort(e.getMessage());
        }
    }

    private String currentKit;

    private void finishGame(UUID winner) {
        JsonObject g = new JsonObject();
        g.addProperty("kit", currentKit);
        String res;
        if (winner == null) {
            draws++;
            g.addProperty("winner", "none");
            res = "ничья";
        } else if (winner.equals(subject)) {
            subjectWins++;
            g.addProperty("winner", "subject");
            res = subjectNick;
        } else {
            testerWins++;
            g.addProperty("winner", "tester");
            res = "тестер";
        }
        games.add(g);
        both("&7Дуэль " + currentKit + ": победа - &f" + res + "&7. Счёт: &f" + subjectNick + " " + subjectWins + " : " + testerWins + " тестер");
    }

    void abort(String why) {
        finish("прерван: " + why);
    }

    void finish(String how) {
        if (state == State.DONE) return;
        state = State.DONE;
        if (task != null) task.cancel();
        plugin.sessionDone(this);
        both("&6Тир-тест " + how + ". Итог: &f" + subjectNick + " " + subjectWins + " : " + testerWins + " тестер&6, ничьих " + draws + ".");
        final JsonObject body = new JsonObject();
        body.addProperty("tester", plugin.nameOf(tester));
        body.addProperty("subject_wins", subjectWins);
        body.addProperty("tester_wins", testerWins);
        body.addProperty("draws", draws);
        body.addProperty("planned", totalGames());
        body.addProperty("completed", how.equals("завершён"));
        body.add("games", games);
        Bukkit.getScheduler().runTaskAsynchronously(plugin, new Runnable() {
            @Override
            public void run() {
                try {
                    plugin.api().post("/tier-tests/" + testId + "/finish", body);
                    plugin.tell(tester, "&aРезультат отправлен на сайт. Оцените аспекты в кабинете тестера, чтобы закрыть заявку.");
                } catch (Exception e) {
                    plugin.tell(tester, "&cНе удалось отправить результат на сайт: " + e.getMessage());
                }
            }
        });
    }

    private void both(String msg) {
        plugin.tell(tester, msg);
        plugin.tell(subject, msg);
    }

    /** Выход игрока: до начала просто отменяем, во время теста - пишем итог как прерванный. */
    void onQuit() {
        if (state == State.INVITED) state = State.DONE;
        else if (state == State.RUNNING && current == null) abort("игрок вышел");
    }
}
