<script setup>
/**
 * Склад питомцев — свой инвентарь: снаряжение из подземелий, материалы,
 * ядра боссов, ключи сундуков и Рубли. Здесь предметы видно и можно
 * выставить на рынок; надевают и куют в Кузнице.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { dungeonApi } from '@/services/pets/dungeon.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import SellDialog from '@/game/SellDialog.vue'

const gear = ref([])
const craft = ref(null)
const world = ref(null)
const error = ref('')
const notice = ref('')
const hover = ref(null)
const sell = ref(null)

const SLOTS = [['weapon', 'Оружие'], ['armor', 'Доспех'], ['trinket', 'Талисман']]
const KEY_NAMES = { seeker: 'Ключ искателя', royal: 'Королевский ключ' }
const COLORS = { common: '#9ca3af', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }

const bySlot = (slot) => gear.value.filter((g) => g.slot === slot)
const materials = computed(() => Object.entries(craft.value?.catalog ?? {})
  .map(([key, def]) => ({ key, ...def, qty: craft.value?.materials?.[key] ?? 0 }))
  .filter((m) => m.qty > 0))
const keys = computed(() => Object.entries(world.value?.keys ?? {}).map(([slug, count]) => ({ slug, count })))
const statsText = (g) => [['atk', 'атака'], ['def', 'защита'], ['hp', 'здоровье'], ['spd', 'скорость']].filter(([k]) => g[k]).map(([k, n]) => `${n} ${g[k] > 0 ? '+' : ''}${g[k]}`).join(', ')

async function load() {
  try {
    const [overview, craftIndex, worldState] = await Promise.all([dungeonApi.overview(), dungeonApi.craftIndex(), api.get('/world')])
    gear.value = overview.gear_bag ?? []
    craft.value = craftIndex
    world.value = worldState
  } catch (e) {
    error.value = e.message || 'Не удалось открыть склад.'
  }
}

function done(message) {
  notice.value = message
  load()
}

onMounted(load)
</script>

<template>
  <div class="bag g-scene">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <div class="bag__shelf g-wood">
      <section v-for="[slot, label] in SLOTS" :key="slot" class="bag__col">
        <h3>{{ label }}</h3>
        <div class="bag__grid">
          <button
              v-for="item in bySlot(slot)"
              :key="item.inventory_id"
              type="button"
              class="g-slot"
              :class="{ 'is-worn': item.worn_by }"
              :style="{ '--rar': COLORS[item.rarity] }"
              @mouseenter="hover = item"
              @mouseleave="hover = null"
              @focus="hover = item"
              @click="!item.worn_by && (sell = { kind: 'gear', name: item.name, item: item.inventory_id })"
          >
            <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="32" />
            <i v-if="item.worn_by" class="bag__worn">надет</i>
          </button>
          <span v-for="n in Math.max(0, 6 - bySlot(slot).length)" :key="`e${n}`" class="g-slot g-slot--empty" />
        </div>
      </section>
    </div>

    <p class="bag__tip">
      <template v-if="hover">
        <b :style="{ color: COLORS[hover.rarity] }">{{ hover.name }}</b> · {{ hover.rarity_name }} · {{ statsText(hover) }}
        <template v-if="!hover.worn_by"> · нажми, чтобы продать на рынке</template>
      </template>
      <template v-else>Наведи на предмет, чтобы увидеть характеристики. Надевают и куют в <RouterLink to="/world?at=gear">Кузнице</RouterLink>, продают на <RouterLink to="/world?at=market">Рынке</RouterLink>.</template>
    </p>

    <section class="bag__block">
      <h3>Материалы и ядра</h3>
      <div class="bag__mats">
        <button v-for="m in materials" :key="m.key" type="button" class="mat" :title="`${m.name}: продать на рынке`" @click="sell = { kind: 'material', name: m.name, material: m.key, maxQty: m.qty }">
          <PixelSprite :sprite="m.sprite" :size="30" />
          <b>{{ m.name }}</b>
          <small>{{ m.qty }} · {{ m.core ? 'ядро' : 'материал' }}</small>
          <i class="mat__sell">Продать</i>
        </button>
        <span v-if="!materials.length" class="bag__empty">Материалы падают в подземельях.</span>
      </div>
    </section>

    <section class="bag__block">
      <h3>Ключи и Рубли</h3>
      <div class="bag__keys">
        <span class="g-chip g-chip--gold">₽ {{ Number(world?.rubles ?? 0).toLocaleString('ru-RU') }}</span>
        <span v-for="k in keys" :key="k.slug" class="g-chip">{{ KEY_NAMES[k.slug] ?? k.slug }} × {{ k.count }}</span>
        <span v-if="!keys.length" class="bag__empty">Ключей нет — они выпадают в экспедициях и подземельях.</span>
      </div>
    </section>

    <SellDialog v-if="sell" v-bind="sell" @close="sell = null" @done="done" />
  </div>
</template>

<style scoped>
.bag h3 { margin: 0 0 8px; font-size: 14px; color: #e8dcc0; text-shadow: 1px 1px 0 #000; }
.bag__shelf { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 12px; }
.bag__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.bag__grid .g-slot { width: auto; height: auto; aspect-ratio: 1; }
.g-slot.is-worn { filter: brightness(0.7); cursor: default; }
.bag__worn { position: absolute; bottom: 1px; left: 0; right: 0; font-size: 9px; font-style: normal; text-align: center; background: rgba(0, 0, 0, 0.6); }
.bag__tip { min-height: 38px; margin: 10px 0; padding: 8px 12px; font-size: 13px; background: rgba(0, 0, 0, 0.4); border: 3px solid #4a2a10; }
.bag__tip a { color: #ffd45c; }
.bag__block { margin-top: 14px; }
.bag__mats { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
.mat { position: relative; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 10px 6px; color: #fff; font: inherit; cursor: pointer; background: #3a2412; border: 3px solid #2a170a; }
.mat:hover { border-color: #ffd45c; }
.mat b { font-size: 12px; text-align: center; }
.mat small { color: #e8dcc0; }
.mat__sell { font-size: 10px; font-style: normal; font-weight: 800; color: #ffd45c; }
.bag__keys { display: flex; gap: 8px; flex-wrap: wrap; }
.bag__empty { font-size: 13px; color: #c9a36a; }
@media (max-width: 640px) { .bag__shelf { grid-template-columns: 1fr; } }
</style>
