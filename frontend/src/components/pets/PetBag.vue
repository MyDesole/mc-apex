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

    <div class="bag__keys">
      <span class="g-chip g-chip--gold">₽ {{ Number(world?.rubles ?? 0).toLocaleString('ru-RU') }}</span>
      <span v-for="k in keys" :key="k.slug" class="g-chip">{{ KEY_NAMES[k.slug] ?? k.slug }} × {{ k.count }}</span>
    </div>

    <section v-for="[slot, label] in SLOTS" :key="slot" class="bag__block">
      <h3>{{ label }} <small>{{ bySlot(slot).length }}</small></h3>
      <div class="bag__grid">
        <button
            v-for="item in bySlot(slot)"
            :key="item.inventory_id"
            type="button"
            class="cell"
            :class="{ 'is-worn': item.worn_by }"
            :style="{ '--rar': COLORS[item.rarity] }"
            @mouseenter="hover = item"
            @mouseleave="hover = null"
            @focus="hover = item"
            @click="!item.worn_by && (sell = { kind: 'gear', name: item.name, item: item.inventory_id })"
        >
          <PixelSprite :sprite="item.sprite || 'mob-rat'" :size="48" />
          <i v-if="item.set" class="cell__set" title="Часть комплекта">◆</i>
          <i v-if="item.perk" class="cell__perk" :title="item.perk.name">✦</i>
          <i v-if="item.worn_by" class="cell__tag">надет</i>
        </button>
        <span v-if="!bySlot(slot).length" class="bag__empty">Пусто: вещи выпадают в подземельях и куются в Кузнице.</span>
      </div>
    </section>

    <section class="bag__block">
      <h3>Материалы и ядра <small>{{ materials.length }}</small></h3>
      <div class="bag__grid">
        <button v-for="m in materials" :key="m.key" type="button" class="cell" :class="{ 'is-core': m.core }" :style="{ '--rar': m.core ? '#fbbf24' : '#9ca3af' }"
                @mouseenter="hover = { name: m.name, rarity_name: m.core ? 'ядро босса' : 'материал', sell: true }" @mouseleave="hover = null"
                @click="sell = { kind: 'material', name: m.name, material: m.key, maxQty: m.qty }">
          <PixelSprite :sprite="m.sprite" :size="48" />
          <i class="cell__qty">{{ m.qty }}</i>
        </button>
        <span v-if="!materials.length" class="bag__empty">Материалы падают в подземельях.</span>
      </div>
    </section>

    <p class="bag__tip">
      <template v-if="hover">
        <b :style="{ color: COLORS[hover.rarity] }">{{ hover.name }}</b> · {{ hover.rarity_name }}<template v-if="!hover.sell"> · {{ statsText(hover) }}</template>
        <template v-if="hover.perk"><br><span class="bag__perk">✦ {{ hover.perk.name }}: {{ hover.perk.text }}</span></template>
        <template v-if="hover.set"><br><span class="bag__set">Комплект «{{ hover.set.name }}»: 2 вещи +8% к статам, 3 вещи +20% и «{{ hover.set.perk_text }}»</span></template>
        <template v-if="hover.sell || !hover.worn_by"><br>Нажми, чтобы продать на рынке</template>
      </template>
      <template v-else>Наведи на предмет — увидишь характеристики. Надевают и куют в <RouterLink to="/world?at=gear">Кузнице</RouterLink>, продают на <RouterLink to="/world?at=market">Рынке</RouterLink>.</template>
    </p>

    <SellDialog v-if="sell" v-bind="sell" @close="sell = null" @done="done" />
  </div>
</template>

<style scoped>
.bag h3 { margin: 0 0 6px; font-size: 14px; color: #e8dcc0; text-shadow: 1px 1px 0 #000; }
.bag h3 small { margin-left: 6px; font-size: 11px; color: #c9a36a; }
.bag__block { margin-top: 12px; }
.bag__grid { display: flex; flex-wrap: wrap; gap: 4px; }
/* Тонкая рамка цвета редкости, большая иконка */
.cell { position: relative; display: grid; place-items: center; width: 58px; height: 58px; padding: 0; cursor: pointer; background: #1c1209; border: 2px solid var(--rar, #4a2a10); box-shadow: inset 0 0 0 2px rgba(0, 0, 0, 0.5), inset 0 0 14px color-mix(in srgb, var(--rar, #4a2a10) 35%, transparent); }
.cell:hover, .cell:focus-visible { outline: 0; background: #2c1d0e; box-shadow: 0 0 0 2px #ffd45c, inset 0 0 0 2px rgba(0, 0, 0, 0.5); z-index: 1; }
.cell.is-worn { filter: brightness(0.6); cursor: default; }
.cell__tag { position: absolute; bottom: 0; left: 0; right: 0; font-size: 9px; font-style: normal; text-align: center; background: rgba(0, 0, 0, 0.7); }
.cell__perk, .cell__set { position: absolute; top: 1px; font-size: 11px; font-style: normal; line-height: 1; text-shadow: 1px 1px 0 #000; }
.cell__perk { right: 3px; color: #ffd45c; }
.cell__set { left: 3px; color: #7dd3fc; }
.bag__perk { color: #ffd45c; }
.bag__set { color: #7dd3fc; }
.cell__qty { position: absolute; right: 2px; bottom: 0; font-size: 12px; font-style: normal; font-weight: 800; text-shadow: 1px 1px 0 #000, -1px -1px 0 #000; }
/* Подсказка всегда видна внизу, чтобы не прыгала вёрстка */
.bag__tip { position: sticky; bottom: 0; margin: 12px 0 0; padding: 8px 12px; font-size: 13px; background: rgba(12, 8, 4, 0.95); border: 3px solid #4a2a10; }
.bag__tip a { color: #ffd45c; }
.bag__keys { display: flex; gap: 8px; flex-wrap: wrap; }
.bag__empty { font-size: 13px; color: #c9a36a; }
</style>
