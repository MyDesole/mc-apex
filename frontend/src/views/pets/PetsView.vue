<script setup>
/**
 * «Мои питомцы»: всё о питомцах в одном месте.
 *
 * Питомцы — уровень, перки, трюк; Экспедиции — поход за монетами и
 * ключами; Арена — пошаговые бои с рейтингом; Питомцедекс — коллекция
 * видов и черт с наградами; Топ — лучшие питомцы сервера.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PetBattle from '@/components/pets/PetBattle.vue'
import PetDex from '@/components/pets/PetDex.vue'
import PetTop from '@/components/pets/PetTop.vue'
import { ELEMENTS, levelXp } from '@/data/players/petArena.js'
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
const now = ref(Date.now())
const playing = ref({})

const pets = computed(() => hub.value?.pets ?? [])
const freePets = computed(() => pets.value.filter((p) => !p.busy))

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
  error.value = e.message || fallback
  notice.value = ''
}

function left(iso) {
  const ms = new Date(iso).getTime() - now.value
  if (ms <= 0) return null
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин` : `${m}:${String(s).padStart(2, '0')}`
}

function xpPct(p) {
  if (p.level >= 30) return 100
  const from = levelXp(p.level)
  const to = levelXp(p.level + 1)
  return Math.round(((p.xp - from) / (to - from)) * 100)
}

function nextPerk(p) {
  return p.perks.find((perk) => !perk.unlocked) ?? null
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

let tick = null

onMounted(async () => {
  await load()
  battle.value = hub.value?.arena?.active ?? null
  if (tab.value === 'arena') loadOpponents()
  tick = setInterval(() => { now.value = Date.now() }, 1000)
})

onUnmounted(() => clearInterval(tick))
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
        <i v-if="id === 'expeditions' && trips.some((t) => !left(t.ends_at))" class="pv-dot"></i>
      </button>
    </nav>

    <p v-if="notice" class="pv-msg pv-msg--ok">{{ notice }}</p>
    <p v-if="error" class="pv-msg pv-msg--err">{{ error }}</p>

    <PageLoader v-if="!hub && !['dex', 'top'].includes(tab)" />

    <!-- ================= Питомцы ================= -->
    <template v-else-if="tab === 'pets'">
      <p v-if="!pets.length" class="pv-empty">
        Питомцев пока нет. Они выпадают в <RouterLink to="/cases">сундуках</RouterLink>,
        продаются в <RouterLink to="/shop">магазине</RouterLink> и на <RouterLink to="/auction">аукционе</RouterLink>.
      </p>

      <div v-else class="pv-grid">
        <article
            v-for="p in pets"
            :key="p.inventory_id"
            class="pc"
            :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color, '--el': ELEMENTS[p.element]?.color }"
        >
          <div class="pc__stage" :title="p.trick ? 'Нажми — покажет трюк' : null" @click="showTrick(p)">
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
      <section v-if="trips.length" class="pv-trips">
        <article v-for="trip in trips" :key="trip.id" class="pv-trip" :class="{ 'is-ready': !left(trip.ends_at) }">
          <PetFigure :species="trip.pet.species" :traits="trip.pet.traits" :size="40" :backdrop="false" />
          <span class="pv-trip__text">
            <strong>{{ trip.pet.name }}</strong>
            <small>{{ trip.zone_name }}</small>
          </span>
          <button v-if="!left(trip.ends_at)" type="button" class="pv-btn pv-btn--go" :disabled="busy === `claim-${trip.id}`" @click="claim(trip)">Забрать добычу</button>
          <span v-else class="pv-trip__timer"><AppIcon icon="clock" :size="13" /> {{ left(trip.ends_at) }}</span>
        </article>
      </section>

      <p v-if="!freePets.length" class="pv-empty">Все питомцы заняты — дождись возвращения.</p>

      <template v-else>
        <div class="pv-pick">
          <span>Кого отправить:</span>
          <button
              v-for="p in freePets"
              :key="p.inventory_id"
              type="button"
              class="pv-pick__pet"
              :class="{ 'is-on': expeditionPet === p.inventory_id }"
              @click="expeditionPet = p.inventory_id"
          >
            <PetFigure :species="p.species" :traits="p.traits" :size="28" :backdrop="false" />
            {{ p.name }} <b>{{ p.level }}</b>
          </button>
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
          </article>
        </div>
      </template>
    </template>

    <!-- ================= Арена ================= -->
    <template v-else-if="tab === 'arena'">
      <PetBattle v-if="battle" :battle="battle" @update="battle = $event" @close="closeBattle" />

      <template v-else>
        <div class="pv-arena-info">
          <p>
            Пошаговый бой против питомца другого игрока — им управляет компьютер. Каждый ход выбирай: атака, защита или умение стихии.
            Огонь бьёт природу, природа — воду, вода — огонь, тень — зверя. Рейтинг остаётся у питомца, даже если его продать.
            Каждый понедельник тройка лучших получает ₳ 20 000 / 10 000 / 5 000.
          </p>
          <span class="pv-arena-left">Боёв сегодня: <b>{{ hub.arena.fights_left }}</b></span>
        </div>

        <p v-if="!freePets.length" class="pv-empty">Свободных питомцев нет.</p>

        <template v-else>
          <div class="pv-pick">
            <span>Боец:</span>
            <button
                v-for="p in freePets"
                :key="p.inventory_id"
                type="button"
                class="pv-pick__pet"
                :class="{ 'is-on': arenaPet === p.inventory_id }"
                @click="arenaPet = p.inventory_id"
            >
              <PetFigure :species="p.species" :traits="p.traits" :size="28" :backdrop="false" />
              {{ p.name }} <b>{{ p.rating }}</b>
            </button>
          </div>

          <PageLoader v-if="!opponents" />
          <p v-else-if="!opponents.length" class="pv-empty">Соперников пока нет.</p>
          <div v-else class="pv-foes">
            <article v-for="foe in opponents" :key="foe.inventory_id" class="pf-card" :style="{ '--el': ELEMENTS[foe.element]?.color }">
              <PetFigure :species="foe.species" :traits="foe.traits" :size="52" flip />
              <div class="pf-card__info">
                <strong>{{ foe.name }}</strong>
                <small>{{ foe.owner?.username }} · ур. {{ foe.level }}</small>
                <span><i>{{ ELEMENTS[foe.element]?.name }}</i> рейтинг {{ foe.rating }} · {{ foe.wins }}–{{ foe.losses }}</span>
              </div>
              <button
                  type="button"
                  class="pv-btn pv-btn--fight"
                  :disabled="!hub.arena.fights_left || busy === `fight-${foe.inventory_id}`"
                  @click="challenge(foe)"
              ><AppIcon icon="sword" :size="14" /> Вызвать</button>
            </article>
            <button type="button" class="pv-btn pv-more" @click="loadOpponents">Других соперников</button>
          </div>
        </template>
      </template>
    </template>

    <PetDex v-else-if="tab === 'dex'" />
    <PetTop v-else-if="tab === 'top'" />

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
