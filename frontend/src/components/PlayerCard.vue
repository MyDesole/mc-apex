<script setup>
import { computed } from 'vue'
import UserName from "@/components/UserName.vue";

const props = defineProps({
  user: { type: Object, required: true },
  aspects: { type: Array, default: () => [] },
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])

const tierColors = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const tierColor = computed(() => tierColors[props.user.tier] || '#6b7280')

const hasSocials = computed(() => {
  return props.user.socials && Object.values(props.user.socials).some(v => v)
})

const socialLabels = {
  discord: 'Discord',
  telegram: 'Telegram',
  youtube: 'YouTube',
  vk: 'VK',
  website: 'Сайт',
}

const defaultAspects = [
  {
    mode: 'pvp',
    block_placing: 10,
    rotka: 5,
    movement: 1,
    building: 2,
    ppl: 4,
    percent: 70,
  },
  {
    mode: 'bedwars',
    block_placing: 0,
    rotka: 0,
    movement: 0,
    building: 0,
    ppl: 0,
    percent: 0,
  },
]

const allAspects = computed(() => {
  const map = new Map()

  for (const a of defaultAspects) {
    map.set(a.mode, { ...a })
  }

  for (const a of props.aspects) {
    map.set(a.mode, {
      ...a,
      percent: a.percent ?? (
          ((a.block_placing + a.rotka + a.movement + a.building + a.ppl) * 2)
      ),
    })
  }

  return Array.from(map.values())
})

const aspectLabels = {
  block_placing: 'БП',
  rotka: 'Ротка',
  movement: 'Мувмент',
  building: 'Строительство',
  ppl: 'Аим',
}

/**
 * Цвет прогресс-бара по значению аспекта (0-10).
 */
function aspectColor(value) {
  if (value >= 8) return 'linear-gradient(90deg, #22c55e, #4ade80)'  // зелёный
  if (value >= 6) return 'linear-gradient(90deg, #7c3aed, #a78bfa)'  // фиолетовый
  if (value >= 4) return 'linear-gradient(90deg, #f59e0b, #fbbf24)'  // жёлтый
  if (value >= 1) return 'linear-gradient(90deg, #ef4444, #f87171)'  // красный
  return 'rgba(255, 255, 255, 0.06)'  // пусто
}

/**
 * Цвет процента по значению (0-100).
 */
function percentColor(percent) {
  if (percent >= 90) return '#facc15'
  if (percent >= 80) return '#f97316'
  if (percent >= 70) return '#8b5cf6'
  if (percent >= 60) return '#06b6d4'
  if (percent >= 50) return '#22c55e'
  return '#6b7280'
}

/**
 * Общий балл (сумма аспектов).
 */
function totalScore(aspect) {
  return aspect.block_placing + aspect.rotka + aspect.movement + aspect.building + aspect.ppl
}
</script>

<template>
  <div class="player-card">
    <!-- HEADER -->
    <header
        class="player-card__header"
        :style="user.cover_url ? {
                backgroundImage: `linear-gradient(rgba(10,10,15,0.65), rgba(10,10,15,0.85)), url(${user.cover_url})`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
            } : {}"
    >
      <div class="player-card__left">
        <div
            class="player-card__avatar"
            :style="{ background: user.banner_color || tierColor }"
        >
          <img
              v-if="user.avatar_url"
              :src="user.avatar_url"
              :alt="user.username"
              class="player-card__avatar-img"
          />
          <template v-else>
            {{ (user.username || 'И').charAt(0).toUpperCase() }}
          </template>
        </div>

        <div class="player-card__info">
          <UserName :user="user" />
          <p v-if="user.bio" class="bio">{{ user.bio }}</p>
          <div v-if="user.achievements?.length" class="player-card__achievements">
            <span
                v-for="a in user.achievements.slice(0, 6)"
                :key="a.id"
                class="mini-achievement"
                :style="{ '--color': a.color }"
                :title="a.name"
            >
                {{ a.icon }}
            </span>
                    <span v-if="user.achievements.length > 6" class="mini-more">
                +{{ user.achievements.length - 6 }}
            </span>
          </div>
        </div>
      </div>

      <div class="player-card__right">
        <button
            v-if="editable"
            class="player-card__edit"
            type="button"
            @click="emit('edit')"
        >
          <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
          >
            <path
                d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
          </svg>
          Настройки
        </button>

        <div
            class="player-card__tier"
            :style="{
                        borderColor: tierColor,
                        color: tierColor,
                        boxShadow: `0 0 20px ${tierColor}40`,
                    }"
        >
          {{ user.tier }}
        </div>
      </div>
    </header>

    <!-- Соцсети -->
    <div v-if="hasSocials" class="player-card__socials">
      <a
          v-for="(url, key) in user.socials"
          :key="key"
          v-show="url"
          :href="url"
          target="_blank"
          rel="noopener"
          class="social-link"
          :class="key"
      >
        {{ socialLabels[key] }}
      </a>
    </div>

    <!-- Аспекты -->
    <section class="aspects">
      <div class="aspects__head">
        <h3 class="aspects__title">Аспекты игрока</h3>
        <span class="aspects__sub">Оценка по 5 критериям · макс. 100%</span>
      </div>

      <div class="aspects__list">
        <article
            v-for="aspect in allAspects"
            :key="aspect.mode"
            class="aspect-card"
            :class="{
                        'aspect-card--empty': !aspect.id,
                        [`aspect-card--${aspect.mode}`]: true,
                    }"
        >
          <!-- Заголовок карточки -->
          <header class="aspect-card__head">
            <div class="aspect-card__head-left">
                            <span class="aspect-card__mode">
                                {{ aspect.mode === 'pvp' ? 'PvP' : 'BedWars' }}
                            </span>
              <span class="aspect-card__sub">
                                {{ aspect.mode === 'pvp' ? 'p-ранг' : 'b-ранг' }}
                            </span>
              <span v-if="!aspect.id" class="badge-empty">
                                не тестирован
                            </span>
            </div>

            <div class="aspect-card__head-right">
              <div class="total">
                <span class="total__value">{{ totalScore(aspect) }}</span>
                <span class="total__max">/50</span>
              </div>
              <div
                  class="percent-pill"
                  :style="{
                                    color: percentColor(aspect.percent),
                                    borderColor: percentColor(aspect.percent) + '55',
                                    background: percentColor(aspect.percent) + '12',
                                }"
              >
                {{ aspect.percent }}%
              </div>
            </div>
          </header>

          <!-- Аспекты -->
          <div class="aspect-card__grid">
            <div
                v-for="(label, key) in aspectLabels"
                :key="key"
                class="aspect"
            >
              <div class="aspect__top">
                <span class="aspect__label">{{ label }}</span>
                <span
                    class="aspect__value"
                    :class="{ 'aspect__value--zero': !aspect[key] }"
                >
                                    {{ aspect[key] }}
                                </span>
              </div>

              <div class="aspect__bar">
                <div
                    class="aspect__fill"
                    :style="{
                                        width: (aspect[key] / 10 * 100) + '%',
                                        background: aspectColor(aspect[key]),
                                    }"
                />
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* ============================================
   PLAYER CARD
   ============================================ */

.player-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
}

/* === HEADER === */

.player-card__header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 24px;
  padding: 20px;
  background: #0d0d14;
  border-radius: 12px;
  overflow: hidden;
  min-height: 110px;
}
.player-card__achievements {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}

.mini-achievement {
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--color);
  border-radius: 7px;
  font-size: 13px;
  box-shadow: 0 0 12px rgba(0, 0, 0, 0.4);
  transition: transform 0.15s;
  cursor: default;
}

.mini-achievement:hover {
  transform: scale(1.15);
}

.mini-more {
  display: flex;
  align-items: center;
  padding: 0 8px;
  height: 26px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 11px;
  font-weight: 800;
}
.player-card__left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

.player-card__avatar {
  position: relative;
  width: 72px;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  font-size: 28px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4);
}

.player-card__avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.player-card__info {
  flex: 1;
  min-width: 0;
}

.player-card__info h2 {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bio {
  margin: 0;
  color: #d1d1db;
  font-size: 13px;
  line-height: 1.5;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.player-card__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

.player-card__edit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  color: #fff;
  background: rgba(124, 58, 237, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  backdrop-filter: blur(8px);
  transition: all 0.2s ease;
}

.player-card__edit:hover {
  background: var(--accent);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4);
}

.player-card__edit:active {
  transform: translateY(0);
}

.player-card__tier {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 12px;
  font-size: 24px;
  font-weight: 900;
  background: rgba(10, 10, 15, 0.85);
  backdrop-filter: blur(8px);
  transition: box-shadow 0.2s ease;
}

/* === СОЦСЕТИ === */

.player-card__socials {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  padding: 7px 12px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  transform: translateY(-1px);
  color: #fff;
}

.social-link.discord:hover {
  background: #5865f2;
  border-color: #5865f2;
  box-shadow: 0 4px 15px rgba(88, 101, 242, 0.35);
}

.social-link.telegram:hover {
  background: #229ed9;
  border-color: #229ed9;
  box-shadow: 0 4px 15px rgba(34, 158, 217, 0.35);
}

.social-link.youtube:hover {
  background: #ff0000;
  border-color: #ff0000;
  box-shadow: 0 4px 15px rgba(255, 0, 0, 0.35);
}

.social-link.vk:hover {
  background: #0077ff;
  border-color: #0077ff;
  box-shadow: 0 4px 15px rgba(0, 119, 255, 0.35);
}

.social-link.website:hover {
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.35);
}

/* ============================================
   АСПЕКТЫ
   ============================================ */

.aspects {
  margin-top: 4px;
}

.aspects__head {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 14px;
  padding: 0 2px;
}

.aspects__title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.2px;
}

.aspects__sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.aspects__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* === CARD === */

.aspect-card {
  position: relative;
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.aspect-card:hover {
  border-color: var(--border-hover);
}

.aspect-card--empty {
  opacity: 0.75;
}

.aspect-card--pvp {
  /* лёгкий оттенок, чтобы отличать pvp от bedwars */
  background:
      linear-gradient(180deg, rgba(124, 58, 237, 0.04), transparent 40%),
      #0d0d14;
}

.aspect-card--bedwars {
  background:
      linear-gradient(180deg, rgba(6, 182, 212, 0.04), transparent 40%),
      #0d0d14;
}

/* Заголовок карточки */

.aspect-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  gap: 12px;
}

.aspect-card__head-left {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;
}

.aspect-card__mode {
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.aspect-card__sub {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

.badge-empty {
  display: inline-block;
  padding: 2px 8px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.aspect-card__head-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.total {
  display: flex;
  align-items: baseline;
  gap: 2px;
  font-weight: 900;
}

.total__value {
  font-size: 16px;
  color: var(--text);
  letter-spacing: -0.5px;
}

.total__max {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
}

.percent-pill {
  padding: 4px 10px;
  border: 1px solid;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: -0.3px;
}

/* Сетка аспектов */

.aspect-card__grid {
  display: grid;
  gap: 10px;
}

.aspect {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.aspect__top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.aspect__label {
  color: var(--text-dim);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.2px;
}

.aspect__value {
  color: var(--text);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.aspect__value--zero {
  color: var(--text-muted);
}

.aspect__bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 999px;
  overflow: hidden;
}

.aspect__fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .player-card {
    padding: 16px;
  }

  .player-card__header {
    padding: 16px;
    gap: 12px;
    min-height: 90px;
  }

  .player-card__left {
    gap: 12px;
  }

  .player-card__avatar {
    width: 56px;
    height: 56px;
    font-size: 22px;
    border-radius: 12px;
  }

  .player-card__info h2 {
    font-size: 16px;
  }

  .bio {
    font-size: 11px;
  }

  .player-card__right {
    gap: 8px;
  }

  .player-card__edit {
    padding: 6px 10px;
    font-size: 11px;
  }

  .player-card__edit svg {
    display: none;
  }

  .player-card__tier {
    width: 44px;
    height: 44px;
    font-size: 19px;
    border-radius: 10px;
  }

  .aspect-card {
    padding: 14px;
  }

  .aspect-card__head {
    flex-wrap: wrap;
  }

  .aspect-card__head-right {
    width: 100%;
    justify-content: space-between;
  }
}
</style>