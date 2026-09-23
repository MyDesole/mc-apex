<script setup>
import { computed } from 'vue'

const props = defineProps({
  user: { type: Object, required: true },
  aspects: { type: Array, default: () => [] },
})

const tierColors = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const tierColor = computed(() => tierColors[props.user.tier] || '#6b7280')

const aspectLabels = {
  block_placing: 'БП',
  rotka: 'Ротка',
  movement: 'Мувмент',
  building: 'Строительство',
  ppl: 'ППЛ',
}
</script>

<template>
  <div class="player-card">
    <div class="player-card__header">
      <div class="player-card__avatar">
        {{ (user.username || 'И').charAt(0).toUpperCase() }}
      </div>

      <div class="player-card__info">
        <h2>{{ user.username }}</h2>
        <p v-if="user.bio" class="bio">{{ user.bio }}</p>
      </div>

      <div class="player-card__tier" :style="{ borderColor: tierColor, color: tierColor }">
        {{ user.tier }}
      </div>
    </div>

    <div v-for="aspect in aspects" :key="aspect.id" class="aspects-block">
      <div class="aspects-block__title">
        {{ aspect.mode === 'pvp' ? 'PvP (p-ранг)' : 'BedWars (b-ранг)' }}
        <span class="percent">{{ aspect.percent ?? 0 }}%</span>
      </div>

      <div class="aspects-grid">
        <div v-for="(label, key) in aspectLabels" :key="key" class="aspect">
          <span class="aspect__label">{{ label }}</span>
          <div class="aspect__bar">
            <div
                class="aspect__fill"
                :style="{ width: (aspect[key] / 10 * 100) + '%' }"
            />
          </div>
          <span class="aspect__value">{{ aspect[key] }}</span>
        </div>
      </div>
    </div>

    <div v-if="!aspects.length" class="empty">
      Аспекты ещё не заполнены
    </div>
  </div>
</template>

<style scoped>
.player-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
}

.player-card__header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.player-card__avatar {
  width: 64px;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 12px;
  font-size: 24px;
  font-weight: 800;
  color: #fff;
}

.player-card__info { flex: 1; }
.player-card__info h2 { margin: 0 0 4px; font-size: 20px; }
.bio { margin: 0; color: var(--text-dim); font-size: 13px; }

.player-card__tier {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 12px;
  font-size: 24px;
  font-weight: 900;
}

.aspects-block { margin-top: 20px; }

.aspects-block__title {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 700;
}

.percent { color: var(--accent-light); }

.aspects-grid {
  display: grid;
  gap: 10px;
}

.aspect {
  display: grid;
  grid-template-columns: 110px 1fr 32px;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.aspect__bar {
  height: 8px;
  background: #1e1e2a;
  border-radius: 999px;
  overflow: hidden;
}

.aspect__fill {
  height: 100%;
  background: linear-gradient(90deg, #7c3aed, #a78bfa);
  border-radius: 999px;
  transition: width 0.3s ease;
}

.aspect__value {
  text-align: right;
  color: var(--text-dim);
}

.empty {
  padding: 20px;
  color: var(--text-dim);
  text-align: center;
  font-size: 13px;
}
</style>