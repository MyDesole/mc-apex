<script setup>
/**
 * Дом питомцев: комната, в которой питомцы стоят на полу. Нажал на
 * питомца — открылась панель: уровень, уход, трюки, перки.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import { ELEMENTS, MAX_PET_LEVEL, levelXp } from '@/data/players/petArena.js'
import { PET_RARITY } from '@/data/players/petTraits.js'
import { CARE, usePets } from '@/game/usePets.js'

const { auth, hub, pets, error, notice, busy, shortage, playing, say, fail, load, run, care, setTrick, xpPct } = usePets()

const pickedId = ref(null)
const picked = computed(() => pets.value.find((p) => p.inventory_id === pickedId.value) ?? null)

const hungryCount = computed(() => hub.value?.bulk?.feed?.count ?? 0)
const feedCost = computed(() => hub.value?.bulk?.feed?.cost ?? 0)

/** Питомцы стоят группами: самые высокие впереди. */
const crowd = computed(() => [...pets.value].sort((a, b) => b.level - a.level || b.xp - a.xp))

const tricks = computed(() => hub.value?.tricks ?? [])
const unlockedPerks = (p) => p.perks.filter((perk) => perk.unlocked)
const nextPerk = (p) => p.perks.find((perk) => !perk.unlocked) ?? null

async function feedAll() {
  await run('bulk-feed', async () => {
    const result = await api.post('/pets/bulk/feed')
    say(`Накормлено: ${result.done}. Плата — ₳ ${result.spent}.`)
    auth.fetchMe()
    await load()
  })
}

/* ---------- Профиль и аукцион ---------- */

const auction = ref(null)
const DURATIONS = [12, 24, 48]

function toggleProfile(p) {
  return run(`profile-${p.inventory_id}`, async () => {
    await api.post(`/shop/pets/${p.inventory_id}/${p.equipped ? 'unequip' : 'equip'}`)
    p.equipped = !p.equipped
    say(p.equipped ? `${p.name} гуляет по твоему профилю.` : `${p.name} ушёл с профиля.`)
  }, 'Не удалось изменить профиль.')
}

function openAuction(p) {
  auction.value = { id: p.inventory_id, price: 1000, hours: 24 }
}

function startAuction(p) {
  return run(`auction-${p.inventory_id}`, async () => {
    await api.post('/shop/auctions', { inventory_id: p.inventory_id, start_price: Number(auction.value.price), hours: Number(auction.value.hours) })
    say(`${p.name} выставлен на аукцион на ${auction.value.hours} ч.`)
    auction.value = null
    pickedId.value = null
    await load()
  }, 'Не удалось выставить на аукцион.')
}

/** «1 из N» с разделителями разрядов. */
const oneIn = (p) => (p.traits?.one_in ? `1 из ${Number(p.traits.one_in).toLocaleString('ru-RU')}` : null)

onMounted(load)
</script>

<template>
  <div class="g-scene hs">
    <header class="hs-top">
      <span class="g-plank hs-top__name">Дом питомцев</span>
      <span class="g-chip">питомцев <b>{{ pets.length }}</b></span>
      <span class="g-chip">в походе <b>{{ hub?.pets ? pets.filter((p) => p.expedition).length : 0 }}</b></span>
      <button v-if="hungryCount > 1" type="button" class="g-btn g-btn--go g-btn--sm hs-top__feed" :disabled="busy === 'bulk-feed'" @click="feedAll">
        Накормить голодных ({{ hungryCount }}) · ₳ {{ feedCost }}
      </button>
    </header>

    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="shortage" class="g-msg g-msg--err">Не хватает {{ shortage }} ApexCoin. <RouterLink to="/world?at=miner">Накопай в шахте</RouterLink></p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <!-- Комната: пол, ковёр, питомцы -->
    <div class="hs-room">
      <div class="hs-room__window" aria-hidden="true"><i /><i /></div>
      <div class="hs-room__rug" aria-hidden="true" />

      <p v-if="hub && !pets.length" class="hs-empty">В доме пусто. Загляни на Общую ферму — там растут питомцы.</p>

      <button
          v-for="(pet, i) in crowd"
          :key="pet.inventory_id"
          type="button"
          class="hs-pet"
          :class="{ 'is-on': pickedId === pet.inventory_id, 'is-busy': pet.busy, 'is-hungry': pet.hungry }"
          :style="{ '--rar': PET_RARITY[pet.traits?.rarity]?.color ?? '#a8a3b8', '--d': (i % 5) * -0.7 + 's' }"
          @click="pickedId = pickedId === pet.inventory_id ? null : pet.inventory_id"
      >
        <i v-if="pet.hungry && !pet.busy" class="hs-pet__bubble" title="Голодный">!</i>
        <span class="hs-pet__body"><PetFigure :species="pet.species" :traits="pet.traits" :size="70" :backdrop="false" :trick="playing[pet.inventory_id]" /></span>
        <i class="hs-pet__shadow" aria-hidden="true" />
        <span class="g-plank hs-pet__plank"><b>{{ pet.name }}</b><small>ур. {{ pet.level }}{{ pet.busy ? ' · занят' : '' }}</small></span>
      </button>
    </div>

    <!-- Панель выбранного питомца -->
    <transition name="g-pop">
      <aside v-if="picked" class="g-board g-wood hs-board">
        <button type="button" class="g-board__x" aria-label="Закрыть" @click="pickedId = null">×</button>

        <div class="hs-board__fig"><PetFigure :species="picked.species" :traits="picked.traits" :size="96" :trick="playing[picked.inventory_id]" /></div>

        <div class="hs-board__main">
          <h3 class="g-title">{{ picked.name }} <small v-if="picked.traits?.serial">#{{ picked.traits.serial }}</small></h3>
          <p class="hs-chips">
            <span class="g-chip" :style="{ color: ELEMENTS[picked.element]?.color }">{{ ELEMENTS[picked.element]?.name }}</span>
            <span class="g-chip" :style="{ color: PET_RARITY[picked.traits?.rarity]?.color }">{{ PET_RARITY[picked.traits?.rarity]?.label }}<template v-if="oneIn(picked)"> · {{ oneIn(picked) }}</template></span>
            <span class="g-chip">рейтинг <b>{{ picked.rating }}</b></span>
            <span class="g-chip">{{ picked.wins }}–{{ picked.losses }}</span>
          </p>

          <div class="hs-level">
            <span>Ур. <b>{{ picked.level }}</b></span>
            <div class="g-bar" :style="{ '--v': xpPct(picked) + '%', '--c': '#5bd6ff' }"><i /></div>
            <small>{{ picked.level >= MAX_PET_LEVEL ? 'максимум' : `${picked.xp} / ${levelXp(picked.level + 1)}` }}</small>
          </div>

          <p v-if="picked.busy" class="g-hint">{{ picked.busy }}</p>

          <div class="hs-manage">
            <button type="button" class="g-btn g-btn--sm" :class="{ 'g-btn--gold': !picked.equipped }" :disabled="busy === `profile-${picked.inventory_id}`" @click="toggleProfile(picked)">
              {{ picked.equipped ? 'Снять с профиля' : 'Надеть на профиль' }}
            </button>
            <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="!!picked.busy || picked.equipped" :title="picked.equipped ? 'Сначала сними с профиля' : ''" @click="openAuction(picked)">На аукцион</button>
          </div>
          <form v-if="auction?.id === picked.inventory_id" class="hs-auction" @submit.prevent="startAuction(picked)">
            <label>Стартовая цена, ₳ <input v-model.number="auction.price" type="number" min="10"></label>
            <label>Срок <select v-model.number="auction.hours"><option v-for="h in DURATIONS" :key="h" :value="h">{{ h }} ч</option></select></label>
            <button type="submit" class="g-btn g-btn--go g-btn--sm" :disabled="busy === `auction-${picked.inventory_id}`">Выставить</button>
            <button type="button" class="g-btn g-btn--sm" @click="auction = null">Отмена</button>
          </form>

          <div class="hs-care">
            <button
                v-for="c in CARE"
                :key="c.id"
                type="button"
                class="g-btn g-btn--sm"
                :class="{ 'g-btn--go': !picked.care?.includes(c.id) }"
                :disabled="picked.care?.includes(c.id) || busy === `care-${picked.inventory_id}-${c.id}`"
                @click="care(picked, c)"
            >{{ c.label }} · +{{ c.xp }}</button>
          </div>
        </div>

        <div class="hs-board__side">
          <span class="hs-label">Трюки</span>
          <div class="hs-tricks">
            <button
                v-for="t in tricks"
                :key="t.key"
                type="button"
                class="g-slot hs-trick"
                :class="{ 'is-on': picked.trick === t.key }"
                :disabled="picked.level < t.level || busy === `trick-${picked.inventory_id}`"
                :title="picked.level < t.level ? `${t.name}: с ${t.level} уровня` : t.name"
                @click="setTrick(picked, picked.trick === t.key ? null : t.key)"
            >
              <small>{{ t.name }}</small>
              <i class="g-slot__tag">{{ picked.level < t.level ? t.level : '' }}</i>
            </button>
          </div>

          <span class="hs-label">Перки</span>
          <ul class="hs-perks">
            <li v-for="perk in unlockedPerks(picked)" :key="perk.key"><b>{{ perk.level }}</b> {{ perk.label }}</li>
            <li v-if="nextPerk(picked)" class="is-next"><b>{{ nextPerk(picked).level }}</b> {{ nextPerk(picked).label }}</li>
          </ul>
        </div>
      </aside>
    </transition>
  </div>
</template>

<style scoped>
.hs-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.hs-top__name { padding: 4px 18px; font-size: 16px; }
.hs-top__feed { margin-left: auto; }

.hs-room {
  position: relative; display: flex; flex-wrap: wrap; align-content: flex-end; justify-content: center; gap: 6px 14px; min-height: 380px; padding: 90px 14px 22px; overflow: hidden;
  background: linear-gradient(#a87f4a 0 28%, transparent 28%), repeating-linear-gradient(90deg, #a07038 0 64px, #8a5c2e 64px 68px);
  border: 4px solid #1b0f06; box-shadow: inset 0 0 0 4px rgba(0, 0, 0, 0.15);
}
/* Стена с узором и окна */
.hs-room::before { content: ""; position: absolute; inset: 0 0 72% 0; background: repeating-linear-gradient(90deg, #d6c6a0 0 36px, #c9b88e 36px 40px); border-bottom: 6px solid #5c3a18; }
.hs-room__window { position: absolute; z-index: 1; top: 14px; left: 0; right: 0; display: flex; justify-content: space-around; padding: 0 12%; }
.hs-room__window i { width: 64px; height: 56px; background: linear-gradient(#8fd3ff, #d8f0ff); border: 5px solid #5c3a18; box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 0.4); }
.hs-room__rug { position: absolute; left: 12%; right: 12%; bottom: 22px; height: 46%; background: repeating-linear-gradient(45deg, #8b2b2b 0 14px, #a83a3a 14px 28px); border: 5px solid #5a1818; opacity: 0.92; }
.hs-empty { position: relative; z-index: 2; align-self: center; padding: 10px 16px; font-size: 14px; background: rgba(0, 0, 0, 0.55); }

.hs-pet { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 2px; width: 118px; padding: 4px 0 0; color: #fff; font: inherit; cursor: pointer; background: none; border: 0; }
.hs-pet__body { display: block; animation: hs-idle 2.4s ease-in-out var(--d) infinite alternate; }
.hs-pet__shadow { position: absolute; bottom: 26px; width: 56px; height: 9px; background: rgba(0, 0, 0, 0.3); border-radius: 50%; }
.hs-pet:hover .hs-pet__body, .hs-pet.is-on .hs-pet__body { animation-duration: 0.7s; }
.hs-pet.is-on .hs-pet__plank { background: #3f9a2f; border-color: #1f4d17; }
.hs-pet.is-busy { opacity: 0.6; }
.hs-pet__plank { max-width: 112px; }
.hs-pet__plank b { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hs-pet__bubble { position: absolute; top: -6px; right: 14px; width: 22px; height: 22px; font-size: 14px; font-style: normal; font-weight: 800; line-height: 18px; text-align: center; color: #fff; background: #e23b3b; border: 3px solid #fff; animation: hs-pop 1s steps(2) infinite; }
@keyframes hs-idle { to { transform: translateY(-6px); } }
@keyframes hs-pop { 50% { transform: translateY(-4px); } }

.hs-board { display: grid; grid-template-columns: 120px 1fr minmax(220px, 1fr); gap: 14px; align-items: start; }
.hs-board__fig { display: grid; place-items: center; width: 120px; height: 120px; background: rgba(0, 0, 0, 0.25); border: 3px solid #4a2a10; }
.hs-board h3 small { font-size: 12px; color: #ffe9a8; }
.hs-chips { display: flex; gap: 5px; flex-wrap: wrap; margin: 6px 0; }
.hs-level { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 8px; margin: 8px 0; font-size: 13px; }
.hs-care { display: flex; gap: 6px; flex-wrap: wrap; }
.hs-manage { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0; }
.hs-auction { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 8px; padding: 8px; background: rgba(0, 0, 0, 0.3); border: 3px solid #4a2a10; }
.hs-auction label { display: flex; flex-direction: column; gap: 3px; font-size: 11px; font-weight: 800; color: #ffe9a8; }
.hs-auction input, .hs-auction select { width: 110px; padding: 5px 8px; font: inherit; font-size: 13px; color: #fff; background: #20150c; border: 3px solid #4a2a10; }
.hs-label { display: block; margin-bottom: 4px; font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; color: #ffe9a8; }
.hs-tricks { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 10px; }
.hs-trick { width: 62px; height: 40px; }
.hs-trick small { padding: 0 2px; font-size: 9px; line-height: 1.1; text-align: center; }
.hs-perks { margin: 0; padding: 0; list-style: none; font-size: 12px; line-height: 1.5; }
.hs-perks li b { display: inline-block; min-width: 26px; color: #ffd45c; }
.hs-perks .is-next { color: rgba(255, 255, 255, 0.45); }

@media (max-width: 760px) {
  .hs-board { grid-template-columns: 1fr; }
  .hs-room { padding-top: 70px; }
}
@media (prefers-reduced-motion: reduce) { .hs-pet__body, .hs-pet__bubble { animation: none; } }
</style>
