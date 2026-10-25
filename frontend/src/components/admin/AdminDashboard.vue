<script setup>
/**
 * Дашборд админки: что требует внимания, очередь тестов, игроки,
 * активность тестеров, тесты по дням, экономика и последние действия.
 */
import { computed, inject, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'

const openDossier = inject('openDossier', () => {})
const router = useRouter()
const route = useRoute()

const data = ref(null)
const loading = ref(true)
const failed = ref(false)

const SOURCE_LABELS = {
  tier_test: 'Тир-тесты',
  achievement: 'Ачивки',
  daily_bonus: 'Ежедневный бонус',
  admin: 'Начисления админа',
  referral: 'Рефералы',
  gift_in: 'Подарки',
}

const attention = computed(() => {
  const a = data.value?.attention ?? {}
  return [
    { key: 'reports', label: 'Жалобы ждут решения', value: a.reports, tab: 'reports', urgent: a.reports > 0 },
    { key: 'appeals', label: 'Апелляции', value: a.appeals, tab: 'appeals', urgent: a.appeals > 0 },
    { key: 'tests', label: 'Тир-тестов в очереди', value: a.tests_pending, link: '/tester' },
    { key: 'wars', label: 'Клан-варов идёт', value: a.wars_pending },
    { key: 'tournaments', label: 'Турниров активно', value: a.tournaments_active, tab: 'tournaments' },
  ]
})

const days = computed(() => {
  const rows = data.value?.tests_by_day ?? []
  const max = Math.max(1, ...rows.map((r) => r.tests))
  return rows.map((r) => ({
    ...r,
    height: (r.tests / max) * 100,
    label: new Date(r.date).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }),
  }))
})

function go(item) {
  if (item.tab) router.replace({ query: { ...route.query, tab: item.tab } })
  else if (item.link) router.push(item.link)
}

function duration(minutes) {
  if (minutes === null || minutes === undefined) return '—'
  if (minutes < 60) return `${minutes} мин`
  const hours = Math.floor(minutes / 60)
  if (hours < 48) return `${hours} ч ${minutes % 60} мин`
  return `${Math.floor(hours / 24)} дн`
}

function ago(iso) {
  if (!iso) return 'никогда'
  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (minutes < 5) return 'онлайн'
  if (minutes < 60) return `${minutes} мин назад`
  if (minutes < 60 * 24) return `${Math.floor(minutes / 60)} ч назад`
  return `${Math.floor(minutes / 60 / 24)} дн назад`
}

function time(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    data.value = await adminApi.dashboard()
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="kit-empty">Загрузка…</div>
  <div v-else-if="failed" class="kit-empty">Не удалось загрузить дашборд</div>

  <div v-else class="dash">
    <!-- Требует внимания -->
    <section class="dash__attention">
      <button
          v-for="item in attention"
          :key="item.key"
          type="button"
          class="tile"
          :class="{ 'tile--urgent': item.urgent, 'tile--link': item.tab || item.link }"
          @click="go(item)"
      >
        <strong>{{ item.value ?? 0 }}</strong>
        <span>{{ item.label }}</span>
      </button>
    </section>

    <div class="dash__grid">
      <!-- Очередь тестов -->
      <section class="kit-card">
        <h3>Очередь тир-тестов</h3>
        <dl class="facts">
          <div><dt>Ждут тестера</dt><dd>{{ data.queue.pending }}</dd></div>
          <div><dt>В работе</dt><dd>{{ data.queue.in_progress }}</dd></div>
          <div><dt>Среднее ожидание</dt><dd>{{ duration(data.queue.avg_wait_minutes) }}</dd></div>
          <div>
            <dt>Самая старая заявка</dt>
            <dd>{{ data.queue.oldest_pending_at ? ago(data.queue.oldest_pending_at) : '—' }}</dd>
          </div>
          <div><dt>Проведено за неделю</dt><dd>{{ data.queue.completed_week }}</dd></div>
        </dl>
      </section>

      <!-- Игроки -->
      <section class="kit-card">
        <h3>Игроки</h3>
        <dl class="facts">
          <div><dt>Всего</dt><dd>{{ data.players.total }}</dd></div>
          <div><dt>Онлайн сейчас</dt><dd class="green">{{ data.players.online }}</dd></div>
          <div><dt>Активны за неделю</dt><dd>{{ data.players.active_week }}</dd></div>
          <div><dt>Новых сегодня / за неделю</dt><dd>{{ data.players.new_today }} / {{ data.players.new_week }}</dd></div>
          <div><dt>В бане</dt><dd class="red">{{ data.players.banned }}</dd></div>
        </dl>
      </section>

      <!-- Тесты по дням -->
      <section class="kit-card dash__wide">
        <h3>Тир-тесты за две недели</h3>
        <div class="bars">
          <div v-for="d in days" :key="d.date" class="bars__item" :title="`${d.label}: ${d.tests}`">
            <span class="bars__value">{{ d.tests || '' }}</span>
            <span class="bars__track"><span :style="{ height: `${d.height}%` }" /></span>
            <span class="bars__label">{{ d.label }}</span>
          </div>
        </div>
      </section>

      <!-- Тестеры -->
      <section class="kit-card">
        <h3>Тестеры <span class="kit-muted">тестов за неделю</span></h3>
        <p v-if="!data.testers.length" class="kit-muted">Тестеров пока нет</p>
        <ul class="people">
          <li v-for="t in data.testers" :key="t.id">
            <button type="button" class="kit-user" @click="openDossier(t.id)">
              <span class="kit-avatar">
                <img v-if="t.avatar_url" :src="t.avatar_url" alt="" />
                <template v-else>{{ t.username.charAt(0).toUpperCase() }}</template>
              </span>
              <span>{{ t.username }}</span>
            </button>
            <small class="kit-muted">{{ ago(t.last_seen_at) }}</small>
            <b :class="{ zero: !t.tests_week }">{{ t.tests_week }}</b>
          </li>
        </ul>
      </section>

      <!-- Экономика: только админу -->
      <section v-if="data.economy" class="kit-card">
        <h3>ApexCoin</h3>
        <dl class="facts">
          <div><dt>На руках у игроков</dt><dd>{{ data.economy.in_circulation.toLocaleString('ru-RU') }}</dd></div>
          <div><dt>Выдано за неделю</dt><dd class="green">+{{ data.economy.issued_week.toLocaleString('ru-RU') }}</dd></div>
          <div><dt>Потрачено за неделю</dt><dd class="red">−{{ data.economy.spent_week.toLocaleString('ru-RU') }}</dd></div>
        </dl>
        <ul class="sources">
          <li v-for="(amount, source) in data.economy.by_source" :key="source">
            <span>{{ SOURCE_LABELS[source] || source }}</span>
            <b>{{ Number(amount).toLocaleString('ru-RU') }}</b>
          </li>
        </ul>
      </section>

      <!-- Последние действия -->
      <section class="kit-card" :class="{ dash__wide: !data.economy }">
        <h3>Последние действия персонала</h3>
        <p v-if="!data.recent_actions.length" class="kit-muted">Пока пусто</p>
        <ul class="actions">
          <li v-for="a in data.recent_actions" :key="a.id">
            <span class="kit-tag">{{ a.label }}</span>
            <span>
              {{ a.actor?.username ?? 'система' }}
              <template v-if="a.target"> →
                <button type="button" class="kit-user" @click="openDossier(a.target.id)">
                  <span>{{ a.target.username }}</span>
                </button>
              </template>
            </span>
            <small class="kit-muted">{{ time(a.created_at) }}</small>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.dash {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.dash__attention {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 4px;

  padding: 14px;

  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  text-align: left;

  cursor: default;
}

.tile--link {
  cursor: pointer;
}

.tile--link:hover {
  border-color: var(--text-muted);
}

.tile strong {
  font-family: var(--font-display);
  font-size: 26px;
}

.tile span {
  color: var(--text-muted);
  font-size: 13px;
}

.tile--urgent {
  border-color: rgba(239, 68, 68, 0.5);
  box-shadow: inset 0 3px 0 #ef4444;
}

.tile--urgent strong {
  color: #fca5a5;
}

.dash__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.dash__wide {
  grid-column: 1 / -1;
}

.facts {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
}

.facts div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.facts dt {
  color: var(--text-muted);
  font-size: 14px;
}

.facts dd {
  margin: 0;
  font-weight: 700;
}

.green { color: #4ade80; }
.red { color: #f87171; }

.bars {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  height: 150px;
}

.bars__item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  height: 100%;
}

.bars__value {
  min-height: 15px;
  font-size: 12px;
  font-weight: 700;
}

.bars__track {
  flex: 1;
  display: flex;
  align-items: flex-end;
  width: 100%;
}

.bars__track span {
  width: 100%;
  min-height: 2px;
  background: var(--accent);
  border-radius: 3px 3px 0 0;
  box-shadow: inset 0 -3px 0 var(--accent-edge);
}

.bars__label {
  color: var(--text-muted);
  font-size: 10px;
  white-space: nowrap;
}

.people,
.actions,
.sources {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.people li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto 32px;
  gap: 10px;
  align-items: center;
}

.people b {
  font-family: var(--font-display);
  text-align: right;
}

.people b.zero {
  color: #f87171;
}

.actions li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  font-size: 14px;
}

.sources {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.sources li {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--text-dim);
}

@media (max-width: 760px) {
  .dash__grid {
    grid-template-columns: 1fr;
  }

  .bars__label {
    display: none;
  }

  .actions li {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
</style>
