<script setup>
/**
 * Музей питомцев: коллекция в витринах. Найденные виды стоят на
 * постаментах, ненайденные — тёмные силуэты. Награды за наборы — медали
 * над входом: набрал — забирай.
 */
import { onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import { chanceLabel } from '@/data/players/petTraits.js'
import { RARITY_COLORS } from '@/data/wallet/economy.js'

const auth = useAuthStore()
const dex = ref(null)
const busy = ref(null)
const notice = ref('')
const error = ref('')

const SHELVES = [['model', 'Окрасы'], ['backdrop', 'Фоны'], ['accessory', 'Аксессуары']]

async function load() {
  try {
    dex.value = await api.get('/pets/dex')
  } catch (e) {
    error.value = e.message || 'Не удалось открыть музей.'
  }
}

async function claim(m) {
  busy.value = m.key
  error.value = ''
  try {
    dex.value = await api.post(`/pets/dex/${m.key}/claim`)
    notice.value = `Награда за «${m.title}»: ₳ ${m.coins.toLocaleString('ru-RU')}${m.keys?.royal ? ` и ${m.keys.royal} королевских ключа` : ''}.`
    auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="g-scene ms">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <template v-if="dex">
      <!-- Медали над входом -->
      <section class="ms-medals">
        <article v-for="m in dex.milestones" :key="m.key" class="ms-medal g-wood" :class="{ 'is-done': m.done, 'is-claimed': m.claimed }">
          <b>{{ m.title }}</b>
          <span class="g-bar" :style="{ '--v': Math.min(100, (m.progress / m.need) * 100) + '%', '--c': m.done ? '#ffd45c' : '#5bd6ff' }"><i /></span>
          <small>{{ m.progress }} / {{ m.need }} · ₳ {{ m.coins.toLocaleString('ru-RU') }}<template v-if="m.keys?.royal"> + ключи</template></small>
          <button v-if="m.done && !m.claimed" type="button" class="g-btn g-btn--gold g-btn--sm" :disabled="busy === m.key" @click="claim(m)">Забрать</button>
          <i v-else-if="m.claimed" class="ms-medal__ok">получено</i>
        </article>
      </section>

      <!-- Витрины с видами -->
      <h3 class="g-title ms-h">Виды <small>{{ dex.sections.species.owned }} / {{ dex.sections.species.total }}</small></h3>
      <div class="ms-cases">
        <div v-for="s in dex.sections.species.entries" :key="s.key" class="ms-case" :class="{ 'is-owned': s.owned }" :style="{ '--r': RARITY_COLORS[s.rarity] || '#8b8b9e' }">
          <span class="ms-case__glass"><PetFigure :species="s.key" :size="44" :backdrop="false" class="ms-case__pet" /></span>
          <span class="ms-case__stand" aria-hidden="true" />
          <span class="g-plank ms-case__plank">{{ s.owned ? s.name : '???' }}</span>
        </div>
      </div>

      <!-- Полки с окрасами, фонами, аксессуарами -->
      <section v-for="[kind, title] in SHELVES" :key="kind" class="ms-shelf g-stone">
        <h3 class="g-title">{{ title }} <small>{{ dex.sections[kind].owned }} / {{ dex.sections[kind].total }}</small></h3>
        <ul>
          <li v-for="t in dex.sections[kind].entries" :key="t.key" :class="{ 'is-owned': t.owned }">
            <span>{{ t.owned ? t.name : '???' }}</span><small>{{ chanceLabel(t.chance) }}</small>
          </li>
        </ul>
      </section>
    </template>
    <p v-else-if="!error" class="g-hint">Музей открывается…</p>
  </div>
</template>

<style scoped>
.ms { background: linear-gradient(#e6dcc6 0 120px, transparent 120px); }
.ms-medals { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; margin-bottom: 14px; }
.ms-medal { display: flex; flex-direction: column; gap: 5px; padding: 8px 10px; font-size: 12px; }
.ms-medal b { text-shadow: 1px 1px 0 #4a2a10; }
.ms-medal small { color: #ffe9a8; }
.ms-medal.is-done:not(.is-claimed) { box-shadow: inset 0 4px 0 #a8672f, 0 0 0 3px #ffd45c, 5px 5px 0 rgba(0, 0, 0, 0.4); }
.ms-medal.is-claimed { opacity: 0.6; }
.ms-medal__ok { font-size: 11px; font-style: normal; font-weight: 800; color: #86efac; }
.ms-h small { font-size: 13px; color: #ffe9a8; }
.ms-cases { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: 10px; margin: 10px 0 16px; }
.ms-case { position: relative; display: flex; flex-direction: column; align-items: center; gap: 0; padding: 6px 4px 4px; background: #3a2a52; border: 4px solid #1b1030; }
.ms-case__glass { display: grid; place-items: center; width: 100%; height: 74px; background: linear-gradient(135deg, rgba(180, 220, 255, 0.28), rgba(255, 255, 255, 0.06)); border: 3px solid rgba(180, 220, 255, 0.35); }
.ms-case__stand { width: 80%; height: 10px; background: #6d4ea1; border: 3px solid #1b1030; }
.ms-case__plank { margin-top: 5px; max-width: 100%; }
.ms-case:not(.is-owned) .ms-case__pet { filter: brightness(0) opacity(0.55); }
.ms-case.is-owned { border-color: var(--r); }
.ms-shelf { margin-bottom: 10px; padding: 10px 12px; }
.ms-shelf h3 small { font-size: 12px; color: #c9b9e8; }
.ms-shelf ul { display: flex; flex-wrap: wrap; gap: 4px; margin: 8px 0 0; padding: 0; list-style: none; }
.ms-shelf li { display: flex; gap: 6px; padding: 3px 8px; font-size: 11px; color: rgba(255, 255, 255, 0.4); background: rgba(0, 0, 0, 0.35); border: 2px solid #0e0b14; }
.ms-shelf li.is-owned { color: #fff; border-color: #4ade80; }
.ms-shelf li small { color: #c9b9e8; }
</style>
