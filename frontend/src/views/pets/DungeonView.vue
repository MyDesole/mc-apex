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

const KEY_NAMES = { seeker: 'Сундук искателя', royal: 'Королевский сундук' }

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
    // верхний ряд — слева направо
    catacombs: { x: 16, y: 34 },
    sunken: { x: 46, y: 22 },
    forge: { x: 76, y: 34 },
    crypt: { x: 106, y: 22 },
    void: { x: 136, y: 34 },
    abyss: { x: 166, y: 22 },
    // нижний ряд — справа налево, тропа змейкой
    lair: { x: 166, y: 70 },
    throne: { x: 136, y: 58 },
    crystal: { x: 106, y: 70 },
    pantheon: { x: 76, y: 58 },
    trial: { x: 46, y: 70 },
    rubledeep: { x: 16, y: 58 },
}

const MAP_W = 190
const MAP_H = 76

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

/** По умолчанию каждый живой питомец бьёт атакой. */
function applyDefaultMoves() {
    for (const unit of battle.value?.units ?? []) {
        if (unit.hp > 0 && !moves[unit.id]) moves[unit.id] = 'attack'
    }
}

/*
 * light — ход внутри похода (зал, раунд боя): ответ уже содержит весь поход,
 * поэтому не перезапрашиваем сводку со всем зверинцем и складом.
 */
async function act(call, { light = false } = {}) {
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

        if (light && data.value && outcome?.status === 'active' && outcome.members) {
            data.value.run = outcome
            applyDefaultMoves()

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
}, { light: true })

const turn = () => act(async () => {
    const payload = {}

    for (const unit of battle.value?.units ?? []) {
        if (unit.hp > 0) payload[unit.id] = moves[unit.id] ?? 'attack'
    }

    return dungeonApi.move(run.value.id, payload)
}, { light: true })

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
        <!-- Игровая панель вместо заголовка страницы -->
        <header v-if="data" class="dg-hud">
            <span class="dg-title">Подземелья</span>
            <span class="dg-chip"><b>{{ data.runs_left }}</b> походов{{ data.extra_bought ? ' +' + data.extra_bought : '' }}</span>
            <span class="dg-chip">отряд <b>{{ data.squad_min }}–{{ data.squad_max }}</b></span>
            <button class="dg-buy" :class="{ 'is-hot': data.runs_left === 0 }" type="button" :disabled="busy" @click="buyRun">
                Ещё поход · ₳ {{ data.next_run_price }}
            </button>
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
                    <RouterLink to="/world?at=gear" class="dgn-btn" @click="markSeen">
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

        <!-- ================= ВЫБОР ПОХОДА: гора подземелий, лагерь ================= -->
        <section v-else-if="data" class="dg-pick">
            <!-- Гора: входы в подземелья на тропе -->
            <div class="dg-world">
                <div class="dg-sky" aria-hidden="true">
                    <i v-for="n in 16" :key="n" class="dg-star" :style="{ left: ((n * 37) % 97) + '%', top: ((n * 23) % 52) + '%', animationDelay: (n % 5) * -0.6 + 's' }" />
                </div>
                <div class="dg-mountains" aria-hidden="true"><i /><i /><i /><i /></div>
                <div class="dg-ground" aria-hidden="true" />
                <svg class="dg-trail" :viewBox="`0 0 ${MAP_W} ${MAP_H}`" preserveAspectRatio="none" aria-hidden="true">
                    <polyline :points="mapTrail" />
                </svg>

                <button
                    v-for="(dungeon, index) in dungeons"
                    :key="dungeon.key"
                    type="button"
                    class="dg-gate"
                    :class="[`dgn-tone-${dungeon.tone}`, { 'is-on': dungeon.key === selected, 'is-locked': !dungeon.available }]"
                    :style="nodeStyle(dungeon, index)"
                    :title="dungeon.about"
                    @click="selected = dungeon.key"
                >
                    <i v-if="dungeon.key === selected" class="dg-gate__arrow" aria-hidden="true" />
                    <span class="dg-gate__arch" aria-hidden="true">
                        <i class="dg-torch dg-torch--l" /><i class="dg-torch dg-torch--r" />
                        <span class="dg-gate__door"><PixelSprite :sprite="dungeon.emblem" :size="38" /></span>
                        <i v-if="!dungeon.available" class="dg-gate__chain" />
                    </span>
                    <span class="dg-gate__plank">
                        <b>{{ dungeon.name }}</b>
                        <small>{{ dungeon.available ? `от ${dungeon.min_level} ур.` : `закрыто · ${dungeon.min_level} ур.` }}</small>
                    </span>
                </button>
            </div>

            <!-- Доска выбранного подземелья -->
            <div v-if="selectedDungeon" class="dg-board" :class="`dgn-tone-${selectedDungeon.tone}`">
                <div class="dg-board__title">
                    <strong>{{ selectedDungeon.name }}</strong>
                    <span class="dg-skulls" :title="`Сложность ${selectedDungeon.stage} из 8`">
                        <i v-for="n in 8" :key="n" :class="{ 'is-on': n <= selectedDungeon.stage }" />
                    </span>
                </div>
                <p>{{ selectedDungeon.about }}</p>
                <div class="dg-board__row">
                    <span class="dg-board__beads" :title="`${selectedDungeon.floors} этажей, ${selectedDungeon.rooms} залов`">
                        <i v-for="(bead, i) in gateBeads(selectedDungeon)" :key="i" :class="{ 'is-boss': bead.boss }" />
                    </span>
                    <span class="dg-board__bosses" :title="selectedDungeon.bosses.map((boss) => boss.name).join(' · ')">
                        <PixelSprite v-for="boss in selectedDungeon.bosses" :key="boss.name" :sprite="boss.sprite" :size="22" />
                    </span>
                    <span class="dg-board__lvl" :class="{ 'is-locked': !selectedDungeon.available }">от {{ selectedDungeon.min_level }} ур.</span>
                </div>
            </div>

            <!-- Лагерь: отряд у костра и хотбар питомцев -->
            <div class="dg-camp">
                <div class="dg-camp__top">
                    <h3>Лагерь <small>{{ squad.length }} / 3</small></h3>
                    <button type="button" class="dg-best" :disabled="readyPets.length < 2" title="Подобрать связку против стихий этого подземелья" @click="bestSquad">
                        Подобрать отряд
                    </button>
                </div>

                <div class="dg-camp__scene">
                    <div v-for="i in 3" :key="i" class="dg-seat" :class="{ 'is-filled': squad[i - 1] }">
                        <template v-if="squad[i - 1]">
                            <PetFigure
                                v-for="pet in pets.filter((p) => p.inventory_id === squad[i - 1])"
                                :key="pet.inventory_id"
                                :species="pet.species"
                                :traits="pet.traits"
                                :size="46"
                            />
                            <span class="dg-seat__name">{{ pets.find((p) => p.inventory_id === squad[i - 1])?.name }}</span>
                            <button type="button" class="dg-seat__drop" aria-label="Убрать из отряда" @click="toggle(squad[i - 1])">×</button>
                        </template>
                        <span v-else class="dg-seat__empty">+</span>
                        <i class="dg-seat__log" aria-hidden="true" />
                    </div>
                    <i class="dg-fire" aria-hidden="true"><b /><b /><b /></i>
                </div>

                <p v-if="!readyPets.length" class="dg-camp__hint">
                    Нет подходящих питомцев: нужны свободные от {{ activeDungeon?.min_level ?? 1 }} уровня.
                </p>

                <div v-else class="dg-hotbar">
                    <button
                        v-for="pet in readyPets"
                        :key="pet.inventory_id"
                        type="button"
                        class="dg-slot"
                        :class="{ 'is-on': squad.includes(pet.inventory_id) }"
                        :style="{ '--rar': PET_RARITY[pet.traits?.rarity]?.color || '#a8a3b8', '--el': elementColor(pet.element) }"
                        :title="`${pet.name} · ${elementName(pet.element)} · сила ${pet.power} · ${abilityOf(pet.element)?.ultimate?.name ?? ''}`"
                        @click="toggle(pet.inventory_id)"
                    >
                        <PetFigure :species="pet.species" :traits="pet.traits" :size="34" :backdrop="false" />
                        <i class="dg-slot__lvl">{{ pet.level }}</i>
                        <i class="dg-slot__el" />
                    </button>
                </div>

                <button v-if="data.runs_left === 0" class="dg-enter" :disabled="busy" @click="buyRun">
                    Походы кончились · ещё за {{ data.next_run_price }} ₳
                </button>
                <button v-else class="dg-enter" :disabled="busy || !canStart || !selectedDungeon?.available" @click="enter">
                    Спуститься в подземелье
                </button>
                <p v-if="!canStart" class="dg-camp__hint">Возьми минимум двух питомцев.</p>
                <p v-else-if="autoPicked" class="dg-camp__hint">Отряд подобран по силе — с экипировкой и связкой стихий.</p>
            </div>

            <!-- Связки стихий — в сундуке, а не простынёй текста -->
            <details class="dg-fold">
                <summary>Связки стихий</summary>
                <div class="dg-combos">
                    <article v-for="(combo, key) in ref_.combos" :key="key">
                        <strong>{{ combo.name }}</strong>
                        <span>
                            <span :style="{ color: elementColor(combo.pair[0]) }">{{ elementName(combo.pair[0]) }}</span>
                            +
                            <span :style="{ color: elementColor(combo.pair[1]) }">{{ elementName(combo.pair[1]) }}</span>
                        </span>
                        <small>{{ combo.text }}</small>
                        <em>+35% против {{ elementName(combo.counters) }}</em>
                    </article>
                </div>
            </details>
        </section>

    </div>
</template>

<style scoped>
@import "./DungeonView.css";
@import "./DungeonPick.css";
</style>
