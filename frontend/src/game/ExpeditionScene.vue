<script setup>
/**
 * Экспедиции: карта походов. Зоны — места на карте; питомец, ушедший в
 * поход, стоит у своей зоны с таймером. Выбираешь питомца, нажимаешь
 * на место — получаешь табличку с добычей и кнопку «Отправить».
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import { chanceLabel, PET_RARITY } from '@/data/players/petTraits.js'
import { useNow } from '@/composables/core/useNow.js'
import { usePets } from '@/game/usePets.js'

const { auth, hub, pets, freePets, trips, error, notice, busy, shortage, say, load, run } = usePets()
const now = useNow()

/** Где на карте (проценты) стоит каждая зона. */
const SPOTS = {
  meadow: { x: 16, y: 70 },
  forest: { x: 38, y: 38 },
  caves: { x: 62, y: 70 },
  nether: { x: 84, y: 36 },
}

const petId = ref(null)
const zoneKey = ref(null)
const loot = ref(null)

const pet = computed(() => freePets.value.find((p) => p.inventory_id === petId.value) ?? null)
const zone = computed(() => hub.value?.zones?.find((z) => z.key === zoneKey.value) ?? null)
const fees = computed(() => hub.value?.bulk?.fees ?? { feed: 0, send: 0, claim: 0 })
const readyCount = computed(() => trips.value.filter((t) => new Date(t.ends_at).getTime() <= now.value).length)

const isReady = (trip) => new Date(trip.ends_at).getTime() <= now.value
const sendable = (z) => freePets.value.filter((p) => p.level >= z.min_level).length

/** Монеты за поход: как на сервере — уровень учитывается до 30-го. */
function coins(z, p) {
  if (!p) return `${z.coins[0]}–${z.coins[1]}`
  const perk = p.level >= 25 ? 1.25 : p.level >= 5 ? 1.1 : 1
  const k = (1 + Math.min(p.level, 30) * 0.02) * perk
  return `${Math.round(z.coins[0] * k)}–${Math.round(z.coins[1] * k)}`
}

function send(z) {
  return run(`zone-${z.key}`, async () => {
    await api.post(`/pets/${petId.value}/expedition`, { zone: z.key })
    say(`${pet.value.name} ушёл в «${z.name}».`)
    petId.value = null
    await load()
  }, 'Не удалось отправить.')
}

function claim(trip) {
  return run(`claim-${trip.id}`, async () => {
    const result = await api.post(`/pets/expeditions/${trip.id}/claim`)
    loot.value = { ...result, pet: trip.pet }
    auth.fetchMe()
    await load()
  })
}

function recall(trip) {
  return run(`recall-${trip.id}`, async () => {
    await api.post(`/pets/expeditions/${trip.id}/recall`)
    say(`${trip.pet.name} вернулся досрочно — без добычи.`)
    await load()
  })
}

function bulk(action, payload = {}) {
  return run(`bulk-${action}`, async () => {
    const result = await api.post(`/pets/bulk/${action}`, payload)
    say(action === 'claim'
      ? `Добыча с ${result.done} походов: ₳ ${result.loot.coins.toLocaleString('ru-RU')}. Плата — ₳ ${result.spent}.`
      : `В походе: ${result.done}. Плата — ₳ ${result.spent}.`)
    auth.fetchMe()
    await load()
  })
}

watch(freePets, (list) => { if (!petId.value && list.length) petId.value = list[0].inventory_id }, { immediate: true })
onMounted(load)
</script>

<template>
  <div class="g-scene ex">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="shortage" class="g-msg g-msg--err">Не хватает {{ shortage }} ApexCoin. <RouterLink to="/world?at=miner">Накопай в шахте</RouterLink></p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <!-- Карта походов -->
    <div class="ex-map">
      <div class="ex-map__sky" aria-hidden="true"><i class="ex-cloud ex-cloud--a" /><i class="ex-cloud ex-cloud--b" /></div>
      <div class="ex-map__land" aria-hidden="true" />
      <svg class="ex-map__trail" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <polyline points="16,70 38,38 62,70 84,36" />
      </svg>

      <button
          v-for="z in hub?.zones ?? []"
          :key="z.key"
          type="button"
          class="ex-spot"
          :class="[`ex-spot--${z.key}`, { 'is-on': zoneKey === z.key, 'is-locked': pet && pet.level < z.min_level }]"
          :style="{ left: SPOTS[z.key]?.x + '%', top: SPOTS[z.key]?.y + '%' }"
          @click="zoneKey = z.key"
      >
        <span class="ex-spot__art" aria-hidden="true"><i /><i /><i /></span>
        <span class="g-plank ex-spot__plank">{{ z.name }}<small>{{ z.hours }} ч · с {{ z.min_level }} ур.</small></span>
        <span v-for="t in trips.filter((x) => x.zone === z.key)" :key="t.id" class="ex-walker" :class="{ 'is-back': isReady(t) }">
          <PetFigure :species="t.pet.species" :traits="t.pet.traits" :size="32" :backdrop="false" />
        </span>
      </button>
    </div>

    <!-- Табличка выбранной зоны -->
    <transition name="g-pop">
      <aside v-if="zone" class="g-board g-wood ex-board">
        <button type="button" class="g-board__x" aria-label="Закрыть" @click="zoneKey = null">×</button>
        <h3 class="g-title">{{ zone.name }} <small>{{ zone.hours }} ч в пути</small></h3>
        <ul class="ex-board__stats">
          <li><span>Монеты</span><b>₳ {{ coins(zone, pet) }}</b></li>
          <li><span>Опыт питомцу</span><b>+{{ zone.xp }}</b></li>
          <li><span>Ключ искателя</span><b>{{ chanceLabel(zone.key_chance + (pet?.level >= 15 ? 5 : 0)) }}</b></li>
          <li><span>Ключ королевского</span><b>{{ chanceLabel(zone.royal_key_chance) }}</b></li>
        </ul>
        <div class="ex-board__act">
          <button type="button" class="g-btn g-btn--go" :disabled="!pet || pet.level < zone.min_level || busy === `zone-${zone.key}`" @click="send(zone)">
            {{ !pet ? 'Выбери питомца' : pet.level < zone.min_level ? `С ${zone.min_level} уровня` : `Отправить: ${pet.name}` }}
          </button>
          <button v-if="sendable(zone) > 1" type="button" class="g-btn g-btn--gold" :disabled="busy === 'bulk-send'" @click="bulk('send', { zone: zone.key })">
            Всех подходящих ({{ sendable(zone) }}) · ₳ {{ sendable(zone) * fees.send }}
          </button>
        </div>
      </aside>
      <p v-else class="g-hint ex-hint">Выбери питомца внизу и нажми на место на карте.</p>
    </transition>

    <!-- Кто в пути -->
    <section v-if="trips.length" class="ex-trips g-stone">
      <header>
        <b>В пути</b>
        <button v-if="readyCount > 1" type="button" class="g-btn g-btn--go g-btn--sm" :disabled="busy === 'bulk-claim'" @click="bulk('claim')">
          Забрать у всех ({{ readyCount }}) · ₳ {{ readyCount * fees.claim }}
        </button>
      </header>
      <div v-for="t in trips" :key="t.id" class="ex-trip" :class="{ 'is-ready': isReady(t) }">
        <PetFigure :species="t.pet.species" :traits="t.pet.traits" :size="36" :backdrop="false" />
        <span><b>{{ t.pet.name }}</b><small>{{ t.zone_name }}</small></span>
        <button v-if="isReady(t)" type="button" class="g-btn g-btn--go g-btn--sm" :disabled="busy === `claim-${t.id}`" @click="claim(t)">Забрать добычу</button>
        <template v-else>
          <span class="g-chip"><CountdownText :to="t.ends_at" /></span>
          <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy === `recall-${t.id}`" @click="recall(t)">Вернуть</button>
        </template>
      </div>
    </section>

    <!-- Хотбар: кого отправить -->
    <div v-if="freePets.length" class="g-hotbar ex-hotbar">
      <button
          v-for="p in freePets"
          :key="p.inventory_id"
          type="button"
          class="g-slot"
          :class="{ 'is-on': petId === p.inventory_id }"
          :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }"
          :title="`${p.name} · ур. ${p.level}`"
          @click="petId = p.inventory_id"
      >
        <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" />
        <i class="g-slot__tag">{{ p.level }}</i>
      </button>
    </div>
    <p v-else-if="hub" class="g-hint">Все питомцы заняты — дождись возвращения.</p>

    <!-- Добыча -->
    <div v-if="loot" class="g-modal" @click.self="loot = null">
      <div class="g-modal__box g-wood">
        <PetFigure :species="loot.pet.species" :traits="loot.pet.traits" :size="80" trick="sparkle" />
        <h3>{{ loot.pet.name }} вернулся!</h3>
        <p class="g-hint">{{ loot.zone_name }}: {{ loot.reward.story }}.</p>
        <ul>
          <li><b>₳ {{ loot.reward.coins.toLocaleString('ru-RU') }}</b><small>{{ loot.reward.double ? 'двойная добыча!' : 'монеты' }}</small></li>
          <li><b>+{{ loot.reward.xp }}</b><small>опыта</small></li>
          <li v-if="loot.reward.keys?.seeker"><b>{{ loot.reward.keys.seeker }}</b><small>ключ искателя</small></li>
          <li v-if="loot.reward.keys?.royal"><b>{{ loot.reward.keys.royal }}</b><small>королевский ключ</small></li>
        </ul>
        <button type="button" class="g-btn g-btn--go" @click="loot = null">Отлично</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ex-map { position: relative; height: 340px; overflow: hidden; border: 4px solid #1b0f06; box-shadow: inset 0 0 0 4px rgba(0, 0, 0, 0.15); background: #4f9a3c; }
.ex-map__sky { position: absolute; inset: 0 0 62% 0; background: linear-gradient(#6ec1ff, #bfe6ff); }
.ex-cloud { position: absolute; width: 90px; height: 22px; background: #fff; box-shadow: 14px -10px 0 #fff, 40px -10px 0 #fff, 56px 0 0 #fff; animation: ex-drift 70s linear infinite; }
.ex-cloud--a { top: 36px; left: -120px; }
.ex-cloud--b { top: 14px; left: -380px; transform: scale(0.7); animation-delay: -35s; animation-duration: 100s; }
@keyframes ex-drift { to { transform: translateX(1300px); } }
.ex-map__land { position: absolute; inset: 38% 0 0 0; background: repeating-conic-gradient(#4f9a3c 0 25%, #468c35 0 50%) 0 0 / 32px 32px; }
.ex-map__trail { position: absolute; inset: 0; width: 100%; height: 100%; }
.ex-map__trail polyline { fill: none; stroke: rgba(201, 166, 107, 0.8); stroke-width: 1.4; stroke-dasharray: 3 3; vector-effect: non-scaling-stroke; }

.ex-spot { position: absolute; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 0; translate: -50% -80%; color: #fff; font: inherit; cursor: pointer; background: none; border: 0; transition: transform 0.12s steps(3); }
.ex-spot:hover { transform: translateY(-4px); }
.ex-spot.is-on .ex-spot__art { box-shadow: 0 0 0 4px #ffd45c; }
.ex-spot.is-locked { filter: grayscale(0.7) brightness(0.8); }
.ex-spot__art { position: relative; display: block; width: 76px; height: 60px; background: #3d7f2e; border: 4px solid rgba(0, 0, 0, 0.4); }
.ex-spot__art i { position: absolute; display: block; }
.ex-spot__plank small { color: #ffe9a8; }
/* Облик зон: луг с цветами, лес, пещера, портал Незера */
.ex-spot--meadow .ex-spot__art { background: #6bbf4a; }
.ex-spot--meadow .ex-spot__art i { width: 8px; height: 8px; background: #f472b6; }
.ex-spot--meadow .ex-spot__art i:nth-child(1) { left: 12px; top: 14px; }
.ex-spot--meadow .ex-spot__art i:nth-child(2) { left: 44px; top: 30px; background: #fde047; }
.ex-spot--meadow .ex-spot__art i:nth-child(3) { left: 24px; top: 40px; background: #fff; }
.ex-spot--forest .ex-spot__art { background: #23532a; }
.ex-spot--forest .ex-spot__art i { width: 18px; height: 40px; bottom: 8px; background: #15381c; clip-path: polygon(50% 0, 100% 100%, 0 100%); }
.ex-spot--forest .ex-spot__art i:nth-child(1) { left: 6px; } .ex-spot--forest .ex-spot__art i:nth-child(2) { left: 24px; height: 48px; } .ex-spot--forest .ex-spot__art i:nth-child(3) { left: 44px; }
.ex-spot--caves .ex-spot__art { background: #6a6a72; }
.ex-spot--caves .ex-spot__art i:nth-child(1) { inset: 12px 16px 0; background: #0d0d12; border-radius: 30px 30px 0 0; }
.ex-spot--caves .ex-spot__art i:nth-child(2) { left: 6px; top: 8px; width: 10px; height: 10px; background: #7dd3fc; }
.ex-spot--caves .ex-spot__art i:nth-child(3) { right: 6px; top: 18px; width: 8px; height: 8px; background: #a78bfa; }
.ex-spot--nether .ex-spot__art { background: #4a1414; }
.ex-spot--nether .ex-spot__art i:nth-child(1) { inset: 6px 20px; background: linear-gradient(#a855f7, #6d28d9); border: 4px solid #1a0a2e; animation: ex-portal 1s steps(2) infinite; }
.ex-spot--nether .ex-spot__art i:nth-child(2) { left: 6px; bottom: 4px; width: 12px; height: 8px; background: #f97316; }
.ex-spot--nether .ex-spot__art i:nth-child(3) { right: 6px; bottom: 4px; width: 12px; height: 8px; background: #ef4444; }
@keyframes ex-portal { 50% { filter: brightness(1.4); } }
.ex-walker { position: absolute; right: -10px; top: 6px; animation: ex-walk 1.4s steps(2) infinite; }
.ex-walker.is-back { animation: ex-hop 0.7s steps(2) infinite; filter: drop-shadow(0 0 6px #ffd45c); }
@keyframes ex-walk { 50% { transform: translateY(-3px); } }
@keyframes ex-hop { 50% { transform: translateY(-10px); } }

.ex-board h3 small { font-size: 12px; color: #ffe9a8; }
.ex-board__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 6px; margin: 8px 0; padding: 0; list-style: none; }
.ex-board__stats li { display: flex; justify-content: space-between; gap: 8px; padding: 4px 8px; font-size: 12px; background: rgba(0, 0, 0, 0.3); border: 3px solid #4a2a10; }
.ex-board__stats b { color: #ffd45c; }
.ex-board__act { display: flex; gap: 8px; flex-wrap: wrap; }
.ex-hint { margin-top: 10px; }
.ex-trips { margin-top: 12px; padding: 10px 12px; }
.ex-trips header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 13px; }
.ex-trip { display: flex; align-items: center; gap: 10px; padding: 5px 0; border-top: 2px solid rgba(255, 255, 255, 0.08); font-size: 13px; }
.ex-trip > span:nth-child(2) { display: flex; flex-direction: column; margin-right: auto; }
.ex-trip small { font-size: 11px; color: #c9b9e8; }
.ex-trip.is-ready { background: rgba(74, 222, 128, 0.08); }
.ex-hotbar { margin-top: 12px; }
@media (max-width: 760px) { .ex-map { height: 300px; } .ex-spot__art { width: 58px; height: 48px; } }
@media (prefers-reduced-motion: reduce) { .ex-cloud, .ex-walker, .ex-spot--nether .ex-spot__art i { animation: none; } }
</style>
