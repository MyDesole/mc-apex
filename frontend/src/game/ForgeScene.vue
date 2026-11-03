<script setup>
/**
 * Кузница: наковальня со слотами питомца и верстак. Выбрал предмет в
 * сумке — надень, переплавь в материалы, выстави на рынок или отправь в
 * усиление. На верстаке три рецепта: обычный крафт, ковка на ядре босса
 * и усиление двух одинаковых предметов.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { dungeonApi } from '@/services/pets/dungeon.js'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import SellDialog from '@/game/SellDialog.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

const auth = useAuthStore()

const SLOTS = [['weapon', 'Оружие'], ['armor', 'Доспех'], ['trinket', 'Талисман']]
const STATS = [['atk', 'атака'], ['def', 'защита'], ['hp', 'здоровье'], ['spd', 'скорость']]
const COLORS = { common: '#9ca3af', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }
const NEXT = { common: 'rare', rare: 'epic', epic: 'legendary' }

const data = ref(null)
const craft = ref(null)
const error = ref('')
const notice = ref('')
const busy = ref(false)
const petId = ref(null)
const slot = ref('weapon')
const sel = ref(null)
const pair = ref([])
const coreSel = ref(null)
const sell = ref(null)
const made = ref(null)

const pets = computed(() => data.value?.pets ?? [])
const bag = computed(() => data.value?.gear_bag ?? [])
const pet = computed(() => pets.value.find((p) => p.inventory_id === petId.value) ?? pets.value[0] ?? null)
const free = computed(() => bag.value.filter((g) => g.slot === slot.value && !g.worn_by))
const freeAll = computed(() => bag.value.filter((g) => !g.worn_by))
const cost = computed(() => craft.value?.cost ?? 3)
const recipes = computed(() => craft.value?.recipes ?? {})
const mats = computed(() => Object.entries(craft.value?.catalog ?? {}).map(([key, def]) => ({ key, ...def, qty: craft.value?.materials?.[key] ?? 0 })))
const plain = computed(() => mats.value.filter((m) => !m.core))
const cores = computed(() => mats.value.filter((m) => m.core && m.qty > 0))
const pairItems = computed(() => pair.value.map((id) => bag.value.find((g) => g.inventory_id === id)).filter(Boolean))
const pairOk = computed(() => pairItems.value.length === 2 && pairItems.value[0].slot === pairItems.value[1].slot && pairItems.value[0].rarity === pairItems.value[1].rarity && NEXT[pairItems.value[0].rarity])
const stage = (item) => Number(item?.stage ?? 0)

const total = computed(() => {
  const sum = { atk: 0, def: 0, hp: 0, spd: 0 }
  for (const item of Object.values(pet.value?.gear ?? {})) for (const [k] of STATS) sum[k] += item?.[k] ?? 0
  return sum
})

const stats = (item) => STATS.filter(([k]) => item?.[k]).map(([k, label]) => `${label} ${item[k] > 0 ? '+' : ''}${item[k]}`).join(', ')

async function load() {
  try {
    const [overview, index] = await Promise.all([dungeonApi.overview(), dungeonApi.craftIndex()])
    data.value = overview
    craft.value = index
    if (!petId.value && pets.value.length) petId.value = pets.value[0].inventory_id
  } catch (e) {
    error.value = e.message || 'Не удалось открыть кузницу.'
  }
}

async function act(call, ok) {
  busy.value = true
  error.value = ''
  notice.value = ''
  try {
    const result = await call()
    if (result?.crafted) made.value = result.crafted
    if (ok) notice.value = ok(result)
    sel.value = null
    pair.value = []
    auth.fetchMe()
    await load()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
    await load()
  } finally {
    busy.value = false
  }
}

const equip = (item) => act(() => dungeonApi.equip(pet.value.inventory_id, item.inventory_id), () => `${item.name} надет.`)
const unequip = (key) => act(() => dungeonApi.unequip(pet.value.inventory_id, key))
const makeBasic = (m) => act(() => dungeonApi.craft(m.key))
const forgeCore = (core, key) => act(() => api.post('/dungeons/craft/forge', { core: core.key, slot: key }), () => { coreSel.value = null; return 'Откована вещь на ядре босса!' })
const upgrade = () => act(() => api.post('/dungeons/craft/upgrade', { first: pair.value[0], second: pair.value[1] }))
const dismantle = (item) => act(() => api.post('/dungeons/craft/dismantle', { item: item.inventory_id }), (r) => `Переплавлено: ${r.dismantled} → ${r.count} шт. материала.`)

function pick(item) {
  sel.value = sel.value?.inventory_id === item.inventory_id ? null : item
}

function toPair(item) {
  if (pair.value.includes(item.inventory_id)) pair.value = pair.value.filter((id) => id !== item.inventory_id)
  else pair.value = [...pair.value.slice(-1), item.inventory_id]
}

onMounted(load)
</script>

<template>
  <div class="g-scene fg">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>
    <p v-if="data && !pets.length" class="g-hint">Питомцев нет: снаряжение надевать не на кого.</p>

    <template v-else-if="pet">
      <div class="fg-shop">
        <!-- Наковальня и слоты вокруг питомца -->
        <section class="fg-anvil g-dark">
          <div class="fg-fire" aria-hidden="true"><i /><i /><i /></div>

          <button
              v-for="[key, label] in SLOTS"
              :key="key"
              type="button"
              class="g-slot fg-slot"
              :class="[`fg-slot--${key}`, { 'is-on': slot === key }]"
              :style="{ '--rar': COLORS[pet.gear?.[key]?.rarity] ?? '#6b5a45' }"
              :title="pet.gear?.[key] ? `${pet.gear[key].name}: ${stats(pet.gear[key])}` : label"
              @click="slot = key"
              @dblclick="pet.gear?.[key] && unequip(key)"
          >
            <PixelSprite v-if="pet.gear?.[key]" :sprite="pet.gear[key].sprite || 'mob-rat'" :size="34" />
            <small v-else>{{ label }}</small>
          </button>

          <div class="fg-hero">
            <PetFigure :species="pet.species" :traits="pet.traits" :size="104" :backdrop="false" />
            <span class="g-plank">{{ pet.name }}<small>ур. {{ pet.level }}</small></span>
          </div>
          <i class="fg-block" aria-hidden="true" />

          <ul class="fg-total">
            <li v-for="[k, label] in STATS" :key="k" :class="{ 'is-zero': !total[k] }"><span>{{ label }}</span><b>{{ total[k] > 0 ? '+' : '' }}{{ total[k] }}</b></li>
          </ul>
          <button v-if="pet.gear?.[slot]" type="button" class="g-btn g-btn--red g-btn--sm fg-take" :disabled="busy" @click="unequip(slot)">Снять</button>
        </section>

        <!-- Сумка выбранного слота и действия с предметом -->
        <section class="fg-bag g-wood">
          <h3 class="g-title">{{ SLOTS.find(([k]) => k === slot)[1] }} в сумке <small>{{ free.length }}</small></h3>
          <div class="fg-bag__grid">
            <button
                v-for="item in free"
                :key="item.inventory_id"
                type="button"
                class="g-slot"
                :class="{ 'is-on': sel?.inventory_id === item.inventory_id, 'is-paired': pair.includes(item.inventory_id) }"
                :style="{ '--rar': COLORS[item.rarity] }"
                :disabled="busy"
                @click="pick(item)"
            ><PixelSprite :sprite="item.sprite || 'mob-rat'" :size="34" /></button>
            <span v-for="n in Math.max(0, 8 - free.length)" :key="`e${n}`" class="g-slot g-slot--empty" />
          </div>

          <div v-if="sel" class="fg-sel">
            <p><b :style="{ color: COLORS[sel.rarity] }">{{ sel.name }}</b> · {{ sel.rarity_name }}<br><small>{{ stats(sel) }}</small></p>
            <div class="fg-sel__act">
              <button type="button" class="g-btn g-btn--go g-btn--sm" :disabled="busy" @click="equip(sel)">Надеть</button>
              <button type="button" class="g-btn g-btn--sm" :disabled="busy" @click="toPair(sel)">{{ pair.includes(sel.inventory_id) ? 'Убрать из усиления' : 'В усиление' }}</button>
              <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy" @click="dismantle(sel)">Переплавить</button>
              <button type="button" class="g-btn g-btn--gold g-btn--sm" @click="sell = { kind: 'gear', name: sel.name, item: sel.inventory_id }">Продать на рынке</button>
            </div>
          </div>
          <p v-else class="fg-tip">
            <template v-if="!free.length">Здесь пусто. Снаряжение падает в подземельях и куётся на верстаке ниже.
              <RouterLink to="/world?at=dungeon">В подземелья</RouterLink></template>
            <template v-else>Нажми предмет, чтобы надеть, переплавить, усилить или продать. Двойной клик по слоту снимает надетое.</template>
          </p>
        </section>
      </div>

      <!-- Верстак -->
      <section class="fg-bench g-stone">
        <h3 class="g-title">Верстак</h3>

        <div class="fg-bench__row">
          <div class="fg-recipe">
            <b>Крафт</b>
            <small>{{ cost }} одинаковых материала → случайный предмет их ступени</small>
            <div class="fg-mats">
              <button v-for="m in plain" :key="m.key" type="button" class="g-slot" :class="{ 'is-on': m.qty >= cost }" :disabled="busy || m.qty < cost" :title="`${m.name}: ${m.qty} / ${cost}`" @click="makeBasic(m)">
                <PixelSprite :sprite="m.sprite" :size="28" /><i class="g-slot__tag">{{ m.qty }}</i>
              </button>
            </div>
          </div>

          <div class="fg-recipe">
            <b>Ковка на ядре</b>
            <small>ядро босса + {{ recipes.core_materials ?? 3 }} материала его ступени → предмет выбранного слота, не ниже эпика</small>
            <div class="fg-mats">
              <button v-for="c in cores" :key="c.key" type="button" class="g-slot" :class="{ 'is-on': coreSel?.key === c.key }" :title="`${c.name} × ${c.qty}`" @click="coreSel = coreSel?.key === c.key ? null : c">
                <PixelSprite :sprite="c.sprite" :size="28" /><i class="g-slot__tag">{{ c.qty }}</i>
              </button>
              <span v-if="!cores.length" class="fg-none">ядра падают с боссов подземелий</span>
            </div>
            <div v-if="coreSel" class="fg-slotpick">
              <button v-for="[key, label] in SLOTS" :key="key" type="button" class="g-btn g-btn--gold g-btn--sm" :disabled="busy" @click="forgeCore(coreSel, key)">{{ label }}</button>
            </div>
          </div>

          <div class="fg-recipe">
            <b>Усиление</b>
            <small>два свободных предмета одного слота и редкости + монеты → предмет на редкость выше</small>
            <div class="fg-pair">
              <span v-for="n in 2" :key="n" class="g-slot" :style="{ '--rar': COLORS[pairItems[n - 1]?.rarity] }">
                <PixelSprite v-if="pairItems[n - 1]" :sprite="pairItems[n - 1].sprite || 'mob-rat'" :size="28" />
              </span>
              <button type="button" class="g-btn g-btn--go g-btn--sm" :disabled="busy || !pairOk" @click="upgrade">
                Усилить<template v-if="pairOk"> → {{ NEXT[pairItems[0].rarity] }}</template>
              </button>
            </div>
            <small v-if="pairItems.length === 2 && !pairOk">Нужны одного слота и редкости, не легендарные.</small>
            <small v-else>Цена: ₳ {{ recipes.upgrade_coins_per_stage ?? 1500 }} × ступень предметов.</small>
          </div>
        </div>
      </section>

      <!-- Хотбар питомцев -->
      <div class="g-hotbar fg-hotbar">
        <button
            v-for="p in pets"
            :key="p.inventory_id"
            type="button"
            class="g-slot"
            :class="{ 'is-on': pet.inventory_id === p.inventory_id }"
            :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }"
            :title="`${p.name} · ур. ${p.level}`"
            @click="petId = p.inventory_id"
        >
          <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" />
          <i class="g-slot__tag">{{ Object.keys(p.gear ?? {}).length }}/3</i>
        </button>
      </div>
    </template>

    <!-- Результат ковки -->
    <div v-if="made" class="g-modal" @click.self="made = null">
      <div class="g-modal__box g-wood">
        <PixelSprite :sprite="made.sprite || 'mob-rat'" :size="64" anim="bounce" />
        <h3 :style="{ color: COLORS[made.rarity] }">{{ made.name }}</h3>
        <p class="g-hint">{{ made.rarity_name }} · {{ stats(made) }}</p>
        <button type="button" class="g-btn g-btn--go" @click="made = null">В сумку</button>
      </div>
    </div>

    <SellDialog v-if="sell" v-bind="sell" @close="sell = null" @done="(m) => { notice = m; sel = null; load() }" />
  </div>
</template>

<style scoped>
.fg-shop { display: grid; grid-template-columns: minmax(300px, 1.1fr) 1fr; gap: 12px; }
.fg-anvil { position: relative; min-height: 330px; padding: 12px; background: radial-gradient(60% 50% at 50% 85%, rgba(255, 122, 26, 0.22), transparent 70%), #1d1626; }
.fg-fire { position: absolute; left: 14px; bottom: 14px; width: 30px; height: 30px; }
.fg-fire i { position: absolute; bottom: 0; width: 10px; background: #ff9a2a; box-shadow: 0 0 16px 6px rgba(255, 154, 42, 0.5); animation: fg-flame 0.6s steps(2) infinite; }
.fg-fire i:nth-child(1) { left: 0; height: 16px; } .fg-fire i:nth-child(2) { left: 9px; height: 26px; background: #ffd45c; animation-delay: -0.2s; } .fg-fire i:nth-child(3) { left: 18px; height: 14px; animation-delay: -0.4s; }
@keyframes fg-flame { 50% { transform: scale(0.85, 1.2); opacity: 0.85; } }
.fg-slot { position: absolute; width: 66px; height: 66px; }
.fg-slot small { padding: 2px; font-size: 10px; text-align: center; color: rgba(255, 255, 255, 0.5); }
.fg-slot--weapon { left: 10%; top: 38%; }
.fg-slot--armor { right: 10%; top: 38%; }
.fg-slot--trinket { left: 50%; top: 8px; margin-left: -33px; }
.fg-hero { position: absolute; left: 50%; top: 78px; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; translate: -50% 0; }
.fg-block { position: absolute; left: 50%; bottom: 52px; width: 120px; height: 26px; translate: -50% 0; background: #4a4458; border: 4px solid #0e0b14; box-shadow: inset 0 4px 0 #6b6480; }
.fg-total { position: absolute; left: 12px; right: 12px; bottom: 10px; display: flex; justify-content: flex-end; gap: 6px; margin: 0; padding: 0; list-style: none; font-size: 11px; }
.fg-total li { display: flex; gap: 4px; padding: 2px 7px; background: rgba(0, 0, 0, 0.5); border: 2px solid #1b0f06; }
.fg-total li.is-zero { opacity: 0.45; }
.fg-total b { color: #ffd45c; }
.fg-take { position: absolute; right: 12px; top: 12px; }
.fg-bag { padding: 14px; }
.fg-bag h3 small { color: #ffe9a8; font-size: 12px; }
.fg-bag__grid { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.g-slot.is-paired { outline: 3px dashed #4ade80; outline-offset: 2px; }
.fg-tip, .fg-sel { min-height: 40px; margin: 0; padding: 8px 10px; font-size: 12px; line-height: 1.5; background: rgba(0, 0, 0, 0.35); border: 3px solid #4a2a10; }
.fg-tip a { color: #ffd45c; }
.fg-sel p { margin: 0 0 6px; }
.fg-sel small { color: #e8dcc0; }
.fg-sel__act { display: flex; gap: 6px; flex-wrap: wrap; }
.fg-bench { margin-top: 12px; padding: 12px; }
.fg-bench__row { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; margin-top: 8px; }
.fg-recipe { display: flex; flex-direction: column; gap: 5px; padding: 8px 10px; background: rgba(0, 0, 0, 0.3); border: 3px solid #0e0b14; }
.fg-recipe b { color: #ffd45c; }
.fg-recipe small { font-size: 11px; line-height: 1.4; color: #c9b9e8; }
.fg-mats, .fg-pair, .fg-slotpick { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
.fg-none { font-size: 11px; color: #9b8bb8; }
.fg-hotbar { margin-top: 12px; }
@media (max-width: 760px) { .fg-shop { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .fg-fire i { animation: none; } }
</style>
