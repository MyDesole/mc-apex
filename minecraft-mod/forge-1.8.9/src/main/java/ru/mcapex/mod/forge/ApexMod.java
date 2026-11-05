package ru.mcapex.mod.forge;

import net.minecraft.client.Minecraft;
import net.minecraft.client.entity.EntityPlayerSP;
import net.minecraft.client.gui.FontRenderer;
import net.minecraft.client.network.NetworkPlayerInfo;
import net.minecraft.client.renderer.GlStateManager;
import net.minecraft.client.renderer.Tessellator;
import net.minecraft.client.renderer.WorldRenderer;
import net.minecraft.client.renderer.vertex.DefaultVertexFormats;
import net.minecraft.command.CommandBase;
import net.minecraft.command.CommandException;
import net.minecraft.command.ICommandSender;
import net.minecraft.entity.player.EntityPlayer;
import net.minecraft.event.ClickEvent;
import net.minecraft.scoreboard.ScorePlayerTeam;
import net.minecraft.util.ChatComponentText;
import net.minecraft.util.EnumChatFormatting;
import net.minecraft.util.IChatComponent;
import net.minecraftforge.client.ClientCommandHandler;
import net.minecraftforge.client.event.RenderLivingEvent;
import net.minecraftforge.common.MinecraftForge;
import net.minecraftforge.fml.common.Mod;
import net.minecraftforge.fml.common.event.FMLInitializationEvent;
import net.minecraftforge.fml.common.eventhandler.SubscribeEvent;
import net.minecraftforge.fml.common.gameevent.TickEvent;
import org.lwjgl.opengl.GL11;
import ru.mcapex.mod.core.ApexClient;
import ru.mcapex.mod.core.Badge;
import ru.mcapex.mod.core.ChatFormat;
import ru.mcapex.mod.core.PlayerInfo;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@Mod(modid = "apex", name = "APEX", version = "1.0.0", clientSideOnly = true, acceptedMinecraftVersions = "[1.8.9]")
public class ApexMod {
    private static final String API = "https://mc-apex.ru/api/mod";
    private static final EnumChatFormatting[] LEGACY = {
            EnumChatFormatting.BLACK, EnumChatFormatting.DARK_BLUE, EnumChatFormatting.DARK_GREEN, EnumChatFormatting.DARK_AQUA,
            EnumChatFormatting.DARK_RED, EnumChatFormatting.DARK_PURPLE, EnumChatFormatting.GOLD, EnumChatFormatting.GRAY,
            EnumChatFormatting.DARK_GRAY, EnumChatFormatting.BLUE, EnumChatFormatting.GREEN, EnumChatFormatting.AQUA,
            EnumChatFormatting.RED, EnumChatFormatting.LIGHT_PURPLE, EnumChatFormatting.YELLOW, EnumChatFormatting.WHITE
    };

    private final ApexClient client = new ApexClient(API);
    /** Что мы сами поставили в таб: чужие (серверные) имена не трогаем. */
    private final Map<NetworkPlayerInfo, IChatComponent> applied = new HashMap<NetworkPlayerInfo, IChatComponent>();
    private int ticks;

    @Mod.EventHandler
    public void init(FMLInitializationEvent event) {
        MinecraftForge.EVENT_BUS.register(this);
        ClientCommandHandler.instance.registerCommand(new CommandBase() {
            public String getCommandName() {
                return "apex";
            }

            public String getCommandUsage(ICommandSender sender) {
                return "/apex <ник>";
            }

            public int getRequiredPermissionLevel() {
                return 0;
            }

            public boolean canCommandSenderUseCommand(ICommandSender sender) {
                return true;
            }

            public void processCommand(ICommandSender sender, String[] args) throws CommandException {
                if (args.length < 1) {
                    say(text("Использование: /apex <ник>", EnumChatFormatting.GRAY));
                    return;
                }
                final String query = args[0];
                say(text("APEX: ищу " + query + "...", EnumChatFormatting.GRAY));
                client.lookup(query, new ApexClient.Callback<PlayerInfo>() {
                    public void done(final PlayerInfo info, final String error) {
                        Minecraft.getMinecraft().addScheduledTask(new Runnable() {
                            public void run() {
                                if (error != null) {
                                    say(text("APEX: ошибка запроса: " + error, EnumChatFormatting.RED));
                                } else if (info == null) {
                                    say(text("APEX: игрок «" + query + "» не найден на mc-apex.ru", EnumChatFormatting.RED));
                                } else {
                                    for (ChatFormat.Line line : ChatFormat.player(info)) {
                                        say(toComponent(line));
                                    }
                                }
                            }
                        });
                    }
                });
            }

            public List addTabCompletionOptions(ICommandSender sender, String[] args, net.minecraft.util.BlockPos pos) {
                List<String> names = new ArrayList<String>();
                if (args.length == 1 && Minecraft.getMinecraft().getNetHandler() != null) {
                    for (NetworkPlayerInfo i : Minecraft.getMinecraft().getNetHandler().getPlayerInfoMap()) {
                        names.add(i.getGameProfile().getName());
                    }
                }
                return getListOfStringsMatchingLastWord(args, names.toArray(new String[0]));
            }
        });
    }

    // ---------------------------------------------------------------- таб

    @SubscribeEvent
    public void onTick(TickEvent.ClientTickEvent e) {
        Minecraft mc = Minecraft.getMinecraft();
        if (e.phase != TickEvent.Phase.END || mc.getNetHandler() == null || mc.theWorld == null) {
            return;
        }
        if (++ticks % 20 != 0) {
            return;
        }
        List<String> names = new ArrayList<String>();
        for (NetworkPlayerInfo info : mc.getNetHandler().getPlayerInfoMap()) {
            names.add(info.getGameProfile().getName());
        }
        client.request(names);

        applied.keySet().retainAll(mc.getNetHandler().getPlayerInfoMap());
        for (NetworkPlayerInfo info : mc.getNetHandler().getPlayerInfoMap()) {
            IChatComponent mine = applied.get(info);
            IChatComponent current = info.getDisplayName();
            if (current != null && current != mine) {
                continue; // имя поставил сервер
            }
            Badge badge = client.cachedBadge(info.getGameProfile().getName());
            if (badge == null) {
                if (mine != null) {
                    info.setDisplayName(null);
                    applied.remove(info);
                }
                continue;
            }
            IChatComponent name = new ChatComponentText(ScorePlayerTeam.formatPlayerName(info.getPlayerTeam(), info.getGameProfile().getName()));
            IChatComponent full = badgeComponent(badge).appendSibling(name);
            if (mine == null || !mine.getUnformattedText().equals(full.getUnformattedText())
                    || !mine.getFormattedText().equals(full.getFormattedText())) {
                info.setDisplayName(full);
                applied.put(info, full);
            }
        }
    }

    // ------------------------------------------------------------- над ником

    @SubscribeEvent
    public void onRenderLabel(RenderLivingEvent.Specials.Post event) {
        if (!(event.entity instanceof EntityPlayer) || event.entity.isInvisible() || event.entity.isSneaking()) {
            return;
        }
        Minecraft mc = Minecraft.getMinecraft();
        EntityPlayer player = (EntityPlayer) event.entity;
        if (player == mc.thePlayer && mc.gameSettings.thirdPersonView == 0) {
            return;
        }
        if (mc.thePlayer.getDistanceSqToEntity(player) > 64 * 64) {
            return;
        }
        Badge badge = client.cachedBadge(player.getName());
        if (badge == null) {
            return;
        }

        String label = badge.text();
        FontRenderer font = mc.fontRendererObj;
        float yOffset = player.height + 0.75f;

        GlStateManager.pushMatrix();
        GlStateManager.translate((float) event.x, (float) event.y + yOffset, (float) event.z);
        GL11.glNormal3f(0.0F, 1.0F, 0.0F);
        GlStateManager.rotate(-mc.getRenderManager().playerViewY, 0.0F, 1.0F, 0.0F);
        GlStateManager.rotate(mc.getRenderManager().playerViewX, 1.0F, 0.0F, 0.0F);
        GlStateManager.scale(-0.02666667F, -0.02666667F, 0.02666667F);
        GlStateManager.disableLighting();
        GlStateManager.depthMask(false);
        GlStateManager.disableDepth();
        GlStateManager.enableBlend();
        GlStateManager.tryBlendFuncSeparate(770, 771, 1, 0);

        int half = font.getStringWidth(label) / 2;
        GlStateManager.disableTexture2D();
        Tessellator tess = Tessellator.getInstance();
        WorldRenderer wr = tess.getWorldRenderer();
        wr.begin(7, DefaultVertexFormats.POSITION_COLOR);
        wr.pos(-half - 2, -1, 0).color(0.0F, 0.0F, 0.0F, 0.25F).endVertex();
        wr.pos(-half - 2, 8, 0).color(0.0F, 0.0F, 0.0F, 0.25F).endVertex();
        wr.pos(half + 2, 8, 0).color(0.0F, 0.0F, 0.0F, 0.25F).endVertex();
        wr.pos(half + 2, -1, 0).color(0.0F, 0.0F, 0.0F, 0.25F).endVertex();
        tess.draw();
        GlStateManager.enableTexture2D();

        font.drawString(label, -half, 0, 0xFF000000 | badge.rgb);
        GlStateManager.enableDepth();
        GlStateManager.depthMask(true);
        GlStateManager.enableLighting();
        GlStateManager.disableBlend();
        GlStateManager.color(1.0F, 1.0F, 1.0F, 1.0F);
        GlStateManager.popMatrix();
    }

    // -------------------------------------------------------------- хелперы

    private static IChatComponent badgeComponent(Badge badge) {
        IChatComponent open = text("[", EnumChatFormatting.DARK_GRAY);
        IChatComponent label = text(badge.text(), LEGACY[ChatFormat.nearestLegacyIndex(badge.rgb)]);
        label.getChatStyle().setBold(true);
        return open.appendSibling(label).appendSibling(text("] ", EnumChatFormatting.DARK_GRAY));
    }

    private static IChatComponent toComponent(ChatFormat.Line line) {
        IChatComponent root = new ChatComponentText("");
        for (ChatFormat.Seg seg : line.segs) {
            IChatComponent part = new ChatComponentText(seg.text);
            part.getChatStyle().setColor(LEGACY[ChatFormat.nearestLegacyIndex(seg.rgb)]).setBold(seg.bold);
            if (line.clickUrl != null && seg.rgb == 0x60a5fa) {
                part.getChatStyle().setChatClickEvent(new ClickEvent(ClickEvent.Action.OPEN_URL, line.clickUrl)).setUnderlined(true);
            }
            root.appendSibling(part);
        }
        return root;
    }

    private static IChatComponent text(String s, EnumChatFormatting color) {
        IChatComponent c = new ChatComponentText(s);
        c.getChatStyle().setColor(color);
        return c;
    }

    private static void say(IChatComponent c) {
        EntityPlayerSP p = Minecraft.getMinecraft().thePlayer;
        if (p != null) {
            p.addChatMessage(c);
        }
    }
}
