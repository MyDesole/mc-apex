<script setup>
/**
 * Сумка питомцев — свой инвентарь: снаряжение из подземелий, материалы
 * для верстака, ключи сундуков и Рубли. Надевают снаряжение в Кузнице,
 * здесь его только видно и здесь же крафтят из материалов.
 */
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { dungeonApi } from '@/services/pets/dungeon.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

const gear = ref([])
const craft = ref(null)
const world = ref(null)
const error = ref('')
const notice = ref('')
const busy = ref('')
const hover = ref(null)

const SLOTS = [
  ['weapon', 'Оружие'],
  ['armor', 'Доспех'],
  ['trinket', 'Талисман'],
]
const KEY_NAMES = { seeker: 'Ключ искателя', royal: 'Королевский ключ' }
const RARITY_COLOR = { common: '#9ca3af', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }

const bySlot = (slot) => gear.value.filter((g) => g.slot === slot)
const materials = computed(() => Object.entries(craft.value?.catalog ?? {}).map(([key, def]) => ({
  key, ...def, qty: craft.value?.materials?.[key] ?? 0,
})))
const keys = computed(() => Object.entries(world.value?.keys ?? {}).map(([slug, count]) => ({ slug, count })))

async function load() {
  try {
    const [overview, craftIndex, worldState] = await Promise.all([dungeonApi.overview(), dungeonApi.craftIndex(), api.get('/world')])
    gear.value = overview.gear_bag ?? []
    craft.value = craftIndex
    world.value = worldState
  } catch (e) {
    error.value = e.message || 'Не удалось открыть сумку.'
  }
}

async function make(material) {
  busy.value = material.key
  error.value = ''
  try {
    const result = await dungeonApi.craft(material.key)
    notice.value = `Сделано: ${result.crafted.name} (${result.crafted.rarity_name}).`
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось скрафтить.'
  } finally {
    busy.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="bag">
    <p v-if="error" class="bag__msg bag__msg--err">{{ error }}</p>
    <p v-if="notice" class="bag__msg">{{ notice }}</p>

    <div class="bag__shelf">
      <section v-for="[slot, label] in SLOTS" :key="slot" class="bag__col">
        <h3>{{ label }}</h3>
        <div class="bag__grid">
          <button
              v-for="item in bySlot(slot)"
              :key="item.inventory_id"
              type="button"
              class="cell"
              :class="{ 'is-worn': item.worn_by }"
              :style="{ '--c': RARITY_COLOR[item.rarity] }"
              @mouseenter="hover = item"
              @mouseleave="hover = null"
              @focus="hover = item"
          >
            <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="32" />
            <i v-if="item.worn_by" class="cell__worn">надет</i>
          </button>
          <span v-for="n in Math.max(0, 6 - bySlot(slot).length)" :key="`e${n}`" class="cell cell--empty" />
        </div>
      </section>
    </div>

    <p class="bag__tip">
      <template v-if="hover">
        <b :style="{ color: RARITY_COLOR[hover.rarity] }">{{ hover.name }}</b>
        · {{ PET_RARITY[hover.rarity]?.label ?? hover.rarity_name }}
        · атака {{ hover.atk }}, защита {{ hover.def }}, здоровье {{ hover.hp }}, скорость {{ hover.spd }}
      </template>
      <template v-else>Наведи на предмет, чтобы увидеть характеристики. Надевают снаряжение в Кузнице.</template>
    </p>

    <section class="bag__block">
      <h3>Материалы и верстак</h3>
      <div class="bag__mats">
        <div v-for="m in materials" :key="m.key" class="mat" :class="{ 'is-ready': m.qty >= (craft?.cost ?? 3) }">
          <PixelSprite :sprite="m.sprite" :size="30" />
          <b>{{ m.name }}</b>
          <small>{{ m.qty }} / {{ craft?.cost ?? 3 }}</small>
          <button type="button" class="bag-btn" :disabled="m.qty < (craft?.cost ?? 3) || busy === m.key" @click="make(m)">Скрафтить</button>
        </div>
      </div>
    </section>

    <section class="bag__block">
      <h3>Ключи и Рубли</h3>
      <div class="bag__keys">
        <span class="key key--ruble">₽ {{ Number(world?.rubles ?? 0).toLocaleString('ru-RU') }}</span>
        <span v-for="k in keys" :key="k.slug" class="key">{{ KEY_NAMES[k.slug] ?? k.slug }} × {{ k.count }}</span>
        <span v-if="!keys.length" class="bag__empty">Ключей нет — они выпадают в экспедициях и подземельях.</span>
      </div>
    </section>
  </div>
</template>

<style scoped>
.bag { padding: 16px; color: #fff; background: #20150c; min-height: 360px; }
.bag h3 { margin: 0 0 8px; font-size: 14px; color: #e8dcc0; text-shadow: 1px 1px 0 #000; }
.bag__msg { margin: 0 0 10px; padding: 8px 12px; font-size: 13px; background: rgba(20, 60, 20, 0.85); border: 3px solid #2f6b2f; }
.bag__msg--err { background: rgba(90, 20, 20, 0.88); border-color: #8b2b2b; }
.bag__shelf { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 12px; background: #7c4a21; border: 4px solid #4a2a10; box-shadow: inset 0 4px 0 #a8672f; }
.bag__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.cell { position: relative; display: grid; place-items: center; aspect-ratio: 1; padding: 0; cursor: pointer; background: #3a2412; border: 3px solid var(--c, #2a170a); box-shadow: inset 0 0 0 2px rgba(0, 0, 0, 0.35); }
.cell--empty { cursor: default; border-color: #2a170a; }
.cell.is-worn { filter: brightness(0.7); }
.cell__worn { position: absolute; bottom: 1px; left: 0; right: 0; font-size: 9px; font-style: normal; text-align: center; background: rgba(0, 0, 0, 0.6); }
.bag__tip { min-height: 38px; margin: 10px 0; padding: 8px 12px; font-size: 13px; background: rgba(0, 0, 0, 0.4); border: 3px solid #4a2a10; }
.bag__block { margin-top: 14px; }
.bag__mats { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
.mat { display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 10px 6px; background: #3a2412; border: 3px solid #2a170a; }
.mat.is-ready { border-color: #4ade80; }
.mat small { color: #e8dcc0; }
.bag-btn { padding: 4px 10px; font: inherit; font-size: 12px; font-weight: 800; color: #fff; cursor: pointer; background: #3f9a2f; border: 3px solid #1f4d17; box-shadow: 0 3px 0 #11300d; }
.bag-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.bag__keys { display: flex; gap: 8px; flex-wrap: wrap; }
.key { padding: 6px 12px; font-size: 13px; font-weight: 800; background: #3a2412; border: 3px solid #8a6a10; }
.key--ruble { color: #ffd45c; }
.bag__empty { font-size: 13px; color: #c9a36a; }
@media (max-width: 640px) { .bag__shelf { grid-template-columns: 1fr; } }
</style>
