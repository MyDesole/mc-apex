<script setup>
import { computed, onMounted, ref } from 'vue'
import { siteApi } from '@/services/site/site.js'
import { seasonsApi } from '@/services/seasons/seasons.js'
import { userLink } from '@/utils/links.js'
import { tierColors } from '@/data/players/profileCustomization.js'
import { ASPECTS, ASPECT_MAX } from '@/data/players/aspects.js'
import PageLoader from '@/components/core/PageLoader.vue'
import PageState from '@/components/core/PageState.vue'

const stats = ref(null)
const loading = ref(true)
const failed = ref(false)

const MONTHS = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек']

const totals = computed(() => {
  const t = stats.value?.totals ?? {}
  return [
    { label: 'Игроков', value: t.players },
    { label: 'В рейтинге', value: t.ranked },
    { label: 'Тир-тестов', value: t.tests },
    { label: 'Кланов', value: t.clans },
    { label: 'Клановых войн', value: t.clan_wars },
  ]
})

const tiers = computed(() => {
  const rows = stats.value?.tiers ?? []
  const max = Math.max(1, ...rows.map((r) => r.players))
  const sum = rows.reduce((acc, r) => acc + r.players, 0)
  return rows.map((r) => ({
    ...r,
    color: tierColors[r.tier],
    width: (r.players / max) * 100,
    share: sum ? Math.round((r.players / sum) * 100) : 0,
  }))
})

const aspects = computed(() => {
  const byKey = Object.fromEntries((stats.value?.aspects ?? []).map((a) => [a.key, a.average]))
  return ASPECTS.pvp.map((a) => ({ ...a, value: byKey[a.key] ?? 0, width: ((byKey[a.key] ?? 0) / ASPECT_MAX) * 100 }))
})

const months = computed(() => {
  const rows = stats.value?.tests_by_month ?? []
  const max = Math.max(1, ...rows.map((r) => r.tests))
  return rows.map((r) => ({
    ...r,
    label: MONTHS[Number(r.month.slice(5)) - 1],
    height: (r.tests / max) * 100,
  }))
})

const seasons = ref(null)

function formatDate(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : ''
}

onMounted(async () => {
  // Сезоны — дополнение: без них страница всё равно показывает статистику
  seasonsApi.list().then((data) => { seasons.value = data }).catch(() => {})

  try {
    stats.value = await siteApi.stats()
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="content-page">
    <header class="content-page__head">
      <h1>Статистика</h1>
      <p>Как распределены игроки по тирам и чем живёт проект. Обновляется раз в 10 минут.</p>
    </header>

    <PageLoader v-if="loading" />
    <PageState v-else-if="failed" kind="error" title="Не удалось загрузить статистику" />

    <template v-else>
      <section class="stats-totals">
        <div v-for="item in totals" :key="item.label" class="content-card stats-total">
          <strong>{{ item.value ?? 0 }}</strong>
          <span>{{ item.label }}</span>
        </div>
      </section>

      <div class="stats-grid">
        <section class="content-card">
          <h2>Игроки по тирам</h2>
          <ul class="bars">
            <li v-for="row in tiers" :key="row.tier" class="bars__row">
              <span class="tier-block" :style="{ '--tier': row.color }">{{ row.tier }}</span>
              <div class="bars__track">
                <div class="bars__fill" :style="{ width: `${row.width}%`, background: row.color }" />
              </div>
              <span class="bars__value">{{ row.players }} <small>{{ row.share }}%</small></span>
            </li>
          </ul>
        </section>

        <section class="content-card">
          <h2>Средние аспекты PvP</h2>
          <ul class="bars">
            <li v-for="row in aspects" :key="row.key" class="bars__row bars__row--wide">
              <span class="bars__label">{{ row.label }}</span>
              <div class="bars__track">
                <div class="bars__fill" :style="{ width: `${row.width}%` }" />
              </div>
              <span class="bars__value">{{ row.value }}<small>/{{ ASPECT_MAX }}</small></span>
            </li>
          </ul>
        </section>
      </div>

      <section class="content-card">
        <h2>Тир-тесты за полгода</h2>
        <div class="columns">
          <div v-for="row in months" :key="row.month" class="columns__item">
            <span class="columns__value">{{ row.tests }}</span>
            <div class="columns__track">
              <div class="columns__fill" :style="{ height: `${row.height}%` }" />
            </div>
            <span class="columns__label">{{ row.label }}</span>
          </div>
        </div>
      </section>

      <section v-if="seasons" class="content-card">
        <h2>Сезоны</h2>
        <p class="season-current">
          Сейчас идёт <strong>{{ seasons.current.name }}</strong> — с {{ formatDate(seasons.current.starts_at) }}.
          В конце сезона места в рейтинге сохраняются в профилях игроков.
        </p>

        <div v-if="seasons.past.length" class="season-list">
          <article v-for="s in seasons.past" :key="s.id" class="season">
            <header>
              <strong>{{ s.name }}</strong>
              <span>{{ formatDate(s.starts_at) }} — {{ formatDate(s.ends_at) }}</span>
            </header>
            <ol>
              <li v-for="row in s.top" :key="row.rank">
                <span class="tier-block season__tier" :style="{ '--tier': tierColors[row.tier] }">{{ row.tier }}</span>
                <RouterLink v-if="row.user" :to="userLink(row.user)">{{ row.user.username }}</RouterLink>
                <span class="season__score">{{ row.tier_score }}%</span>
              </li>
            </ol>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.stats-totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
}

.stats-total {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stats-total strong {
  font-family: var(--font-display);
  font-size: 28px;
  color: var(--text);
}

.stats-total span {
  font-size: 13px;
  color: var(--text-muted);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.bars {
  display: flex;
  flex-direction: column;
  gap: 10px;

  margin: 0;
  padding: 0;
  list-style: none;
}

.bars__row {
  display: grid;
  grid-template-columns: 32px 1fr 64px;
  gap: 12px;
  align-items: center;
}

.bars__row--wide {
  grid-template-columns: 120px 1fr 64px;
}

.bars__label {
  font-size: 14px;
  color: var(--text-muted);
}

.bars__track {
  overflow: hidden;
  height: 10px;
  border-radius: 3px;
  background: var(--border);
}

.bars__fill {
  height: 100%;
  border-radius: 3px;
  background: var(--accent);
  transition: width 0.4s ease;
}

.bars__value {
  font-weight: 700;
  text-align: right;
}

.bars__value small {
  margin-left: 2px;
  font-weight: 400;
  color: var(--text-muted);
}

.columns {
  display: flex;
  gap: 12px;
  align-items: flex-end;

  height: 180px;
}

.columns__item {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 6px;
  align-items: center;

  height: 100%;
}

.columns__track {
  display: flex;
  flex: 1;
  align-items: flex-end;

  width: 100%;
  max-width: 48px;
}

.columns__fill {
  width: 100%;
  min-height: 2px;
  border-radius: 3px 3px 0 0;
  background: var(--accent);
  box-shadow: inset 0 -4px 0 var(--accent-edge);
}

.columns__value {
  font-weight: 700;
}

.columns__label {
  font-size: 12px;
  color: var(--text-muted);
}

.season-current {
  margin: 0;
  color: var(--text-muted);
}

.season-current strong {
  color: var(--text);
}

.season-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.season {
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: 6px;
}

.season header {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 10px;
}

.season header span {
  font-size: 12px;
  color: var(--text-muted);
}

.season ol {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.season li {
  display: flex;
  gap: 10px;
  align-items: center;
}

.season__tier {
  width: 22px;
  height: 22px;
  font-size: 11px;
}

.season__score {
  margin-left: auto;
  color: var(--text-muted);
}

@media (width <= 480px) {
  .bars__row--wide {
    grid-template-columns: 90px 1fr 56px;
  }
}
</style>
