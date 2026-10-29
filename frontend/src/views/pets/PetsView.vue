<script setup>
/**
 * «Мои питомцы»: всё о питомцах в одном месте.
 *
 * Питомцы — уровень, перки, трюк; Экспедиции — поход за монетами и
 * ключами; Арена — пошаговые бои с рейтингом; Питомцедекс — коллекция
 * видов и черт с наградами; Топ — лучшие питомцы сервера.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PetBattle from '@/components/pets/PetBattle.vue'
import PetDex from '@/components/pets/PetDex.vue'
import PetTop from '@/components/pets/PetTop.vue'
import PetViewer from '@/components/pets/PetViewer.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import PetPicker from '@/components/pets/PetPicker.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import { useNow } from '@/composables/core/useNow.js'
import { ELEMENTS, PET_NAMES, levelXp } from '@/data/players/petArena.js'
import { PET_RARITY, chanceLabel } from '@/data/players/petTraits.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const TABS = [
  ['pets', 'Питомцы', 'heart'],
  ['expeditions', 'Экспедиции', 'globe'],
  ['arena', 'Арена', 'sword'],
  ['dex', 'Питомцедекс', 'box'],
  ['top', 'Топ', 'trophy'],
]

const tab = computed({
  get: () => (TABS.some(([id]) => id === route.query.tab) ? route.query.tab : 'pets'),
  set: (value) => router.replace({ query: { ...route.query, tab: value } }),
})

const hub = ref(null)
const error = ref('')
const notice = ref('')
const busy = ref(null)
const now = useNow()
const playing = ref({})
const viewing = ref(null)

const pets = computed(() => hub.value?.pets ?? [])
const freePets = computed(() => pets.value.filter((p) => !p.busy))

/* ---------- Поиск и фильтры вкладки «Питомцы» ---------- */

const RARITY_ORDER = ['mythic', 'legendary', 'epic', 'rare', 'common']
const query = ref('')
const filters = ref({ species: '', rarity: '', element: '', status: '', sort: 'level' })

// В списке видов — только те, что есть у игрока
const ownedSpecies = computed(() => [...new Set(pets.value.map((p) => p.species))].sort((a, b) => (PET_NAMES[a] || a).localeCompare(PET_NAMES[b] || b, 'ru')))

const STATUSES = {
  walking: { label: 'В профиле', test: (p) => p.equipped },
  free: { label: 'Свободные', test: (p) => !p.busy },
  busy: { label: 'Заняты', test: (p) => !!p.busy },
  hungry: { label: 'Голодные', test: (p) => p.hungry && !p.care?.includes('feed') },
  care: { label: 'Ждут ухода', test: (p) => (p.care?.length ?? 0) < 3 },
}

const SORTS = {
  level: { label: 'По уровню', fn: (a, b) => b.level - a.level || b.xp - a.xp },
  rating: { label: 'По рейтингу арены', fn: (a, b) => b.rating - a.rating },
  rarity: { label: 'По редкости', fn: (a, b) => RARITY_ORDER.indexOf(a.traits?.rarity) - RARITY_ORDER.indexOf(b.traits?.rarity) },
  name: { label: 'По имени', fn: (a, b) => a.name.localeCompare(b.name, 'ru') },
  newest: { label: 'Сначала новые', fn: (a, b) => b.inventory_id - a.inventory_id },
}

const shownPets = computed(() => {
  const q = query.value.trim().toLowerCase()
  const f = filters.value

  return pets.value
      .filter((p) => !q || [p.name, PET_NAMES[p.species], p.traits?.model?.name, p.traits?.backdrop?.name, p.traits?.accessory?.name, p.traits?.serial && `#${p.traits.serial}`]
          .some((text) => text && String(text).toLowerCase().includes(q)))
      .filter((p) => !f.species || p.species === f.species)
      .filter((p) => !f.rarity || p.traits?.rarity === f.rarity)
      .filter((p) => !f.element || p.element === f.element)
      .filter((p) => !f.status || STATUSES[f.status].test(p))
      .sort(SORTS[f.sort]?.fn ?? SORTS.level.fn)
})

const filtered = computed(() => !!query.value.trim() || Object.entries(filters.value).some(([k, v]) => k !== 'sort' && v))

function resetFilters() {
  query.value = ''
  filters.value = { species: '', rarity: '', element: '', status: '', sort: filters.value.sort }
}

async function load() {
  try {
    hub.value = await api.get('/pets')
    if (!expeditionPet.value && freePets.value.length) expeditionPet.value = freePets.value[0].inventory_id
    if (!arenaPet.value && freePets.value.length) arenaPet.value = freePets.value[0].inventory_id
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить питомцев.'
  }
}

function say(text) {
  notice.value = text
  error.value = ''
}

function fail(e, fallback) {
  // Не хватает монет — подсказываем, где заработать, а не просто ошибка
  if (e.data?.missing) {
    shortage.value = e.data.missing
    error.value = ''
  } else {
    error.value = e.message || fallback
  }
  notice.value = ''
}

const shortage = ref(0)

/* ---------- Всё разом за монеты ---------- */

const bulk = computed(() => hub.value?.bulk ?? null)
const fees = computed(() => bulk.value?.fees ?? { feed: 0, send: 0, claim: 0 })

/** Сколько свободных питомцев подходят для земли (по уровню). */
function sendable(zone) {
  return freePets.value.filter((p) => p.level >= zone.min_level).length
}

async function bulkAction(action, payload = {}) {
  busy.value = `bulk-${action}`
  shortage.value = 0
  error.value = ''
  try {
    const result = await api.post(`/pets/bulk/${action}`, payload)
    if (action === 'claim') {
      const keys = Object.values(result.loot?.keys ?? {}).reduce((sum, n) => sum + n, 0)
      say(`Добыча с ${result.done} экспедиций: ₳ ${result.loot.coins.toLocaleString('ru-RU')}, +${result.loot.xp} опыта${keys ? `, ключей: ${keys}` : ''}. Плата — ₳ ${result.spent}.`)
    } else if (action === 'feed') {
      say(`Накормлено питомцев: ${result.done}. Плата — ₳ ${result.spent}.`)
    } else {
      say(`В экспедицию ушли: ${result.done}. Плата — ₳ ${result.spent}.`)
    }
    auth.fetchMe()
    await load()
  } catch (e) {
    fail(e, 'Не получилось.')
  } finally {
    busy.value = null
  }
}

async function buyFight() {
  busy.value = 'buy-fight'
  shortage.value = 0
  try {
    const result = await api.post('/pets/arena/buy-fight')
    hub.value.arena.fights_left = result.fights_left
    hub.value.arena.extra_price = result.next_price
    say('Ещё один бой куплен — выбирай соперника.')
    auth.fetchMe()
  } catch (e) {
    fail(e, 'Не удалось купить бой.')
  } finally {
    busy.value = null
  }
}

// Вернувшиеся экспедиции. Строка, а не массив: computed отдаёт новое
// значение только когда кто-то вернулся — страница со всеми питомцами
// не перерисовывается каждую секунду, тикает лишь CountdownText
const readyKey = computed(() => trips.value
  .filter((t) => new Date(t.ends_at).getTime() <= now.value)
  .map((t) => t.id)
  .join(','))
const readyIds = computed(() => new Set(readyKey.value ? readyKey.value.split(',').map(Number) : []))
const isReady = (trip) => readyIds.value.has(trip.id)

function xpPct(p) {
  if (p.level >= 30) return 100
  const from = levelXp(p.level)
  const to = levelXp(p.level + 1)
  return Math.round(((p.xp - from) / (to - from)) * 100)
}

function nextPerk(p) {
  return p.perks.find((perk) => !perk.unlocked) ?? null
}

/* ---------- Уход: погладить, покормить, поиграть ---------- */

const CARE = [
  { id: 'pet', label: 'Погладить', icon: 'heart', xp: 1, reaction: 'hearts' },
  { id: 'feed', label: 'Покормить', icon: 'meat', xp: 2, reaction: 'dance' },
  { id: 'play', label: 'Поиграть', icon: 'ball', xp: 2, reaction: 'flip' },
]

async function care(p, c) {
  busy.value = `care-${p.inventory_id}`
  try {
    const result = await api.post(`/players/${auth.user.id}/pet/${c.id}`, { pet: p.inventory_id })
    p.care = [...(p.care ?? []), c.id]
    if (c.id === 'feed') p.hungry = false
    // Опыт ухода сервер считает вместе с опытом экспедиций и боёв
    p.xp = result.xp ?? p.xp
    if (result.level && result.level > p.level) {
      say(`${p.name} вырос до ${result.level} уровня!`)
      p.level = result.level
    }
    showTrick(p, c.reaction)
  } catch (e) {
    fail(e, 'Не получилось.')
  } finally {
    busy.value = null
  }
}

/* ---------- Трюки ---------- */

function showTrick(p, trick = p.trick) {
  if (!trick) return
  playing.value = { ...playing.value, [p.inventory_id]: null }
  requestAnimationFrame(() => {
    playing.value = { ...playing.value, [p.inventory_id]: trick }
    setTimeout(() => { playing.value = { ...playing.value, [p.inventory_id]: null } }, 1700)
  })
}

async function setTrick(p, trick) {
  busy.value = `trick-${p.inventory_id}`
  try {
    await api.post(`/pets/${p.inventory_id}/trick`, { trick })
    p.trick = trick
    if (trick) showTrick(p, trick)
    say(trick ? 'Трюк выбран — питомец будет показывать его гостям профиля.' : 'Трюк убран.')
  } catch (e) {
    fail(e, 'Не получилось.')
  } finally {
    busy.value = null
  }
}

/* ---------- Экспедиции ---------- */

const expeditionPet = ref(null)
const selectedPet = computed(() => pets.value.find((p) => p.inventory_id === expeditionPet.value) ?? null)
const trips = computed(() => pets.value.filter((p) => p.expedition).map((p) => ({ ...p.expedition, pet: p })))

function zoneCoins(zone, p) {
  if (!p) return `${zone.coins[0]}–${zone.coins[1]}`
  const perk = p.level >= 25 ? 1.25 : p.level >= 5 ? 1.1 : 1
  const k = (1 + p.level * 0.02) * perk
  return `${Math.round(zone.coins[0] * k)}–${Math.round(zone.coins[1] * k)}`
}

async function sendExpedition(zone) {
  busy.value = `zone-${zone.key}`
  try {
    await api.post(`/pets/${expeditionPet.value}/expedition`, { zone: zone.key })
    say(`${selectedPet.value?.name} отправился: ${zone.name}. Вернётся через ${zone.hours} ч.`)
    expeditionPet.value = null
    await load()
  } catch (e) {
    fail(e, 'Не удалось отправить.')
  } finally {
    busy.value = null
  }
}

const loot = ref(null)

async function claim(trip) {
  busy.value = `claim-${trip.id}`
  try {
    const result = await api.post(`/pets/expeditions/${trip.id}/claim`)
    loot.value = { ...result, pet: trip.pet }
    auth.fetchMe()
    await load()
  } catch (e) {
    fail(e, 'Не удалось забрать добычу.')
  } finally {
    busy.value = null
  }
}

/* ---------- Арена ---------- */

const arenaPet = ref(null)
const opponents = ref(null)
const battle = ref(null)
const fighter = computed(() => freePets.value.find((p) => p.inventory_id === arenaPet.value) ?? null)

// Кто кого бьёт сильнее — как на сервере
const STRONG = { fire: 'nature', nature: 'water', water: 'fire', shadow: 'beast' }

/** Примерный шанс: Эло по рейтингу плюс поправка за разницу уровней и стихии. */
function odds(foe) {
  const me = fighter.value
  if (!me) return 50
  let diff = (me.rating - foe.rating) + (me.level - foe.level) * 25
  if (STRONG[me.element] === foe.element) diff += 60
  if (STRONG[foe.element] === me.element) diff -= 60
  return Math.max(3, Math.min(97, Math.round(100 / (1 + 10 ** (-diff / 400)))))
}

function matchup(foe) {
  const me = fighter.value
  if (!me) return null
  if (STRONG[me.element] === foe.element) return { text: 'Твоя стихия сильнее', cls: 'is-good' }
  if (STRONG[foe.element] === me.element) return { text: 'Его стихия сильнее', cls: 'is-bad' }
  return null
}

async function loadOpponents() {
  if (!arenaPet.value) return
  opponents.value = null
  try {
    opponents.value = (await api.get(`/pets/${arenaPet.value}/opponents`)).opponents
  } catch (e) {
    opponents.value = []
    fail(e, 'Не удалось найти соперников.')
  }
}

async function challenge(foe) {
  busy.value = `fight-${foe.inventory_id}`
  try {
    battle.value = await api.post(`/pets/${arenaPet.value}/battle`, { opponent: foe.inventory_id })
    error.value = ''
  } catch (e) {
    fail(e, 'Не удалось начать бой.')
  } finally {
    busy.value = null
  }
}

async function closeBattle() {
  battle.value = null
  await load()
  loadOpponents()
}

watch(arenaPet, () => { if (tab.value === 'arena') loadOpponents() })
watch(tab, (t) => { if (t === 'arena' && !opponents.value) loadOpponents() })

onMounted(async () => {
  await load()
  battle.value = hub.value?.arena?.active ?? null
  if (tab.value === 'arena') loadOpponents()
})
</script>

<template>
  <div class="pv">
    <header class="pv-head">
      <div>
        <span class="pv-head__kicker"><AppIcon icon="heart" :size="14" /> Питомцы</span>
        <h1>Мои питомцы</h1>
        <p>Отправляй питомцев в экспедиции, сражайся на арене, учи трюкам и собирай коллекцию. С уровнем открываются перки и новые земли.</p>
      </div>
      <dl v-if="hub" class="pv-head__stats">
        <div><dt>Питомцев</dt><dd>{{ pets.length }}</dd></div>
        <div><dt>В походе</dt><dd>{{ trips.length }}</dd></div>
        <div><dt>Боёв сегодня</dt><dd>{{ hub.arena.fights_left }}</dd></div>
      </dl>
    </header>

    <nav class="pv-tabs">
      <button v-for="[id, label, icon] in TABS" :key="id" type="button" :class="{ 'is-on': tab === id }" @click="tab = id">
        <AppIcon :icon="icon" :size="14" /> {{ label }}
        <i v-if="id === 'expeditions' && readyIds.size" class="pv-dot"></i>
      </button>
    </nav>

    <p v-if="notice" class="pv-msg pv-msg--ok">{{ notice }}</p>
    <p v-if="error" class="pv-msg pv-msg--err">{{ error }}</p>

    <!-- Не хватает монет -->
    <div v-if="shortage" class="pv-short">
      <span><strong>Не хватает ₳ {{ shortage.toLocaleString('ru-RU') }}.</strong> Монеты можно накопать в шахте или принести из экспедиций.</span>
      <RouterLink to="/miner" class="pv-short__go">В шахту</RouterLink>
      <button type="button" class="pv-short__close" aria-label="Скрыть" @click="shortage = 0"><AppIcon icon="close" :size="14" /></button>
    </div>

    <PageLoader v-if="!hub && !['dex', 'top'].includes(tab)" />

    <!-- ================= Питомцы ================= -->
    <template v-else-if="tab === 'pets'">
      <p v-if="!pets.length" class="pv-empty">
        Питомцев пока нет. Их выбивают в <RouterLink to="/cases">Сундуке питомцев</RouterLink>
        или покупают у других игроков на <RouterLink to="/auction">аукционе</RouterLink>.
      </p>

      <!-- Всё разом за монеты -->
      <div v-if="pets.length > 1 && bulk" class="pv-bulk">
        <button
            type="button"
            class="pv-bulk__btn"
            :disabled="!bulk.feed.count || busy === 'bulk-feed'"
            @click="bulkAction('feed')"
        >
          <PixelSprite sprite="meat" :size="16" />
          <span>
            <b>Покормить всех</b>
            <small>{{ bulk.feed.count ? `${bulk.feed.count} голодных · ₳ ${bulk.feed.cost}` : 'все сыты' }}</small>
          </span>
        </button>
        <button
            type="button"
            class="pv-bulk__btn"
            :disabled="!bulk.send.count"
            @click="tab = 'expeditions'"
        >
          <AppIcon icon="globe" :size="16" />
          <span>
            <b>Отправить всех</b>
            <small>{{ bulk.send.count ? `${bulk.send.count} свободных · ₳ ${fees.send} за каждого` : 'свободных нет' }}</small>
          </span>
        </button>
        <button
            type="button"
            class="pv-bulk__btn"
            :class="{ 'is-ready': bulk.claim.count }"
            :disabled="!bulk.claim.count || busy === 'bulk-claim'"
            @click="bulkAction('claim')"
        >
          <AppIcon icon="gift" :size="16" />
          <span>
            <b>Забрать у всех</b>
            <small>{{ bulk.claim.count ? `${bulk.claim.count} вернулись · ₳ ${bulk.claim.cost}` : 'никто не вернулся' }}</small>
          </span>
        </button>
      </div>

      <!-- Поиск и фильтры -->
      <div v-if="pets.length > 1" class="pv-filters">
        <label class="pv-search">
          <AppIcon icon="target" :size="14" />
          <input v-model="query" placeholder="Имя, вид, окрас, аксессуар или #номер" />
        </label>

        <select v-model="filters.species" aria-label="Вид">
          <option value="">Все виды</option>
          <option v-for="s in ownedSpecies" :key="s" :value="s">{{ PET_NAMES[s] || s }}</option>
        </select>

        <select v-model="filters.rarity" aria-label="Редкость">
          <option value="">Любая редкость</option>
          <option v-for="r in RARITY_ORDER" :key="r" :value="r">{{ PET_RARITY[r].label }}</option>
        </select>

        <select v-model="filters.element" aria-label="Стихия">
          <option value="">Любая стихия</option>
          <option v-for="(el, key) in ELEMENTS" :key="key" :value="key">{{ el.name }}</option>
        </select>

        <select v-model="filters.status" aria-label="Состояние">
          <option value="">Все</option>
          <option v-for="(s, key) in STATUSES" :key="key" :value="key">{{ s.label }}</option>
        </select>

        <select v-model="filters.sort" aria-label="Сортировка">
          <option v-for="(s, key) in SORTS" :key="key" :value="key">{{ s.label }}</option>
        </select>

        <span class="pv-filters__count">
          {{ shownPets.length }} из {{ pets.length }}
          <button v-if="filtered" type="button" @click="resetFilters">Сбросить</button>
        </span>
      </div>

      <p v-if="pets.length && !shownPets.length" class="pv-empty">
        Никого не нашлось. <button type="button" class="pv-link" @click="resetFilters">Сбросить фильтры</button>
      </p>

      <div v-else-if="pets.length" class="pv-grid">
        <article
            v-for="p in shownPets"
            :key="p.inventory_id"
            class="pc"
            :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color, '--el': ELEMENTS[p.element]?.color }"
        >
          <div class="pc__stage" title="Рассмотреть питомца" @click="viewing = p">
            <PetFigure :species="p.species" :traits="p.traits" :size="72" :trick="playing[p.inventory_id]" />
            <span class="pc__lvl"><small>УР</small>{{ p.level }}</span>
            <span class="pc__el">{{ ELEMENTS[p.element]?.name }}</span>
            <span v-if="p.equipped" class="pc__walk">в профиле</span>
          </div>

          <div class="pc__body">
            <div class="pc__name">
              <strong>{{ p.name }}</strong>
              <em v-if="p.traits?.serial">#{{ p.traits.serial }}</em>
            </div>
            <small class="pc__traits">{{ p.traits?.model?.name }} · {{ p.traits?.backdrop?.name }} · {{ p.traits?.accessory?.name }}</small>

            <div class="pc__xp">
              <div><i :style="{ width: `${xpPct(p)}%` }"></i></div>
              <small>{{ p.level >= 30 ? 'максимальный уровень' : `${p.xp} / ${levelXp(p.level + 1)} опыта` }}</small>
            </div>

            <dl class="pc__stats">
              <div><dt>Рейтинг</dt><dd>{{ p.rating }}</dd></div>
              <div><dt>Победы</dt><dd>{{ p.wins }}</dd></div>
              <div><dt>Поражения</dt><dd>{{ p.losses }}</dd></div>
            </dl>

            <div class="pc__perks">
              <span
                  v-for="perk in p.perks"
                  :key="perk.key"
                  class="pc-perk"
                  :class="{ 'is-on': perk.unlocked }"
                  :title="`${perk.level} ур.: ${perk.label}`"
              >{{ perk.level }}</span>
              <small v-if="nextPerk(p)">Следующий перк на {{ nextPerk(p).level }} ур.</small>
              <small v-else>Все перки открыты</small>
            </div>

            <div class="pc__care">
              <button
                  v-for="c in CARE"
                  :key="c.id"
                  type="button"
                  class="pc-care"
                  :class="{ 'is-done': p.care?.includes(c.id), 'is-want': c.id === 'feed' && p.hungry && !p.care?.includes('feed') }"
                  :disabled="p.care?.includes(c.id) || busy === `care-${p.inventory_id}`"
                  :title="p.care?.includes(c.id) ? `${c.label}: уже сегодня` : `${c.label}: +${c.xp} опыта`"
                  @click="care(p, c)"
              >
                <PixelSprite :sprite="c.icon" :size="15" />
                <span>{{ c.label }}</span>
              </button>
            </div>
            <p v-if="p.hungry && !p.care?.includes('feed')" class="pc__hungry">Проголодался — покорми!</p>

            <label class="pc__trick">
              <span>Трюк</span>
              <select :value="p.trick ?? ''" :disabled="busy === `trick-${p.inventory_id}`" @change="setTrick(p, $event.target.value || null)">
                <option value="">Без трюка</option>
                <option v-for="t in hub.tricks" :key="t.key" :value="t.key" :disabled="p.level < t.level">
                  {{ t.name }}{{ p.level < t.level ? ` — с ${t.level} ур.` : '' }}
                </option>
              </select>
            </label>

            <p v-if="p.busy" class="pc__busy"><AppIcon icon="clock" :size="12" /> {{ p.expedition ? `В походе: ${p.expedition.zone_name}` : p.busy }}</p>
            <div v-else class="pc__actions">
              <button type="button" @click="expeditionPet = p.inventory_id; tab = 'expeditions'"><AppIcon icon="globe" :size="13" /> В поход</button>
              <button type="button" @click="arenaPet = p.inventory_id; tab = 'arena'"><AppIcon icon="sword" :size="13" /> На арену</button>
            </div>
          </div>
        </article>
      </div>

      <section v-if="pets.length" class="pv-perks">
        <h3>Перки по уровню</h3>
        <ul>
          <li v-for="perk in hub.perks" :key="perk.key"><b>{{ perk.level }} ур.</b> {{ perk.label }}</li>
        </ul>
      </section>
    </template>

    <!-- ================= Экспедиции ================= -->
    <template v-else-if="tab === 'expeditions'">
      <div v-if="bulk?.claim.count > 1" class="pv-bulk">
        <button type="button" class="pv-bulk__btn is-ready" :disabled="busy === 'bulk-claim'" @click="bulkAction('claim')">
          <AppIcon icon="gift" :size="16" />
          <span>
            <b>Забрать у всех</b>
            <small>{{ bulk.claim.count }} вернулись · ₳ {{ bulk.claim.cost }}</small>
          </span>
        </button>
      </div>

      <section v-if="trips.length" class="pv-trips">
        <article v-for="trip in trips" :key="trip.id" class="pv-trip" :class="{ 'is-ready': isReady(trip) }">
          <PetFigure :species="trip.pet.species" :traits="trip.pet.traits" :size="40" :backdrop="false" />
          <span class="pv-trip__text">
            <strong>{{ trip.pet.name }}</strong>
            <small>{{ trip.zone_name }}</small>
          </span>
          <button v-if="isReady(trip)" type="button" class="pv-btn pv-btn--go" :disabled="busy === `claim-${trip.id}`" @click="claim(trip)">Забрать добычу</button>
          <span v-else class="pv-trip__timer"><AppIcon icon="clock" :size="13" /> <CountdownText :to="trip.ends_at" /></span>
        </article>
      </section>

      <p v-if="!freePets.length" class="pv-empty">Все питомцы заняты — дождись возвращения.</p>

      <template v-else>
        <div class="pa-block">
          <span class="pa-label">Кого отправить</span>
          <PetPicker v-model="expeditionPet" :pets="freePets" />
        </div>

        <div class="pv-zones">
          <article
              v-for="zone in hub.zones"
              :key="zone.key"
              class="pz"
              :class="[`pz--${zone.key}`, { 'is-locked': selectedPet && selectedPet.level < zone.min_level }]"
          >
            <header>
              <strong>{{ zone.name }}</strong>
              <span><AppIcon icon="clock" :size="12" /> {{ zone.hours }} ч</span>
            </header>
            <ul>
              <li><span>Монеты</span><b>₳ {{ zoneCoins(zone, selectedPet) }}</b></li>
              <li><span>Опыт питомцу</span><b>+{{ zone.xp }}</b></li>
              <li><span>Ключ искателя</span><b>{{ chanceLabel(zone.key_chance + (selectedPet?.level >= 15 ? 5 : 0)) }}</b></li>
              <li><span>Ключ питомцев</span><b>{{ chanceLabel(zone.pet_key_chance) }}</b></li>
            </ul>
            <button
                type="button"
                class="pv-btn pv-btn--go"
                :disabled="!selectedPet || selectedPet.level < zone.min_level || busy === `zone-${zone.key}`"
                @click="sendExpedition(zone)"
            >{{ selectedPet && selectedPet.level < zone.min_level ? `С ${zone.min_level} уровня` : 'Отправить' }}</button>
            <!-- Всех подходящих по уровню — сразу, за плату -->
            <button
                v-if="sendable(zone) > 1"
                type="button"
                class="pv-btn pz__all"
                :disabled="busy === 'bulk-send'"
                @click="bulkAction('send', { zone: zone.key })"
            >Всех сюда ({{ sendable(zone) }}) · ₳ {{ sendable(zone) * fees.send }}</button>
          </article>
        </div>
      </template>
    </template>

    <!-- ================= Арена ================= -->
    <template v-else-if="tab === 'arena'">
      <PetBattle v-if="battle" :battle="battle" @update="battle = $event" @close="closeBattle" />

      <template v-else>
        <p v-if="!freePets.length" class="pv-empty">Свободных питомцев нет — все в походе или на торгах.</p>

        <template v-else>
          <!-- Боец и сводка арены -->
          <div class="pa-top">
            <section v-if="fighter" class="pa-me" :style="{ '--el': ELEMENTS[fighter.element]?.color }">
              <button type="button" class="pa-me__stage" title="Рассмотреть" @click="viewing = fighter">
                <PetFigure :species="fighter.species" :traits="fighter.traits" :size="72" />
              </button>
              <div class="pa-me__info">
                <span class="pa-label">Твой боец</span>
                <strong>{{ fighter.name }}</strong>
                <div class="pa-chips">
                  <span class="pa-chip pa-chip--el">{{ ELEMENTS[fighter.element]?.name }}</span>
                  <span class="pa-chip">Ур. {{ fighter.level }}</span>
                  <span class="pa-chip">Рейтинг {{ fighter.rating }}</span>
                  <span class="pa-chip">{{ fighter.wins }}–{{ fighter.losses }}</span>
                </div>
                <small>Умение: {{ ELEMENTS[fighter.element]?.skill }}</small>
              </div>
            </section>

            <section class="pa-side">
              <div class="pa-left">
                <span class="pa-label">Боёв сегодня</span>
                <b>{{ hub.arena.fights_left }}</b>
              </div>
              <!-- Бесплатные кончились — можно докупить -->
              <button
                  type="button"
                  class="pa-buy"
                  :class="{ 'is-hot': !hub.arena.fights_left }"
                  :disabled="busy === 'buy-fight'"
                  @click="buyFight"
              >
                <AppIcon icon="sword" :size="13" /> Ещё бой · ₳ {{ hub.arena.extra_price }}
              </button>
              <small class="pa-prize">Победа — монеты ×{{ hub.arena.win_multiplier }}</small>
              <div class="pa-wheel" title="Кто кого бьёт сильнее">
                <span v-for="[a, b] in [['fire', 'nature'], ['nature', 'water'], ['water', 'fire'], ['shadow', 'beast']]" :key="a">
                  <i :style="{ color: ELEMENTS[a].color }">{{ ELEMENTS[a].name }}</i> → <i :style="{ color: ELEMENTS[b].color }">{{ ELEMENTS[b].name }}</i>
                </span>
              </div>
              <small class="pa-prize">Каждый понедельник топ-3 арены: ₳ 20 000 / 10 000 / 5 000</small>
            </section>
          </div>

          <div class="pa-block">
            <span class="pa-label">Выбери бойца</span>
            <PetPicker v-model="arenaPet" :pets="freePets" show="rating" />
          </div>

          <div class="pa-block">
            <div class="pa-block__head">
              <span class="pa-label">Соперники твоего уровня</span>
              <button type="button" class="pa-refresh" @click="loadOpponents"><AppIcon icon="bolt" :size="13" /> Других</button>
            </div>

            <PageLoader v-if="!opponents" />
            <p v-else-if="!opponents.length" class="pv-empty">Соперников пока нет.</p>
            <div v-else class="pa-foes">
              <article v-for="foe in opponents" :key="foe.inventory_id" class="pa-foe" :style="{ '--el': ELEMENTS[foe.element]?.color }">
                <button type="button" class="pa-foe__stage" title="Рассмотреть соперника" @click="viewing = foe">
                  <span class="pa-foe__el">{{ ELEMENTS[foe.element]?.name }}</span>
                  <PetFigure :species="foe.species" :traits="foe.traits" :size="58" flip />
                </button>
                <div class="pa-foe__body">
                  <strong>{{ foe.name }}</strong>
                  <small>{{ foe.owner?.username }} · ур. {{ foe.level }} · рейтинг {{ foe.rating }}</small>
                  <div class="pa-odds" :title="`Шанс победы по рейтингу и уровню ~${odds(foe)}%`">
                    <i :style="{ width: `${odds(foe)}%` }"></i>
                    <span>Шанс ~{{ odds(foe) }}%</span>
                  </div>
                  <small v-if="matchup(foe)" class="pa-matchup" :class="matchup(foe).cls">{{ matchup(foe).text }}</small>
                </div>
                <button
                    type="button"
                    class="pv-btn pv-btn--fight pa-foe__go"
                    :disabled="!hub.arena.fights_left || busy === `fight-${foe.inventory_id}`"
                    @click="challenge(foe)"
                ><AppIcon icon="sword" :size="14" /> Вызвать</button>
              </article>
            </div>
          </div>
        </template>
      </template>
    </template>

    <PetDex v-else-if="tab === 'dex'" />
    <PetTop v-else-if="tab === 'top'" />

    <PetViewer
        v-if="viewing"
        :pet="viewing"
        :tricks="viewing.owner?.id === auth.user?.id ? hub?.tricks ?? [] : []"
        @close="viewing = null"
    />

    <!-- Добыча из экспедиции -->
    <Teleport to="body">
      <div v-if="loot" class="pv-loot" @click.self="loot = null">
        <div class="pv-loot__box">
          <PetFigure :species="loot.pet.species" :traits="loot.pet.traits" :size="80" trick="sparkle" />
          <h3>{{ loot.pet.name }} вернулся!</h3>
          <p>{{ loot.zone_name }}: {{ loot.reward.story }}.</p>
          <ul>
            <li><b>₳ {{ loot.reward.coins.toLocaleString('ru-RU') }}</b><small>{{ loot.reward.double ? 'двойная добыча!' : 'монеты' }}</small></li>
            <li><b>+{{ loot.reward.xp }}</b><small>опыта питомцу</small></li>
            <li v-if="loot.reward.keys?.seeker"><b>{{ loot.reward.keys.seeker }}</b><small>ключ искателя</small></li>
            <li v-if="loot.reward.keys?.pets"><b>{{ loot.reward.keys.pets }}</b><small>ключ питомцев</small></li>
          </ul>
          <button type="button" class="pv-btn pv-btn--go" @click="loot = null">Отлично</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import "@/views/pets/PetsView.css";
</style>
