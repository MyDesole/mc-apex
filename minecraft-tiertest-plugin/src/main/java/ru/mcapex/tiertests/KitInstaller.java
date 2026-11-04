package ru.mcapex.tiertests;

import dev.revere.alley.AlleyPlugin;
import dev.revere.alley.feature.arena.Arena;
import dev.revere.alley.feature.arena.ArenaService;
import dev.revere.alley.feature.kit.Kit;
import dev.revere.alley.feature.kit.KitService;
import dev.revere.alley.feature.kit.setting.KitSetting;
import dev.revere.alley.feature.kit.setting.KitSettingService;
import org.bukkit.ChatColor;
import org.bukkit.Material;
import org.bukkit.command.CommandSender;
import org.bukkit.enchantments.Enchantment;
import org.bukkit.inventory.ItemStack;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

/** Создаёт в Alley дополнительные PvP-режимы (кит + привязка к открытой арене). Команда: /tier kits install. */
final class KitInstaller {
    private KitInstaller() {
    }

    private static final String ARENA = "BeachHouse";

    static void install(CommandSender to) {
        AlleyPlugin ap = AlleyPlugin.getInstance();
        KitService ks = ap.getService(KitService.class);
        KitSettingService ss = ap.getService(KitSettingService.class);
        ArenaService as = ap.getService(ArenaService.class);
        Arena arena = as.getArenaByName(ARENA);
        if (arena == null) {
            to.sendMessage("§cАрена " + ARENA + " не найдена в Alley.");
            return;
        }
        List<String> created = new ArrayList<String>();
        make(ks, ss, "NoDebuff", "§bNoDebuff", Material.POTION, noDebuff(), armor(Material.DIAMOND_HELMET, 2), created);
        make(ks, ss, "Classic", "§6Classic", Material.FISHING_ROD, classic(), armor(Material.IRON_HELMET, 1), created);
        make(ks, ss, "Gapple", "§eGapple", Material.GOLDEN_APPLE, gapple(), armor(Material.DIAMOND_HELMET, 2), created);
        for (String n : new String[]{"NoDebuff", "Classic", "Gapple"}) {
            if (ks.getKit(n) != null && !arena.getKits().contains(n)) arena.getKits().add(n);
        }
        as.saveArena(arena);
        installBuildKits(ks, ss, as, created);
        ks.saveKits();
        to.sendMessage(created.isEmpty() ? "§7Все режимы уже были созданы." : "§aСозданы режимы: §f" + created);
        to.sendMessage("§7Арена для них: §f" + ARENA + "§7. Новые режимы появятся в меню тир-теста.");
    }

    /** Режимы со строительством: привязываются к открытым общим аренам Standard. */
    private static final String[] BUILD_ARENAS = {"Arena", "Complex", "Courtyardv1", "FightNight", "SpikerockBay", "Reef"};

    private static void installBuildKits(KitService ks, KitSettingService ss, ArenaService as, List<String> created) {
        make(ks, ss, "Cobweb", "§fПаутинка", Material.WEB, cobweb(), diamondArmor(), created, "Build");
        make(ks, ss, "UHC", "§cUHC", Material.LAVA_BUCKET, uhc(), uhcArmor(), created, "Build");
        make(ks, ss, "BlockFight", "§6BlockFight", Material.SANDSTONE, blockFight(), diamondArmor(), created, "Build");
        for (String an : BUILD_ARENAS) {
            Arena a = as.getArenaByName(an);
            if (a == null) continue;
            for (String k : new String[]{"Cobweb", "UHC", "BlockFight"}) {
                if (ks.getKit(k) != null && !a.getKits().contains(k)) a.getKits().add(k);
            }
            as.saveArena(a);
        }
    }

    private static ItemStack[] diamondArmor() {
        return new ItemStack[]{new ItemStack(Material.DIAMOND_BOOTS), new ItemStack(Material.DIAMOND_LEGGINGS),
                new ItemStack(Material.DIAMOND_CHESTPLATE), new ItemStack(Material.DIAMOND_HELMET)};
    }

    private static ItemStack[] uhcArmor() {
        return new ItemStack[]{new ItemStack(Material.DIAMOND_BOOTS), new ItemStack(Material.IRON_LEGGINGS),
                new ItemStack(Material.IRON_CHESTPLATE), new ItemStack(Material.DIAMOND_HELMET)};
    }

    /** Паутинка: меч, лук с поджогом, 16 паутины, 8 стрел, 2 стака песчаника, алмазная кирка. */
    private static ItemStack[] cobweb() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = new ItemStack(Material.DIAMOND_SWORD);
        inv[1] = ench(Material.BOW, 1, Enchantment.ARROW_FIRE, 1);
        inv[2] = new ItemStack(Material.WEB, 16);
        inv[3] = new ItemStack(Material.SANDSTONE, 64);
        inv[4] = new ItemStack(Material.SANDSTONE, 64);
        inv[5] = new ItemStack(Material.DIAMOND_PICKAXE);
        inv[8] = new ItemStack(Material.ARROW, 8);
        return inv;
    }

    /** UHC: меч, удочка, лук, золотые яблоки, вода и лава, блоки, кирка и топор. */
    private static ItemStack[] uhc() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = new ItemStack(Material.DIAMOND_SWORD);
        inv[1] = new ItemStack(Material.FISHING_ROD);
        inv[2] = new ItemStack(Material.BOW);
        inv[3] = new ItemStack(Material.GOLDEN_APPLE, 6);
        inv[4] = new ItemStack(Material.LAVA_BUCKET);
        inv[5] = new ItemStack(Material.WATER_BUCKET);
        inv[6] = new ItemStack(Material.COBBLESTONE, 64);
        inv[7] = new ItemStack(Material.WOOD, 64);
        inv[8] = new ItemStack(Material.ARROW, 32);
        inv[9] = new ItemStack(Material.LAVA_BUCKET);
        inv[10] = new ItemStack(Material.WATER_BUCKET);
        inv[11] = new ItemStack(Material.DIAMOND_PICKAXE);
        inv[12] = new ItemStack(Material.DIAMOND_AXE);
        return inv;
    }

    /** БлокФайт: меч, 2 стака песчаника, кирка, лук без чар. */
    private static ItemStack[] blockFight() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = new ItemStack(Material.DIAMOND_SWORD);
        inv[1] = new ItemStack(Material.BOW);
        inv[2] = new ItemStack(Material.SANDSTONE, 64);
        inv[3] = new ItemStack(Material.SANDSTONE, 64);
        inv[4] = new ItemStack(Material.DIAMOND_PICKAXE);
        inv[8] = new ItemStack(Material.ARROW, 8);
        return inv;
    }

    private static void make(KitService ks, KitSettingService ss, String name, String display, Material icon,
                             ItemStack[] items, ItemStack[] armor, List<String> created, String... extra) {
        if (ks.getKit(name) != null) return;
        ks.createKit(name, items, armor, icon);
        Kit kit = ks.getKit(name);
        if (kit == null) return;
        kit.setDisplayName(display);
        kit.setEnabled(true);
        List<String> names = new ArrayList<String>(Arrays.asList("NoHunger", "PearlCooldown", "BowShotIndicator", "HealthBar"));
        names.addAll(Arrays.asList(extra));
        for (String s : names) {
            KitSetting set = ss.createSettingByName(s);
            if (set != null) {
                set.setEnabled(true);
                kit.addKitSetting(set);
            }
        }
        ks.saveKit(kit);
        created.add(ChatColor.stripColor(display));
    }

    private static ItemStack ench(Material m, int amount, Object... pairs) {
        ItemStack it = new ItemStack(m, amount);
        for (int i = 0; i < pairs.length; i += 2) it.addUnsafeEnchantment((Enchantment) pairs[i], (Integer) pairs[i + 1]);
        return it;
    }

    /** boots, leggings, chestplate, helmet - в порядке Bukkit. */
    private static ItemStack[] armor(Material helmet, int prot) {
        String base = helmet.name().replace("_HELMET", "");
        Enchantment p = Enchantment.PROTECTION_ENVIRONMENTAL;
        Enchantment u = Enchantment.DURABILITY;
        return new ItemStack[]{
                ench(Material.valueOf(base + "_BOOTS"), 1, p, prot, u, 3),
                ench(Material.valueOf(base + "_LEGGINGS"), 1, p, prot, u, 3),
                ench(Material.valueOf(base + "_CHESTPLATE"), 1, p, prot, u, 3),
                ench(helmet, 1, p, prot, u, 3)};
    }

    private static ItemStack[] noDebuff() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = ench(Material.DIAMOND_SWORD, 1, Enchantment.DAMAGE_ALL, 1, Enchantment.DURABILITY, 3);
        inv[1] = new ItemStack(Material.ENDER_PEARL, 16);
        inv[2] = new ItemStack(Material.POTION, 1, (short) 8226);
        inv[3] = new ItemStack(Material.POTION, 1, (short) 8259);
        for (int i = 4; i < 36; i++) inv[i] = new ItemStack(Material.POTION, 1, (short) 16421);
        inv[8] = new ItemStack(Material.COOKED_BEEF, 32);
        return inv;
    }

    private static ItemStack[] classic() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = ench(Material.DIAMOND_SWORD, 1, Enchantment.DURABILITY, 3);
        inv[1] = new ItemStack(Material.FISHING_ROD);
        inv[2] = ench(Material.BOW, 1, Enchantment.ARROW_DAMAGE, 1);
        inv[3] = new ItemStack(Material.GOLDEN_APPLE, 4);
        inv[8] = new ItemStack(Material.ARROW, 16);
        return inv;
    }

    private static ItemStack[] gapple() {
        ItemStack[] inv = new ItemStack[36];
        inv[0] = ench(Material.DIAMOND_SWORD, 1, Enchantment.DAMAGE_ALL, 2, Enchantment.DURABILITY, 3);
        inv[1] = new ItemStack(Material.GOLDEN_APPLE, 12);
        inv[2] = new ItemStack(Material.GOLDEN_APPLE, 3, (short) 1);
        return inv;
    }
}
