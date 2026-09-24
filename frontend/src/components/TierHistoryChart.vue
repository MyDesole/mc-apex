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
    borderColor: '#7c3aed',
    backgroundColor: 'rgba(124, 58, 237, 0.1)',
    tension: 0.4,
    fill: true,
    pointBackgroundColor: props.history.map(t => TIER_COLORS[t.result_tier] || '#7c3aed'),
    pointBorderColor: '#fff',
    pointBorderWidth: 2,
    pointRadius: 6,
    pointHoverRadius: 9,
  }],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#12121a',
      borderColor: '#22222e',
      borderWidth: 1,
      titleColor: '#e2e2e8',
      bodyColor: '#8888a0',
      padding: 12,
      displayColors: false,
      callbacks: {
        label: (ctx) => {
          const item = props.history[ctx.dataIndex]
          return `Тир ${item.result_tier} · ${item.result_score}%`
        },
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      max: 100,
      grid: { color: 'rgba(255, 255, 255, 0.05)' },
      ticks: {
        color: '#5e5e70',
        callback: (v) => `${v}%`,
      },
    },
    x: {
      grid: { display: false },
      ticks: { color: '#5e5e70', font: { size: 11 } },
    },
  },
}
</script>

<template>
  <div class="chart-wrap">
    <div v-if="!history.length" class="empty">
      Нет завершённых тир-тестов
    </div>

    <Line
        v-else
        :data="chartData"
        :options="chartOptions"
    />
  </div>
</template>

<style scoped>
.chart-wrap {
  position: relative;
  height: 280px;
  padding: 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--text-dim);
  font-size: 13px;
}
</style>