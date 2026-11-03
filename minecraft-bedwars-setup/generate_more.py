"""Конфиги арен BedWars1058 из сырых миров Hypixel: python3 -I generate_more.py <корень maps2/bw/Bedwars> <выходная папка>

Результат: <out>/Arenas/<имя>.yml и <out>/worlds/<имя>/ (готово к заливке на сервер).
Кровати берутся как есть, спавн и генераторы базы - в сторону центра карты от кровати.
"""
import math, os, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mapscan

# имя арены -> (подпапка относительно корня, группа, отображаемое имя, игроков в команде)
MAPS = {
    'bw_ruins': ('4v4 Maps/Ruins/Ruins', 'T2', 'Ruins', 4),
    'bw_picnic': ('4v4 Maps/Picnic/Picnic', 'T2', 'Picnic', 4),
    'bw_cryptic': ('4v4 Maps/Cryptic/Cryptic', 'T2', 'Cryptic', 4),
    'bw_gardens': ('4v4 Maps/Gardens/Gardens', 'T2', 'Gardens', 4),
    'bw_swashbuckle': ('Triples-Quads Maps/Regular/Swashbuckle/Swashbuckle', 'T4', 'Swashbuckle', 3),
    'bw_lectus': ('Triples-Quads Maps/Regular/Lectus/Lectus', 'T4', 'Lectus', 3),
    'bw_treenan': ('Triples-Quads Maps/Regular/Treenan/Treenan', 'T4', 'Treenan', 3),
    'bw_archway': ('Triples-Quads Maps/Regular/Archway/Archway', 'T4', 'Archway', 3),
    'bw_stonekeep': ('Triples-Quads Maps/Regular/Stonekeep/Stonekeep', 'T4', 'Stonekeep', 3),
    'bw_playground': ('Solo-Doubles Maps/Regular/Playground/Playground', 'T8', 'Playground', 2),
    'bw_waterfall': ('Solo-Doubles Maps/Regular/Waterfall/Waterfall', 'T8', 'Waterfall', 2),
    'bw_lotus': ('Solo-Doubles Maps/Regular/Lotus/Lotus', 'T8', 'Lotus', 2),
    'bw_glacier': ('Solo-Doubles Maps/Regular/Glacier/Glacier', 'T8', 'Glacier', 2),
    'bw_amazon': ('Solo-Doubles Maps/Regular/Amazon/Amazon', 'T8', 'Amazon', 2),
}
COLORS = ['RED', 'BLUE', 'GREEN', 'YELLOW', 'AQUA', 'WHITE', 'PINK', 'GRAY']


def isolated(pts, r=3):
    return [p for p in pts if not any(q is not p and abs(p[0] - q[0]) <= r and abs(p[1] - q[1]) <= r and abs(p[2] - q[2]) <= r for q in pts)]


def beds_of(raw):
    seen, out = set(), []
    for p in raw:
        if p in seen: continue
        seen.add(p)
        for q in raw:
            if q not in seen and p[1] == q[1] and abs(p[0] - q[0]) + abs(p[2] - q[2]) == 1:
                seen.add(q); break
        out.append(p)
    return out


def loc(p, yaw=0):
    return "%s,%s,%s,%s,0" % (p[0], p[1], p[2], round(yaw, 1))


def build(world, group, display, maxin):
    raw, dia, em, _ = mapscan.scan(world)
    beds = beds_of(raw)
    dia, em = isolated(dia), isolated(em)
    cx = sum(b[0] for b in beds) / len(beds); cz = sum(b[2] for b in beds) / len(beds)
    # порядок команд - по углу вокруг центра
    beds.sort(key=lambda b: math.atan2(b[2] - cz, b[0] - cx))
    L = ["group: %s" % group, "display-name: %s (%d)" % (display, len(beds)), "minPlayers: 2", "maxInTeam: %d" % maxin,
         "allowSpectate: true", "spawn-protection: 5", "shop-protection: 1", "upgrades-protection: 1", "generator-protection: 1",
         "island-radius: 14", "worldBorder: 600", "y-kill-height: 0", "max-build-y: 200", "disable-generator-for-empty-teams: false",
         "disable-npcs-for-empty-teams: true", "vanilla-death-drops: false", "use-bed-hologram: true", "allow-map-break: false",
         "game-rules:", "- doDaylightCycle:false", "- announceAdvancements:false", "- doInsomnia:false", "- doImmediateRespawn:true",
         "- doWeatherCycle:false", "- doFireTick:false"]
    teams = []
    for i, b in enumerate(beds):
        dx, dz = cx - b[0], cz - b[2]
        n = math.hypot(dx, dz) or 1
        ux, uz = dx / n, dz / n
        yaw = math.degrees(math.atan2(-ux, uz))
        at = lambda d: (round(b[0] + ux * d + 0.5, 1), float(b[1]), round(b[2] + uz * d + 0.5, 1))
        sp = at(4)
        sh = (round(sp[0] - uz * 3, 1), sp[1], round(sp[2] + ux * 3, 1))
        up = (round(sp[0] + uz * 3, 1), sp[1], round(sp[2] - ux * 3, 1))
        gen = at(7)
        teams.append((COLORS[i % 8], b, sp, sh, up, gen, yaw))
    t0 = teams[0][2]
    L += ["waiting:", '  Loc: "%s"' % loc((t0[0], t0[1] + 0.5, t0[2]), teams[0][6]), "Team:"]
    for c, b, sp, sh, up, gen, yaw in teams:
        L += ["  %s:" % c.capitalize(), "    Color: %s" % c, '    Spawn: "%s"' % loc(sp, yaw), '    Bed: "%s"' % loc(b),
              '    Shop: "%s"' % loc(sh, yaw), '    Upgrade: "%s"' % loc(up, yaw), '    Iron: "%s"' % loc(gen), '    Gold: "%s"' % loc(gen)]
    L.append("generator:")
    for k, v in (("Diamond", dia), ("Emerald", em)):
        L.append("  %s:" % k)
        for p in v: L.append('    - "%s"' % loc((p[0] + 0.5, p[1] + 1.0, p[2] + 0.5)))
    return "\n".join(L) + "\n", len(beds), len(dia), len(em)


if __name__ == '__main__':
    root, out = sys.argv[1], sys.argv[2]
    os.makedirs(out + '/Arenas', exist_ok=True)
    for name, (sub, group, display, maxin) in MAPS.items():
        src = os.path.join(root, sub)
        txt, nb, nd, ne = build(src, group, display, maxin)
        print(name, 'teams', nb, 'dia', nd, 'em', ne, flush=True)
        open('%s/Arenas/%s.yml' % (out, name), 'w', encoding='utf8').write(txt)
        dst = '%s/worlds/%s' % (out, name)
        shutil.rmtree(dst, ignore_errors=True)
        shutil.copytree(src, dst, ignore=shutil.ignore_patterns('playerdata', 'stats', 'DIM*', 'WorldDownloader.txt', 'level.dat_old', 'session.lock', 'data', 'uid.dat'))
