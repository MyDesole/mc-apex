package ru.mcapex.tiertests;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import net.md_5.bungee.api.chat.ClickEvent;
import net.md_5.bungee.api.chat.ComponentBuilder;
import net.md_5.bungee.api.chat.HoverEvent;
import net.md_5.bungee.api.chat.TextComponent;
import org.bukkit.Bukkit;
import org.bukkit.ChatColor;
import org.bukkit.Material;
import org.bukkit.command.Command;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.Listener;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.AsyncPlayerChatEvent;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.java.JavaPlugin;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

public class ApexTierTests extends JavaPlugin implements Listener {
    private static final String LIST_TITLE = "§8Тир-тесты";
    private static final String SETUP_TITLE = "§8Настройка теста";

    private Api api;
    private final Map<String, String> tiers = new ConcurrentHashMap<String, String>();
    private final Map<UUID, Setup> setups = new HashMap<UUID, Setup>();
    private final Map<UUID, Session> byPlayer = new HashMap<UUID, Session>();
    private boolean tabOn;
    private boolean chatOn;

    /** Заявка с сайта. */
    static final class Test {
        int id;
        String nick = "";
        String mode = "";
        String tier = "";
        String status = "";
        boolean priority;
    }

    /** Черновик настройки теста у тестера. */
    static final class Setup {
        Test test;
        final List<String> kits = new ArrayList<String>();
        int rounds = 1;
    }

    @Override
    public void onEnable() {
        saveDefaultConfig();
        readConfig();
        Bukkit.getPluginManager().registerEvents(this, this);
        long every = Math.max(10, getConfig().getInt("tiers.refresh-seconds", 60)) * 20L;
        Bukkit.getScheduler().runTaskTimerAsynchronously(this, new Runnable() {
            public void run() {
                refreshTiers();
            }
        }, 40L, every);
        getLogger().info("ApexTierTests включён: " + getConfig().getString("api.url"));
    }

    private void readConfig() {
        reloadConfig();
        api = new Api(getConfig().getString("api.url", "https://mc-apex.ru"), getConfig().getString("api.server-key", ""));
        tabOn = getConfig().getBoolean("tiers.tab", true);
        chatOn = getConfig().getBoolean("tiers.chat", true);
    }

    Api api() {
        return api;
    }

    String nameOf(UUID id) {
        Player p = Bukkit.getPlayer(id);
        return p == null ? "" : p.getName();
    }

    void tell(final UUID id, final String msg) {
        Bukkit.getScheduler().runTask(this, new Runnable() {
            public void run() {
                Player p = Bukkit.getPlayer(id);
                if (p != null) p.sendMessage(color(msg));
            }
        });
    }

    void sessionDone(Session s) {
        byPlayer.remove(s.tester);
        byPlayer.remove(s.subject);
    }

    static String color(String s) {
        return ChatColor.translateAlternateColorCodes('&', s);
    }

    // ---------------------------------------------------------------- команды

    @Override
    public boolean onCommand(CommandSender sender, Command cmd, String label, String[] args) {
        if (cmd.getName().equalsIgnoreCase("tier")) {
            if (!sender.hasPermission("apextier.admin")) {
                sender.sendMessage("§cНет прав.");
            } else if (args.length > 0 && args[0].equalsIgnoreCase("reload")) {
                readConfig();
                sender.sendMessage("§aApexTierTests: настройки перечитаны.");
            } else {
                sender.sendMessage("§7/tier reload");
            }
            return true;
        }
        if (!(sender instanceof Player)) {
            sender.sendMessage("Только для игрока.");
            return true;
        }
        Player p = (Player) sender;
        String sub = args.length > 0 ? args[0].toLowerCase() : "";

        if (sub.equals("join") || sub.equals("accept")) {
            accept(p);
        } else if (sub.equals("decline")) {
            decline(p);
        } else if (sub.equals("stop")) {
            Session s = byPlayer.get(p.getUniqueId());
            if (s == null || !s.tester.equals(p.getUniqueId())) {
                p.sendMessage("§cУ вас нет своего тир-теста.");
            } else {
                s.abort("остановлен тестером");
            }
        } else {
            if (!p.hasPermission("apextier.use")) {
                p.sendMessage("§cНет прав.");
            } else {
                openList(p, sub.equals("all"));
            }
        }
        return true;
    }

    // ---------------------------------------------------------------- список заявок

    private void openList(final Player p, final boolean all) {
        p.sendMessage("§7Загружаю заявки с сайта...");
        Bukkit.getScheduler().runTaskAsynchronously(this, new Runnable() {
            public void run() {
                try {
                    final List<Test> tests = fetchTests(all ? null : p.getName());
                    Bukkit.getScheduler().runTask(ApexTierTests.this, new Runnable() {
                        public void run() {
                            buildList(p, tests, all);
                        }
                    });
                } catch (Exception e) {
                    tell(p.getUniqueId(), "&cНе удалось получить заявки: " + e.getMessage());
                }
            }
        });
    }

    private void buildList(Player p, List<Test> tests, boolean all) {
        Inventory inv = Bukkit.createInventory(null, 54, LIST_TITLE);
        for (int i = 0; i < tests.size() && i < 45; i++) {
            Test t = tests.get(i);
            Player online = Bukkit.getPlayerExact(t.nick);
            List<String> lore = new ArrayList<String>(Arrays.asList(
                    "§7Режим: §f" + dash(t.mode),
                    "§7Ожидаемый тир: §f" + dash(t.tier),
                    "§7Статус: §f" + statusRu(t.status),
                    online != null ? "§aСейчас на сервере" : "§cНе в сети", "",
                    online != null ? "§eНажмите, чтобы настроить тест" : "§8Игрок должен зайти на сервер"));
            ItemStack it = item(online != null ? Material.EMERALD : Material.REDSTONE,
                    "§f" + t.nick + (t.priority ? " §6*" : ""), lore);
            inv.setItem(i, it);
        }
        if (tests.isEmpty()) {
            inv.setItem(22, item(Material.BARRIER, "§cЗаявок нет",
                    Arrays.asList("§7Тесты берутся в кабинете тестера на сайте.", "§7/tiertest all - показать все заявки.")));
        }
        pending.put(p.getUniqueId(), tests);
        p.openInventory(inv);
    }

    private final Map<UUID, List<Test>> pending = new HashMap<UUID, List<Test>>();

    // ---------------------------------------------------------------- настройка теста

    private void openSetup(Player p, Setup s) {
        Inventory inv = Bukkit.createInventory(null, 54, SETUP_TITLE);
        List<Duels.KitInfo> kits = kitList();
        for (int i = 0; i < kits.size() && i < 36; i++) {
            Duels.KitInfo k = kits.get(i);
            int idx = s.kits.indexOf(k.name);
            ItemStack it = item(k.icon, ChatColor.translateAlternateColorCodes('&', k.display),
                    Arrays.asList(idx >= 0 ? "§aВыбрано, номер " + (idx + 1) : "§7Нажмите, чтобы добавить", "§8Порядок дуэлей - по номерам"));
            if (idx >= 0) it.setAmount(idx + 1);
            inv.setItem(i, it);
        }
        inv.setItem(45, item(Material.REDSTONE_BLOCK, "§cМеньше раундов", Arrays.asList("§7Дуэлей на каждый режим")));
        inv.setItem(46, item(Material.PAPER, "§fРаундов на режим: §e" + s.rounds,
                Arrays.asList("§7Всего дуэлей: §f" + (s.kits.size() * s.rounds))));
        inv.setItem(47, item(Material.EMERALD_BLOCK, "§aБольше раундов", Arrays.asList("§7Дуэлей на каждый режим")));
        inv.setItem(49, item(Material.DIAMOND, "§aПригласить " + s.test.nick,
                s.kits.isEmpty() ? Arrays.asList("§cСначала выберите хотя бы один режим")
                        : Arrays.asList("§7Режимов: §f" + s.kits.size(), "§7Дуэлей: §f" + (s.kits.size() * s.rounds))));
        inv.setItem(53, item(Material.ARROW, "§7Назад к заявкам", null));
        setups.put(p.getUniqueId(), s);
        p.openInventory(inv);
    }

    private List<Duels.KitInfo> kitList() {
        return Duels.kits(getConfig().getStringList("test.allowed-kits"));
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        String title = e.getInventory().getTitle();
        if (title == null || !(e.getWhoClicked() instanceof Player)) return;
        boolean list = title.equals(LIST_TITLE);
        boolean setup = title.equals(SETUP_TITLE);
        if (!list && !setup) return;
        e.setCancelled(true);
        Player p = (Player) e.getWhoClicked();
        if (e.getRawSlot() < 0 || e.getRawSlot() >= 54) return;
        int slot = e.getRawSlot();

        if (list) {
            List<Test> tests = pending.get(p.getUniqueId());
            if (tests == null || slot >= tests.size()) return;
            Test t = tests.get(slot);
            if (Bukkit.getPlayerExact(t.nick) == null) {
                p.sendMessage("§cИгрок " + t.nick + " не на сервере.");
                return;
            }
            Setup s = new Setup();
            s.test = t;
            openSetup(p, s);
            return;
        }

        Setup s = setups.get(p.getUniqueId());
        if (s == null) return;
        List<Duels.KitInfo> kits = kitList();
        int max = Math.max(1, getConfig().getInt("test.max-rounds", 7));
        if (slot < 36 && slot < kits.size()) {
            String name = kits.get(slot).name;
            if (!s.kits.remove(name)) s.kits.add(name);
            openSetup(p, s);
        } else if (slot == 45) {
            s.rounds = Math.max(1, s.rounds - 1);
            openSetup(p, s);
        } else if (slot == 47) {
            s.rounds = Math.min(max, s.rounds + 1);
            openSetup(p, s);
        } else if (slot == 49) {
            if (s.kits.isEmpty()) return;
            p.closeInventory();
            invite(p, s);
        } else if (slot == 53) {
            openList(p, false);
        }
    }

    // ---------------------------------------------------------------- приглашение

    private void invite(final Player tester, final Setup s) {
        final Player target = Bukkit.getPlayerExact(s.test.nick);
        if (target == null) {
            tester.sendMessage("§cИгрок " + s.test.nick + " уже вышел.");
            return;
        }
        if (byPlayer.containsKey(tester.getUniqueId()) || byPlayer.containsKey(target.getUniqueId())) {
            tester.sendMessage("§cТест уже идёт или приглашение ожидает ответа.");
            return;
        }
        final Session session = new Session(this, s.test.id, target.getName(), tester.getUniqueId(), target.getUniqueId(),
                new ArrayList<String>(s.kits), s.rounds);
        byPlayer.put(tester.getUniqueId(), session);
        byPlayer.put(target.getUniqueId(), session);

        Bukkit.getScheduler().runTaskAsynchronously(this, new Runnable() {
            public void run() {
                try {
                    JsonObject b = new JsonObject();
                    b.addProperty("tester", tester.getName());
                    JsonArray ks = new JsonArray();
                    for (String k : session.kits) ks.add(new com.google.gson.JsonPrimitive(k));
                    b.add("kits", ks);
                    b.addProperty("rounds", session.rounds);
                    api.post("/tier-tests/" + session.testId + "/invite", b);
                } catch (Exception ex) {
                    tell(tester.getUniqueId(), "&cСайт не принял приглашение: " + ex.getMessage());
                    Bukkit.getScheduler().runTask(ApexTierTests.this, new Runnable() {
                        public void run() {
                            session.state = Session.State.DONE;
                            sessionDone(session);
                        }
                    });
                    return;
                }
                Bukkit.getScheduler().runTask(ApexTierTests.this, new Runnable() {
                    public void run() {
                        sendInvite(tester, target, session);
                    }
                });
            }
        });
    }

    private void sendInvite(Player tester, Player target, final Session session) {
        StringBuilder modes = new StringBuilder();
        for (String k : session.kits) modes.append(modes.length() == 0 ? "" : ", ").append(k);

        target.sendMessage("");
        target.sendMessage(color("&6" + tester.getName() + " &fзовёт вас на тир-тест"));
        target.sendMessage(color("&7Режимы: &f" + modes + "&7, раундов на режим: &f" + session.rounds));
        TextComponent btn = new TextComponent(color("&a&l[ПРИСОЕДИНИТЬСЯ]"));
        btn.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/tiertest join"));
        btn.setHoverEvent(new HoverEvent(HoverEvent.Action.SHOW_TEXT, new ComponentBuilder(color("&7Нажмите, чтобы начать тест")).create()));
        TextComponent no = new TextComponent(color(" &c[ОТКАЗАТЬСЯ]"));
        no.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/tiertest decline"));
        btn.addExtra(no);
        target.spigot().sendMessage(btn);
        target.sendMessage("");
        tester.sendMessage(color("&aПриглашение отправлено игроку &f" + target.getName() + "&a. Ждём ответ."));

        long timeout = Math.max(15, getConfig().getInt("test.invite-timeout-seconds", 120)) * 20L;
        Bukkit.getScheduler().runTaskLater(this, new Runnable() {
            public void run() {
                if (session.state == Session.State.INVITED) {
                    session.state = Session.State.DONE;
                    sessionDone(session);
                    tell(session.tester, "&cПриглашение истекло.");
                    tell(session.subject, "&cПриглашение на тир-тест истекло.");
                }
            }
        }, timeout);
    }

    private void accept(Player p) {
        Session s = byPlayer.get(p.getUniqueId());
        if (s == null || s.state != Session.State.INVITED || !s.subject.equals(p.getUniqueId())) {
            p.sendMessage("§cДля вас нет активных приглашений.");
            return;
        }
        Player t = Bukkit.getPlayer(s.tester);
        if (t == null) {
            p.sendMessage("§cТестер вышел.");
            s.state = Session.State.DONE;
            sessionDone(s);
            return;
        }
        s.begin();
    }

    private void decline(Player p) {
        Session s = byPlayer.get(p.getUniqueId());
        if (s == null || s.state != Session.State.INVITED || !s.subject.equals(p.getUniqueId())) {
            p.sendMessage("§cДля вас нет активных приглашений.");
            return;
        }
        s.state = Session.State.DONE;
        sessionDone(s);
        p.sendMessage("§7Вы отказались от тир-теста.");
        tell(s.tester, "&c" + p.getName() + " отказался от тир-теста.");
    }

    // ---------------------------------------------------------------- тир в чате и таблисте

    @EventHandler
    public void onChat(AsyncPlayerChatEvent e) {
        if (!chatOn) return;
        String t = tiers.get(e.getPlayer().getName().toLowerCase());
        if (t != null && !t.isEmpty()) e.setFormat(prefix(t) + "§7%1$s§8: §f%2$s");
    }

    @EventHandler
    public void onJoin(PlayerJoinEvent e) {
        Bukkit.getScheduler().runTaskLater(this, new Runnable() {
            public void run() {
                applyTab();
            }
        }, 40L);
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        UUID id = e.getPlayer().getUniqueId();
        setups.remove(id);
        pending.remove(id);
        Session s = byPlayer.get(id);
        if (s != null) {
            s.onQuit();
            if (s.state == Session.State.DONE) sessionDone(s);
            tell(s.tester.equals(id) ? s.subject : s.tester, "&c" + e.getPlayer().getName() + " вышел с сервера.");
        }
    }

    private String prefix(String tier) {
        String c;
        switch (tier.toUpperCase()) {
            case "S": c = "§e"; break;
            case "A": c = "§6"; break;
            case "B": c = "§d"; break;
            case "C": c = "§b"; break;
            case "D": c = "§a"; break;
            default: c = "§7";
        }
        return c + "§l" + tier.toUpperCase() + " §r";
    }

    private void applyTab() {
        if (!tabOn) return;
        for (Player p : Bukkit.getOnlinePlayers()) {
            String t = tiers.get(p.getName().toLowerCase());
            String name = (t == null || t.isEmpty()) ? "§f" + p.getName() : prefix(t) + "§7" + p.getName();
            p.setPlayerListName(name);
        }
    }

    private void refreshTiers() {
        List<String> nicks = new ArrayList<String>();
        for (Player p : Bukkit.getOnlinePlayers()) nicks.add(p.getName());
        if (nicks.isEmpty()) return;
        try {
            JsonObject r = api.get("/tiers?nicks=" + java.net.URLEncoder.encode(join(nicks), "UTF-8"));
            for (JsonElement el : r.getAsJsonArray("tiers")) {
                JsonObject o = el.getAsJsonObject();
                String nick = o.get("nick").getAsString().toLowerCase();
                String tier = o.get("tier").isJsonNull() ? "" : o.get("tier").getAsString();
                tiers.put(nick, tier);
            }
            Bukkit.getScheduler().runTask(this, new Runnable() {
                public void run() {
                    applyTab();
                }
            });
        } catch (Exception ex) {
            getLogger().warning("Тиры не обновлены: " + ex.getMessage());
        }
    }

    // ---------------------------------------------------------------- сайт

    private List<Test> fetchTests(String tester) throws Exception {
        String path = "/tier-tests" + (tester == null ? "" : "?tester=" + java.net.URLEncoder.encode(tester, "UTF-8"));
        JsonObject r = api.get(path);
        List<Test> out = new ArrayList<Test>();
        for (JsonElement el : r.getAsJsonArray("tests")) {
            JsonObject o = el.getAsJsonObject();
            Test t = new Test();
            t.id = o.get("id").getAsInt();
            t.nick = str(o, "nickname");
            t.mode = str(o, "mode");
            t.tier = str(o, "expected_tier");
            t.status = str(o, "status");
            t.priority = o.has("priority") && !o.get("priority").isJsonNull() && o.get("priority").getAsBoolean();
            if (!t.nick.isEmpty()) out.add(t);
        }
        return out;
    }

    private static String str(JsonObject o, String k) {
        return o.has(k) && !o.get(k).isJsonNull() ? o.get(k).getAsString() : "";
    }

    private static String join(List<String> l) {
        StringBuilder sb = new StringBuilder();
        for (String s : l) sb.append(sb.length() == 0 ? "" : ",").append(s);
        return sb.toString();
    }

    private static String dash(String s) {
        return s == null || s.isEmpty() ? "-" : s;
    }

    private static String statusRu(String s) {
        if ("pending".equals(s)) return "ожидает";
        if ("claimed".equals(s) || "in_progress".equals(s)) return "в работе";
        return dash(s);
    }

    private static ItemStack item(Material m, String name, List<String> lore) {
        ItemStack it = new ItemStack(m);
        ItemMeta meta = it.getItemMeta();
        meta.setDisplayName(name);
        if (lore != null) meta.setLore(lore);
        it.setItemMeta(meta);
        return it;
    }
}
