<script setup>
import { computed } from 'vue'

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

const aspectLabels = {
  block_placing: 'БП',
  rotka: 'Ротка',
  movement: 'Мувмент',
  building: 'Строительство',
  ppl: 'ППЛ',
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
      <!-- Левая часть: аватар + имя + bio -->
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
          <h2>{{ user.username }}</h2>
          <p v-if="user.bio" class="bio">{{ user.bio }}</p>
        </div>
      </div>

      <!-- Правая часть: кнопка настроек, под ней тир -->
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
    <div
        v-for="aspect in aspects"
        :key="aspect.id"
        class="aspects-block"
    >
      <div class="aspects-block__title">
        {{ aspect.mode === 'pvp' ? 'PvP (p-ранг)' : 'BedWars (b-ранг)' }}
        <span class="percent">{{ aspect.percent ?? 0 }}%</span>
      </div>

      <div class="aspects-grid">
        <div
            v-for="(label, key) in aspectLabels"
            :key="key"
            class="aspect"
        >
          <span class="aspect__label">{{ label }}</span>

          <div class="aspect__bar">
            <div
                class="aspect__fill"
                :style="{ width: (aspect[key] / 10 * 100) + '%' }"
            />
          </div>

          <span class="aspect__value">{{ aspect[key] }}</span>
        </div>
      </div>
    </div>

    <div v-if="!aspects.length" class="empty">
      Аспекты ещё не заполнены
    </div>
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

/* Левая часть — аватар + имя */
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

/* Правая часть — кнопка + тир, столбиком */
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

/* Тир — под кнопкой */
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

/* === АСПЕКТЫ === */

.aspects-block {
  margin-top: 20px;
}

.aspects-block__title {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 700;
}

.percent {
  color: var(--accent-light);
}

.aspects-grid {
  display: grid;
  gap: 10px;
}

.aspect {
  display: grid;
  grid-template-columns: 110px 1fr 32px;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.aspect__label {
  color: var(--text-dim);
}

.aspect__bar {
  height: 8px;
  background: #1e1e2a;
  border-radius: 999px;
  overflow: hidden;
}

.aspect__fill {
  height: 100%;
  background: linear-gradient(90deg, #7c3aed, #a78bfa);
  border-radius: 999px;
  transition: width 0.3s ease;
}

.aspect__value {
  text-align: right;
  color: var(--text-dim);
  font-weight: 700;
}

.empty {
  padding: 20px;
  color: var(--text-dim);
  text-align: center;
  font-size: 13px;
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

  .aspect {
    grid-template-columns: 90px 1fr 28px;
    font-size: 12px;
  }
}
</style>