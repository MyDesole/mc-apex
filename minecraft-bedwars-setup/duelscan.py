"""Анализ дуэльных карт: bbox, пол, точки спавна, порталы-цели.
python3 -I duelscan.py <мир>
"""
import collections, sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import mapscan


class World:
    def __init__(self, path):
        self.ch = {}
        for lv in mapscan.chunks(os.path.join(path, 'region')):
            secs = {}
            for s in lv.get('Sections') or []:
                secs[s['Y']] = s['Blocks']
            if secs:
                self.ch[(lv['xPos'], lv['zPos'])] = secs

    def get(self, x, y, z):
        c = self.ch.get((x >> 4, z >> 4))
        if not c or y < 0: return 0
        b = c.get(y >> 4)
        return b[((y & 15) << 8) | ((z & 15) << 4) | (x & 15)] if b else 0

    def bbox(self):
        mn = [10 ** 9] * 3; mx = [-10 ** 9] * 3
        for (cx, cz), secs in self.ch.items():
            for sy, b in secs.items():
                if not any(b): continue
                for i, v in enumerate(b):
                    if v:
                        x = cx * 16 + (i & 15); y = sy * 16 + (i >> 8); z = cz * 16 + ((i >> 4) & 15)
                        if x < mn[0]: mn[0] = x
                        if x > mx[0]: mx[0] = x
                        if y < mn[1]: mn[1] = y
                        if y > mx[1]: mx[1] = y
                        if z < mn[2]: mn[2] = z
                        if z > mx[2]: mx[2] = z
        return mn, mx

    def find(self, ids):
        out = []
        for (cx, cz), secs in self.ch.items():
            for sy, b in secs.items():
                for i, v in enumerate(b):
                    if v in ids:
                        out.append((cx * 16 + (i & 15), sy * 16 + (i >> 8), cz * 16 + ((i >> 4) & 15), v))
        return out

    def standable(self, x, y, z):
        """y - уровень ног: под ногами твёрдый блок, две клетки воздуха."""
        f = self.get(x, y - 1, z)
        return f not in (0, 8, 9, 10, 11, 18, 31, 78, 106, 166) and self.get(x, y, z) == 0 and self.get(x, y + 1, z) == 0

    def surface(self, x, z, ymin, ymax):
        """Самая высокая стоячая точка в колонке в диапазоне."""
        for y in range(ymax, ymin, -1):
            if self.standable(x, y, z): return y
        return None


def analyse(path):
    w = World(path)
    mn, mx = w.bbox()
    return w, mn, mx


if __name__ == '__main__':
    w, mn, mx = analyse(sys.argv[1])
    print(mn, mx)
    ids = collections.Counter(b[3] for b in w.find({119, 90, 7, 120, 120, 165}))
    print(ids)
