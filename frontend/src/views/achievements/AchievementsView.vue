<script setup>
/**
 * Каталог ачивок: что получено, что ещё впереди и насколько это редко.
 */
import { computed, onMounted, ref } from 'vue'

import AchievementBadge from '@/components/achievements/AchievementBadge.vue'
import { achievementsApi } from '@/services/achievements/achievements.js'
import {
  ACHIEVEMENT_GROUPS,
  RARITY_LABELS,
  RARITY_ORDER,
  SPECIAL_GROUP,
  groupOf,
} from '@/data/achievements/groups.js'

const loading = ref(true)
const error = ref('')
const achievements = ref([])
const points = ref(0)

const group = ref('all')
const status = ref('all')

onMounted(async () => {
  try {
    const data = await achievementsApi.list()

    achievements.value = data.achievements ?? []
    points.value = data.points ?? 0
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить ачивки'
  } finally {
    loading.value = false
  }
})

const earnedCount = computed(() => achievements.value.filter((a) => a.earned).length)

const progress = computed(() =>
    achievements.value.length ? Math.round((earnedCount.value / achievements.value.length) * 100) : 0
)

const byRarity = computed(() =>
    RARITY_ORDER.map((rarity) => {
      const list = achievements.value.filter((a) => a.rarity === rarity)

      return {
        rarity,
        label: RARITY_LABELS[rarity],
        earned: list.filter((a) => a.earned).length,
        total: list.length,
      }
    }).filter((row) => row.total)
)

const groups = computed(() => {
  const all = [...ACHIEVEMENT_GROUPS, SPECIAL_GROUP]

  return all
      .map((g) => ({ ...g, items: achievements.value.filter((a) => groupOf(a.code) === g.key) }))
      .filter((g) => g.items.length)
})

const visibleGroups = computed(() =>
    groups.value
        .filter((g) => group.value === 'all' || g.key === group.value)
        .map((g) => ({
          ...g,
          items: g.items
              .filter((a) => status.value === 'all' || (status.value === 'earned' ? a.earned : !a.earned))
              // Сначала полученные, внутри — от обычных к легендарным
              .sort((a, b) =>
                  Number(b.earned) - Number(a.earned)
                  || RARITY_ORDER.indexOf(a.rarity) - RARITY_ORDER.indexOf(b.rarity)
                  || a.points - b.points
              ),
        }))
        .filter((g) => g.items.length)
)

/** Самая редкая из полученных: ей место на виду. */
const rarest = computed(() =>
    achievements.value
        .filter((a) => a.earned)
        .sort((a, b) => (a.holders_percent ?? 100) - (b.holders_percent ?? 100))[0] ?? null
)

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
}

function holders(a) {
  const percent = Number(a.holders_percent ?? 0)

  if (percent === 0) return 'Пока ни у кого'
  if (percent < 1) return 'Меньше 1% игроков'

  return `${percent}% игроков`
}
</script>

<template>
  <div class="achievements-page">
    <header class="achievements-page__head">
      <h1>Ачивки</h1>
      <p>Награды за тиры, турниры, клановые войны и жизнь сообщества. За каждую — очки и ApexCoin.</p>
    </header>

    <div v-if="loading" class="achievements-page__state">Загружаем ачивки…</div>
    <div v-else-if="error" class="achievements-page__state achievements-page__state--error">{{ error }}</div>

    <template v-else>
      <!-- ===================== Прогресс ===================== -->
      <section class="summary">
        <div class="summary__main">
          <div class="summary__numbers">
            <strong>{{ earnedCount }}</strong>
            <span>из {{ achievements.length }} получено</span>
          </div>

          <div class="summary__bar" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
            <span :style="{ width: `${progress}%` }" />
          </div>

          <div class="summary__meta">
            <span>{{ progress }}% коллекции</span>
            <span>{{ points }} очков</span>
          </div>
        </div>

        <ul class="summary__rarity">
          <li v-for="row in byRarity" :key="row.rarity" :class="`summary__rarity--${row.rarity}`">
            <span class="summary__rarity-label">{{ row.label }}</span>
            <strong>{{ row.earned }} / {{ row.total }}</strong>
          </li>
        </ul>

        <div v-if="rarest" class="summary__rarest">
          <AchievementBadge :achievement="rarest" :size="52" />
          <div>
            <span>Самая редкая</span>
            <strong>{{ rarest.name }}</strong>
            <small>{{ holders(rarest) }}</small>
          </div>
        </div>
      </section>

      <!-- ===================== Фильтры ===================== -->
      <div class="filters">
        <div class="filters__chips" role="tablist" aria-label="Раздел">
          <button
              type="button"
              class="chip"
              :class="{ 'chip--active': group === 'all' }"
              @click="group = 'all'"
          >Все</button>
          <button
              v-for="g in groups"
              :key="g.key"
              type="button"
              class="chip"
              :class="{ 'chip--active': group === g.key }"
              @click="group = g.key"
          >{{ g.label }}</button>
        </div>

        <div class="filters__status" role="tablist" aria-label="Статус">
          <button type="button" :class="{ active: status === 'all' }" @click="status = 'all'">Все</button>
          <button type="button" :class="{ active: status === 'earned' }" @click="status = 'earned'">Полученные</button>
          <button type="button" :class="{ active: status === 'locked' }" @click="status = 'locked'">Закрытые</button>
        </div>
      </div>

      <!-- ===================== Каталог ===================== -->
      <section
          v-for="g in visibleGroups"
          :key="g.key"
          class="ach-group"
      >
        <h2>
          {{ g.label }}
          <span>{{ g.items.filter((a) => a.earned).length }} / {{ g.items.length }}</span>
        </h2>

        <div class="ach-grid">
          <article
              v-for="a in g.items"
              :key="a.id"
              class="ach-card"
              :class="{ 'ach-card--locked': !a.earned }"
          >
            <AchievementBadge :achievement="a" :locked="!a.earned" :size="56" />

            <div class="ach-card__body">
              <div class="ach-card__top">
                <strong>{{ a.name }}</strong>
                <span class="ach-card__rarity" :class="`ach-card__rarity--${a.rarity}`">{{ RARITY_LABELS[a.rarity] }}</span>
              </div>

              <p>{{ a.description }}</p>

              <div class="ach-card__meta">
                <span>+{{ a.points }} очков</span>
                <span>{{ holders(a) }}</span>
                <span v-if="a.earned" class="ach-card__date">{{ formatDate(a.earned_at) }}</span>
              </div>
            </div>
          </article>
        </div>
      </section>

      <p v-if="!visibleGroups.length" class="achievements-page__state">
        {{ status === 'earned' ? 'Здесь пока пусто — самое время пройти тир-тест.' : 'Всё собрано. Впечатляет.' }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.achievements-page {
  width: min(1100px, calc(100% - 40px));
  margin: 0 auto;
  padding: 40px 0 72px;

  display: flex;
  flex-direction: column;
  gap: 20px;
}

.achievements-page__head h1 {
  margin: 0;

  color: #fff;

  font-size: clamp(26px, 4vw, 36px);
}

.achievements-page__head p {
  margin: 8px 0 0;

  color: var(--text-dim);
}

.achievements-page__state {
  padding: 40px 0;

  color: var(--text-muted);

  text-align: center;
}

.achievements-page__state--error {
  color: #f87171;
}

/* ---------- Сводка ---------- */

.summary {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr);
  gap: 24px;
  align-items: center;

  padding: 20px 22px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.summary__numbers {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.summary__numbers strong {
  color: #fff;

  font-family: var(--font-display);
  font-size: 34px;
  font-weight: 700;
}

.summary__numbers span {
  color: var(--text-dim);
}

.summary__bar {
  height: 10px;
  margin: 12px 0 8px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  border-radius: 3px;
}

.summary__bar span {
  display: block;
  height: 100%;

  background: var(--accent);
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25);

  transition: width 0.6s ease;
}

.summary__meta {
  display: flex;
  justify-content: space-between;

  color: var(--text-muted);

  font-size: 13px;
}

.summary__rarity {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;

  margin: 0;
  padding: 0;

  list-style: none;
}

.summary__rarity li {
  display: flex;
  flex-direction: column;

  padding-left: 10px;

  border-left: 3px solid #4a4458;
}

.summary__rarity--rare { border-color: #3b82f6 !important; }
.summary__rarity--epic { border-color: #8b5cf6 !important; }
.summary__rarity--legendary { border-color: #eab308 !important; }

.summary__rarity-label {
  color: var(--text-muted);
  font-size: 12px;
}

.summary__rarity strong {
  color: var(--text);
  font-size: 15px;
}

.summary__rarest {
  display: flex;
  align-items: center;
  gap: 12px;
}

.summary__rarest div {
  display: flex;
  flex-direction: column;
}

.summary__rarest span,
.summary__rarest small {
  color: var(--text-muted);
  font-size: 12px;
}

.summary__rarest strong {
  color: #fff;
  font-size: 15px;
}

/* ---------- Фильтры ---------- */

.filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.filters__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip {
  padding: 7px 12px;

  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 4px;

  font-size: 13px;
  font-weight: 600;

  cursor: pointer;
}

.chip:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.chip--active {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}

.filters__status {
  display: flex;

  padding: 3px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.filters__status button {
  padding: 6px 12px;

  color: var(--text-muted);
  background: transparent;
  border-radius: 4px;

  font-size: 13px;
  font-weight: 600;

  cursor: pointer;
}

.filters__status button.active {
  color: #fff;
  background: var(--bg-card-hover);
}

/* ---------- Каталог ---------- */

.ach-group h2 {
  display: flex;
  align-items: baseline;
  gap: 10px;

  margin: 8px 0 12px;

  color: #fff;

  font-size: 18px;
}

.ach-group h2 span {
  color: var(--text-muted);

  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0;
}

.ach-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 10px;
}

.ach-card {
  display: flex;
  gap: 14px;

  padding: 14px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.ach-card--locked .ach-card__top strong,
.ach-card--locked p {
  color: var(--text-muted);
}

.ach-card__body {
  flex: 1;
  min-width: 0;

  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ach-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ach-card__top strong {
  color: #fff;
  font-size: 15px;
}

.ach-card__rarity {
  flex-shrink: 0;

  color: #a29eb0;

  font-size: 11px;
  font-weight: 700;
}

.ach-card__rarity--rare { color: #60a5fa; }
.ach-card__rarity--epic { color: #a78bfa; }
.ach-card__rarity--legendary { color: #facc15; }

.ach-card p {
  margin: 0;

  color: var(--text-dim);

  font-size: 13px;
}

.ach-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;

  margin-top: 2px;

  color: var(--text-muted);

  font-size: 12px;
}

.ach-card__date {
  color: #4ade80;
}

@media (max-width: 860px) {
  .summary {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .achievements-page {
    width: calc(100% - 32px);
    padding-top: 28px;
  }

  .ach-grid {
    grid-template-columns: 1fr;
  }
}
</style>
