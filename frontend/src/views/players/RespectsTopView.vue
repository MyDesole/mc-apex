<script setup>
/**
 * Самые уважаемые недели: у кого больше всего респектов за 7 дней.
 * Строго: тройка лидеров карточками с номером и долей от лидера,
 * остальные — таблицей с полосками.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import AppIcon from '@/components/core/AppIcon.vue'
import UserName from '@/components/players/UserName.vue'
import { userLink } from '@/utils/links.js'

const players = ref([])
const loading = ref(true)

const leaders = computed(() => players.value.slice(0, 3))
const rest = computed(() => players.value.slice(3))
const best = computed(() => players.value[0]?.respects || 1)
const weekTotal = computed(() => players.value.reduce((sum, p) => sum + p.respects, 0))

const period = computed(() => {
  const to = new Date()
  const from = new Date(Date.now() - 6 * 86400000)
  const fmt = (d) => d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
  return `${fmt(from)} — ${fmt(to)}`
})

function share(p) {
  return Math.max(4, Math.round((p.respects / best.value) * 100))
}

function pad(n) {
  return String(n).padStart(2, '0')
}

onMounted(async () => {
  try {
    players.value = (await api.get('/respects/top')).players ?? []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rt">
    <header class="rt-head">
      <div class="rt-head__main">
        <span class="rt-head__kicker">
          <AppIcon icon="flame" :size="14" />
          Рейтинг уважения
        </span>
        <h1>Самые уважаемые</h1>
        <p>Респект — раз в день любому игроку, кнопкой в шапке его профиля. Здесь те, кого уважают больше всех за последние 7 дней.</p>
        <p class="rt-head__prize">
          Каждый понедельник тройка недели получает 50 000, 30 000 и 10 000 ApexCoin и бесплатный сундук.
        </p>
      </div>

      <dl class="rt-head__stats">
        <div>
          <dt>Период</dt>
          <dd>{{ period }}</dd>
        </div>
        <div>
          <dt>Респектов</dt>
          <dd>{{ weekTotal }}</dd>
        </div>
        <div>
          <dt>Игроков</dt>
          <dd>{{ players.length }}</dd>
        </div>
      </dl>
    </header>

    <p v-if="loading" class="rt-empty">Загружаем рейтинг…</p>
    <p v-else-if="!players.length" class="rt-empty">
      На этой неделе респектов ещё не было. Зайди в профиль друга и стань первым.
    </p>

    <template v-else>
      <!-- Лидеры -->
      <section class="rt-leaders">
        <RouterLink
            v-for="(p, i) in leaders"
            :key="p.id"
            :to="userLink(p)"
            class="rt-leader"
            :class="`rt-leader--${i + 1}`"
        >
          <span class="rt-leader__rank">{{ pad(i + 1) }}</span>

          <span class="rt-leader__avatar">
            <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
            <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
          </span>

          <span class="rt-leader__name">
            <UserName :user="p" />
          </span>

          <span class="rt-leader__count">
            <b>{{ p.respects }}</b>
            респектов
          </span>

          <span class="rt-bar"><i :style="{ width: `${share(p)}%` }"></i></span>

          <span v-if="i === 0" class="rt-leader__crown" title="Лидер недели">
            <AppIcon icon="crown" :size="16" />
          </span>
        </RouterLink>
      </section>

      <!-- Остальные -->
      <section v-if="rest.length" class="rt-table">
        <header class="rt-table__head">
          <span>#</span>
          <span>Игрок</span>
          <span>Доля от лидера</span>
          <span>Респекты</span>
        </header>

        <RouterLink
            v-for="(p, i) in rest"
            :key="p.id"
            :to="userLink(p)"
            class="rt-row"
        >
          <span class="rt-row__pos">{{ pad(i + 4) }}</span>
          <span class="rt-row__player">
            <span class="rt-row__avatar">
              <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
              <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
            </span>
            <UserName :user="p" />
          </span>
          <span class="rt-bar"><i :style="{ width: `${share(p)}%` }"></i></span>
          <span class="rt-row__count">{{ p.respects }}</span>
        </RouterLink>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rt {
  --rt-accent: #f97316;

  width: min(1000px, calc(100% - 32px));
  margin: 28px auto 60px;

  display: flex;
  flex-direction: column;
  gap: 22px;
}

/* ---------- Шапка ---------- */

.rt-head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 24px;

  padding-bottom: 22px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.rt-head__kicker {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: var(--rt-accent);

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.rt-head h1 {
  margin: 8px 0 6px;

  color: #fff;

  font-family: var(--font-display);
  font-size: clamp(30px, 4.5vw, 46px);
  letter-spacing: -0.02em;
  line-height: 1;
}

.rt-head p {
  max-width: 520px;
  margin: 0;

  color: #8a8598;

  font-size: 14px;
  line-height: 1.55;
}

.rt-head p.rt-head__prize {
  margin-top: 10px;
  color: #fdba74;
  font-weight: 700;
}

.rt-head__stats {
  display: flex;
  gap: 1px;

  margin: 0;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
}

.rt-head__stats div {
  min-width: 96px;
  padding: 12px 16px;

  background: var(--bg-card, #15131c);
}

.rt-head__stats dt {
  color: #8a8598;

  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.rt-head__stats dd {
  margin: 4px 0 0;

  color: #fff;

  font-size: 16px;
  font-weight: 800;
  white-space: nowrap;
}

.rt-empty {
  margin: 0;
  padding: 44px 16px;

  color: #8a8598;
  border: 1px dashed rgba(255, 255, 255, 0.12);
  border-radius: 16px;

  text-align: center;
}

/* ---------- Лидеры ---------- */

.rt-leaders {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.rt-leader {
  --medal: #8a8598;

  position: relative;
  overflow: hidden;

  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 18px 18px 16px;

  color: inherit;
  background:
      linear-gradient(180deg, color-mix(in srgb, var(--medal) 8%, transparent), transparent 55%),
      var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-top: 2px solid var(--medal);
  border-radius: 16px;
  text-decoration: none;

  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.rt-leader:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 36px rgba(0, 0, 0, 0.35);
}

.rt-leader--1 { --medal: #f5c451; }
.rt-leader--2 { --medal: #c7ced9; }
.rt-leader--3 { --medal: #cd8a4b; }

.rt-leader__rank {
  position: absolute;
  top: 10px;
  right: 14px;

  color: transparent;
  -webkit-text-stroke: 1px color-mix(in srgb, var(--medal) 55%, transparent);

  font-family: var(--font-display);
  font-size: 54px;
  font-weight: 900;
  line-height: 1;
}

.rt-leader__avatar {
  width: 56px;
  height: 56px;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: #252131;
  border: 2px solid var(--medal);
  border-radius: 14px;

  font-size: 20px;
  font-weight: 900;
}

.rt-leader__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rt-leader__name {
  overflow: hidden;

  color: #fff;

  font-size: 16px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rt-leader__count {
  color: #8a8598;
  font-size: 12px;
  font-weight: 700;
}

.rt-leader__count b {
  margin-right: 4px;

  color: #fff;

  font-family: var(--font-display);
  font-size: 26px;
  font-variant-numeric: tabular-nums;
}

.rt-leader__crown {
  position: absolute;
  top: 16px;
  left: 58px;

  display: flex;

  color: var(--medal);
}

/* ---------- Полоска доли ---------- */

.rt-bar {
  height: 4px;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.07);
  border-radius: 99px;
}

.rt-bar i {
  display: block;
  height: 100%;

  background: var(--medal, var(--rt-accent));
  border-radius: inherit;
}

/* ---------- Таблица ---------- */

.rt-table {
  overflow: hidden;

  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
}

.rt-table__head,
.rt-row {
  display: grid;
  grid-template-columns: 48px minmax(0, 1.4fr) minmax(0, 1fr) 90px;
  align-items: center;
  gap: 14px;

  padding: 0 18px;
}

.rt-table__head {
  height: 40px;

  color: #8a8598;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);

  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.rt-table__head span:last-child,
.rt-row__count {
  text-align: right;
}

.rt-row {
  height: 58px;

  color: #fff;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  text-decoration: none;

  transition: background 0.15s ease;
}

.rt-row:last-child {
  border-bottom: 0;
}

.rt-row:hover {
  background: rgba(255, 255, 255, 0.03);
}

.rt-row__pos {
  color: #8a8598;
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
}

.rt-row__player {
  min-width: 0;

  display: flex;
  align-items: center;
  gap: 10px;

  overflow: hidden;

  font-weight: 800;
  white-space: nowrap;
}

.rt-row__avatar {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  background: #252131;
  border-radius: 10px;

  font-size: 13px;
}

.rt-row__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rt-row .rt-bar i {
  background: var(--rt-accent);
}

.rt-row__count {
  font-family: var(--font-display);
  font-variant-numeric: tabular-nums;
}

/* ---------- Телефон ---------- */

@media (max-width: 760px) {
  .rt-head {
    grid-template-columns: minmax(0, 1fr);
  }

  .rt-head__stats div {
    min-width: 0;
    flex: 1;
  }

  .rt-leaders {
    grid-template-columns: minmax(0, 1fr);
  }

  .rt-table__head,
  .rt-row {
    grid-template-columns: 34px minmax(0, 1fr) 56px;
  }

  .rt-table__head span:nth-child(3),
  .rt-row .rt-bar {
    display: none;
  }
}
</style>
