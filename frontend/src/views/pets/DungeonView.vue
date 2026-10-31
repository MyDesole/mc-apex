<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { dungeonApi } from '@/services/pets/dungeon.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

/**
 * Подземелья: пошаговый бой отрядом питомцев.
 *
 * Каждый раунд игрок выбирает приём каждому живому питомцу, потом все
 * ходят по скорости. Стихия питомца решает, чем он полезен: вода лечит
 * отряд, тень крадёт здоровье, зверь баффает атаку, огонь жжёт. Связки
 * стихий дают супер-контру против стихии врага.
 */
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const data = ref(null)
const selected = ref('')
const squad = ref([])
const autoPicked = ref(false)
const moves = reactive({})

/** Итог последнего похода: показывается окном, пока игрок не закроет. */
const result = ref(null)

const KEY_NAMES = { seeker: 'Сундук искателя', pets: 'Сундук питомцев' }

/** Как закончился поход — словами, а не статусом. */
const verdict = computed(() => {
    const row = result.value

    if (!row) return null

    if (row.status === 'cleared') {
        return {
            tone: 'win',
            title: 'Подземелье зачищено',
            text: `Все ${row.floors} этажей пройдены, все боссы повержены. Отряд вышел с добычей.`,
        }
    }

    if (row.status === 'wiped') {
        const paid = Number(row.loot?.penalty ?? 0)

        return {
            tone: 'lose',
            title: 'Отряд пал',
            text: paid > 0
                ? `Дошли до этажа ${row.floor}, зал ${row.room}. Найденное осталось, но за поражение списали ${paid} ₳.`
                : `Дошли до этажа ${row.floor}, зал ${row.room}. Найденное осталось при вас.`,
        }
    }

    return {
        tone: 'retreat',
        title: 'Отряд отступил',
        text: `Дошли до этажа ${row.floor}, зал ${row.room}. Отступление бесплатно: теряется только поход.`,
    }
})

/** Штраф за поражение списывается только при вайпе. */
const penalty = computed(() =>
    result.value?.status === 'wiped' ? Number(result.value.loot?.penalty ?? 0) : 0,
)

/** Полный штраф по ступени: может быть больше, чем было на счету. */
const fullPenalty = computed(() => Number(result.value?.loot?.penalty_full ?? penalty.value))

/** Ключи в добыче: схлопываем одинаковые в «× N». */
const resultKeys = computed(() => {
    const counts = {}

    for (const slug of result.value?.loot?.keys ?? []) {
        counts[slug] = (counts[slug] ?? 0) + 1
    }

    return Object.entries(counts).map(([slug, count]) => ({
        slug,
        count,
        name: KEY_NAMES[slug] ?? 'Ключ от кейса',
    }))
})

const resultGear = computed(() => result.value?.loot?.items ?? [])

const run = computed(() => data.value?.run ?? null)
const battle = computed(() => run.value?.battle ?? null)
const phase = computed(() => run.value?.phase ?? 'idle')
const finished = computed(() => run.value && run.value.status !== 'active')
const pets = computed(() => data.value?.pets ?? [])
const dungeons = computed(() => data.value?.dungeons ?? [])
const ref_ = computed(() => data.value?.reference ?? { elements: {}, abilities: {}, combos: {} })

const elements = computed(() => ref_.value.elements ?? {})
const abilityOf = (element) => ref_.value.abilities?.[element] ?? null
const elementName = (key) => elements.value[key]?.name ?? key
const elementColor = (key) => elements.value[key]?.color ?? '#a8a3b8'

const readyPets = computed(() => {
    const dungeon = dungeons.value.find((d) => d.key === selected.value)

    if (!dungeon) return []

    return pets.value.filter((pet) => !pet.busy && pet.level >= dungeon.min_level)
})

const activeDungeon = computed(() => dungeons.value.find((d) => d.key === selected.value) ?? null)
const canStart = computed(() => Boolean(selected.value) && squad.value.length >= 2)

/** Связка отряда совпадает со стихией врага — значит контрит. */
const comboCounters = computed(() => {
    const combo = battle.value?.combo

    return Boolean(combo) && combo.counters === battle.value?.foe?.element
})

const sh = (unit) => Math.max(0, Math.round((unit.hp / Math.max(1, unit.max)) * 100))

/* ---------- Сцена: кто на ней ---------- */

const inBattle = computed(() => phase.value === 'battle')

/** Противник: в бою — из состояния боя, иначе — из ожидания. */
const sceneFoe = computed(() => battle.value?.foe ?? run.value?.pending ?? null)

/** Бойцы: в бою с яростью и кулдаунами, вне боя — просто отряд. */
const sceneUnits = computed(() => battle.value?.units ?? run.value?.members ?? [])

/** Карта спуска: этажи и залы, по одному боссу в конце этажа. */
const floorPlan = computed(() => {
    const row = run.value

    if (!row) return []

    const perFloor = (row.per_floor ?? 1) + 1
    const floors = []
    let index = 0

    for (let floor = 1; floor <= row.floors; floor++) {
        const rooms = []

        for (let i = 0; i < perFloor; i++) {
            index++
            rooms.push({ index, boss: i === perFloor - 1 })
        }

        floors.push({ number: floor, rooms })
    }

    return floors
})

/** Кого сейчас ведём: приёмы показываем для него. */
const activeId = ref(null)

const activeUnit = computed(() => {
    const units = sceneUnits.value

    return units.find((unit) => unit.id === activeId.value) ?? units.find((unit) => unit.hp > 0) ?? units[0] ?? null
})

/** Выбрал ли питомец приём в этом раунде. */
const moved = (unit) => Boolean(moves[unit.id]) && inBattle.value

/** Последние строки боя — как подпись на арене. */
const caption = computed(() => (battle.value?.log ?? run.value?.log ?? []).slice(-4))

/** Пройден / текущий / впереди. */
function roomState(room) {
    const current = run.value?.room ?? 0

    if (room.index === current) return 'is-current'

    return room.index < current ? 'is-passed' : 'is-ahead'
}
const foeShare = computed(() => {
    const foe = battle.value?.foe

    return foe ? Math.max(0, Math.round((foe.hp / Math.max(1, foe.max)) * 100)) : 0
})

/*
 * Где стоит узел подземелья на карте. Холст 160×70 — одинаковые
 * пропорции у любого экрана, поэтому тропа не разъезжается.
 */
const MAP_NODES = {
    catacombs: { x: 18, y: 48 },
    sunken: { x: 50, y: 24 },
    forge: { x: 82, y: 50 },
    crypt: { x: 116, y: 22 },
    void: { x: 146, y: 46 },
}

const MAP_W = 160
const MAP_H = 70

/** Положение узла в процентах контейнера. */
function nodeStyle(dungeon, index) {
    const point = MAP_NODES[dungeon.key] ?? { x: 20 + index * 30, y: 34 }

    return {
        left: `${(point.x / MAP_W) * 100}%`,
        top: `${(point.y / MAP_H) * 100}%`,
    }
}

/** Тропа: ломаная через узлы в порядке ступеней. */
const mapTrail = computed(() => {
    const points = dungeons.value
        .map((dungeon, index) => MAP_NODES[dungeon.key] ?? { x: 20 + index * 30, y: 34 })
        .map((point) => `${point.x},${point.y}`)

    return points.join(' ')
})

/** Выбранное подземелье — карточка под картой. */
const selectedDungeon = computed(
    () => dungeons.value.find((dungeon) => dungeon.key === selected.value) ?? null,
)

/** Бусины залов на карточке: мобы и босс в конце каждого этажа. */
function gateBeads(dungeon) {
    const perFloor = (dungeon.per_floor ?? 1) + 1
    const beads = []

    for (let floor = 1; floor <= dungeon.floors; floor++) {
        for (let i = 0; i < perFloor; i++) beads.push({ boss: i === perFloor - 1 })
    }

    return beads
}

/** Сколько слотов экипировки занято у питомца. */
const gearCount = (pet) => Object.keys(pet.gear ?? {}).length

function toggle(id) {
    autoPicked.value = false

    const at = squad.value.indexOf(id)

    if (at >= 0) {
        squad.value.splice(at, 1)
    } else if (squad.value.length < 3) {
        squad.value.push(id)
    }
}

async function load() {
    loading.value = true
    error.value = ''

    try {
        data.value = await dungeonApi.overview()

        if (!selected.value && dungeons.value.length) {
            selected.value = dungeons.value[0].key
        }

        // Возвращаем отряд, с которым ходили в прошлый раз
        if (!squad.value.length) {
            const saved = readSquad()

            squad.value = saved.filter((id) => pets.value.some((pet) => pet.inventory_id === id)).slice(0, 3)
        }

        /*
         * Страховка: если ответ на ход потерялся, сводка всё равно знает,
         * чем кончился последний поход. Показываем один раз.
         */
        const last = data.value?.last_result

        if (last && !result.value) {
            let seen = null

            try {
                seen = localStorage.getItem(SEEN_KEY)
            } catch {
                seen = null
            }

            if (String(seen) !== String(last.id)) result.value = last
        }

        // По умолчанию каждый бьёт атакой
        for (const unit of battle.value?.units ?? []) {
            if (unit.hp > 0 && !moves[unit.id]) moves[unit.id] = 'attack'
        }
    } catch (e) {
        error.value = e.message ?? 'Не удалось открыть подземелья'
    } finally {
        loading.value = false
    }
}

async function act(call) {
    busy.value = true
    error.value = ''

    try {
        const outcome = await call()

        /*
         * Поход закончился — показываем окно итогов. Перезагружать сводку
         * нельзя: закрытый поход в неё не попадает, и результат терялся.
         */
        if (outcome && outcome.status && outcome.status !== 'active') {
            result.value = outcome

            if (data.value) data.value.run = null

            return
        }

        await load()
    } catch (e) {
        error.value = e.message ?? 'Не получилось'
    } finally {
        busy.value = false
    }
}

/** Запомнить, что этот итог уже видели: окно не всплывёт снова. */
function markSeen() {
    if (!result.value) return

    try {
        localStorage.setItem(SEEN_KEY, String(result.value.id))
    } catch {
        /* приватный режим — покажем ещё раз, не страшно */
    }
}

/** Закрыть окно итогов и вернуться к выбору подземелья. */
function closeResult() {
    markSeen()

    result.value = null
    load()
}

const enter = () => act(async () => {
    saveSquad(squad.value)

    const started = await dungeonApi.start(selected.value, squad.value)

    squad.value = []

    return started
})

const enterRoom = () => act(async () => {
    for (const key of Object.keys(moves)) delete moves[key]

    return dungeonApi.advance(run.value.id)
})

const turn = () => act(async () => {
    const payload = {}

    for (const unit of battle.value?.units ?? []) {
        if (unit.hp > 0) payload[unit.id] = moves[unit.id] ?? 'attack'
    }

    return dungeonApi.move(run.value.id, payload)
})

const retreat = () => act(() => dungeonApi.retreat(run.value.id))

const buyRun = () => act(() => dungeonApi.buyRun())

const moveLabel = (key) => ref_.value.move_names?.[key] ?? key

/* Иконки приёмов — те же, что на арене */
const MOVE_ICONS = { attack: 'sword', skill: 'sparkles', defend: 'shield', ultimate: 'flame' }

const moveIcon = (key) => MOVE_ICONS[key] ?? 'sword'

/** Название приёма: у умения и ультимейта — своё, по стихии. */
function moveName(key, unit) {
    if (key === 'skill') return abilityOf(unit.element)?.skill?.name ?? 'Умение'
    if (key === 'ultimate') return abilityOf(unit.element)?.ultimate?.name ?? 'Ультимейт'

    return moveLabel(key)
}

/** Подпись под приёмом: кулдаун, ярость или эффект. */
function moveHint(key, unit) {
    if (key === 'skill') return unit.cd > 0 ? `через ${unit.cd}` : 'умение'
    if (key === 'ultimate') return unit.rage >= 100 ? 'готово!' : `ярость ${unit.rage}%`
    if (key === 'defend') return '−50% урона'

    return 'удар'
}

/* ---------- Запоминание отряда ---------- */

const SQUAD_KEY = 'apex.dungeon.squad'

/** Какой итог уже посмотрели — чтобы окно не всплывало повторно. */
const SEEN_KEY = 'apex.dungeon.seen'

function saveSquad(ids) {
    try {
        localStorage.setItem(SQUAD_KEY, JSON.stringify(ids))
    } catch {
        /* приватный режим — просто не запомним */
    }
}

function readSquad() {
    try {
        const parsed = JSON.parse(localStorage.getItem(SQUAD_KEY) ?? '[]')

        return Array.isArray(parsed) ? parsed.map(Number).filter(Boolean) : []
    } catch {
        return []
    }
}

/**
 * Подобрать лучший отряд: перебор пар и троек.
 *
 * Считаем силу питомцев, плюс большой бонус, если связка из отряда
 * контрит стихию, которая в этом подземелье встречается. Отряд из
 * четырёх-пятнадцати питомцев — это максимум 455 вариантов, считаем прямо
 * в браузере.
 */
function bestSquad() {
    const pool = readyPets.value

    if (pool.length < 2) return

    const dungeonElements = new Set(activeDungeon.value?.elements ?? [])
    const combos = Object.values(ref_.value.combos ?? {})

    const score = (picked) => {
        const power = picked.reduce((sum, pet) => sum + (pet.power ?? 0), 0)
        const combo = combos.some(
            (c) => picked.some((p) => p.element === c.pair[0])
                && picked.some((p) => p.element === c.pair[1])
                && dungeonElements.has(c.counters),
        )

        return power + (combo ? 400 : 0)
    }

    let best = null

    const walk = (from, acc) => {
        for (let i = from; i < pool.length; i++) {
            const next = [...acc, pool[i]]

            if (next.length >= 2 && (!best || score(next) > score(best))) best = next
            if (next.length < 3) walk(i + 1, next)
        }
    }

    walk(0, [])

    if (best) {
        squad.value = best.map((pet) => pet.inventory_id)
        autoPicked.value = true
    }
}

onMounted(load)
</script>

<template>
    <div class="dgn">
        <header class="dgn-head">
            <div>
                <RouterLink to="/pets" class="dgn-back"><span aria-hidden="true">←</span> Мои питомцы</RouterLink>
                <span class="dgn-kicker">Подземелья</span>
                <h1>Поход отрядом</h1>
                <p class="dgn-sub">
                    Пошаговый бой: каждый раунд — приём каждому питомцу. Стихия решает,
                    чем питомец полезен отряду, а связка стихий бьёт врага сильнее.
                </p>
            </div>

            <div v-if="data" class="dgn-stats">
                <span class="dgn-stat">
                    <b>{{ data.runs_left }}</b>
                    <small>походов сегодня{{ data.extra_bought ? ' · +' + data.extra_bought : '' }}</small>
                </span>
                <span class="dgn-stat"><b>{{ data.squad_min }}–{{ data.squad_max }}</b><small>в отряде</small></span>
                <span class="dgn-stat"><b>{{ Object.keys(ref_.combos ?? {}).length }}</b><small>связок</small></span>
                <button
                    class="dgn-buy"
                    :class="{ 'is-hot': data.runs_left === 0 }"
                    type="button"
                    :disabled="busy"
                    @click="buyRun"
                >
                    <AppIcon icon="rocket" :size="13" /> Ещё поход · ₳ {{ data.next_run_price }}
                </button>
            </div>
        </header>

        <p v-if="error" class="dgn-error">{{ error }}</p>
        <p v-if="loading" class="dgn-loading">Загрузка…</p>

        <!-- ================= Итог похода ================= -->
        <div v-if="result && verdict" class="dgn-modal" @click.self="closeResult">
            <div class="dgn-modal__card" :class="`is-${verdict.tone}`">
                <header class="dgn-modal__head">
                    <span class="dgn-modal__art">
                        <PixelSprite
                            :sprite="result.emblem"
                            :size="42"
                            :anim="verdict.tone === 'win' ? 'bounce' : null"
                        />
                    </span>
                    <div>
                        <span class="dgn-modal__kicker">{{ result.name }}</span>
                        <h2>{{ verdict.title }}</h2>
                        <p>{{ verdict.text }}</p>
                    </div>
                </header>

                <div class="dgn-modal__stats">
                    <span><b>{{ result.room }}</b><small>зал</small></span>
                    <span><b>{{ result.floor }} / {{ result.floors }}</b><small>этаж</small></span>
                    <span><b>{{ result.floors }}</b><small>боссов</small></span>
                </div>

                <div class="dgn-modal__loot">
                    <div class="dgn-modal__coins">
                        <AppIcon icon="coin" :size="20" />
                        <b>+{{ result.loot.coins }}</b>
                        <small>ApexCoin уже на кошельке</small>
                    </div>

                    <div v-if="penalty > 0" class="dgn-modal__fine">
                        <span class="dgn-modal__label">Штраф за поражение</span>
                        <b>−{{ penalty }} ₳</b>
                        <small v-if="penalty < fullPenalty">
                            Долг {{ fullPenalty }} ₳, списали сколько было на счету
                        </small>
                    </div>

                    <p v-else-if="result.status === 'retreat'" class="dgn-hint">
                        Отступил без потерь — потрачен только поход.
                    </p>

                    <div v-if="resultGear.length" class="dgn-modal__items">
                        <span class="dgn-modal__label">Экипировка</span>
                        <div
                            v-for="item in resultGear"
                            :key="item.inventory_id"
                            class="dgn-modal__item"
                            :class="`dgn-rar-${item.rarity}`"
                        >
                            <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="24" />
                            <div class="dgn-modal__item-body">
                                <span class="dgn-modal__item-name">{{ item.name }}</span>
                                <small>{{ item.slot_name }} · {{ item.rarity_name }}</small>
                            </div>
                            <div class="dgn-modal__item-stats">
                                <span v-if="item.atk"><AppIcon icon="sword" :size="11" /> +{{ item.atk }}</span>
                                <span v-if="item.def"><AppIcon icon="shield" :size="11" /> +{{ item.def }}</span>
                                <span v-if="item.hp"><AppIcon icon="heart" :size="11" /> +{{ item.hp }}</span>
                                <span v-if="item.spd">
                                    <AppIcon icon="bolt" :size="11" /> {{ item.spd > 0 ? '+' : '' }}{{ item.spd }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="resultKeys.length" class="dgn-modal__keys">
                        <span class="dgn-modal__label">Ключи</span>
                        <div v-for="key in resultKeys" :key="key.slug" class="dgn-modal__key">
                            <AppIcon icon="box" :size="15" />
                            <span>{{ key.name }}</span>
                            <b>× {{ key.count }}</b>
                        </div>
                    </div>

                    <p v-if="!resultGear.length && !resultKeys.length" class="dgn-hint">
                        Экипировка и ключи не выпали — но монеты начислены.
                    </p>
                </div>

                <footer class="dgn-modal__foot">
                    <button type="button" class="dgn-btn dgn-btn--main" @click="closeResult">Новый поход</button>
                    <RouterLink to="/pets?tab=gear" class="dgn-btn" @click="markSeen">
                        Надеть экипировку
                    </RouterLink>
                </footer>
            </div>
        </div>

        <!-- ================= ПОХОД ================= -->
        <section v-if="run" class="dgn-run" :class="`dgn-tone-${run.tone}`">
            <!-- Карта спуска -->
            <div class="dgn-path">
                <div v-for="floor in floorPlan" :key="floor.number" class="dgn-path__floor">
                    <span class="dgn-path__label">{{ floor.number }}</span>
                    <div class="dgn-path__rooms">
                        <span
                            v-for="room in floor.rooms"
                            :key="room.index"
                            class="dgn-path__room"
                            :class="[roomState(room), { 'is-boss': room.boss }]"
                        >
                            <PixelSprite
                                v-if="room.index === run.room && sceneFoe"
                                :sprite="sceneFoe.sprite"
                                :size="20"
                            />
                            <AppIcon v-else-if="room.boss" icon="crown" :size="13" />
                            <span v-else-if="roomState(room) === 'is-passed'" class="dgn-path__check">✓</span>
                            <span v-else class="dgn-path__dot"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Сцена -->
            <div class="dgn-stage" :class="{ 'is-boss': sceneFoe?.is_boss }">
                <div v-if="sceneFoe" class="dgn-hud">
                    <div class="dgn-hud__top">
                        <i class="dgn-hud__el" :style="{ background: elementColor(sceneFoe.element) }"></i>
                        <strong>{{ sceneFoe.name }}</strong>
                        <span class="dgn-hud__tag" :class="{ 'is-boss': sceneFoe.is_boss }">
                            {{ sceneFoe.is_boss ? 'босс' : 'моб' }}
                        </span>
                        <small>{{ sceneFoe.hp }} / {{ sceneFoe.max }}</small>
                    </div>

                    <div class="dgn-hud__bar">
                        <i :style="{ width: `${foeShare}%` }"></i>
                    </div>

                    <div class="dgn-hud__tags">
                        <span class="dgn-tag">{{ elementName(sceneFoe.element) }}</span>
                        <span class="dgn-tag dgn-tag--trait" :title="sceneFoe.trait_text">
                            {{ sceneFoe.trait_name }}
                        </span>
                        <span
                            v-for="(rounds, key) in (sceneFoe.statuses ?? {})"
                            :key="key"
                            class="dgn-tag dgn-tag--fx"
                        >
                            {{ ref_.statuses?.[key]?.name ?? key }} {{ rounds }}
                        </span>
                    </div>
                </div>

                <div class="dgn-arena-row">
                <!-- Отряд слева -->
                <div class="dgn-side dgn-side--me">
                    <div
                        v-for="unit in sceneUnits"
                        :key="unit.inventory_id"
                        class="dgn-fig"
                        :class="{ 'is-down': unit.hp <= 0, 'is-active': unit.id === activeUnit?.id && inBattle }"
                        :style="{ '--el': elementColor(unit.element) }"
                        @click="inBattle && (activeId = unit.id)"
                    >
                        <div class="dgn-fig__art">
                            <PetFigure :species="unit.species" :traits="unit.traits" :size="72" />
                        </div>

                        <span class="dgn-fig__name">{{ unit.name }}</span>

                        <div class="dgn-fig__hp">
                            <i :style="{ width: `${sh(unit)}%` }"></i>
                            <small>{{ unit.hp }}</small>
                        </div>

                        <div v-if="inBattle" class="dgn-fig__rage">
                            <i :style="{ width: `${unit.rage}%` }"></i>
                        </div>

                        <div v-if="unit.gear && unit.gear.length" class="dgn-fig__gear" title="Экипировка">
                            <span v-for="item in unit.gear" :key="item.inventory_id" class="dgn-fig__slot">
                                <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="20" />
                            </span>
                        </div>
                    </div>
                </div>

                <span class="dgn-vs" aria-hidden="true">VS</span>

                <!-- Противник справа -->
                <div v-if="sceneFoe" class="dgn-side dgn-side--foe">
                    <div class="dgn-fig dgn-fig--foe" :class="{ 'is-boss': sceneFoe.is_boss }">
                        <div class="dgn-fig__art">
                            <PixelSprite
                                :sprite="sceneFoe.sprite"
                                :size="sceneFoe.is_boss ? 148 : 118"
                                :anim="sceneFoe.is_boss ? 'glow' : 'bounce'"
                                :glow="sceneFoe.is_boss ? 'rgba(251,191,36,.85)' : null"
                                :flip="true"
                            />
                        </div>
                    </div>
                </div>
                </div>

                <ul v-if="caption.length" class="dgn-caption">
                    <li v-for="(entry, i) in caption" :key="i" :class="`is-${entry.kind}`">{{ entry.text }}</li>
                </ul>
            </div>

            <!-- Текущий итог похода -->
            <div v-if="finished" class="dgn-result" :class="`dgn-result--${run.status}`">
                <strong v-if="run.status === 'cleared'">Подземелье зачищено</strong>
                <strong v-else-if="run.status === 'wiped'">Отряд пал</strong>
                <strong v-else>Отряд отступил</strong>
                <p>Монет: <b>{{ run.loot.coins }}</b> · предметов: <b>{{ run.loot.gear.length }}</b></p>
            </div>

            <!-- Полоса питомцев: чьи приёмы выбираем -->
            <div v-if="inBattle && !finished" class="dgn-turns">
                <button
                    v-for="unit in sceneUnits"
                    :key="unit.inventory_id"
                    type="button"
                    class="dgn-turn"
                    :class="{ 'is-on': unit.id === activeUnit?.id, 'is-done': moved(unit), 'is-down': unit.hp <= 0 }"
                    :style="{ '--el': elementColor(unit.element) }"
                    :disabled="unit.hp <= 0"
                    @click="activeId = unit.id"
                >
                    <PetFigure :species="unit.species" :traits="unit.traits" :size="26" :backdrop="false" />
                    <span>{{ unit.name }}</span>
                    <AppIcon v-if="moved(unit)" icon="check" :size="12" />
                </button>
            </div>

            <!-- Приёмы выбранного питомца — одной строкой, как на арене -->
            <div v-if="inBattle && !finished && activeUnit" class="dgn-moves">
                <button
                    v-for="key in ref_.moves"
                    :key="key"
                    type="button"
                    class="dgn-move"
                    :class="[`dgn-move--${key}`, { 'is-on': moves[activeUnit.id] === key, 'is-ready': key === 'ultimate' && activeUnit.rage >= 100 }]"
                    :style="key === 'skill' || key === 'ultimate' ? { '--el': elementColor(activeUnit.element), '--fill': `${activeUnit.rage}%` } : null"
                    :disabled="activeUnit.hp <= 0 || (key === 'ultimate' && activeUnit.rage < 100) || (key === 'skill' && activeUnit.cd > 0)"
                    @click="moves[activeUnit.id] = key"
                >
                    <AppIcon :icon="moveIcon(key)" :size="18" />
                    <b>{{ moveName(key, activeUnit) }}</b>
                    <small>{{ moveHint(key, activeUnit) }}</small>
                </button>
            </div>

            <div class="dgn-actions">
                <template v-if="finished">
                    <button type="button" class="dgn-btn dgn-btn--main" @click="closeResult">
                        Новый поход
                    </button>
                </template>
                <template v-else-if="inBattle">
                    <button type="button" class="dgn-btn dgn-btn--main" :disabled="busy" @click="turn">
                        Сделать ход
                    </button>
                    <button type="button" class="dgn-btn" :disabled="busy" @click="retreat">Отступить</button>
                </template>
                <template v-else>
                    <button type="button" class="dgn-btn dgn-btn--main" :disabled="busy" @click="enterRoom">
                        {{ run.is_boss_next ? 'В бой с боссом' : 'В бой' }}
                    </button>
                    <button type="button" class="dgn-btn" :disabled="busy" @click="retreat">Отступить</button>
                </template>
            </div>
        </section>

        <!-- ================= ВЫБОР ПОХОДА ================= -->
        <section v-else-if="data" class="dgn-pick">
            <h2>Куда идём</h2>
            <p class="dgn-pick__sub">
                Выбери подземелье — отряд соберёшь ниже. Кольцо показывает выбранное.
            </p>
            <!-- Карта мира -->
            <div class="dgn-map">
                <svg class="dgn-map__trail" :viewBox="`0 0 ${MAP_W} ${MAP_H}`" preserveAspectRatio="none" aria-hidden="true">
                    <polyline :points="mapTrail" />
                </svg>

                <button
                    v-for="(dungeon, index) in dungeons"
                    :key="dungeon.key"
                    type="button"
                    class="dgn-node"
                    :class="[
                        `dgn-tone-${dungeon.tone}`,
                        {
                            'is-on': dungeon.key === selected,
                            'is-locked': !dungeon.available,
                        },
                    ]"
                    :style="nodeStyle(dungeon, index)"
                    @click="selected = dungeon.key"
                >
                    <span class="dgn-node__medal">
                        <PixelSprite :sprite="dungeon.emblem" :size="44" />
                    </span>
                    <span class="dgn-node__name">{{ dungeon.name }}</span>
                    <span class="dgn-node__lvl">{{ dungeon.min_level }} ур.</span>
                    <span v-if="!dungeon.available" class="dgn-node__lock">закрыто</span>
                </button>
            </div>

            <!-- Выбранное подземелье -->
            <div
                v-if="selectedDungeon"
                class="dgn-gate dgn-gate--info"
                :class="[`dgn-tone-${selectedDungeon.tone}`, { 'is-locked': !selectedDungeon.available }]"
            >
                <span class="dgn-gate__arch">
                    <PixelSprite :sprite="selectedDungeon.emblem" :size="54" anim="bounce" />
                </span>

                <span class="dgn-gate__main">
                    <span class="dgn-gate__top">
                        <strong>{{ selectedDungeon.name }}</strong>
                        <span class="dgn-gate__rank" :title="`Сложность ${selectedDungeon.stage} из 5`">
                            <i v-for="n in 5" :key="n" :class="{ 'is-on': n <= selectedDungeon.stage }"></i>
                        </span>
                    </span>

                    <small class="dgn-gate__about">{{ selectedDungeon.about }}</small>

                    <span class="dgn-gate__beads">
                        <i
                            v-for="(bead, i) in gateBeads(selectedDungeon)"
                            :key="i"
                            :class="{ 'is-boss': bead.boss }"
                        ></i>
                    </span>

                    <span class="dgn-gate__bottom">
                        <span class="dgn-gate__lvl" :class="{ 'is-locked': !selectedDungeon.available }">
                            от {{ selectedDungeon.min_level }} ур.
                        </span>

                        <span
                            class="dgn-gate__bosses"
                            :title="selectedDungeon.bosses.map((boss) => boss.name).join(' · ')"
                        >
                            <span v-for="boss in selectedDungeon.bosses.slice(0, 3)" :key="boss.name" class="dgn-gate__boss">
                                <PixelSprite :sprite="boss.sprite" :size="18" />
                            </span>
                            <span v-if="selectedDungeon.bosses.length > 3" class="dgn-gate__more">
                                +{{ selectedDungeon.bosses.length - 3 }}
                            </span>
                        </span>

                        <button
                            type="button"
                            class="dgn-gate__go"
                            :class="{ 'is-locked': !selectedDungeon.available }"
                            :disabled="!selectedDungeon.available || busy"
                            @click="enter"
                        >
                            {{ selectedDungeon.available ? 'Войти' : 'Закрыто' }}
                        </button>
                    </span>
                </span>
            </div>

            <div class="dgn-squad-head">
                <h3>Отряд <small>{{ squad.length }} / 3</small></h3>
                <button
                    type="button"
                    class="dgn-best"
                    :disabled="readyPets.length < 2"
                    title="Подобрать связку против стихий этого подземелья"
                    @click="bestSquad"
                >
                    <AppIcon icon="sparkles" :size="13" /> Подобрать отряд
                </button>
            </div>

            <div class="dgn-slots">
                <div v-for="i in 3" :key="i" class="dgn-slot" :class="{ 'is-filled': squad[i - 1] }">
                    <template v-if="squad[i - 1]">
                        <PetFigure
                            v-for="pet in pets.filter((p) => p.inventory_id === squad[i - 1])"
                            :key="pet.inventory_id"
                            :species="pet.species"
                            :traits="pet.traits"
                            :size="38"
                        />
                        <span class="dgn-slot__name">{{ pets.find((p) => p.inventory_id === squad[i - 1])?.name }}</span>
                        <span class="dgn-slot__meta">
                            {{ elementName(pets.find((p) => p.inventory_id === squad[i - 1])?.element) }}
                            · ур. {{ pets.find((p) => p.inventory_id === squad[i - 1])?.level }}
                        </span>
                        <button type="button" class="dgn-slot__drop" @click="toggle(squad[i - 1])">×</button>
                    </template>
                    <template v-else>
                        <span class="dgn-slot__plus">+</span>
                        <span class="dgn-slot__meta">свободно</span>
                    </template>
                </div>
            </div>

            <h3>Кого взять <small>питомцы от {{ activeDungeon?.min_level ?? 1 }} уровня</small></h3>

            <div v-if="readyPets.length" class="dgn-roster">
                <button
                    v-for="pet in readyPets"
                    :key="pet.inventory_id"
                    type="button"
                    class="dgn-roster__pet"
                    :class="{ 'is-on': squad.includes(pet.inventory_id) }"
                    :style="{ '--rar': PET_RARITY[pet.traits?.rarity]?.color || '#a8a3b8', '--el': elementColor(pet.element) }"
                    @click="toggle(pet.inventory_id)"
                >
                    <PetFigure :species="pet.species" :traits="pet.traits" :size="36" :backdrop="false" />
                    <strong>{{ pet.name }}</strong>
                    <small>{{ elementName(pet.element) }} · ур. {{ pet.level }}</small>
                    <span class="dgn-roster__power">
                        сила {{ pet.power }}
                        <template v-if="gearCount(pet)"> · {{ gearCount(pet) }}/3</template>
                    </span>
                    <span class="dgn-roster__skill">{{ abilityOf(pet.element)?.ultimate?.name }}</span>
                </button>
            </div>

            <p v-else class="dgn-hint">
                Нет подходящих питомцев. Нужны свободные питомцы от {{ activeDungeon?.min_level ?? 1 }} уровня.
            </p>

            <button
                v-if="data.runs_left === 0"
                class="dgn-btn dgn-btn--main dgn-enter"
                :disabled="busy"
                @click="buyRun"
            >
                Походы кончились — купить ещё за {{ data.next_run_price }} ₳
            </button>

            <button
                v-else
                class="dgn-btn dgn-btn--main dgn-enter"
                :disabled="busy || !canStart"
                @click="enter"
            >
                Войти в подземелье
            </button>
            <p v-if="!canStart" class="dgn-hint">Возьми минимум двух питомцев.</p>
            <p v-else-if="autoPicked" class="dgn-hint">
                Отряд подобран по силе — она считается вместе с надетой экипировкой и связкой стихий.
            </p>

            <h3>Связки стихий</h3>
            <div class="dgn-combos">
                <article v-for="(combo, key) in ref_.combos" :key="key" class="dgn-combo-card">
                    <strong>{{ combo.name }}</strong>
                    <span class="dgn-combo-card__pair">
                        <span :style="{ color: elementColor(combo.pair[0]) }">{{ elementName(combo.pair[0]) }}</span>
                        +
                        <span :style="{ color: elementColor(combo.pair[1]) }">{{ elementName(combo.pair[1]) }}</span>
                    </span>
                    <span class="dgn-combo-card__text">{{ combo.text }}</span>
                    <span class="dgn-combo-card__hot">+35% против {{ elementName(combo.counters) }}</span>
                </article>
            </div>
        </section>

    </div>
</template>

<style scoped>
@import "./DungeonView.css";
</style>
