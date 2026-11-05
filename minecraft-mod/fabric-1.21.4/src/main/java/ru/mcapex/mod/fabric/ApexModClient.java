package ru.mcapex.mod.fabric;

import com.mojang.brigadier.arguments.StringArgumentType;
import net.fabricmc.api.ClientModInitializer;
import net.fabricmc.fabric.api.client.command.v2.ClientCommandManager;
import net.fabricmc.fabric.api.client.command.v2.ClientCommandRegistrationCallback;
import net.fabricmc.fabric.api.client.event.lifecycle.v1.ClientTickEvents;
import net.minecraft.client.Minecraft;
import net.minecraft.client.multiplayer.PlayerInfo;
import net.minecraft.network.chat.ClickEvent;
import net.minecraft.network.chat.Component;
import net.minecraft.network.chat.MutableComponent;
import net.minecraft.network.chat.Style;
import net.minecraft.network.chat.TextColor;
import ru.mcapex.mod.core.ApexClient;
import ru.mcapex.mod.core.Badge;
import ru.mcapex.mod.core.ChatFormat;

import java.util.ArrayList;
import java.util.IdentityHashMap;
import java.util.List;
import java.util.Map;

public class ApexModClient implements ClientModInitializer {
    private static final ApexClient CLIENT = new ApexClient("https://mc-apex.ru/api/mod");
    private static final Map<PlayerInfo, Component> APPLIED = new IdentityHashMap<>();
    private static int ticks;

    /** Ник с значком, либо null, если игрока нет на сайте. Вызывается из mixin. */
    public static Component withBadge(String nick, Component original) {
        Badge badge = CLIENT.cachedBadge(nick);
        if (badge == null || original == null) {
            return null;
        }
        return badge(badge).append(original);
    }

    private static MutableComponent badge(Badge b) {
        MutableComponent label = Component.literal(b.text())
                .withStyle(Style.EMPTY.withColor(TextColor.fromRgb(b.rgb)).withBold(true));
        return Component.literal("[").withStyle(Style.EMPTY.withColor(TextColor.fromRgb(0x555555)))
                .append(label)
                .append(Component.literal("] ").withStyle(Style.EMPTY.withColor(TextColor.fromRgb(0x555555))));
    }

    @Override
    public void onInitializeClient() {
        ClientTickEvents.END_CLIENT_TICK.register(this::tick);

        ClientCommandRegistrationCallback.EVENT.register((dispatcher, registryAccess) ->
                dispatcher.register(ClientCommandManager.literal("apex")
                        .then(ClientCommandManager.argument("nick", StringArgumentType.word())
                                .executes(ctx -> {
                                    String query = StringArgumentType.getString(ctx, "nick");
                                    say(Component.literal("APEX: ищу " + query + "...").withStyle(Style.EMPTY.withColor(TextColor.fromRgb(0xaaaaaa))));
                                    CLIENT.lookup(query, (info, error) -> Minecraft.getInstance().execute(() -> {
                                        if (error != null) {
                                            say(Component.literal("APEX: ошибка запроса: " + error).withStyle(Style.EMPTY.withColor(TextColor.fromRgb(0xff5555))));
                                        } else if (info == null) {
                                            say(Component.literal("APEX: игрок «" + query + "» не найден на mc-apex.ru").withStyle(Style.EMPTY.withColor(TextColor.fromRgb(0xff5555))));
                                        } else {
                                            for (ChatFormat.Line line : ChatFormat.player(info)) {
                                                say(toComponent(line));
                                            }
                                        }
                                    }));
                                    return 1;
                                }))));
    }

    private void tick(Minecraft mc) {
        if (mc.getConnection() == null || mc.level == null || ++ticks % 20 != 0) {
            return;
        }
        List<String> names = new ArrayList<>();
        for (PlayerInfo info : mc.getConnection().getOnlinePlayers()) {
            names.add(info.getProfile().getName());
        }
        CLIENT.request(names);

        APPLIED.keySet().retainAll(mc.getConnection().getOnlinePlayers());
        for (PlayerInfo info : mc.getConnection().getOnlinePlayers()) {
            Component mine = APPLIED.get(info);
            Component current = info.getTabListDisplayName();
            if (current != null && current != mine) {
                continue; // имя в табе поставил сервер
            }
            Badge badge = CLIENT.cachedBadge(info.getProfile().getName());
            if (badge == null) {
                if (mine != null) {
                    info.setTabListDisplayName(null);
                    APPLIED.remove(info);
                }
                continue;
            }
            Component name = net.minecraft.world.scores.PlayerTeam.formatNameForTeam(info.getTeam(), Component.literal(info.getProfile().getName()));
            Component full = badge(badge).append(name);
            if (mine == null || !mine.getString().equals(full.getString())
                    || !mine.getStyle().equals(full.getStyle())) {
                info.setTabListDisplayName(full);
                APPLIED.put(info, full);
            }
        }
    }

    private static Component toComponent(ChatFormat.Line line) {
        MutableComponent root = Component.empty();
        for (ChatFormat.Seg seg : line.segs) {
            Style style = Style.EMPTY.withColor(TextColor.fromRgb(seg.rgb)).withBold(seg.bold);
            if (line.clickUrl != null && seg.rgb == 0x60a5fa) {
                style = style.withClickEvent(new ClickEvent(ClickEvent.Action.OPEN_URL, line.clickUrl)).withUnderlined(true);
            }
            root.append(Component.literal(seg.text).withStyle(style));
        }
        return root;
    }

    private static void say(Component c) {
        Minecraft mc = Minecraft.getInstance();
        if (mc.player != null) {
            mc.player.displayClientMessage(c, false);
        }
    }
}
