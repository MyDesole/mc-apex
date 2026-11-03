<script setup>
/**
 * Кузница: питомец стоит у наковальни, вокруг него три слота экипировки
 * (оружие, доспех, талисман). Нажал слот — справа подходящее снаряжение
 * из сумки; нажал предмет — он надет.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { dungeonApi } from '@/services/pets/dungeon.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

const SLOTS = [
  ['weapon', 'Оружие'],
  ['armor', 'Доспех'],
  ['trinket', 'Талисман'],
]
const STATS = [['atk', 'атака'], ['def', 'защита'], ['hp', 'здоровье'], ['spd', 'скорость']]
const COLORS = { common: '#9ca3af', rare: '#60a5fa', epic: '#c084fc', legendary: '#fbbf24' }

const data = ref(null)
const error = ref('')
const busy = ref(false)
const petId = ref(null)
const slot = ref('weapon')
const hover = ref(null)

const pets = computed(() => data.value?.pets ?? [])
const bag = computed(() => data.value?.gear_bag ?? [])
const pet = computed(() => pets.value.find((p) => p.inventory_id === petId.value) ?? pets.value[0] ?? null)
const free = computed(() => bag.value.filter((g) => g.slot === slot.value && !g.worn_by))

/** Сумма характеристик надетого. */
const total = computed(() => {
  const sum = { atk: 0, def: 0, hp: 0, spd: 0 }
  for (const item of Object.values(pet.value?.gear ?? {})) for (const [k] of STATS) sum[k] += item?.[k] ?? 0
  return sum
})

const stats = (item) => STATS.filter(([k]) => item?.[k]).map(([k, label]) => `${label} ${item[k] > 0 ? '+' : ''}${item[k]}`).join(', ')

async function load() {
  try {
    data.value = await dungeonApi.overview()
    if (!petId.value && pets.value.length) petId.value = pets.value[0].inventory_id
  } catch (e) {
    error.value = e.message || 'Не удалось открыть кузницу.'
  }
}

async function act(call) {
  busy.value = true
  error.value = ''
  try {
    await call()
    await load()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

const equip = (item) => act(() => dungeonApi.equip(pet.value.inventory_id, item.inventory_id))
const unequip = (key) => act(() => dungeonApi.unequip(pet.value.inventory_id, key))

onMounted(load)
</script>

<template>
  <div class="g-scene fg">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
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
          <button v-if="pet.gear?.[slot]" type="button" class="g-btn g-btn--red g-btn--sm fg-take" :disabled="busy" @click="unequip(slot)">Снять {{ SLOTS.find(([k]) => k === slot)[1].toLowerCase() }}</button>
        </section>

        <!-- Что можно надеть в выбранный слот -->
        <section class="fg-bag g-wood">
          <h3 class="g-title">{{ SLOTS.find(([k]) => k === slot)[1] }} в сумке <small>{{ free.length }}</small></h3>
          <div class="fg-bag__grid">
            <button
                v-for="item in free"
                :key="item.inventory_id"
                type="button"
                class="g-slot"
                :style="{ '--rar': COLORS[item.rarity] }"
                :disabled="busy"
                @mouseenter="hover = item"
                @mouseleave="hover = null"
                @focus="hover = item"
                @click="equip(item)"
            ><PixelSprite :sprite="item.sprite || 'mob-rat'" :size="34" /></button>
            <span v-for="n in Math.max(0, 8 - free.length)" :key="`e${n}`" class="g-slot g-slot--empty" />
          </div>
          <p class="fg-tip">
            <template v-if="hover"><b :style="{ color: COLORS[hover.rarity] }">{{ hover.name }}</b> · {{ hover.rarity_name }} · {{ stats(hover) }}</template>
            <template v-else-if="!free.length">Здесь пусто. Снаряжение падает в подземельях и делается на верстаке.
              <RouterLink to="/world?at=dungeon">В подземелья</RouterLink></template>
            <template v-else>Нажми предмет — питомец наденет его. Двойной клик по слоту снимает предмет.</template>
          </p>
        </section>
      </div>

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
.fg-hero { position: absolute; left: 50%; top: 78px; display: flex; flex-direction: column; align-items: center; gap: 4px; translate: -50% 0; }
.fg-block { position: absolute; left: 50%; bottom: 52px; width: 120px; height: 26px; translate: -50% 0; background: #4a4458; border: 4px solid #0e0b14; box-shadow: inset 0 4px 0 #6b6480; z-index: 0; }
.fg-hero { z-index: 1; }
.fg-total { position: absolute; left: 12px; right: 12px; bottom: 10px; display: flex; justify-content: flex-end; gap: 6px; margin: 0; padding: 0; list-style: none; font-size: 11px; }
.fg-total li { display: flex; gap: 4px; padding: 2px 7px; background: rgba(0, 0, 0, 0.5); border: 2px solid #1b0f06; }
.fg-total li.is-zero { opacity: 0.45; }
.fg-total b { color: #ffd45c; }
.fg-take { position: absolute; right: 12px; top: 12px; }
.fg-bag { padding: 14px; }
.fg-bag h3 small { color: #ffe9a8; font-size: 12px; }
.fg-bag__grid { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.fg-tip { min-height: 40px; margin: 0; padding: 8px 10px; font-size: 12px; line-height: 1.5; background: rgba(0, 0, 0, 0.35); border: 3px solid #4a2a10; }
.fg-tip a { color: #ffd45c; }
.fg-hotbar { margin-top: 12px; }
@media (max-width: 760px) { .fg-shop { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .fg-fire i { animation: none; } }
</style>
