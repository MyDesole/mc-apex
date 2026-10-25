<script setup>
/**
 * Сравнение двух игроков: аспекты на радаре и по отдельности,
 * история тиров на одном графике и сводка фактов.
 *
 * Игроки задаются в адресе: /compare?a=desole&b=MyDesole — такой
 * ссылкой удобно делиться.
 */
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { Line, Radar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  RadialLinearScale,
  Tooltip,
} from 'chart.js'

import ComparePlayerPicker from '@/components/players/ComparePlayerPicker.vue'
import { api } from '@/services/core/api.js'
import { playersApi } from '@/services/players/players.js'
import { tierColors } from '@/data/players/profileCustomization.js'
import { ASPECT_MAX, ASPECTS, MODE_LABELS, playerAspects } from '@/data/players/aspects.js'
import { loadSkin, renderSkinHead } from '@/utils/minecraftSkin.js'
import { userLink, clanLink } from '@/utils/links.js'

ChartJS.register(RadialLinearScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const route = useRoute()
const router = useRouter()

/** Цвета сторон: A — фирменный фиолетовый, B — алмазный. */
const SIDES = {
  a: { color: '#a78bfa', fill: 'rgba(167, 139, 250, 0.22)' },
  b: { color: '#22d3ee', fill: 'rgba(34, 211, 238, 0.18)' },
}

const slots = ref({
  a: emptySlot(),
  b: emptySlot(),
})

function emptySlot() {
  return { key: null, loading: false, error: null, user: null, rank: null, history: [], head: null }
}

/* ------------------------------ Загрузка ------------------------------ */

async function loadSlot(side, key) {
  if (!key) {
    slots.value[side] = emptySlot()
    return
  }

  if (slots.value[side].key === key && slots.value[side].user) return

  slots.value[side] = { ...emptySlot(), key, loading: true }

  try {
    const data = await playersApi.show(key)
    const user = data.user

    const [history, skin] = await Promise.all([
      api.get(`/players/${user.id}/tier-history`).then((r) => r.history ?? []).catch(() => []),
      loadSkin(user.id),
    ])

    // Пока грузили, игрока могли сменить — устаревший ответ не нужен
    if (slots.value[side].key !== key) return

    slots.value[side] = {
      key,
      loading: false,
      error: null,
      user,
      rank: data.rank ?? null,
      history,
      head: skin ? renderSkinHead(skin.image, 64).toDataURL() : null,
    }
  } catch (e) {
    if (slots.value[side].key !== key) return

    slots.value[side] = {
      ...emptySlot(),
      key,
      error: e?.status === 404 ? 'Игрок не найден' : 'Не удалось загрузить игрока',
    }
  }
}

watch(
    () => [route.query.a, route.query.b],
    ([a, b]) => {
      loadSlot('a', a ? String(a) : null)
      loadSlot('b', b ? String(b) : null)
    },
    { immediate: true },
)

function setSide(side, username) {
  router.replace({ query: { ...route.query, [side]: username || undefined } })
}

function swap() {
  router.replace({ query: { a: route.query.b, b: route.query.a } })
}

/* ------------------------------ Аспекты ------------------------------ */

const A = computed(() => slots.value.a.user)
const B = computed(() => slots.value.b.user)
const ready = computed(() => Boolean(A.value && B.value))

/** Режим сравнения: PvP, если он есть хотя бы у одного, иначе BedWars. */
const mode = computed(() => {
  const modes = [playerAspects(A.value)?.mode, playerAspects(B.value)?.mode]

  return modes.includes('pvp') || !modes.includes('bedwars') ? 'pvp' : 'bedwars'
})

function aspectValues(user) {
  const record = user?.aspects?.[mode.value] ?? {}

  return ASPECTS[mode.value].map((aspect) => Number(record[aspect.key] ?? 0))
}

const aspectsA = computed(() => aspectValues(A.value))
const aspectsB = computed(() => aspectValues(B.value))

const hasAspects = computed(() =>
    aspectsA.value.some((v) => v > 0) || aspectsB.value.some((v) => v > 0)
)

const rows = computed(() =>
    ASPECTS[mode.value].map((aspect, index) => {
      const a = aspectsA.value[index]
      const b = aspectsB.value[index]

      return {
        ...aspect,
        a,
        b,
        winner: a === b ? null : (a > b ? 'a' : 'b'),
      }
    })
)

const totals = computed(() => ({
  a: aspectsA.value.reduce((sum, v) => sum + v, 0),
  b: aspectsB.value.reduce((sum, v) => sum + v, 0),
}))

const verdict = computed(() => {
  if (!ready.value) return null

  const wins = {
    a: rows.value.filter((row) => row.winner === 'a').length,
    b: rows.value.filter((row) => row.winner === 'b').length,
  }

  const diff = Math.round((totals.value.a - totals.value.b) * 10) / 10

  if (!hasAspects.value) {
    return { side: null, title: 'Аспектов пока нет', text: 'Оба игрока ещё не проходили тир-тест в этом режиме.' }
  }

  if (diff === 0) {
    return { side: null, title: 'Равные силы', text: `По сумме аспектов ничья. Навыки: ${wins.a} : ${wins.b}.` }
  }

  const side = diff > 0 ? 'a' : 'b'
  const leader = side === 'a' ? A.value : B.value

  return {
    side,
    title: `Сильнее — ${leader.username}`,
    text: `+${Math.abs(diff)} к сумме аспектов, лучше в ${wins[side]} из ${rows.value.length} навыков.`,
  }
})

/* ------------------------------ Графики ------------------------------ */

const chartFont = { family: '"Golos Text", system-ui, sans-serif' }

const radarData = computed(() => ({
  labels: ASPECTS[mode.value].map((aspect) => aspect.label),
  datasets: [
    dataset(A.value?.username, aspectsA.value, SIDES.a),
    dataset(B.value?.username, aspectsB.value, SIDES.b),
  ],
}))

function dataset(label, data, side) {
  return {
    label,
    data,
    borderColor: side.color,
    backgroundColor: side.fill,
    borderWidth: 2,
    pointBackgroundColor: side.color,
    pointBorderColor: '#16141c',
    pointBorderWidth: 2,
    pointRadius: 4,
    pointHoverRadius: 6,
  }
}

const radarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: tooltip(),
  },
  scales: {
    r: {
      min: 0,
      max: ASPECT_MAX,
      ticks: { display: false, stepSize: 5 },
      grid: { color: 'rgba(255, 255, 255, 0.07)' },
      angleLines: { color: 'rgba(255, 255, 255, 0.07)' },
      pointLabels: { color: '#a29eb0', font: { ...chartFont, size: 13, weight: '600' } },
    },
  },
}

const historyData = computed(() => ({
  datasets: [
    historyDataset(A.value?.username, slots.value.a.history, SIDES.a),
    historyDataset(B.value?.username, slots.value.b.history, SIDES.b),
  ],
}))

function historyDataset(label, history, side) {
  return {
    label,
    data: history
        .filter((test) => test.completed_at)
        .map((test) => ({
          x: new Date(test.completed_at).getTime(),
          y: Number(test.result_score),
          tier: test.result_tier,
          mode: test.mode,
        })),
    borderColor: side.color,
    backgroundColor: side.color,
    borderWidth: 2,
    tension: 0.3,
    pointRadius: 4,
    pointHoverRadius: 6,
  }
}

const hasHistory = computed(() =>
    slots.value.a.history.length + slots.value.b.history.length > 0
)

const historyBaseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'nearest', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      ...tooltip(),
      callbacks: {
        title: (items) => new Date(items[0].parsed.x).toLocaleDateString('ru-RU', {
          day: 'numeric', month: 'long', year: 'numeric',
        }),
        label: (item) => `${item.dataset.label}: тир ${item.raw.tier} · ${item.raw.y}%`,
      },
    },
  },
  scales: {
    x: {
      type: 'linear',
      grid: { display: false },
      border: { display: false },
      ticks: {
        color: '#6f6a7c',
        font: chartFont,
        maxTicksLimit: 6,
        callback: (value) => new Date(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }),
      },
    },
    y: {
      min: 0,
      max: 100,
      border: { display: false },
      grid: { color: 'rgba(255, 255, 255, 0.05)' },
      ticks: { color: '#6f6a7c', font: chartFont, callback: (value) => `${value}%` },
    },
  },
}

const WEEK_MS = 7 * 24 * 3600 * 1000

/**
 * Границы оси времени по реальным датам с запасом в неделю: иначе
 * при одной точке Chart.js растягивает ось на годы вокруг неё.
 */
const historyRange = computed(() => {
  const times = historyData.value.datasets.flatMap((set) => set.data.map((point) => point.x))

  if (!times.length) return {}

  return { min: Math.min(...times) - WEEK_MS, max: Math.max(...times) + WEEK_MS }
})

const historyOptions = computed(() => ({
  ...historyBaseOptions,
  scales: {
    ...historyBaseOptions.scales,
    x: { ...historyBaseOptions.scales.x, ...historyRange.value },
  },
}))

function tooltip() {
  return {
    backgroundColor: '#0d0c11',
    borderColor: '#3a3447',
    borderWidth: 1,
    titleColor: '#fff',
    bodyColor: '#a29eb0',
    titleFont: { ...chartFont, weight: '700' },
    bodyFont: chartFont,
    padding: 10,
    cornerRadius: 6,
  }
}

/* ------------------------------ Сводка ------------------------------ */

const facts = computed(() => {
  if (!ready.value) return []

  const place = (slot) => slot.rank?.position ? `#${slot.rank.position}` : '—'
  const count = (user, key) => (user?.[key] ?? []).length

  return [
    {
      label: 'Место в рейтинге',
      a: place(slots.value.a),
      b: place(slots.value.b),
      better: compareNumbers(slots.value.a.rank?.position, slots.value.b.rank?.position, true),
    },
    {
      label: 'Очки тира',
      a: `${Math.round(A.value.tier_score ?? 0)}%`,
      b: `${Math.round(B.value.tier_score ?? 0)}%`,
      better: compareNumbers(A.value.tier_score, B.value.tier_score),
    },
    {
      label: 'Пройдено тир-тестов',
      a: slots.value.a.history.length,
      b: slots.value.b.history.length,
      better: compareNumbers(slots.value.a.history.length, slots.value.b.history.length),
    },
    {
      label: 'Ачивки',
      a: count(A.value, 'all_achievements'),
      b: count(B.value, 'all_achievements'),
      better: compareNumbers(count(A.value, 'all_achievements'), count(B.value, 'all_achievements')),
    },
    {
      label: 'Клан',
      a: A.value.clan_member?.clan ?? null,
      b: B.value.clan_member?.clan ?? null,
      clan: true,
    },
  ]
})

/** Кто лучше по числу: 'a', 'b' или null. lowerIsBetter — для мест. */
function compareNumbers(a, b, lowerIsBetter = false) {
  const x = Number(a)
  const y = Number(b)

  if (!Number.isFinite(x) || !Number.isFinite(y) || x === y || (lowerIsBetter && (!a || !b))) {
    return null
  }

  return (lowerIsBetter ? x < y : x > y) ? 'a' : 'b'
}

function tierColor(tier) {
  return tierColors[tier] || '#a78bfa'
}

function barWidth(value) {
  return `${Math.max(0, Math.min(100, (value / ASPECT_MAX) * 100))}%`
}
</script>

<template>
  <div class="compare">
    <header class="compare__head">
      <h1>Сравнение игроков</h1>
      <p>Два игрока бок о бок: аспекты тир-теста, история тиров и место в рейтинге.</p>
    </header>

    <!-- ===================== Игроки ===================== -->
    <section class="duel">
      <div
          v-for="side in ['a', 'b']"
          :key="side"
          class="duel__slot"
          :class="`duel__slot--${side}`"
          :style="{ '--side': SIDES[side].color }"
      >
        <template v-if="slots[side].user">
          <RouterLink :to="userLink(slots[side].user)" class="duel__player">
            <span class="duel__avatar">
              <img
                  v-if="slots[side].head || slots[side].user.avatar_url"
                  :src="slots[side].head || slots[side].user.avatar_url"
                  :class="{ 'duel__avatar--pixel': slots[side].head }"
                  alt=""
              />
              <span v-else>{{ slots[side].user.username.charAt(0).toUpperCase() }}</span>
            </span>

            <span class="duel__identity">
              <strong>
                <span
                    v-if="slots[side].user.clan_tag"
                    class="duel__tag"
                    :style="slots[side].user.clan_color ? { color: slots[side].user.clan_color } : {}"
                >[{{ slots[side].user.clan_tag }}]</span>
                {{ slots[side].user.username }}
              </strong>
              <span>{{ Math.round(slots[side].user.tier_score ?? 0) }}% · {{ slots[side].rank?.position ? `#${slots[side].rank.position} в рейтинге` : 'вне рейтинга' }}</span>
            </span>

            <span
                class="tier-block duel__tier"
                :style="{ '--tier': tierColor(slots[side].user.tier) }"
            >{{ slots[side].user.tier }}</span>
          </RouterLink>

          <button
              type="button"
              class="duel__change"
              @click="setSide(side, null)"
          >
            Сменить
          </button>
        </template>

        <div v-else class="duel__pick">
          <span class="duel__label">Игрок {{ side === 'a' ? '1' : '2' }}</span>

          <p v-if="slots[side].loading" class="duel__hint">Загружаем…</p>
          <p v-else-if="slots[side].error" class="duel__hint duel__hint--error">{{ slots[side].error }}</p>

          <ComparePlayerPicker
              :placeholder="side === 'a' ? 'Ник первого игрока' : 'Ник второго игрока'"
              :exclude="side === 'a' ? String(route.query.b ?? '') : String(route.query.a ?? '')"
              @select="(username) => setSide(side, username)"
          />
        </div>
      </div>

      <button
          type="button"
          class="duel__swap"
          title="Поменять местами"
          :disabled="!route.query.a && !route.query.b"
          @click="swap"
      >
        <span>VS</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </section>

    <p v-if="!ready" class="compare__empty">
      Выберите двух игроков — здесь появится разбор по каждому навыку.
    </p>

    <template v-else>
      <!-- ===================== Вердикт ===================== -->
      <section
          class="verdict"
          :style="verdict.side ? { '--side': SIDES[verdict.side].color } : {}"
          :class="{ 'verdict--even': !verdict.side }"
      >
        <strong>{{ verdict.title }}</strong>
        <span>{{ verdict.text }}</span>
      </section>

      <div class="compare__grid">
        <!-- ===================== Радар ===================== -->
        <section class="panel">
          <header class="panel__head">
            <h2>Аспекты</h2>
            <span class="panel__mode">{{ MODE_LABELS[mode] }}</span>
          </header>

          <div v-if="hasAspects" class="panel__radar">
            <Radar :data="radarData" :options="radarOptions" />
          </div>
          <p v-else class="panel__empty">Тир-тесты в этом режиме ещё не пройдены.</p>

          <div class="legend">
            <span v-for="side in ['a', 'b']" :key="side" :style="{ '--side': SIDES[side].color }">
              {{ slots[side].user.username }}
            </span>
          </div>
        </section>

        <!-- ===================== Навык за навыком ===================== -->
        <section class="panel">
          <header class="panel__head">
            <h2>Навык за навыком</h2>
            <span class="panel__mode">из {{ ASPECT_MAX }}</span>
          </header>

          <ul class="butterfly">
            <li
                v-for="row in rows"
                :key="row.key"
                class="butterfly__row"
            >
              <span class="butterfly__value" :class="{ 'butterfly__value--win': row.winner === 'a' }">{{ row.a }}</span>

              <span class="butterfly__bar butterfly__bar--a">
                <span :style="{ width: barWidth(row.a), background: SIDES.a.color }" />
              </span>

              <span class="butterfly__label">{{ row.label }}</span>

              <span class="butterfly__bar butterfly__bar--b">
                <span :style="{ width: barWidth(row.b), background: SIDES.b.color }" />
              </span>

              <span class="butterfly__value" :class="{ 'butterfly__value--win': row.winner === 'b' }">{{ row.b }}</span>
            </li>

            <li class="butterfly__row butterfly__row--total">
              <span class="butterfly__value">{{ totals.a }}</span>
              <span />
              <span class="butterfly__label">Сумма</span>
              <span />
              <span class="butterfly__value">{{ totals.b }}</span>
            </li>
          </ul>
        </section>
      </div>

      <!-- ===================== История ===================== -->
      <section class="panel">
        <header class="panel__head">
          <h2>История тиров</h2>
        </header>

        <div v-if="hasHistory" class="panel__line">
          <Line :data="historyData" :options="historyOptions" />
        </div>
        <p v-else class="panel__empty">Ни у одного игрока пока нет завершённых тир-тестов.</p>
      </section>

      <!-- ===================== Сводка ===================== -->
      <section class="panel">
        <header class="panel__head">
          <h2>Сводка</h2>
        </header>

        <table class="facts">
          <tbody>
            <tr v-for="fact in facts" :key="fact.label">
              <td class="facts__value" :class="{ 'facts__value--win': fact.better === 'a' }">
                <template v-if="fact.clan">
                  <RouterLink
                      v-if="fact.a"
                      :to="clanLink(fact.a)"
                      :style="{ color: fact.a.banner_color || 'inherit' }"
                  >[{{ fact.a.tag }}] {{ fact.a.name }}</RouterLink>
                  <span v-else class="facts__none">без клана</span>
                </template>
                <template v-else>{{ fact.a }}</template>
              </td>

              <th scope="row">{{ fact.label }}</th>

              <td class="facts__value facts__value--right" :class="{ 'facts__value--win': fact.better === 'b' }">
                <template v-if="fact.clan">
                  <RouterLink
                      v-if="fact.b"
                      :to="clanLink(fact.b)"
                      :style="{ color: fact.b.banner_color || 'inherit' }"
                  >[{{ fact.b.tag }}] {{ fact.b.name }}</RouterLink>
                  <span v-else class="facts__none">без клана</span>
                </template>
                <template v-else>{{ fact.b }}</template>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<style scoped>
@import "@/views/players/CompareView.css";
</style>
