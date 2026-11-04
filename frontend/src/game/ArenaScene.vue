<script setup>
/**
 * Арена: песчаное кольцо с трибунами. Слева твой боец, справа соперники
 * стоят в ряд, как гладиаторы перед боем. Бой идёт на той же сцене.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PetBattle from '@/components/pets/PetBattle.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { PET_RARITY } from '@/data/players/petTraits.js'
import { usePets } from '@/game/usePets.js'

const { auth, hub, freePets, error, notice, busy, shortage, say, fail, load, run } = usePets()

const petId = ref(null)
const opponents = ref(null)
const battle = ref(null)

const fighter = computed(() => freePets.value.find((p) => p.inventory_id === petId.value) ?? null)
const arena = computed(() => hub.value?.arena ?? null)

// Кто кого бьёт сильнее — как на сервере
const STRONG = {
  fire: ['nature', 'wind'], nature: ['water', 'earth'], water: ['fire'], shadow: ['beast'],
  beast: ['light'], light: ['shadow'], wind: ['earth'], earth: ['fire'],
}
const beats = (a, b) => (STRONG[a] ?? []).includes(b)

/** Примерный шанс: Эло по рейтингу плюс поправка за уровни и стихии. */
function odds(foe) {
  const me = fighter.value
  if (!me) return 50
  let diff = (me.rating - foe.rating) + (me.level - foe.level) * 25
  if (beats(me.element, foe.element)) diff += 60
  if (beats(foe.element, me.element)) diff -= 60
  return Math.max(3, Math.min(97, Math.round(100 / (1 + 10 ** (-diff / 400)))))
}

function matchup(foe) {
  const me = fighter.value
  if (!me) return null
  if (beats(me.element, foe.element)) return { text: 'Твоя стихия сильнее', cls: 'is-good' }
  if (beats(foe.element, me.element)) return { text: 'Его стихия сильнее', cls: 'is-bad' }
  return null
}

async function loadOpponents() {
  if (!petId.value) return
  opponents.value = null
  try {
    opponents.value = (await api.get(`/pets/${petId.value}/opponents`)).opponents
  } catch (e) {
    opponents.value = []
    fail(e, 'Не удалось найти соперников.')
  }
}

function challenge(foe) {
  return run(`fight-${foe.inventory_id}`, async () => {
    battle.value = await api.post(`/pets/${petId.value}/battle`, { opponent: foe.inventory_id })
    error.value = ''
  }, 'Не удалось начать бой.')
}

function buyFight() {
  return run('buy-fight', async () => {
    const result = await api.post('/pets/arena/buy-fight')
    hub.value.arena.fights_left = result.fights_left
    hub.value.arena.extra_price = result.next_price
    say('Ещё один бой куплен — выбирай соперника.')
    auth.fetchMe()
  }, 'Не удалось купить бой.')
}

async function closeBattle() {
  battle.value = null
  await load()
  loadOpponents()
}

watch(freePets, (list) => { if (!petId.value && list.length) petId.value = list[0].inventory_id }, { immediate: true })
watch(petId, loadOpponents)

onMounted(async () => {
  await load()
  battle.value = hub.value?.arena?.active ?? null
  loadOpponents()
})
</script>

<template>
  <div class="g-scene ar">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="shortage" class="g-msg g-msg--err">Не хватает {{ shortage }} ApexCoin. <RouterLink to="/world?at=miner">Накопай в шахте</RouterLink></p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <!-- Идёт бой -->
    <div v-if="battle" class="ar-ring">
      <div class="ar-stands" aria-hidden="true"><i /><i /><i /></div>
      <PetBattle :battle="battle" @update="battle = $event" @close="closeBattle" />
      <div class="ar-sand" aria-hidden="true" />
    </div>

    <!-- Перед боем -->
    <template v-else-if="arena">
      <div v-if="!freePets.length" class="g-hint">Свободных питомцев нет: все в походе или на торгах.</div>

      <template v-else>
        <div class="ar-ring">
          <div class="ar-stands" aria-hidden="true"><i /><i /><i /></div>

          <div class="ar-pit">
            <section v-if="fighter" class="ar-me" :style="{ '--el': ELEMENTS[fighter.element]?.color }">
              <span class="ar-me__fig"><PetFigure :species="fighter.species" :traits="fighter.traits" :size="96" :backdrop="false" /></span>
              <span class="g-plank ar-me__plank">
                {{ fighter.name }}
                <small>{{ ELEMENTS[fighter.element]?.name }} · ур. {{ fighter.level }} · {{ fighter.rating }} · {{ fighter.wins }}–{{ fighter.losses }}</small>
              </span>
            </section>

            <span class="ar-vs" aria-hidden="true">VS</span>

            <div class="ar-foes">
              <p v-if="!opponents" class="ar-wait">Соперники выходят на песок…</p>
              <p v-else-if="!opponents.length" class="ar-wait">Соперников пока нет.</p>
              <article v-for="foe in opponents ?? []" :key="foe.inventory_id" class="ar-foe" :style="{ '--el': ELEMENTS[foe.element]?.color }">
                <span class="ar-foe__fig"><PetFigure :species="foe.species" :traits="foe.traits" :size="62" :backdrop="false" flip /></span>
                <span class="g-plank ar-foe__plank">
                  {{ foe.name }}
                  <small>{{ foe.owner?.username }} · ур. {{ foe.level }}</small>
                </span>
                <span class="g-bar ar-odds" :title="`Шанс победы ~${odds(foe)}%`" :style="{ '--v': odds(foe) + '%', '--c': odds(foe) >= 50 ? '#4ade80' : '#f87171' }"><i /></span>
                <small v-if="matchup(foe)" class="ar-match" :class="matchup(foe).cls">{{ matchup(foe).text }}</small>
                <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="!arena.fights_left || busy === `fight-${foe.inventory_id}`" @click="challenge(foe)">В бой</button>
              </article>
            </div>
          </div>
          <div class="ar-sand" aria-hidden="true" />
        </div>

        <!-- Табло арены -->
        <div class="ar-board g-stone">
          <span class="g-chip"><b>{{ arena.fights_left }}</b> боёв сегодня</span>
          <button type="button" class="g-btn g-btn--gold g-btn--sm" :disabled="busy === 'buy-fight'" @click="buyFight">Ещё бой · ₳ {{ arena.extra_price }}</button>
          <button type="button" class="g-btn g-btn--sm" @click="loadOpponents">Другие соперники</button>
          <small class="g-hint">Победа — монеты ×{{ arena.win_multiplier }}. Топ-3 арены в понедельник: ₳ 20 000 / 10 000 / 5 000.</small>
          <span class="ar-wheel" title="Кто кого бьёт сильнее">
            <span v-for="[a, b] in [['fire', 'nature'], ['nature', 'water'], ['water', 'fire'], ['shadow', 'beast'], ['beast', 'light'], ['light', 'shadow']]" :key="a">
              <i :style="{ color: ELEMENTS[a]?.color }">{{ ELEMENTS[a]?.name }}</i> → <i :style="{ color: ELEMENTS[b]?.color }">{{ ELEMENTS[b]?.name }}</i>
            </span>
          </span>
        </div>

        <!-- Хотбар бойцов -->
        <div class="g-hotbar ar-hotbar">
          <button
              v-for="p in freePets"
              :key="p.inventory_id"
              type="button"
              class="g-slot"
              :class="{ 'is-on': petId === p.inventory_id }"
              :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }"
              :title="`${p.name} · ур. ${p.level} · рейтинг ${p.rating}`"
              @click="petId = p.inventory_id"
          >
            <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" />
            <i class="g-slot__tag">{{ p.level }}</i>
          </button>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.ar-ring { position: relative; border: 4px solid #4a2a10; background: #2a1a0c; }
.ar-stands { display: grid; }
.ar-stands i {
  display: block; height: 32px; border-bottom: 4px solid #1b0f06; animation: ar-cheer 1.2s steps(2) infinite;
  background:
    radial-gradient(circle at 8px 12px, #f4c7a1 0 5px, transparent 6px) 0 0 / 24px 32px,
    radial-gradient(circle at 20px 22px, #c9a66b 0 4px, transparent 5px) 0 0 / 24px 32px,
    radial-gradient(circle at 14px 8px, #e23b3b 0 3px, transparent 4px) 0 0 / 48px 32px,
    linear-gradient(#5d3f23, #4a2f16);
}
.ar-stands i:nth-child(2) { background-position: 12px 0, 12px 0, 24px 0, 0 0; animation-delay: -0.4s; }
.ar-stands i:nth-child(3) { background-position: 6px 0, 18px 0, 12px 0, 0 0; animation-delay: -0.8s; filter: hue-rotate(40deg); }
@keyframes ar-cheer { 50% { transform: translateY(-2px); } }
.ar-sand { height: 18px; border-top: 4px solid #8a6a30; background: repeating-conic-gradient(#e3c88b 0 25%, #d9bd7a 0 50%) 0 0 / 18px 18px; }
.ar-pit {
  display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; min-height: 250px; padding: 16px 14px 8px;
  background: repeating-conic-gradient(#e3c88b 0 25%, #d9bd7a 0 50%) 0 0 / 18px 18px;
}
.ar-me { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 190px; }
.ar-me__fig { display: block; padding: 6px; background: radial-gradient(circle at 50% 80%, color-mix(in srgb, var(--el, #888) 45%, transparent), transparent 70%); }
.ar-me__plank, .ar-foe__plank { max-width: 180px; }
.ar-vs { align-self: center; flex: none; font-size: 30px; font-weight: 900; color: #8b2b2b; text-shadow: 3px 3px 0 rgba(0, 0, 0, 0.35); }
.ar-foes { display: flex; flex: 1; align-items: flex-end; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
.ar-wait { margin: auto; padding: 8px 14px; font-size: 13px; font-weight: 800; color: #4a2a10; background: rgba(255, 255, 255, 0.5); }
.ar-foe { display: flex; flex-direction: column; align-items: center; gap: 3px; width: 112px; }
.ar-foe__fig { display: block; background: radial-gradient(circle at 50% 80%, color-mix(in srgb, var(--el, #888) 40%, transparent), transparent 70%); }
.ar-odds { width: 90px; }
.ar-match { font-size: 10px; font-weight: 800; text-shadow: 1px 1px 0 rgba(255, 255, 255, 0.5); }
.ar-match.is-good { color: #166534; }
.ar-match.is-bad { color: #991b1b; }
.ar-board { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding: 10px 12px; }
.ar-board .g-hint { flex-basis: 100%; margin: 0; }
.ar-wheel { display: flex; gap: 10px; flex-wrap: wrap; font-size: 11px; color: #e8dcc0; }
.ar-hotbar { margin-top: 12px; }
@media (max-width: 760px) { .ar-pit { flex-direction: column; align-items: center; } .ar-foes { justify-content: center; } }
@media (prefers-reduced-motion: reduce) { .ar-stands i { animation: none; } }
</style>
