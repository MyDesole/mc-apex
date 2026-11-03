"""Сканер сырых миров 1.8 (Anvil) без внешних зависимостей.

Ищет кровати (26), алмазные (57) и изумрудные (133) блоки, считает высоту поверхности.
Запуск: python3 -I mapscan.py <папка мира> -> JSON в stdout
"""
import json, os, struct, sys, zlib


def read_nbt(b):
    pos = 0

    def val(t):
        nonlocal pos
        if t == 1: pos += 1; return b[pos - 1]
        if t == 2: pos += 2; return struct.unpack('>h', b[pos - 2:pos])[0]
        if t == 3: pos += 4; return struct.unpack('>i', b[pos - 4:pos])[0]
        if t == 4: pos += 8; return 0
        if t == 5: pos += 4; return 0
        if t == 6: pos += 8; return 0
        if t == 7:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; pos += 4
            r = b[pos:pos + n]; pos += n; return r
        if t == 8:
            n = struct.unpack('>H', b[pos:pos + 2])[0]; pos += 2 + n; return None
        if t == 9:
            it = b[pos]; n = struct.unpack('>i', b[pos + 1:pos + 5])[0]; pos += 5
            return [val(it) for _ in range(n)]
        if t == 10:
            d = {}
            while True:
                tt = b[pos]; pos += 1
                if tt == 0: return d
                n = struct.unpack('>H', b[pos:pos + 2])[0]; pos += 2
                name = b[pos:pos + n].decode('utf8', 'replace'); pos += n
                d[name] = val(tt)
        if t == 11:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; pos += 4 + 4 * n; return None
        if t == 12:
            n = struct.unpack('>i', b[pos:pos + 4])[0]; pos += 4 + 8 * n; return None
    assert b[0] == 10
    pos = 3
    return val(10)


def chunks(region_dir):
    for f in sorted(os.listdir(region_dir)):
        if not f.endswith('.mca'): continue
        data = open(os.path.join(region_dir, f), 'rb').read()
        if len(data) < 8192: continue
        for i in range(1024):
            o = struct.unpack('>I', data[i * 4:i * 4 + 4])[0]
            if not o: continue
            off = (o >> 8) * 4096
            ln, comp = struct.unpack('>IB', data[off:off + 5])
            raw = data[off + 5:off + 4 + ln]
            try:
                raw = zlib.decompress(raw) if comp == 2 else __import__('gzip').decompress(raw)
                yield read_nbt(raw)['Level']
            except Exception:
                continue


def scan(world):
    beds, dia, em, top = [], [], [], {}
    for lv in chunks(os.path.join(world, 'region')):
        cx, cz = lv['xPos'], lv['zPos']
        for s in lv.get('Sections') or []:
            bl = s['Blocks']; sy = s['Y'] * 16
            for ids, out in ((26, beds), (57, dia), (133, em)):
                i = bl.find(bytes([ids]))
                while i != -1:
                    out.append((cx * 16 + (i & 15), sy + (i >> 8), cz * 16 + ((i >> 4) & 15)))
                    i = bl.find(bytes([ids]), i + 1)
            for i, v in enumerate(bl):
                if v:
                    k = (cx * 16 + (i & 15), cz * 16 + ((i >> 4) & 15))
                    y = sy + (i >> 8)
                    if top.get(k, -1) < y: top[k] = y
    return beds, dia, em, top


if __name__ == '__main__':
    b, d, e, t = scan(sys.argv[1])
    print(json.dumps(dict(beds=b, dia=d, em=e, top={'%d,%d' % k: v for k, v in t.items()})))
