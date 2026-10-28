<script setup>
/**
 * Питомцедекс: все виды и черты, что когда-либо были у игрока.
 * Не найденные виды — тёмные силуэты. За собранные наборы — награды.
 */
import { onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PetFigure from '@/components/pets/PetFigure.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import { chanceLabel } from '@/data/players/petTraits.js'
import { RARITY_COLORS } from '@/data/wallet/economy.js'

const auth = useAuthStore()
const dex = ref(null)
const busy = ref(null)
const notice = ref('')
const error = ref('')

const TRAIT_SECTIONS = [['model', 'Окрасы'], ['backdrop', 'Фоны'], ['accessory', 'Аксессуары']]

async function load() {
  dex.value = await api.get('/pets/dex')
}

async function claim(m) {
  busy.value = m.key
  error.value = ''
  try {
    dex.value = await api.post(`/pets/dex/${m.key}/claim`)
    notice.value = `Награда за «${m.title}» получена: ₳ ${m.coins.toLocaleString('ru-RU')}${m.keys?.pets ? ` и ${m.keys.pets} ключа от Сундука питомцев` : ''}.`
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
  <PageLoader v-if="!dex" />

  <div v-else class="dx">
    <p v-if="notice" class="dx-msg dx-msg--ok">{{ notice }}</p>
    <p v-if="error" class="dx-msg dx-msg--err">{{ error }}</p>

    <section class="dx-goals">
      <article
          v-for="m in dex.milestones"
          :key="m.key"
          class="dx-goal"
          :class="{ 'is-done': m.done, 'is-claimed': m.claimed }"
      >
        <div class="dx-goal__top">
          <strong>{{ m.title }}</strong>
          <span>₳ {{ m.coins.toLocaleString('ru-RU') }}<template v-if="m.keys?.pets"> + {{ m.keys.pets }} ключа</template></span>
        </div>
        <div class="dx-bar"><i :style="{ width: `${(m.progress / m.need) * 100}%` }"></i></div>
        <div class="dx-goal__foot">
          <small>{{ m.progress }} / {{ m.need }}</small>
          <button v-if="m.done && !m.claimed" type="button" :disabled="busy === m.key" @click="claim(m)">Забрать</button>
          <span v-else-if="m.claimed" class="dx-goal__ok"><AppIcon icon="check" :size="12" /> получено</span>
        </div>
      </article>
    </section>

    <section class="dx-block">
      <h3>Виды <span>{{ dex.sections.species.owned }} / {{ dex.sections.species.total }}</span></h3>
      <div class="dx-species">
        <div
            v-for="s in dex.sections.species.entries"
            :key="s.key"
            class="dx-sp"
            :class="{ 'is-owned': s.owned }"
            :style="{ '--r': RARITY_COLORS[s.rarity] || '#8b8b9e' }"
        >
          <PetFigure :species="s.key" :size="40" :backdrop="false" class="dx-sp__fig" />
          <strong>{{ s.owned ? s.name : '???' }}</strong>
        </div>
      </div>
    </section>

    <section v-for="[kind, title] in TRAIT_SECTIONS" :key="kind" class="dx-block">
      <h3>{{ title }} <span>{{ dex.sections[kind].owned }} / {{ dex.sections[kind].total }}</span></h3>
      <ul class="dx-traits">
        <li v-for="t in dex.sections[kind].entries" :key="t.key" :class="{ 'is-owned': t.owned }">
          <span>{{ t.owned ? t.name : '???' }}</span>
          <small>{{ chanceLabel(t.chance) }}</small>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.dx { display: flex; flex-direction: column; gap: 18px; }

.dx-msg { margin: 0; padding: 10px 14px; border-radius: 10px; font-size: 13px; }
.dx-msg--ok { color: #bbf7d0; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); }
.dx-msg--err { color: #fecaca; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); }

.dx-goals { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 8px; }

.dx-goal {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 12px;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;
}

.dx-goal.is-done:not(.is-claimed) { border-color: rgba(74, 222, 128, 0.5); box-shadow: 0 0 0 1px rgba(74, 222, 128, 0.2); }
.dx-goal.is-claimed { opacity: 0.6; }

.dx-goal__top { display: flex; justify-content: space-between; gap: 8px; }
.dx-goal__top strong { color: #fff; font-size: 13px; }
.dx-goal__top span { color: #fde68a; font-size: 12px; font-weight: 800; white-space: nowrap; }

.dx-bar { height: 6px; overflow: hidden; background: rgba(255, 255, 255, 0.06); border-radius: 4px; }
.dx-bar i { display: block; height: 100%; background: linear-gradient(90deg, #8b5cf6, #4ade80); }

.dx-goal__foot { display: flex; align-items: center; justify-content: space-between; min-height: 26px; }
.dx-goal__foot small { color: #8a8598; font-size: 12px; }

.dx-goal__foot button {
  padding: 4px 12px;

  color: #052e16;
  background: #4ade80;
  border: 0;
  border-radius: 7px;

  font: inherit;
  font-size: 12px;
  font-weight: 800;

  cursor: pointer;
}

.dx-goal__ok { display: inline-flex; align-items: center; gap: 4px; color: #86efac; font-size: 12px; font-weight: 700; }

.dx-block h3 {
  display: flex;
  align-items: center;
  gap: 8px;

  margin: 0 0 10px;

  color: #fff;

  font-size: 15px;
}

.dx-block h3 span { color: #8a8598; font-size: 12px; font-weight: 700; }

.dx-species { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px; }

.dx-sp {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;

  padding: 14px 8px 10px;

  background: rgba(255, 255, 255, 0.02);
  border: 1px dashed rgba(255, 255, 255, 0.1);
  border-radius: 12px;
}

.dx-sp strong { color: #6f6a80; font-size: 12px; }

.dx-sp:not(.is-owned) .dx-sp__fig { filter: brightness(0) opacity(0.45); }

.dx-sp.is-owned {
  background: radial-gradient(circle at 50% 30%, color-mix(in srgb, var(--r) 16%, transparent), transparent 70%), var(--bg-card, #15131c);
  border: 1px solid color-mix(in srgb, var(--r) 40%, transparent);
}

.dx-sp.is-owned strong { color: #fff; }

.dx-traits { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; padding: 0; list-style: none; }

.dx-traits li {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;

  padding: 5px 9px;

  color: #6f6a80;
  background: rgba(255, 255, 255, 0.02);
  border: 1px dashed rgba(255, 255, 255, 0.1);
  border-radius: 8px;

  font-size: 12px;
  font-weight: 700;
}

.dx-traits li small { font-size: 10.5px; }
.dx-traits li.is-owned { color: #e9e5f5; background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); }
.dx-traits li.is-owned small { color: #a78bfa; }
</style>
