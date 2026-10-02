<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
)

const props = defineProps({
  history: { type: Array, default: () => [] },
})

const TIER_COLORS = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const chartData = computed(() => ({
  labels: props.history.map(t =>
      new Date(t.completed_at).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: 'short',
      })
  ),

  datasets: [{
    label: 'Процент',
    data: props.history.map(t => t.result_score),

    borderColor: '#8b5cf6',
    backgroundColor: 'rgba(139, 92, 246, 0.10)',

    borderWidth: 2,
    tension: 0.42,
    fill: true,

    pointBackgroundColor: props.history.map(
        t => TIER_COLORS[t.result_tier] || '#8b5cf6'
    ),
    pointBorderColor: '#0b0c16',
    pointBorderWidth: 3,

    pointRadius: 5,
    pointHoverRadius: 8,

    pointHoverBorderColor: '#ffffff',
    pointHoverBorderWidth: 2,
  }],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,

  interaction: {
    intersect: false,
    mode: 'index',
  },

  plugins: {
    legend: {
      display: false,
    },

    tooltip: {
      backgroundColor: 'rgba(8, 9, 20, 0.96)',
      borderColor: 'rgba(139, 92, 246, 0.38)',
      borderWidth: 1,

      titleColor: '#ffffff',
      bodyColor: '#a5a5b8',

      titleFont: {
        size: 11,
        weight: '700',
      },

      bodyFont: {
        size: 12,
        weight: '600',
      },

      padding: 12,
      cornerRadius: 10,

      displayColors: false,

      callbacks: {
        title: (items) => {
          const item = props.history[items[0].dataIndex]

          return new Date(item.completed_at).toLocaleDateString(
              'ru-RU',
              {
                day: '2-digit',
                month: 'long',
                year: 'numeric',
              }
          )
        },

        label: (ctx) => {
          const item = props.history[ctx.dataIndex]

          return `ТИР ${item.result_tier}  ·  ${item.result_score}%`
        },
      },
    },
  },

  scales: {
    y: {
      beginAtZero: true,
      max: 100,

      border: {
        display: false,
      },

      grid: {
        color: 'rgba(255, 255, 255, 0.045)',
        drawTicks: false,
      },

      ticks: {
        color: '#62627a',
        padding: 10,

        font: {
          size: 10,
          weight: '600',
        },

        callback: (value) => `${value}%`,
      },
    },

    x: {
      border: {
        display: false,
      },

      grid: {
        display: false,
      },

      ticks: {
        color: '#62627a',
        padding: 8,

        font: {
          size: 10,
          weight: '600',
        },

        maxRotation: 0,
      },
    },
  },
}
</script>

<template>
  <div class="chart">
    <div class="chart__glow"></div>

    <div class="chart__header">
      <div class="chart__title">
        <span class="chart__dot"></span>

        <div>
          <span class="chart__label">
            PERFORMANCE
          </span>

          <span class="chart__caption">
            История результатов
          </span>
        </div>
      </div>

      <div
          v-if="history.length"
          class="chart__count"
      >
        {{ history.length }} ТЕСТ{{ history.length === 1 ? '' : 'ОВ' }}
      </div>
    </div>

    <div class="chart__body">
      <div
          v-if="!history.length"
          class="empty"
      >
        <div class="empty__icon">
          +
        </div>

        <span class="empty__title">
          Нет данных
        </span>

        <span class="empty__text">
          Завершите тир-тест, чтобы увидеть прогресс
        </span>
      </div>

      <Line
          v-else
          :data="chartData"
          :options="chartOptions"
      />
    </div>
  </div>
</template>

<style scoped>
@import "@/components/tiers/TierHistoryChart.css";
</style>
