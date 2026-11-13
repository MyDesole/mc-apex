<script setup>
/**
 * Дашборд админки: что требует внимания, очередь тестов, игроки,
 * активность тестеров, тесты по дням, экономика и последние действия.
 */
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
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

/* ----------------------------- Сервер, лента, динамика ----------------------------- */

const SERIES = [
  { key: 'signups', label: 'Регистрации' },
  { key: 'tests', label: 'Заявки на тест' },
  { key: 'topics', label: 'Темы форума' },
  { key: 'last_seen', label: 'Заходы игроков' },
]

const EVENT_ICONS = {
  signup: '🆕', test_new: '📝', test_done: '✅', appeal: '⚖️', report: '🚩',
  support: '💬', forum: '📰', clan: '🏰', war: '⚔️', purchase: '🛒',
}

const series = ref('signups')
const system = computed(() => data.value?.system ?? null)

const growthBars = computed(() => {
  const rows = data.value?.growth ?? []
  const max = Math.max(1, ...rows.map((r) => r[series.value]))
  return rows.map((r) => ({
    date: r.date,
    value: r[series.value],
    height: (r[series.value] / max) * 100,
    label: new Date(r.date).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }),
  }))
})

function uptime(seconds) {
  if (!seconds && seconds !== 0) return '—'
  const d = Math.floor(seconds / 86400)
  const h = Math.floor((seconds % 86400) / 3600)
  return d ? `${d} дн ${h} ч` : `${h} ч ${Math.floor((seconds % 3600) / 60)} мин`
}

/** Цвет полосы по заполненности: до 70% — норма, до 85% — внимание. */
function level(pct) {
  if (pct >= 85) return 'crit'
  if (pct >= 70) return 'warn'
  return 'ok'
}

const loadPct = computed(() => {
  const l = system.value?.host?.load?.[0]
  const cores = system.value?.host?.cores || 1
  return l === undefined ? 0 : Math.min(100, Math.round((l / cores) * 100))
})

function dot(ok) {
  return ok ? 'dot dot--ok' : 'dot dot--bad'
}

let liveTimer = null

async function refreshLive() {
  if (document.hidden || !data.value) return
  try {
    const live = await adminApi.dashboardLive()
    data.value.activity = live.activity
    data.value.attention = live.attention
    data.value.players.online = live.online
    if (live.system) data.value.system = live.system
  } catch {
    // следующий проход обновит
  }
}

onMounted(async () => {
  try {
    data.value = await adminApi.dashboard()
    liveTimer = setInterval(refreshLive, 30000)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => clearInterval(liveTimer))
</script>

<template>
  <div v-if="loading" class="kit-empty">Загрузка…</div>
  <div v-else-if="failed" class="kit-empty">Не удалось загрузить дашборд</div>

  <div v-else class="dash">
    <!-- Тревоги по серверу -->
    <section v-if="system?.alerts?.length" class="dash__alerts">
      <div
          v-for="(a, i) in system.alerts"
          :key="i"
          class="alert-line"
          :class="`alert-line--${a.level}`"
      >
        {{ a.level === 'crit' ? '⛔' : '⚠️' }} {{ a.text }}
      </div>
    </section>

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

    <!-- Общие счётчики сайта -->
    <section class="dash__totals">
      <div><b>{{ data.totals.clans ?? '—' }}</b><span>кланов</span></div>
      <div><b>{{ data.totals.forum_topics ?? '—' }}</b><span>тем на форуме</span></div>
      <div><b>{{ data.totals.forum_posts_day ?? '—' }}</b><span>постов за сутки</span></div>
      <div><b>{{ data.totals.messages_day ?? '—' }}</b><span>сообщений за сутки</span></div>
      <div><b>{{ data.totals.tests_total ?? '—' }}</b><span>тестов проведено</span></div>
      <div><b>{{ data.totals.tests_day ?? '—' }}</b><span>заявок за сутки</span></div>
      <div><b>{{ data.totals.tickets_open ?? '—' }}</b><span>обращений открыто</span></div>
      <div><b>{{ data.totals.purchases_week ?? '—' }}</b><span>покупок за неделю</span></div>
    </section>

    <!-- Состояние сервера, на котором стоит сайт (только админу) -->
    <section v-if="system" class="kit-card">
      <h3>
        Сервер сайта
        <span class="kit-muted">
          {{ system.host.hostname }} · {{ system.host.os }} · аптайм {{ uptime(system.host.uptime_seconds) }}
        </span>
      </h3>

      <div class="sys">
        <div class="sys__meter">
          <div class="sys__row"><span>Нагрузка CPU</span><b>{{ system.host.load ? system.host.load.join(' / ') : '—' }}</b></div>
          <div class="meter"><span :class="level(loadPct)" :style="{ width: `${loadPct}%` }" /></div>
          <small class="kit-muted">load 1/5/15 мин · ядер: {{ system.host.cores ?? '—' }}</small>
        </div>

        <div v-if="system.host.memory" class="sys__meter">
          <div class="sys__row"><span>Память</span><b>{{ system.host.memory.used_mb }} / {{ system.host.memory.total_mb }} МБ</b></div>
          <div class="meter"><span :class="level(system.host.memory.used_pct)" :style="{ width: `${system.host.memory.used_pct}%` }" /></div>
          <small class="kit-muted">доступно {{ system.host.memory.available_mb }} МБ · своп {{ system.host.memory.swap_used_mb }} МБ</small>
        </div>

        <div v-if="system.host.disk" class="sys__meter">
          <div class="sys__row"><span>Диск</span><b>{{ system.host.disk.used_gb }} / {{ system.host.disk.total_gb }} ГБ</b></div>
          <div class="meter"><span :class="level(system.host.disk.used_pct)" :style="{ width: `${system.host.disk.used_pct}%` }" /></div>
          <small class="kit-muted">занято {{ system.host.disk.used_pct }}%</small>
        </div>
      </div>

      <div class="sys__chips">
        <div class="chip">
          <span :class="dot(system.database.ok)" /> <b>База данных</b>
          <small v-if="system.database.ok">
            {{ system.database.driver }} · отклик {{ system.database.ping_ms }} мс
            <template v-if="system.database.size_mb !== null"> · {{ system.database.size_mb }} МБ · {{ system.database.tables }} таблиц</template>
            <template v-if="system.database.connections !== null"> · соединений {{ system.database.connections }}</template>
          </small>
          <small v-else class="red">{{ system.database.error }}</small>
        </div>

        <div class="chip">
          <span :class="dot(system.redis.ok)" /> <b>Redis</b>
          <small v-if="system.redis.ok">отклик {{ system.redis.ping_ms }} мс · память {{ system.redis.used_memory ?? '—' }} · клиентов {{ system.redis.clients }}</small>
          <small v-else class="red">{{ system.redis.error }}</small>
        </div>

        <div class="chip">
          <span :class="dot((system.queue.workers ?? 1) > 0 && !system.queue.failed)" /> <b>Очередь</b>
          <small>
            в ожидании {{ system.queue.pending ?? '—' }} · упало {{ system.queue.failed ?? '—' }} ·
            обработчиков {{ system.queue.workers ?? '—' }}
          </small>
        </div>

        <div class="chip">
          <span :class="dot(system.reverb.ok)" /> <b>Веб-сокеты</b>
          <small>Reverb, порт {{ system.reverb.port }}: {{ system.reverb.ok ? 'работает' : 'не отвечает' }}</small>
        </div>

        <div class="chip">
          <span :class="dot(!system.app.debug)" /> <b>Приложение</b>
          <small>
            PHP {{ system.app.php }} · Laravel {{ system.app.laravel }} · {{ system.app.environment }}
            · opcache {{ system.app.opcache ? 'вкл' : 'выкл' }} · debug {{ system.app.debug ? 'ВКЛ' : 'выкл' }}
          </small>
        </div>

        <div class="chip">
          <span :class="dot(system.log.errors_day < 10)" /> <b>Лог ошибок</b>
          <small>{{ system.log.errors_day }} за сутки · файл {{ system.log.size_mb }} МБ</small>
        </div>
      </div>

      <ul v-if="system.log.last.length" class="errors">
        <li v-for="(e, i) in system.log.last" :key="i">
          <small class="kit-muted">{{ e.at }}</small>
          <span class="red">{{ e.level }}</span>
          <code>{{ e.message }}</code>
        </li>
      </ul>
    </section>

    <div class="dash__grid">
      <!-- Динамика сайта -->
      <section class="kit-card dash__wide">
        <h3>
          Динамика за две недели
          <span class="dash__tabs">
            <button
                v-for="s in SERIES"
                :key="s.key"
                type="button"
                :class="{ on: series === s.key }"
                @click="series = s.key"
            >{{ s.label }}</button>
          </span>
        </h3>
        <div class="bars">
          <div v-for="d in growthBars" :key="d.date" class="bars__item" :title="`${d.label}: ${d.value}`">
            <span class="bars__value">{{ d.value || '' }}</span>
            <span class="bars__track"><span :style="{ height: `${d.height}%` }" /></span>
            <span class="bars__label">{{ d.label }}</span>
          </div>
        </div>
      </section>

      <!-- Лента событий -->
      <section class="kit-card dash__wide">
        <h3>Что происходит на сайте <span class="kit-muted">обновляется каждые 30 секунд</span></h3>
        <p v-if="!data.activity.length" class="kit-muted">Пока тихо</p>
        <ul class="feed">
          <li v-for="(e, i) in data.activity" :key="i">
            <span class="feed__icon">{{ EVENT_ICONS[e.type] || '•' }}</span>
            <span class="feed__body">
              <b>{{ e.title }}</b>
              <span>{{ e.text }}</span>
            </span>
            <button v-if="e.user_id" type="button" class="kit-user feed__user" @click="openDossier(e.user_id)">досье</button>
            <small class="kit-muted">{{ time(e.at) }}</small>
          </li>
        </ul>
      </section>

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

/* Без этого карточка растягивается по содержимому и вылезает за экран телефона */
.dash__grid > * {
  min-width: 0;
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
  min-width: 0;
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

/* ----- тревоги, счётчики, сервер, лента ----- */
.dash__alerts {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.alert-line {
  padding: 9px 14px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
}

.alert-line--crit {
  color: #fecaca;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.5);
}

.alert-line--warn {
  color: #fde68a;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.dash__totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
}

.dash__totals div {
  display: flex;
  flex-direction: column;
  padding: 10px 14px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.dash__totals b {
  font-family: var(--font-display);
  font-size: 20px;
}

.dash__totals span {
  color: var(--text-muted);
  font-size: 12px;
}

.sys {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.sys__row {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 14px;
  margin-bottom: 6px;
}

.meter {
  height: 8px;
  margin-bottom: 4px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 4px;
  overflow: hidden;
}

.meter span {
  display: block;
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s;
}

.meter .ok { background: #4ade80; }
.meter .warn { background: #fbbf24; }
.meter .crit { background: #f87171; }

.sys__chips {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 10px;
}

.chip {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: 8px;
  align-items: baseline;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 14px;
}

.chip small {
  grid-column: 2;
  color: var(--text-muted);
  font-size: 12px;
  line-height: 1.5;
}

.dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
}

.dot--ok { background: #4ade80; box-shadow: 0 0 8px rgba(74, 222, 128, 0.7); }
.dot--bad { background: #f87171; box-shadow: 0 0 8px rgba(248, 113, 113, 0.7); }

.errors {
  margin: 14px 0 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
}

.errors li {
  display: grid;
  grid-template-columns: auto auto 1fr;
  gap: 8px;
  align-items: baseline;
}

.errors code {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-dim);
}

.dash__tabs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-left: 12px;
}

.dash__tabs button {
  padding: 3px 10px;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 12px;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}

.dash__tabs button.on {
  color: var(--text);
  border-color: var(--accent);
}

.feed {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
  max-height: 420px;
  overflow-y: auto;
}

.feed li {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) auto auto;
  gap: 10px;
  align-items: center;
  padding: 7px 4px;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
}

.feed__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.feed__body span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-dim);
  font-size: 13px;
}

.feed__user {
  font-size: 12px;
}

@media (max-width: 760px) {
  .dash__grid {
    grid-template-columns: 1fr;
  }

  .bars__label {
    display: none;
  }

  .bars {
    gap: 3px;
  }

  .dash__tabs {
    display: flex;
    margin: 8px 0 0;
  }

  .actions li {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
</style>
