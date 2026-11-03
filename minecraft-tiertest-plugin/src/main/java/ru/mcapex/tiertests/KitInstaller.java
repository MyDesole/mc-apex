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
        ks.saveKits();
        to.sendMessage(created.isEmpty() ? "§7Все режимы уже были созданы." : "§aСозданы режимы: §f" + created);
        to.sendMessage("§7Арена для них: §f" + ARENA + "§7. Новые режимы появятся в меню тир-теста.");
    }

    private static void make(KitService ks, KitSettingService ss, String name, String display, Material icon,
                             ItemStack[] items, ItemStack[] armor, List<String> created) {
        if (ks.getKit(name) != null) return;
        ks.createKit(name, items, armor, icon);
        Kit kit = ks.getKit(name);
        if (kit == null) return;
        kit.setDisplayName(display);
        kit.setEnabled(true);
        for (String s : Arrays.asList("NoHunger", "PearlCooldown", "BowShotIndicator", "HealthBar")) {
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
