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
.chart {
  position: relative;
  height: 320px;
  overflow: hidden;

  padding: 16px 16px 14px;

  background:
      radial-gradient(
          circle at 72% 0%,
          rgba(139, 92, 246, 0.11),
          transparent 34%
      ),
      linear-gradient(
          145deg,
          #101020 0%,
          #0b0c17 48%,
          #080914 100%
      );

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 16px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.035),
      0 18px 45px rgba(0, 0, 0, 0.28);
}

/* ambient purple light */

.chart::before {
  content: '';

  position: absolute;
  width: 220px;
  height: 220px;

  top: -150px;
  right: -70px;

  background: rgba(139, 92, 246, 0.16);
  filter: blur(70px);

  pointer-events: none;
}

.chart::after {
  content: '';

  position: absolute;
  left: 16px;
  right: 16px;
  bottom: 0;

  height: 1px;

  background: linear-gradient(
      90deg,
      transparent,
      rgba(139, 92, 246, 0.35),
      transparent
  );

  opacity: 0.7;
}

.chart__glow {
  position: absolute;
  left: 10%;
  right: 10%;
  bottom: -100px;

  height: 150px;

  background: rgba(139, 92, 246, 0.07);
  filter: blur(60px);

  pointer-events: none;
}

.chart__header {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: center;
  justify-content: space-between;

  min-height: 34px;
  margin-bottom: 6px;
}

.chart__title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.chart__dot {
  position: relative;

  width: 7px;
  height: 7px;

  flex: 0 0 7px;

  background: #8b5cf6;
  border-radius: 50%;

  box-shadow:
      0 0 0 4px rgba(139, 92, 246, 0.08),
      0 0 12px rgba(139, 92, 246, 0.75);
}

.chart__dot::after {
  content: '';

  position: absolute;
  inset: -3px;

  border: 1px solid rgba(139, 92, 246, 0.22);
  border-radius: 50%;

  animation: chart-pulse 2.6s ease-out infinite;
}

.chart__label {
  display: block;

  color: #e6e6ef;

  font-size: 11px;
  font-weight: 800;
  line-height: 1.2;

  letter-spacing: 0.13em;
}

.chart__caption {
  display: block;

  margin-top: 3px;

  color: #68687d;

  font-size: 10px;
  font-weight: 500;
}

.chart__count {
  padding: 5px 8px;

  color: #8989a0;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 6px;

  font-size: 9px;
  font-weight: 800;

  letter-spacing: 0.08em;
}

.chart__body {
  position: relative;
  z-index: 1;

  height: calc(100% - 42px);
}

.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;

  height: 100%;

  text-align: center;
}

.empty__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 42px;
  height: 42px;

  margin-bottom: 12px;

  color: #8b5cf6;

  background:
      radial-gradient(
          circle,
          rgba(139, 92, 246, 0.16),
          rgba(139, 92, 246, 0.025)
      );

  border: 1px solid rgba(139, 92, 246, 0.18);
  border-radius: 10px;

  font-size: 20px;
  font-weight: 300;

  box-shadow:
      0 0 25px rgba(139, 92, 246, 0.08),
      inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.empty__title {
  color: #d6d6e1;

  font-size: 12px;
  font-weight: 700;
}

.empty__text {
  max-width: 240px;

  margin-top: 5px;

  color: #5f6073;

  font-size: 11px;
  line-height: 1.5;
}

@keyframes chart-pulse {
  0% {
    opacity: 0.65;
    transform: scale(0.8);
  }

  70%,
  100% {
    opacity: 0;
    transform: scale(1.7);
  }
}

@media (max-width: 600px) {
  .chart {
    height: 290px;
    padding: 14px 12px 12px;
    border-radius: 13px;
  }

  .chart__header {
    margin-bottom: 4px;
  }

  .chart__caption {
    display: none;
  }

  .chart__count {
    font-size: 8px;
    padding: 4px 6px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .chart__dot::after {
    animation: none;
  }
}
</style>