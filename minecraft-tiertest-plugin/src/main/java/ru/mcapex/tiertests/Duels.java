package ru.mcapex.tiertests;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.core.profile.Profile;
import dev.revere.alley.core.profile.ProfileService;
import dev.revere.alley.feature.arena.Arena;
import dev.revere.alley.feature.arena.ArenaService;
import dev.revere.alley.feature.kit.Kit;
import dev.revere.alley.feature.kit.KitService;
import dev.revere.alley.feature.match.Match;
import dev.revere.alley.feature.match.MatchService;
import dev.revere.alley.feature.match.internal.types.DefaultMatch;
import dev.revere.alley.feature.match.model.GameParticipant;
import dev.revere.alley.feature.match.model.internal.MatchGamePlayer;
import org.bukkit.Material;
import org.bukkit.entity.Player;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

/** Тонкая обёртка над Alley: список режимов, запуск дуэли 1 на 1 и ожидание результата. */
final class Duels {
    private Duels() {
    }

    static final class KitInfo {
        final String name;
        final String display;
        final Material icon;

        KitInfo(String name, String display, Material icon) {
            this.name = name;
            this.display = display;
            this.icon = icon;
        }
    }

    /** Включённые киты Alley, при непустом allowed - только они. */
    static List<KitInfo> kits(List<String> allowed) {
        List<KitInfo> out = new ArrayList<KitInfo>();
        KitService ks = AlleyPlugin.getInstance().getService(KitService.class);
        for (Kit k : ks.getKits()) {
            if (!k.isEnabled()) continue;
            if (!allowed.isEmpty() && !containsIgnoreCase(allowed, k.getName())) continue;
            out.add(new KitInfo(k.getName(), k.getDisplayName(), k.getIcon() == null ? Material.IRON_SWORD : k.getIcon()));
        }
        return out;
    }

    private static boolean containsIgnoreCase(List<String> list, String v) {
        for (String s : list) if (s.equalsIgnoreCase(v)) return true;
        return false;
    }

    /** Идёт ли у игрока матч или он в очереди/пати - тогда дуэль запускать нельзя. */
    static boolean busy(Player p) {
        Profile pr = AlleyPlugin.getInstance().getService(ProfileService.class).getProfile(p.getUniqueId());
        return pr == null || pr.getMatch() != null;
    }

    static final class Game {
        private final Match match;
        private final UUID a;
        private final UUID b;
        private UUID winner;
        private boolean finished;

        Game(Match match, UUID a, UUID b) {
            this.match = match;
            this.a = a;
            this.b = b;
        }

        /** Вызывать раз в секунду. */
        void poll() {
            if (finished) return;
            if (match instanceof DefaultMatch) {
                GameParticipant<MatchGamePlayer> w = ((DefaultMatch) match).getWinner();
                if (w != null) winner = w.containsPlayer(a) ? a : (w.containsPlayer(b) ? b : null);
            }
            if (!AlleyPlugin.getInstance().getService(MatchService.class).getMatches().contains(match)) finished = true;
        }

        boolean isFinished() {
            return finished;
        }

        /** Победитель или null, если ничья/не определён. */
        UUID winner() {
            return winner;
        }

        Match match() {
            return match;
        }
    }

    /** Запускает дуэль на случайной арене, подходящей киту. */
    static Game start(String kitName, Player pa, Player pb) {
        AlleyPlugin ap = AlleyPlugin.getInstance();
        Kit kit = ap.getService(KitService.class).getKit(kitName);
        if (kit == null) throw new IllegalStateException("Режим " + kitName + " не найден в Alley.");
        Arena arena = ap.getService(ArenaService.class).getRandomArena(kit);
        if (arena == null) throw new IllegalStateException("Для режима " + kitName + " нет свободной арены.");
        ProfileService ps = ap.getService(ProfileService.class);

        GameParticipant<MatchGamePlayer> ga = new GameParticipant<MatchGamePlayer>(new MatchGamePlayer(pa.getUniqueId(), pa.getName()));
        GameParticipant<MatchGamePlayer> gb = new GameParticipant<MatchGamePlayer>(new MatchGamePlayer(pb.getUniqueId(), pb.getName()));
        ap.getService(MatchService.class).createAndStartMatch(kit, arena, ga, gb, false, false, false);

        Profile prof = ps.getProfile(pa.getUniqueId());
        Match m = prof == null ? null : prof.getMatch();
        if (m == null) throw new IllegalStateException("Alley не запустил матч.");
        return new Game(m, pa.getUniqueId(), pb.getUniqueId());
    }

    static void spectate(Game g, Player p) {
        Profile pr = AlleyPlugin.getInstance().getService(ProfileService.class).getProfile(p.getUniqueId());
        if (pr != null && pr.getMatch() == null) g.match().addSpectator(p);
    }
}
