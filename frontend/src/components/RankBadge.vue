<script setup>
import { computed } from 'vue'

const props = defineProps({
  position: { type: Number, default: null },
  total: { type: Number, default: 0 },
})

const isTop10 = computed(() => props.position && props.position <= 10)

const medal = computed(() => {
  if (props.position === 1) return '🥇'
  if (props.position === 2) return '🥈'
  if (props.position === 3) return '🥉'
  return null
})

const percentile = computed(() => {
  if (!props.position || !props.total) return null

  return Math.round(
      (1 - (props.position - 1) / props.total) * 100
  )
})
</script>

<template>
  <!-- RANKED -->
  <div
      v-if="position"
      class="rank-badge"
      :class="{
      'rank-gold': position === 1,
      'rank-silver': position === 2,
      'rank-bronze': position === 3,
      'rank-top10': isTop10 && position > 3,
    }"
  >
    <div class="rank-badge__glow"></div>

    <div class="rank-badge__topline">
      <span class="rank-badge__label">
        GLOBAL RANKING
      </span>

      <span
          v-if="position <= 10"
          class="rank-badge__status"
      >
        <i></i>
        TOP {{ position <= 3 ? '3' : '10' }}
      </span>
    </div>

    <div class="rank-badge__main">
      <div class="rank-badge__icon">
        <span v-if="medal" class="medal">
          {{ medal }}
        </span>

        <svg
            v-else
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
          <path
              d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"
              stroke-linejoin="round"
          />
        </svg>
      </div>

      <div class="rank-badge__info">
        <div class="rank-badge__position">
          <span class="num">
            #{{ position }}
          </span>

          <span class="label">
            место в рейтинге
          </span>
        </div>

        <div class="rank-badge__meta">
          <span>
            из {{ total }} игроков
          </span>

          <template v-if="percentile !== null">
            <span class="sep">/</span>

            <span class="percentile">
              лучше {{ percentile }}%
            </span>
          </template>
        </div>
      </div>

      <div class="rank-badge__arrow">
        <svg
            width="17"
            height="17"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <path
              d="M9 18l6-6-6-6"
              stroke-linecap="round"
              stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>

    <div class="rank-badge__line">
      <span></span>
    </div>
  </div>

  <!-- UNRANKED -->
  <div
      v-else
      class="rank-badge rank-unranked"
  >
    <div class="rank-badge__topline">
      <span class="rank-badge__label">
        GLOBAL RANKING
      </span>

      <span class="rank-badge__status rank-badge__status--idle">
        <i></i>
        UNRANKED
      </span>
    </div>

    <div class="rank-badge__main">
      <div class="rank-badge__icon">
        <svg
            width="21"
            height="21"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
        >
          <circle cx="12" cy="12" r="10" />

          <path
              d="M12 8v4M12 16h.01"
              stroke-linecap="round"
          />
        </svg>
      </div>

      <div class="rank-badge__info">
        <div class="rank-badge__position">
          <span class="label">
            Нет места в рейтинге
          </span>
        </div>

        <div class="rank-badge__meta">
          Пройди тир-тест, чтобы попасть в рейтинг
        </div>
      </div>
    </div>

    <div class="rank-badge__line">
      <span></span>
    </div>
  </div>
</template>

<style scoped>
/* =========================================================
   BASE
========================================================= */

.rank-badge {
  --rank-color: #8b5cf6;

  position: relative;

  display: block;

  padding: 15px 16px 13px;

  background:
      radial-gradient(
          circle at 0% 50%,
          color-mix(in srgb, var(--rank-color) 11%, transparent),
          transparent 43%
      ),
      linear-gradient(
          135deg,
          rgba(14, 15, 31, 0.98),
          rgba(8, 9, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 15px;

  overflow: hidden;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 10px 30px rgba(0, 0, 0, 0.22);

  transition:
      transform 0.22s ease,
      border-color 0.22s ease,
      box-shadow 0.22s ease;
}

.rank-badge:hover {
  transform: translateY(-2px);

  border-color: color-mix(
      in srgb,
      var(--rank-color) 35%,
      rgba(255, 255, 255, 0.08)
  );

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.035),
      0 14px 38px rgba(0, 0, 0, 0.3),
      0 0 28px color-mix(
          in srgb,
          var(--rank-color) 10%,
          transparent
      );
}

.rank-badge::before {
  content: '';

  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;

  width: 2px;

  background: var(--rank-color);

  box-shadow:
      0 0 10px var(--rank-color),
      0 0 24px color-mix(
          in srgb,
          var(--rank-color) 55%,
          transparent
      );
}

.rank-badge__glow {
  position: absolute;

  width: 130px;
  height: 130px;

  left: -55px;
  top: 25px;

  background: var(--rank-color);
  opacity: 0.045;

  filter: blur(30px);
  border-radius: 50%;

  pointer-events: none;
}

/* =========================================================
   TOPLINE
========================================================= */

.rank-badge__topline {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 12px;
}

.rank-badge__label {
  color: #646679;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.rank-badge__status {
  display: flex;
  align-items: center;
  gap: 5px;

  color: var(--rank-color);

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.9px;
  text-transform: uppercase;
}

.rank-badge__status i {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--rank-color);

  box-shadow:
      0 0 7px var(--rank-color);
}

.rank-badge__status--idle {
  color: #656879;
}

.rank-badge__status--idle i {
  background: #656879;
  box-shadow: 0 0 7px rgba(101, 104, 121, 0.4);
}

/* =========================================================
   MAIN
========================================================= */

.rank-badge__main {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  gap: 13px;
}

.rank-badge__icon {
  position: relative;

  width: 48px;
  height: 48px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: var(--rank-color);

  background:
      linear-gradient(
          145deg,
          color-mix(in srgb, var(--rank-color) 24%, #111225),
          rgba(10, 11, 23, 0.95)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--rank-color) 35%,
      rgba(255, 255, 255, 0.06)
  );

  border-radius: 12px;

  box-shadow:
      0 5px 20px color-mix(
          in srgb,
          var(--rank-color) 13%,
          transparent
      );
}

.medal {
  position: relative;
  z-index: 1;

  font-size: 25px;
  line-height: 1;

  filter:
      drop-shadow(0 2px 6px rgba(0, 0, 0, 0.65))
      drop-shadow(
          0 0 8px
          color-mix(in srgb, var(--rank-color) 40%, transparent)
      );
}

.rank-badge__info {
  flex: 1;
  min-width: 0;
}

.rank-badge__position {
  display: flex;
  align-items: baseline;
  gap: 7px;

  margin-bottom: 4px;

  min-width: 0;
}

.rank-badge__position .num {
  color: var(--rank-color);

  font-size: 24px;
  font-weight: 950;
  line-height: 1;

  letter-spacing: -1px;
}

.rank-badge__position .label {
  overflow: hidden;

  color: #858798;

  font-size: 10px;
  font-weight: 700;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.rank-badge__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 5px;

  color: #666879;

  font-size: 10px;
}

.rank-badge__meta .sep {
  color: #3f4150;
  font-weight: 800;
}

.rank-badge__meta .percentile {
  color: #858798;
  font-weight: 700;
}

/* =========================================================
   ARROW
========================================================= */

.rank-badge__arrow {
  width: 28px;
  height: 28px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #505264;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 8px;

  transition:
      color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.rank-badge:hover .rank-badge__arrow {
  color: var(--rank-color);

  background: color-mix(
      in srgb,
      var(--rank-color) 10%,
      transparent
  );

  border-color: color-mix(
      in srgb,
      var(--rank-color) 20%,
      transparent
  );

  transform: translateX(2px);
}

/* =========================================================
   BOTTOM SIGNAL
========================================================= */

.rank-badge__line {
  position: relative;

  height: 1px;

  margin-top: 13px;

  background: rgba(255, 255, 255, 0.045);
}

.rank-badge__line span {
  display: block;

  width: 32px;
  height: 1px;

  background: var(--rank-color);

  box-shadow: 0 0 8px var(--rank-color);

  transition: width 0.25s ease;
}

.rank-badge:hover .rank-badge__line span {
  width: 72px;
}

/* =========================================================
   TOP 1
========================================================= */

.rank-gold {
  --rank-color: #facc15;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(250, 204, 21, 0.12),
          transparent 43%
      ),
      linear-gradient(
          135deg,
          rgba(19, 18, 26, 0.98),
          rgba(9, 9, 18, 0.98)
      );

  border-color: rgba(250, 204, 21, 0.25);

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 10px 30px rgba(0, 0, 0, 0.22),
      0 0 28px rgba(250, 204, 21, 0.055);
}

.rank-gold .rank-badge__icon {
  background:
      linear-gradient(
          145deg,
          rgba(250, 204, 21, 0.24),
          rgba(245, 158, 11, 0.08)
      );

  border-color: rgba(250, 204, 21, 0.32);

  box-shadow:
      0 5px 22px rgba(250, 204, 21, 0.13),
      inset 0 0 18px rgba(250, 204, 21, 0.06);
}

/* =========================================================
   TOP 2
========================================================= */

.rank-silver {
  --rank-color: #c0c0c0;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(192, 192, 192, 0.09),
          transparent 43%
      ),
      linear-gradient(
          135deg,
          rgba(15, 16, 26, 0.98),
          rgba(8, 9, 18, 0.98)
      );

  border-color: rgba(192, 192, 192, 0.2);
}

.rank-silver .rank-badge__icon {
  background:
      linear-gradient(
          145deg,
          rgba(243, 244, 246, 0.18),
          rgba(156, 163, 175, 0.06)
      );

  border-color: rgba(192, 192, 192, 0.25);
}

/* =========================================================
   TOP 3
========================================================= */

.rank-bronze {
  --rank-color: #cd7f32;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(205, 127, 50, 0.1),
          transparent 43%
      ),
      linear-gradient(
          135deg,
          rgba(18, 15, 18, 0.98),
          rgba(9, 9, 18, 0.98)
      );

  border-color: rgba(205, 127, 50, 0.2);
}

.rank-bronze .rank-badge__icon {
  background:
      linear-gradient(
          145deg,
          rgba(224, 153, 102, 0.18),
          rgba(160, 82, 45, 0.06)
      );

  border-color: rgba(205, 127, 50, 0.25);
}

/* =========================================================
   TOP 10
========================================================= */

.rank-top10 {
  --rank-color: #8b5cf6;

  border-color: rgba(139, 92, 246, 0.22);
}

.rank-top10 .rank-badge__icon {
  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, 0.2),
          rgba(109, 40, 217, 0.06)
      );

  border-color: rgba(139, 92, 246, 0.28);
}

/* =========================================================
   UNRANKED
========================================================= */

.rank-unranked {
  --rank-color: #6b6d7d;

  border-style: dashed;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(139, 92, 246, 0.045),
          transparent 43%
      ),
      linear-gradient(
          135deg,
          rgba(12, 13, 25, 0.98),
          rgba(8, 9, 18, 0.98)
      );
}

.rank-unranked .rank-badge__icon {
  color: #676a7b;

  background: rgba(255, 255, 255, 0.025);

  border-color: rgba(255, 255, 255, 0.06);

  box-shadow: none;
}

.rank-unranked .rank-badge__position .label {
  color: #b0b1bc;

  font-size: 12px;
  font-weight: 800;
}

.rank-unranked .rank-badge__meta {
  color: #626475;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 600px) {
  .rank-badge {
    padding: 14px;
  }

  .rank-badge__topline {
    margin-bottom: 10px;
  }

  .rank-badge__icon {
    width: 44px;
    height: 44px;

    border-radius: 11px;
  }

  .rank-badge__position .num {
    font-size: 22px;
  }

  .rank-badge__arrow {
    width: 26px;
    height: 26px;
  }
}

/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {
  .rank-badge,
  .rank-badge__arrow,
  .rank-badge__line span {
    transition: none;
  }

  .rank-badge:hover {
    transform: none;
  }

  .rank-badge:hover .rank-badge__arrow {
    transform: none;
  }

  .rank-badge:hover .rank-badge__line span {
    width: 32px;
  }
}
</style>