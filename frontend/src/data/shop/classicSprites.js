/**
 * Мобы и боссы первых пяти подземелий (Катакомбы, Глубины, Кузня, Ледяной
 * чертог, Пустота), перерисованные вручную. Рисуем левую половину, правая
 * зеркалится: так строки всегда ровные. Мобы 12×12, боссы 14×14.
 * Ключи совпадают с Dungeons.php.
 */
const sym = (half) => half + [...half].reverse().join('')
const make = (palette, halves) => ({ palette, rows: halves.map(sym) })

export const CLASSIC_SPRITES = {
    /* ============================ МОБЫ ============================ */

    'mob-rat': make({ k: '#1b1722', g: '#8d8d9e', G: '#b9b9c7', e: '#ef4444', p: '#e5989b', w: '#f5f5f5' }, [
        '.kk...', 'kppk..', 'kpgkkk', 'kGgggg', 'kgeggg', 'kgggpp', '.kgkpp', '.kggww', '..kggg', '..kgg.', '.kgk..', '.kkk..',
    ]),
    'mob-bat': make({ k: '#150f20', v: '#3b2a55', V: '#6b4f93', e: '#ef4444', w: '#f5f5f5' }, [
        '....kk', 'k...kV', 'kk..kV', 'kVk.kv', 'kVVkve', 'kVVVvv', 'kVVkvw', 'kVk.kv', 'kk..kv', 'k...kv', '....kk', '......',
    ]),
    'mob-slime': make({ k: '#10260f', g: '#3fae3b', G: '#8be06b', e: '#10260f', w: '#f5f5f5' }, [
        '......', '..kkkk', '.kGGgg', '.kGggg', 'kGgggg', 'kgekgg', 'kgggkg', 'kgggww', 'kggggg', '.kgggg', '..kkkk', '......',
    ]),
    'mob-spider': make({ k: '#0d0d14', s: '#2f2f3d', S: '#55556b', r: '#ef4444', w: '#f5f5f5' }, [
        '......', 'k...kk', '.k.kss', '..ksSS', 'kkksrS', '..ksSS', 'kkkssS', '.k.kss', 'k..kSs', '...kss', '....kk', '......',
    ]),
    'mob-devourer': make({ k: '#1a0a22', p: '#5a2a6a', P: '#8a4aa0', r: '#d63a4a', w: '#f5f5f5', e: '#fde047' }, [
        '..kkkk', '.kppPP', 'kpPPPP', 'kPePPP', 'kPPPPP', 'kkwkwk', 'krrrrr', 'kkwkwk', 'kpPPPk', '.kpppp', '..kkkk', '......',
    ]),
    'mob-drowned': make({ k: '#0d1f1c', t: '#3f7a6a', T: '#6fb5a0', e: '#f5f5f5', c: '#2a4a5a', m: '#3a8a3a' }, [
        '..kkkk', '.kTTTT', '.kTTmT', '.kTeTT', '.kTTTT', '.kTkkk', '..kccc', '.kcmcc', 'kTkccc', 'kTk.cc', '.kk.kk', '......',
    ]),
    'mob-imp': make({ k: '#2a0a0a', r: '#c0392b', R: '#ef6a52', y: '#fde047', h: '#3a1a1a' }, [
        'h.....', 'hk..kk', '.kRRRR', '.kRyRR', '.kRRRR', '.kRkyk', '..kRRR', 'kkkrrr', 'kRkrrr', '.k.krr', '...kkk', '..kk..',
    ]),
    'mob-skeleton': make({ k: '#1f1d18', w: '#e8e4d4', W: '#ffffff', s: '#b0ac9c' }, [
        '..kkkk', '.kwwWW', '.kwwww', '.kwkkw', '.kwwwk', '..kwkw', '...kkk', '.kkwww', '.kwkkk', '.kkwww', '..kwkk', '..kk..',
    ]),
    'mob-ashhound': make({ k: '#1a110e', b: '#3a2a24', B: '#5a4236', o: '#f0701a', y: '#fde047' }, [
        '.kk...', 'kbBk..', 'kbbbkk', 'kbbbbb', 'kbyobb', 'kbbbbb', '.kbbbo', '..kkoo', '..kbbb', '.kbbbo', '.kbkbb', '.kkkkk',
    ]),
    'mob-forgespirit': make({ k: '#2a0f05', o: '#e8651a', O: '#ffb347', y: '#fff3b0', c: '#7a3a1a' }, [
        '....ky', '...koy', '..koOy', '..kOOO', '.koOyO', '.kOOOO', 'koOkyO', 'koOOOO', '.koOOO', '..kooO', '..kcco', '...kkc',
    ]),
    'mob-guard': make({ k: '#14161f', s: '#7f8aa0', S: '#b3bdd1', r: '#c0392b', e: '#fde047', d: '#4a5266' }, [
        '..rrkk', '.kssSS', '.ksSSS', '.kdkkk', '.kseSS', '.kssSS', '..kkkk', '.kSSss', 'kSkssd', 'kSksdd', '.kkddd', '..kkkk',
    ]),
    'mob-golem': make({ k: '#1c1a16', s: '#7a7468', S: '#a59e8e', m: '#4a8a3a', e: '#fb923c' }, [
        '..kkkk', '.ksSSS', '.kSSSS', '.kSeSS', '.kSSSS', 'kkSSsm', 'kSSSss', 'kSSsSS', 'kSsSSs', 'kSksSs', '.kk.kS', '.kk.kk',
    ]),
    'mob-frostskeleton': make({ k: '#0f1d2e', w: '#cfe8f5', W: '#ffffff', i: '#7fc4e8', e: '#38bdf8' }, [
        '..kkkk', '.kwwWi', '.kwwww', '.kwekk', '.kwwwi', '..kwkw', '...kkk', '.kkwwi', '.kwkkk', '.kkiww', '..kwkk', '..kk..',
    ]),
    'mob-iceslime': make({ k: '#0d2438', b: '#4aa8e0', B: '#a6e3ff', w: '#ffffff', e: '#0d2438' }, [
        '......', '..kkkk', '.kBBww', '.kBbbb', 'kBbbbb', 'kbekbb', 'kbbbkb', 'kbbbww', 'kbbbbb', '.kbbbb', '..kkkk', '......',
    ]),
    'mob-frostwolf': make({ k: '#101c2a', g: '#8aa4b8', G: '#cfe0ec', w: '#ffffff', e: '#38bdf8' }, [
        '.kk...', 'kGgk..', 'kGgggk', 'kgggGG', 'kgeggg', 'kgggww', '.kggkw', '..kkgg', '..kggg', '.kgggG', '.kgkgg', '.kkkkk',
    ]),
    'mob-yeti': make({ k: '#0f1d2e', w: '#e8f4fb', W: '#ffffff', b: '#8ab8d4', e: '#1e3a5f', f: '#fb7185' }, [
        '..kkkk', '.kwwWW', 'kwwwww', 'kwekWw', 'kwwwww', 'kwkffk', '.kwwww', 'kwwbww', 'kwwwbw', 'kwkwww', '.kbk.k', '.kkk.k',
    ]),
    'mob-icewitch': make({ k: '#0f1d2e', h: '#2a4a8a', H: '#4a78c8', s: '#f1d9c4', e: '#38bdf8', w: '#e8f4fb' }, [
        '....kk', '...khH', '..khHH', '.khhhH', '.kkkkk', '.ksesk', '.kssss', '..khww', '.khhHw', 'khhhhH', 'khhkhh', '.kkk.k',
    ]),
    'mob-wraith': make({ k: '#0f0f1c', c: '#4a4a78', C: '#8a8ac8', e: '#a5f3fc', d: '#2a2a48' }, [
        '..kkkk', '.kccCC', '.kcCCC', 'kcCeeC', 'kcCCCC', 'kccCkk', 'kccCCC', 'kcdcCC', '.kdcdc', '.kd.dc', '..k.kd', '......',
    ]),
    'mob-voideye': make({ k: '#0a0614', v: '#2a1a4a', V: '#5a3a9a', e: '#f5f5f5', i: '#d946ef', p: '#0a0614' }, [
        '..kkkk', '.kvVVV', 'kvVeee', 'kVeiii', 'kVeipp', 'kVeiii', 'kvVeee', '.kvVVV', '..kkvv', '.k.kv.', '.k.kv.', '......',
    ]),
    'mob-shade': make({ k: '#05050a', d: '#1a1a2e', D: '#34344f', e: '#ef4444', m: '#6a6a8a' }, [
        '..kkkk', '.kddDD', '.kdDDD', 'kdDeDD', 'kddDDD', 'kddDkk', 'kdddDD', 'kmddDD', '.kmddd', '.km.dd', '..k.kd', '......',
    ]),
    'mob-reaper': make({ k: '#0a0a12', c: '#232338', C: '#3d3d5c', e: '#7dd3fc', s: '#d1d5db', b: '#6b4a1c' }, [
        '..kkkk', '.kccCC', '.kcCCC', '.kCeCC', '.kcCCC', '.kcckk', 'kccCCC', 'kcCCCC', 'kccCCC', 'kcccCC', '.kcccc', '.kkkkk',
    ]),
    'mob-shardgolem': make({ k: '#1a0f2a', p: '#7a4ab8', P: '#c084fc', w: '#f5d0fe', e: '#fde047' }, [
        '..k.k.', '.kPkPk', '.kpPPP', 'kpPePP', 'kpPPPP', 'kkPwPw', 'kpPPww', 'kpPPPP', 'kppPPw', 'kpkpPP', '.kk.kp', '.kk.kk',
    ]),
    'mob-faceless': make({ k: '#0a0a10', c: '#3a3a4a', C: '#5a5a70', w: '#d1d5db', m: '#1a1a24' }, [
        '..kkkk', '.kccCC', '.kcCCC', '.kcCCC', '.kcCmC', '.kcCCC', '..kcCC', '.kwwcC', 'kwwkcC', 'kwk.cc', '.kk.kc', '......',
    ]),
    'mob-endcrawler': make({ k: '#0c0614', v: '#3a1a5a', V: '#7a3aaa', p: '#d946ef', e: '#fde047' }, [
        '......', '..kkkk', '.kvVVV', 'kvVeVV', 'kvVVVV', 'kvvVpp', 'kkvvVV', 'kVkvvV', 'kVk.kv', '.kk..k', '......', '......',
    ]),

    /* ============================ БОССЫ 14×14 ============================ */

    'boss-ratking': make({ k: '#1b1722', g: '#8d8d9e', G: '#b9b9c7', e: '#ef4444', p: '#e5989b', y: '#fbbf24', Y: '#fde68a', w: '#f5f5f5' }, [
        '..kyyyy', '..kyYYy', '.kyyyyy', '.kkkkkk', '.kppk..', 'kpgkkkk', 'kgggGgg', 'kgeggge', 'kgggggg', 'kggggpp', '.kgkwww', '.kkgggg', '..kgggg', '..kkkkk',
    ]),
    'boss-rotking': make({ k: '#14201a', g: '#5a7a3a', G: '#8aaa5a', b: '#8a6a3a', e: '#fde047', m: '#2a3a1a', y: '#d9b44a', w: '#e8e4d4' }, [
        '..kyyyy', '.kyyGyG', '.kkkkkk', '.kgGGGG', 'kggeGGG', 'kgGGGGG', 'kgmgkkk', 'kgmggww', '.kgggGG', 'kbbkgGG', 'kbgbkgg', 'kbgbkmg', '.kkkkgm', '..kkkkk',
    ]),
    'boss-elder': make({ k: '#1a1411', w: '#d8cfc0', W: '#ffffff', g: '#7a7a8a', G: '#3a9ad0', e: '#38bdf8', c: '#4a3a6a', C: '#6a5a9a', s: '#f1d9c4' }, [
        '..kwwww', '.kwWWWW', '.kwWsss', '.kwseGs', '.kwssss', '.kwwsww', 'kwwwwWw', 'kccCCCC', 'kcCCCCc', 'kcCkcCC', 'kcCkCCC', 'kcCCCCC', '.kccccc', '..kkkkk',
    ]),
    'boss-deep-lord': make({ k: '#06141c', t: '#1f6a7a', T: '#4ab3c8', b: '#0f3a4a', e: '#fde047', y: '#fbbf24', w: '#e0f7fa' }, [
        '..k.k.k', '.kyyyyy', '.kttTTT', 'kttTTTT', 'ktteTTT', 'kttTTTT', 'kkwkwkw', 'kttwkwk', '.kbttTT', 'kbbtTTt', 'kbbbkTT', 'kbkbkbk', 'kk.kk.k', '.......',
    ]),
    'boss-miner-giant': make({ k: '#1c1612', s: '#6a5a4a', S: '#a08a72', y: '#fbbf24', Y: '#fde047', e: '#f1d9c4', r: '#c0392b', i: '#9aa0aa' }, [
        '..kyyyy', '.kyYYYY', '.kkkkkk', '.kseeee', '.ksekee', '.kseeee', '.kssrrr', 'kSSkkkk', 'kSSSSSS', 'kiSSkSS', 'kiiSSSS', 'kiSkSkS', '.kkkSkS', '..kkkkk',
    ]),
    'boss-smith': make({ k: '#1a1410', s: '#8a8a94', S: '#bcbcc8', e: '#f1d9c4', b: '#5a3a1a', o: '#f0701a', y: '#fde047', a: '#3a3a44' }, [
        '..kssss', '.kssSSS', '.kseeee', '.kekeee', '.keeeee', '.kebbbb', '.kbbbbb', 'kaakbbb', 'kSakaaa', 'kSakaaa', 'kooakaa', 'kooaakk', '.kkakbb', '..kkkkk',
    ]),
    'boss-forgemaster': make({ k: '#1a0a05', a: '#4a4a55', A: '#7a7a88', o: '#f0701a', y: '#fde047', r: '#c0392b', e: '#fff3b0' }, [
        '..k.k.k', '.kAkAkA', '.kaAAAA', 'kaaAAAA', 'kaaeAAy', 'kaaAAAA', 'kkarrrr', 'kaakAAA', 'kAakaaa', 'kooakaa', 'kyoakAA', 'kooakAA', '.kkakaa', '..kkkkk',
    ]),
    'boss-lavalord': make({ k: '#1c0803', r: '#9a2a0a', o: '#f0701a', y: '#fde047', Y: '#fff3b0', b: '#3a1a10' }, [
        '.k.k.k.', 'kyk.kyk', 'kyokkyo', 'kooooyy', 'kooYooo', 'kooYooo', 'kkoooyy', 'korooor', 'krrooor', 'krrkoor', 'kbrkrrr', 'kbbkbrr', '.kbkbbb', '..kkkkk',
    ]),
    'boss-frostking': make({ k: '#0d1f33', i: '#7fc4e8', I: '#cfe8f5', w: '#ffffff', s: '#e5eef5', e: '#1e3a5f', c: '#2a5a8a', C: '#4a8ac8' }, [
        '..k.kk.', '.kIkIkI', '.kiIIII', '.kkkkkk', '.kssssw', '.ksessw', '.kssssw', '.kiwwii', 'kccCCCC', 'kcCCiCC', 'kcCkCCC', 'kcCkcCC', '.kcckcc', '..kkkkk',
    ]),
    'boss-icequeen': make({ k: '#0d1f33', i: '#7fc4e8', I: '#cfe8f5', w: '#ffffff', s: '#f6e3d8', h: '#f6e3d8', e: '#38bdf8', c: '#4a78c8', C: '#8ab8f0', p: '#f472b6' }, [
        '..k.k.k', '.kIkIkI', '.kiIIII', '.kkkkkk', '.ksssss', '.kseesh', '.kssssp', '..kiIww', '.kcCCCC', 'kcCCiCC', 'kcCCCCC', 'kccCCCC', 'kcccCCC', '.kkkkkk',
    ]),
    'boss-frostgiant': make({ k: '#0d1f33', s: '#6a8aa8', S: '#a8c8e0', w: '#ffffff', e: '#38bdf8', i: '#cfe8f5', b: '#3a5a7a', f: '#e8f4fb' }, [
        '..kkkkk', '.kfffff', '.ksSSSS', 'ksSeSSe', 'ksSSSSS', 'ksSSwww', 'kkSSSSS', 'kSSbbbb', 'kSSSSbb', 'kiSkSSb', 'kiiSkSS', 'kiikkSS', '.kkk.kS', '.......',
    ]),
    'boss-cryptkeeper': make({ k: '#0c0c10', c: '#2a2a3a', C: '#4a4a62', w: '#e0dccc', e: '#4ade80', g: '#fbbf24', b: '#6b4a1c' }, [
        '..kkkkk', '.kccCCC', '.kcCCCC', 'kcCkkkk', 'kcCkeke', 'kcCkwww', 'kcccCkw', 'kccCCCC', 'kcCCCCg', 'kcCbbCC', 'kccbCCC', 'kcCbCCC', '.kcccCc', '..kkkkk',
    ]),
    'boss-voidwarden': make({ k: '#07040f', v: '#2a1a4a', V: '#5a3a9a', p: '#d946ef', e: '#f5f5f5', a: '#1a1030', i: '#a78bfa' }, [
        '..k.k.k', '.kvkVkV', '.kvVVVV', 'kvVkkkk', 'kvVeepe', 'kvVkkkk', 'kkvVVVV', 'kaavVVV', 'kaiakVV', 'kaiakvV', 'kaaikvv', 'kaaikkv', '.kaa.kk', '..kk...',
    ]),
    'boss-worldeater': make({ k: '#0a0614', v: '#3a1a5a', V: '#7a3aaa', p: '#d946ef', w: '#f5f5f5', r: '#a02a5a', e: '#fde047' }, [
        '..kkkkk', '.kvvVVV', 'kvVVVVV', 'kvVeVVe', 'kvVVVVV', 'kkwkwkw', 'krrrrrr', 'kkwkwkw', 'kvVVVVp', 'kvvVVpp', '.kvvVpV', '..kvvVV', '...kkkk', '.......',
    ]),
    'boss-abyss': make({ k: '#05040a', d: '#14102a', D: '#2a2050', p: '#d946ef', i: '#a78bfa', e: '#f5f5f5', r: '#ef4444' }, [
        '..k.k.k', '.kdkdkd', 'kddDDDD', 'kdDDDDD', 'kdDeeee', 'kdDeipp', 'kdDeeee', 'kddDDDD', 'kdkDkDD', 'kd.kdkD', 'kk.kd.k', '..kd.kd', '.kd..kk', '.kk....',
    ]),
    'boss-waiter': make({ k: '#0f0f18', b: '#1a1a22', w: '#f5f5f5', s: '#f1d9c4', h: '#f1d9c4', e: '#ef4444', g: '#fbbf24', r: '#a02a2a', y: '#fde047' }, [
        '...kkkk', '..kbbbb', '..kbbbb', '.kkkkkk', '.ksssss', '.kseesh', '.ksssrr', '..kwwwk', '.kwwrwr', 'kwwwwwb', 'kwwkwwb', 'kwwkwgg', '.kbbkbb', '.kkkkkk',
    ]),
}
