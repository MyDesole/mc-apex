"""Переносит сырые миры 1.8 в регионы мира arenas (со сдвигом) и готовит арены Alley.

python3 -I mapimport.py <корень Duels> <выходная папка>
Результат: <out>/region/r.X.0.mca (положить в arenas/region при остановленном сервере)
           <out>/arenas_add.yml (дописать в arenas: plugins/Alley/storage/arenas.yml)
           <out>/spawn.txt (точка лобби)
"""
import collections, math, os, struct, sys, zlib
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import duelscan, mapscan

STANDARD = ['Arena', 'Complex', 'Courtyard v1', 'Fight Night', 'Spikerock Bay', 'Reef']
SUMO = ['Fort Royale', 'Ponsen', 'Space Mine', 'Sumo', 'White Crystal']
SPACING = 3  # регионов между картами
X0 = 12      # первый регион по X (блок 6144)


# ---------- полный NBT (сохраняет типы) ----------
def parse(b):
    pos = 0

    def rd(t):
        nonlocal pos
        if t in (1, 2, 3, 4, 5, 6):
            n = {1: 1, 2: 2, 3: 4, 4: 8, 5: 4, 6: 8}[t]
            v = b[pos:pos + n]; pos += n; return v
        if t == 7:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; v = b[pos:pos + 4 + n]; pos += 4 + n; return v
        if t == 8:
            n = struct.unpack('>H', b[pos:pos + 2])[0]; v = b[pos:pos + 2 + n]; pos += 2 + n; return v
        if t == 9:
            it = b[pos]; n = struct.unpack('>i', b[pos + 1:pos + 5])[0]; pos += 5
            return (it, [rd(it) for _ in range(n)])
        if t == 10:
            d = {}
            while True:
                tt = b[pos]; pos += 1
                if tt == 0: return d
                n = struct.unpack('>H', b[pos:pos + 2])[0]; pos += 2
                name = b[pos:pos + n]; pos += n
                d[name.decode('utf8', 'replace')] = (tt, rd(tt))
        if t == 11:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; v = b[pos:pos + 4 + 4 * n]; pos += 4 + 4 * n; return v
        if t == 12:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; v = b[pos:pos + 4 + 8 * n]; pos += 4 + 8 * n; return v
    pos = 3
    return rd(10)


def emit(t, v):
    if t in (1, 2, 3, 4, 5, 6, 7, 8, 11, 12): return v
    if t == 9:
        return bytes([v[0]]) + struct.pack('>i', len(v[1])) + b''.join(emit(v[0], x) for x in v[1])
    out = b''
    for k, (tt, vv) in v.items():
        kb = k.encode('utf8')
        out += bytes([tt]) + struct.pack('>H', len(kb)) + kb + emit(tt, vv)
    return out + b'\0'


def i32(v): return struct.unpack('>i', v)[0]
def pack(n): return struct.pack('>i', n)


def shift_chunk(lv, dcx, dcz):
    L = lv['Level'][1]
    L['xPos'] = (3, pack(i32(L['xPos'][1]) + dcx)); L['zPos'] = (3, pack(i32(L['zPos'][1]) + dcz))
    L['Entities'] = (9, (10, []))
    for key in ('TileEntities', 'TileTicks'):
        if key in L and L[key][1][1]:
            for e in L[key][1][1]:
                e['x'] = (3, pack(i32(e['x'][1]) + dcx * 16)); e['z'] = (3, pack(i32(e['z'][1]) + dcz * 16))
    return lv


def raw_chunks(path):
    rd = os.path.join(path, 'region')
    for f in sorted(os.listdir(rd)):
        if not f.endswith('.mca'): continue
        data = open(os.path.join(rd, f), 'rb').read()
        if len(data) < 8192: continue
        for i in range(1024):
            o = struct.unpack('>I', data[i * 4:i * 4 + 4])[0]
            if not o: continue
            off = (o >> 8) * 4096
            ln, comp = struct.unpack('>IB', data[off:off + 5])
            try:
                yield parse(zlib.decompress(data[off + 5:off + 4 + ln]))
            except Exception:
                continue


def _has_blocks(c):
    s = c['Level'][1].get('Sections')
    return bool(s) and any(any(sec['Blocks'][1][4:]) for sec in s[1][1])


def write_region(path, chunks):
    """chunks: {(lx, lz): nbt bytes}"""
    header = bytearray(8192); body = b''; sector = 2
    for (lx, lz), raw in chunks.items():
        z = zlib.compress(raw)
        rec = struct.pack('>IB', len(z) + 1, 2) + z
        pad = (-len(rec)) % 4096
        n = (len(rec) + pad) // 4096
        i = lx + lz * 32
        header[i * 4:i * 4 + 4] = struct.pack('>I', (sector << 8) | n)
        header[4096 + i * 4:4096 + i * 4 + 4] = struct.pack('>I', 1700000000)
        body += rec + b'\0' * pad; sector += n
    open(path, 'wb').write(bytes(header) + body)


def place(path, slot, out):
    """Копирует карту в регион X0+slot*SPACING. Возвращает (dx, dz) сдвиг в блоках."""
    chs = [c for c in raw_chunks(path) if _has_blocks(c)]
    xs = [i32(c['Level'][1]['xPos'][1]) for c in chs]; zs = [i32(c['Level'][1]['zPos'][1]) for c in chs]
    rx = X0 + slot * SPACING
    dcx, dcz = rx * 32 - min(xs), 0 - min(zs)
    assert max(xs) + dcx < rx * 32 + 32 and max(zs) + dcz < 32, 'карта больше региона'
    res = {}
    for c in chs:
        c = shift_chunk(c, dcx, dcz)
        L = c['Level'][1]
        res[(i32(L['xPos'][1]) - rx * 32, i32(L['zPos'][1]))] = b'\x0a\x00\x00' + emit(10, c)
    os.makedirs(out + '/region', exist_ok=True)
    write_region('%s/region/r.%d.0.mca' % (out, rx), res)
    return dcx * 16, dcz * 16


def spawns(w, mn, mx, gap):
    """Точки дуэли по самой частой высоте пола. Возвращает (pos1, pos2, center, floorY)."""
    cnt = collections.Counter(); cols = collections.defaultdict(list)
    r = 12 if gap <= 4 else 22  # карты центрированы в 0,0: пол ищем вокруг центра
    for x in range(max(mn[0], -r), min(mx[0], r) + 1):
        for z in range(max(mn[2], -r), min(mx[2], r) + 1):
            for y in range(mn[1] + 1, mx[1]):
                if w.standable(x, y, z):
                    cnt[y] += 1; cols[y].append((x, z))
    print('  levels', cnt.most_common(3))
    fy = max(cnt, key=lambda y: cnt[y])
    pts = cols[fy]
    cx = sum(p[0] for p in pts) / len(pts); cz = sum(p[1] for p in pts) / len(pts)
    ex = max(p[0] for p in pts) - min(p[0] for p in pts); ez = max(p[1] for p in pts) - min(p[1] for p in pts)
    ax = (1, 0) if ex >= ez else (0, 1)
    d = min(gap, max(ex, ez) / 4)

    def near(tx, tz):
        return min(pts, key=lambda p: (p[0] - tx) ** 2 + (p[1] - tz) ** 2)
    a = near(cx - ax[0] * d, cz - ax[1] * d); b = near(cx + ax[0] * d, cz + ax[1] * d)
    c = near(cx, cz)
    return a, b, c, fy


def fmt(x, y, z, yaw=0.0):
    return 'arenas:%.1f:%.1f:%.1f:%.1f:0.0' % (x, y, z, yaw)


def main():
    root, out = sys.argv[1], sys.argv[2]
    os.makedirs(out, exist_ok=True)
    jobs = [('Standard Duels Maps/Regular', n, 'std', 6) for n in STANDARD] + [('Sumo Duels Maps', n, 'sumo', 4) for n in SUMO]
    yml = []
    for slot, (cat, n, kind, gap) in enumerate(jobs):
        p = os.path.join(root, cat, n, n)
        w = duelscan.World(p)
        mn, mx = w.bbox()
        a, b, c, fy = spawns(w, mn, mx, 10 if kind == 'std' else gap)
        dx, dz = place(p, slot, out)
        yaw = lambda p1, p2: math.degrees(math.atan2(-(p2[0] - p1[0]), p2[1] - p1[1]))
        key = n.replace(' ', '')
        kits = ['Boxing', 'NoDebuff', 'Classic', 'Gapple'] if kind == 'std' else ['Sumo']
        S = lambda x, y, z: (x + dx, y, z + dz)
        mnS, mxS = S(mn[0], mn[1], mn[2]), S(mx[0] + 1, mx[1] + 1, mx[2] + 1)
        yml += ['  %s:' % key, '    type: SHARED',
                '    minimum: ' + fmt(mnS[0], mnS[1], mnS[2], 0), '    maximum: ' + fmt(mxS[0], mxS[1], mxS[2], 0),
                '    center: ' + fmt(c[0] + dx + 0.5, fy, c[1] + dz + 0.5),
                '    pos1: ' + fmt(a[0] + dx + 0.5, fy, a[1] + dz + 0.5, yaw(a, b)),
                '    pos2: ' + fmt(b[0] + dx + 0.5, fy, b[1] + dz + 0.5, yaw(b, a)), '    kits:'] + \
               ['    - ' + k for k in kits] + ['    enabled: true', '    display-name: §6' + n]
        print(key, 'floor', fy, 'pos1', a, 'pos2', b, flush=True)
    open(out + '/arenas_add.yml', 'w', encoding='utf8').write('\n'.join(yml) + '\n')
    # лобби: Duels Lobby v2 в следующем слоте, спавн - самая частая высота у центра
    lp = os.path.join(root, 'Lobby', 'Duels Lobby v2', 'Duels Lobby v2')
    w = duelscan.World(lp); mn, mx = w.bbox()
    a, b, c, fy = spawns(w, mn, mx, 10)
    dx, dz = place(lp, len(jobs), out)
    open(out + '/spawn.txt', 'w').write('arenas:%.1f:%.1f:%.1f:0.0:0.0\n' % (c[0] + dx + 0.5, fy, c[1] + dz + 0.5))
    print('lobby', mn, mx, 'spawn', open(out + '/spawn.txt').read())


if __name__ == '__main__':
    main()
