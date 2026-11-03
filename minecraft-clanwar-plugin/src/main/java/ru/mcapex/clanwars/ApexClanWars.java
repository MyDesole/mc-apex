package ru.mcapex.clanwars;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import net.md_5.bungee.api.chat.ClickEvent;
import net.md_5.bungee.api.chat.HoverEvent;
import net.md_5.bungee.api.chat.ComponentBuilder;
import net.md_5.bungee.api.chat.TextComponent;
import org.bukkit.Bukkit;
import org.bukkit.ChatColor;
import org.bukkit.Material;
import org.bukkit.command.Command;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.EventPriority;
import org.bukkit.event.Listener;
import org.bukkit.event.inventory.InventoryClickEvent;
import org.bukkit.event.player.PlayerCommandPreprocessEvent;
import org.bukkit.event.player.PlayerQuitEvent;
import org.bukkit.inventory.Inventory;
import org.bukkit.inventory.ItemStack;
import org.bukkit.inventory.meta.ItemMeta;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitRunnable;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.UUID;

public class ApexClanWars extends JavaPlugin implements Listener {
    private static final String MODE_TITLE = "§8КВ: где играем";
    private static final String KITS_TITLE = "§8КВ: выбор дуэлей";

    private Api api;
    private Bw bw;
    private final Map<Integer, War> wars = new HashMap<Integer, War>();
    private final Map<UUID, War> byController = new HashMap<UUID, War>();
    private final Set<UUID> bwAllowed = new HashSet<UUID>();
    private War bwWar;

    /** Один клан в войне. */
    static final class Side {
        String name = "", tag = "", leader = "";
        final Set<String> members = new HashSet<String>();      // ники в нижнем регистре
        final Set<UUID> controllers = new HashSet<UUID>();      // лидер/офицер, открывший /cw
        boolean ready;
        boolean agreed;

        String label() {
            return tag.isEmpty() ? name : "[" + tag + "] " + name;
        }
    }

    enum Phase { GATHER, SETUP, CONFIRM, RUNNING, DONE }

    static final class War {
        int id;
        final Side a = new Side();     // вызвавший клан
        final Side b = new Side();     // принявший клан
        Phase phase = Phase.GATHER;
        String mode;                   // duels | bedwars
        final List<String> kits = new ArrayList<String>();
        int rounds = 1;
        int scoreA, scoreB;
        final JsonArray games = new JsonArray();
        // ход дуэлей
        final List<String> queue = new ArrayList<String>();
        Duels.Game current;
        String currentKit;
        UUID curA, curB;
        long nextAt;
        int stuck;
        BukkitRunnable task;
    }

    @Override
    public void onEnable() {
        saveDefaultConfig();
        api = new Api(getConfig().getString("api.url", "https://mc-apex.ru"), getConfig().getString("api.server-key", ""));
        Bukkit.getPluginManager().registerEvents(this, this);
        if (Bukkit.getPluginManager().getPlugin("BedWars1058") != null) {
            bw = new Bw(this, getConfig().getString("war.bedwars-arena", "bw_cw"));
            Bukkit.getPluginManager().registerEvents(bw, this);
        } else {
            getLogger().warning("BedWars1058 не найден: КВ на BedWars недоступны.");
        }
        getLogger().info("ApexClanWars включён");
    }

    Set<UUID> bwAllowed() {
        return bwAllowed;
    }

    static String c(String s) {
        return ChatColor.translateAlternateColorCodes('&', s);
    }

    // ---------------------------------------------------------------- команда /cw

    @Override
    public boolean onCommand(CommandSender sender, Command cmd, String label, String[] args) {
        if (!(sender instanceof Player)) {
            sender.sendMessage("Только для игрока.");
            return true;
        }
        Player p = (Player) sender;
        String sub = args.length > 0 ? args[0].toLowerCase(Locale.ROOT) : "";
        War w = byController.get(p.getUniqueId());

        if (sub.equals("ready")) {
            if (w == null || w.phase != Phase.GATHER) {
                p.sendMessage("§cСначала откройте войну командой /cw.");
            } else {
                setReady(w, p);
            }
        } else if (sub.equals("agree")) {
            if (w == null || w.phase != Phase.CONFIRM) p.sendMessage("§cНечего подтверждать.");
            else agree(w, p);
        } else if (sub.equals("cancel")) {
            if (w == null) p.sendMessage("§cУ вас нет открытой войны.");
            else cancel(w, p.getName() + " отменил КВ");
        } else if (sub.equals("pick") && args.length > 1) {
            pick(p, args[1]);
        } else {
            open(p);
        }
        return true;
    }

    private void open(final Player p) {
        p.sendMessage("§7Загружаю войны с сайта...");
        Bukkit.getScheduler().runTaskAsynchronously(this, new Runnable() {
            public void run() {
                try {
                    JsonObject r = api.get("/clan-wars?leader=" + java.net.URLEncoder.encode(p.getName(), "UTF-8"));
                    final JsonArray arr = r.getAsJsonArray("wars");
                    Bukkit.getScheduler().runTask(ApexClanWars.this, new Runnable() {
                        public void run() {
                            onWars(p, arr);
                        }
                    });
                } catch (Exception e) {
                    msg(p.getUniqueId(), "&cНе удалось получить войны: " + e.getMessage());
                }
            }
        });
    }

    private final Map<UUID, JsonArray> pending = new HashMap<UUID, JsonArray>();

    private void onWars(Player p, JsonArray arr) {
        if (arr.size() == 0) {
            p.sendMessage("§cНет принятых клановых войн. Лидер или офицер должен принять вызов на сайте.");
            return;
        }
        if (arr.size() == 1) {
            join(p, arr.get(0).getAsJsonObject());
            return;
        }
        pending.put(p.getUniqueId(), arr);
        p.sendMessage("§6Выберите войну:");
        for (JsonElement el : arr) {
            JsonObject o = el.getAsJsonObject();
            TextComponent t = new TextComponent(c(" &f" + o.getAsJsonObject("challenger").get("name").getAsString()
                    + " &7против &f" + o.getAsJsonObject("opponent").get("name").getAsString() + " &a[ОТКРЫТЬ]"));
            t.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/cw pick " + o.get("id").getAsInt()));
            p.spigot().sendMessage(t);
        }
    }

    private void pick(Player p, String id) {
        JsonArray arr = pending.get(p.getUniqueId());
        if (arr == null) {
            open(p);
            return;
        }
        for (JsonElement el : arr) {
            if (String.valueOf(el.getAsJsonObject().get("id").getAsInt()).equals(id)) {
                join(p, el.getAsJsonObject());
                return;
            }
        }
    }

    private void join(Player p, JsonObject o) {
        int id = o.get("id").getAsInt();
        War w = wars.get(id);
        if (w == null) {
            w = new War();
            w.id = id;
            fill(w.a, o.getAsJsonObject("challenger"));
            fill(w.b, o.getAsJsonObject("opponent"));
            wars.put(id, w);
        }
        if (w.phase != Phase.GATHER && w.phase != Phase.SETUP && w.phase != Phase.CONFIRM) {
            p.sendMessage("§cВойна уже идёт.");
            return;
        }
        Side mine = o.get("my_side").getAsString().equals("challenger") ? w.a : w.b;
        mine.controllers.add(p.getUniqueId());
        byController.put(p.getUniqueId(), w);
        p.sendMessage(c("&6Клановая война: &f" + w.a.label() + " &7против &f" + w.b.label()));
        p.sendMessage(c("&7Когда ваш клан готов, нажмите кнопку. Начнём, как только отметятся оба лидера."));
        TextComponent t = new TextComponent(c("&a&l[ГОТОВ ДРАТЬСЯ]"));
        t.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/cw ready"));
        t.setHoverEvent(new HoverEvent(HoverEvent.Action.SHOW_TEXT, new ComponentBuilder(c("&7Готовность вашего клана")).create()));
        TextComponent no = new TextComponent(c(" &c[ОТМЕНА]"));
        no.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/cw cancel"));
        t.addExtra(no);
        p.spigot().sendMessage(t);
    }

    private void fill(Side s, JsonObject o) {
        s.name = o.get("name").getAsString();
        s.tag = o.has("tag") && !o.get("tag").isJsonNull() ? o.get("tag").getAsString() : "";
        s.leader = o.has("leader") && !o.get("leader").isJsonNull() ? o.get("leader").getAsString() : "";
        for (JsonElement m : o.getAsJsonArray("members")) s.members.add(m.getAsString().toLowerCase(Locale.ROOT));
    }

    private Side sideOf(War w, Player p) {
        return w.a.controllers.contains(p.getUniqueId()) ? w.a : (w.b.controllers.contains(p.getUniqueId()) ? w.b : null);
    }

    private void setReady(War w, Player p) {
        Side s = sideOf(w, p);
        if (s == null) return;
        s.ready = true;
        tellWar(w, "&a" + s.label() + " готов драться (" + p.getName() + ").");
        if (w.a.ready && w.b.ready) {
            w.phase = Phase.SETUP;
            tellWar(w, "&6Оба клана готовы. Вызвавший клан выбирает, где играем.");
            for (UUID u : w.a.controllers) {
                Player lead = Bukkit.getPlayer(u);
                if (lead != null) {
                    openMode(lead);
                    break;
                }
            }
        }
    }

    // ---------------------------------------------------------------- выбор режима

    private void openMode(Player p) {
        Inventory inv = Bukkit.createInventory(null, 27, MODE_TITLE);
        inv.setItem(11, item(Material.DIAMOND_SWORD, "§bДуэли", Arrays.asList("§7Бои 1 на 1 по очереди", "§7Вы выбираете режимы и число раундов")));
        inv.setItem(15, item(Material.BED, "§cBedWars", Arrays.asList("§7Клан против клана на арене КВ", bw == null ? "§cBedWars недоступен" : "§7Без лимита игроков")));
        p.openInventory(inv);
    }

    private final Map<UUID, List<String>> pickedKits = new HashMap<UUID, List<String>>();
    private final Map<UUID, Integer> pickedRounds = new HashMap<UUID, Integer>();

    private void openKits(Player p) {
        List<String> picked = pickedKits.get(p.getUniqueId());
        if (picked == null) {
            picked = new ArrayList<String>();
            pickedKits.put(p.getUniqueId(), picked);
        }
        int rounds = pickedRounds.containsKey(p.getUniqueId()) ? pickedRounds.get(p.getUniqueId()) : 1;
        Inventory inv = Bukkit.createInventory(null, 54, KITS_TITLE);
        List<Duels.KitInfo> kits = Duels.kits(getConfig().getStringList("war.allowed-kits"));
        for (int i = 0; i < kits.size() && i < 36; i++) {
            Duels.KitInfo k = kits.get(i);
            int idx = picked.indexOf(k.name);
            ItemStack it = item(k.icon, c(k.display), Arrays.asList(idx >= 0 ? "§aВыбрано, номер " + (idx + 1) : "§7Нажмите, чтобы добавить"));
            if (idx >= 0) it.setAmount(idx + 1);
            inv.setItem(i, it);
        }
        inv.setItem(45, item(Material.REDSTONE_BLOCK, "§cМеньше раундов", null));
        inv.setItem(46, item(Material.PAPER, "§fРаундов на режим: §e" + rounds, Arrays.asList("§7Всего боёв: §f" + picked.size() * rounds)));
        inv.setItem(47, item(Material.EMERALD_BLOCK, "§aБольше раундов", null));
        inv.setItem(49, item(Material.DIAMOND, "§aПредложить условия", picked.isEmpty() ? Arrays.asList("§cВыберите хотя бы один режим") : null));
        p.openInventory(inv);
    }

    @EventHandler
    public void onClick(InventoryClickEvent e) {
        String title = e.getInventory().getTitle();
        if (title == null || !(e.getWhoClicked() instanceof Player)) return;
        boolean mode = title.equals(MODE_TITLE), kitsMenu = title.equals(KITS_TITLE);
        if (!mode && !kitsMenu) return;
        e.setCancelled(true);
        Player p = (Player) e.getWhoClicked();
        War w = byController.get(p.getUniqueId());
        if (w == null || w.phase != Phase.SETUP || e.getRawSlot() < 0 || e.getRawSlot() >= 54) return;
        int slot = e.getRawSlot();
        if (mode) {
            if (slot == 11) {
                w.mode = "duels";
                openKits(p);
            } else if (slot == 15) {
                if (bw == null) {
                    p.sendMessage("§cBedWars недоступен.");
                    return;
                }
                String err = bw.check();
                if (err != null) {
                    p.sendMessage("§c" + err);
                    return;
                }
                w.mode = "bedwars";
                p.closeInventory();
                propose(w);
            }
            return;
        }
        List<String> picked = pickedKits.get(p.getUniqueId());
        int rounds = pickedRounds.containsKey(p.getUniqueId()) ? pickedRounds.get(p.getUniqueId()) : 1;
        List<Duels.KitInfo> kits = Duels.kits(getConfig().getStringList("war.allowed-kits"));
        int max = Math.max(1, getConfig().getInt("war.max-rounds", 7));
        if (slot < 36 && slot < kits.size()) {
            String n = kits.get(slot).name;
            if (!picked.remove(n)) picked.add(n);
        } else if (slot == 45) {
            pickedRounds.put(p.getUniqueId(), Math.max(1, rounds - 1));
        } else if (slot == 47) {
            pickedRounds.put(p.getUniqueId(), Math.min(max, rounds + 1));
        } else if (slot == 49 && !picked.isEmpty()) {
            w.kits.clear();
            w.kits.addAll(picked);
            w.rounds = rounds;
            p.closeInventory();
            propose(w);
            return;
        }
        openKits(p);
    }

    // ---------------------------------------------------------------- согласование

    private void propose(War w) {
        w.phase = Phase.CONFIRM;
        w.a.agreed = false;
        w.b.agreed = false;
        String what = w.mode.equals("bedwars") ? "BedWars" : "дуэли: " + w.kits + ", раундов на режим: " + w.rounds;
        tellWar(w, "&6Условия КВ: &f" + what);
        TextComponent t = new TextComponent(c("&a&l[ПРИНЯТЬ УСЛОВИЯ]"));
        t.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/cw agree"));
        TextComponent no = new TextComponent(c(" &c[ОТМЕНА]"));
        no.setClickEvent(new ClickEvent(ClickEvent.Action.RUN_COMMAND, "/cw cancel"));
        t.addExtra(no);
        for (Side s : new Side[]{w.a, w.b}) {
            for (UUID u : s.controllers) {
                Player p = Bukkit.getPlayer(u);
                if (p != null) p.spigot().sendMessage(t);
            }
        }
    }

    private void agree(War w, Player p) {
        Side s = sideOf(w, p);
        if (s == null) return;
        s.agreed = true;
        tellWar(w, "&a" + s.label() + " принял условия.");
        if (w.a.agreed && w.b.agreed) begin(w);
    }

    // ---------------------------------------------------------------- старт

    private List<Player> online(Side s) {
        List<Player> out = new ArrayList<Player>();
        for (Player p : Bukkit.getOnlinePlayers()) if (s.members.contains(p.getName().toLowerCase(Locale.ROOT))) out.add(p);
        return out;
    }

    private void begin(final War w) {
        List<Player> pa = online(w.a), pb = online(w.b);
        if (pa.isEmpty() || pb.isEmpty()) {
            tellWar(w, "&cНа сервере нет игроков одного из кланов. Соберите бойцов и подтвердите условия снова.");
            w.a.agreed = false;
            w.b.agreed = false;
            return;
        }
        w.phase = Phase.RUNNING;
        if (w.mode.equals("bedwars")) {
            String err = bw.check();
            if (err != null) {
                tellWar(w, "&c" + err);
                w.phase = Phase.CONFIRM;
                return;
            }
            bwWar = w;
            bwAllowed.clear();
            for (Player p : pa) bwAllowed.add(p.getUniqueId());
            for (Player p : pb) bwAllowed.add(p.getUniqueId());
            bw.join(pa, pb);
            tellWar(w, "&6Бойцы на арене КВ: " + w.a.label() + " (" + pa.size() + ") против " + w.b.label() + " (" + pb.size() + "). Старт через несколько секунд.");
            Bukkit.getScheduler().runTaskLater(this, new Runnable() {
                public void run() {
                    bw.start();
                }
            }, 40L);
            return;
        }
        for (String k : w.kits) for (int i = 0; i < w.rounds; i++) w.queue.add(k);
        tellWar(w, "&6Дуэли: " + w.queue.size() + " боёв. Бойцы: " + w.a.label() + " (" + pa.size() + ") против " + w.b.label() + " (" + pb.size() + ").");
        w.nextAt = System.currentTimeMillis() + 3000;
        w.task = new BukkitRunnable() {
            public void run() {
                tick(w);
            }
        };
        w.task.runTaskTimer(this, 20L, 20L);
    }

    private int gameNo;

    private void tick(War w) {
        if (w.current != null) {
            w.current.poll();
            if (w.current.isFinished()) {
                finishGame(w, w.current.winner());
                w.current = null;
                w.nextAt = System.currentTimeMillis() + getConfig().getInt("war.between-games-seconds", 6) * 1000L;
            }
            return;
        }
        if (w.queue.isEmpty()) {
            report(w, "duels");
            return;
        }
        if (System.currentTimeMillis() < w.nextAt) return;
        List<Player> pa = online(w.a), pb = online(w.b);
        if (pa.isEmpty() || pb.isEmpty()) {
            tellWar(w, "&cОдин из кланов остался без бойцов. Война прервана, зачтены сыгранные бои.");
            report(w, "duels");
            return;
        }
        Player fa = pa.get(gameNo % pa.size()), fb = pb.get(gameNo % pb.size());
        if (Duels.busy(fa) || Duels.busy(fb)) {
            w.nextAt = System.currentTimeMillis() + 3000;
            if (++w.stuck > 40) {
                tellWar(w, "&cБойцы заняты другим матчем. Война прервана.");
                report(w, "duels");
            }
            return;
        }
        w.stuck = 0;
        String kit = w.queue.remove(0);
        try {
            w.current = Duels.start(kit, fa, fb);
            w.currentKit = kit;
            w.curA = fa.getUniqueId();
            w.curB = fb.getUniqueId();
            tellWar(w, "&7Бой " + (gameNo + 1) + ": &f" + fa.getName() + " &7против &f" + fb.getName() + " &7(" + kit + ")");
            gameNo++;
        } catch (RuntimeException ex) {
            tellWar(w, "&c" + ex.getMessage());
            report(w, "duels");
        }
    }

    private void finishGame(War w, UUID winner) {
        JsonObject g = new JsonObject();
        g.addProperty("name", w.currentKit);
        if (winner == null) {
            g.addProperty("winner", "none");
        } else if (winner.equals(w.curA)) {
            w.scoreA++;
            g.addProperty("winner", "challenger");
        } else {
            w.scoreB++;
            g.addProperty("winner", "opponent");
        }
        w.games.add(g);
        tellWar(w, "&7Счёт: &f" + w.a.label() + " " + w.scoreA + " : " + w.scoreB + " " + w.b.label());
    }

    void bwFinished(String teamName) {
        War w = bwWar;
        if (w == null) return;
        bwWar = null;
        bwAllowed.clear();
        JsonObject g = new JsonObject();
        g.addProperty("name", "BedWars");
        if ("Red".equalsIgnoreCase(teamName)) {
            w.scoreA = 1;
            g.addProperty("winner", "challenger");
        } else if ("Blue".equalsIgnoreCase(teamName)) {
            w.scoreB = 1;
            g.addProperty("winner", "opponent");
        } else {
            g.addProperty("winner", "none");
        }
        w.games.add(g);
        report(w, "bedwars");
    }

    private void report(final War w, String mode) {
        if (w.phase == Phase.DONE) return;
        w.phase = Phase.DONE;
        if (w.task != null) w.task.cancel();
        tellWar(w, "&6Итог КВ: &f" + w.a.label() + " " + w.scoreA + " : " + w.scoreB + " " + w.b.label());
        final JsonObject body = new JsonObject();
        body.addProperty("challenger_score", w.scoreA);
        body.addProperty("opponent_score", w.scoreB);
        body.addProperty("mode", mode);
        body.add("games", w.games);
        Bukkit.getScheduler().runTaskAsynchronously(this, new Runnable() {
            public void run() {
                try {
                    api.post("/clan-wars/" + w.id + "/result", body);
                    tellWar(w, "&aРезультат записан на сайте.");
                } catch (Exception e) {
                    tellWar(w, "&cНе удалось записать результат на сайте: " + e.getMessage());
                }
                Bukkit.getScheduler().runTask(ApexClanWars.this, new Runnable() {
                    public void run() {
                        forget(w);
                    }
                });
            }
        });
    }

    private void cancel(War w, String why) {
        if (w.phase == Phase.RUNNING && w.mode != null && w.mode.equals("bedwars")) {
            tellWar(w, "&cBedWars уже идёт, отмена невозможна.");
            return;
        }
        if (w.task != null) w.task.cancel();
        tellWar(w, "&c" + why);
        forget(w);
    }

    private void forget(War w) {
        wars.remove(w.id);
        byController.values().removeAll(Arrays.asList(w));
    }

    private void tellWar(final War w, final String text) {
        Bukkit.getScheduler().runTask(this, new Runnable() {
            public void run() {
                for (Player p : Bukkit.getOnlinePlayers()) {
                    String n = p.getName().toLowerCase(Locale.ROOT);
                    if (w.a.members.contains(n) || w.b.members.contains(n)) p.sendMessage(c(text));
                }
            }
        });
    }

    private void msg(final UUID id, final String text) {
        Bukkit.getScheduler().runTask(this, new Runnable() {
            public void run() {
                Player p = Bukkit.getPlayer(id);
                if (p != null) p.sendMessage(c(text));
            }
        });
    }

    // ---------------------------------------------------------------- прочее

    /** Команда /leave у Alley не знает про BedWars: из арены выходим командой BedWars. */
    @EventHandler(priority = EventPriority.LOWEST)
    public void onCommand(PlayerCommandPreprocessEvent e) {
        String m = e.getMessage().toLowerCase(Locale.ROOT).trim();
        if (bw != null && (m.equals("/leave") || m.equals("/lobby") || m.equals("/hub")) && bw.inArena(e.getPlayer())) {
            e.setCancelled(true);
            e.getPlayer().performCommand("bw leave");
        }
    }

    @EventHandler
    public void onQuit(PlayerQuitEvent e) {
        byController.remove(e.getPlayer().getUniqueId());
        pending.remove(e.getPlayer().getUniqueId());
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
