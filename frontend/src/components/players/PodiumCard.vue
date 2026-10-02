<script setup>
/**
 * Карточка пьедестала: игрок или клан на первом, втором или третьем месте.
 *
 * Стили намеренно без scoped: правило Vue применяет атрибут области только
 * к корневому элементу дочернего компонента, поэтому вложенные элементы
 * (.podium-name, .podium-tier и прочие) иначе остались бы без оформления.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import UserName from '@/components/players/UserName.vue'
import { userLink, clanLink } from '@/utils/links.js'
import { tierColors } from '@/data/players/profileCustomization.js'

const props = defineProps({
  // Игрок или клан
  entry: { type: Object, required: true },
  // 1, 2 или 3
  position: { type: Number, required: true },
  // Подпись на основании: APEX CHAMPION, 2ND PLACE, 3RD PLACE
  baseLabel: { type: String, default: '' },
  // Цвет тира: для кланов не используется
  color: { type: String, default: null },
  // Оценка в процентах
  score: { type: [String, Number], default: null },
})

const modifiers = {
  1: 'first',
  2: 'second',
  3: 'third',
}

const modifier = computed(() => modifiers[props.position] ?? 'second')

const rank = computed(() => String(props.position).padStart(2, '0'))

/** Клан или игрок: у клана есть тег, у игрока — ник. */
const isClan = computed(() => Boolean(props.entry?.tag))

/** Ссылка на профиль игрока или на страницу клана. */
const link = computed(() =>
    isClan.value ? clanLink(props.entry) : userLink(props.entry)
)

/** Цвет тира игрока: у клана используется цвет баннера. */
const accent = computed(() => {
  if (isClan.value) return props.entry?.banner_color || '#7c3aed'

  return tierColors[props.entry?.tier] || '#a78bfa'
})

/**
 * Балл игрока.
 *
 * На главной приходит tier_score, в рейтинге — rating_score,
 * поэтому берём то, что есть.
 */
const score = computed(() => props.entry?.tier_score ?? props.entry?.rating_score ?? 0)

/** Первая буква ника, когда нет аватарки. */
const initial = computed(() =>
    (props.entry?.username ?? props.entry?.name ?? '?').charAt(0).toUpperCase()
)

/** Первая буква тега клана. */
const clanInitial = computed(() =>
    props.entry?.tag?.charAt(0)?.toUpperCase() || 'C'
)

const avatar = computed(() => props.entry?.avatar_url ?? null)
</script>

<template>
  <!-- Клан: своя структура карточки -->
  <RouterLink
      v-if="isClan"
      :to="link"
      class="clan-podium"
      :class="`clan-podium--${modifier}`"
      :style="{ '--clan-color': entry.banner_color || '#7c3aed' }"
  >
    <div v-if="position === 1" class="clan-podium__crown">
      <svg
          width="29"
          height="29"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
      >
        <path d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
      </svg>
    </div>

    <div class="clan-podium__glow" />

    <div class="clan-podium__rank">{{ rank }}</div>

    <div class="clan-podium__avatar">
      <div v-if="position === 1" class="clan-podium__avatar-ring" />

      <img
          v-if="entry.avatar_url"
          :src="entry.avatar_url"
          :alt="entry.name"
      />

      <span v-else>{{ clanInitial }}</span>
    </div>

    <div class="clan-podium__tag">[{{ entry.tag }}]</div>

    <h3>{{ entry.name }}</h3>

    <div class="clan-podium__stats">
      <div>
        <strong>{{ entry.power }}</strong>
        <span>{{ position === 1 ? 'POWER' : 'СИЛА' }}</span>
      </div>

      <div>
        <strong>{{ entry.wins }}</strong>
        <span>{{ position === 1 ? 'WINS' : 'ПОБЕДЫ' }}</span>
      </div>
    </div>

    <div class="clan-podium__base">{{ baseLabel }}</div>
  </RouterLink>

  <!-- Игрок -->
  <RouterLink
      v-else
      :to="link"
      class="podium-card"
      :class="`podium-card--${modifier}`"
  >
    <div v-if="position === 1" class="podium-card__stars">
      <i />
      <i />
      <i />
      <i />
    </div>

    <div v-if="position === 1" class="podium-crown">
      <svg
          width="30"
          height="30"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
      >
        <path d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
        <circle cx="3" cy="7" r="1" />
        <circle cx="21" cy="7" r="1" />
        <circle cx="12" cy="5" r="1" />
      </svg>
    </div>

    <div class="podium-card__ambient" />

    <div class="podium-rank">
      <span>{{ rank }}</span>
    </div>

    <div class="podium-avatar">
      <div v-if="position === 1" class="podium-avatar__halo" />

      <img
          v-if="entry.avatar_url"
          :src="entry.avatar_url"
          :alt="entry.username"
      />

      <span v-else>{{ initial }}</span>
    </div>

    <div class="podium-name">
      <UserName :user="entry" />
    </div>

    <div class="podium-tier" :style="{ color: accent }">
      {{ entry.tier }}
    </div>

    <div class="podium-score">
      <strong>{{ score }}</strong>
      <span>%</span>
    </div>

    <div class="podium-base">
      <span>{{ baseLabel }}</span>
    </div>
  </RouterLink>
</template>

<style>
.podium-card {
  position: relative;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 310px;
  padding: 22px 15px 0;
  border: 1px solid var(--line);
  border-radius: 18px 18px 13px 13px;
  overflow: hidden;
  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.035),
          rgba(255, 255, 255, 0.008)
      );
  transition:
      transform 0.3s ease,
      border-color 0.3s ease,
      box-shadow 0.3s ease;
}

.podium-card:hover {
  transform: translateY(-6px);
}

.podium-card__ambient {
  position: absolute;
  width: 230px;
  height: 180px;
  top: -90px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 50%;
  filter: blur(30px);
  opacity: 0.12;
  pointer-events: none;
}

.podium-rank {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45px;
  height: 22px;
  border-radius: 999px;
  font-size: 8px;
  font-weight: 950;
  letter-spacing: 1px;
}

.podium-avatar {
  position: relative;
  z-index: 2;
  width: 78px;
  height: 78px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 18px;
  border-radius: 50%;
  overflow: hidden;
  background: #181820;
  color: #fff;
  font-size: 28px;
  font-weight: 1000;
}

.podium-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.podium-name {
  position: relative;
  z-index: 2;
  max-width: 100%;
  margin-top: 13px;
  overflow: hidden;
  color: #fff;
  font-size: 14px;
  font-weight: 850;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.podium-tier {
  position: relative;
  z-index: 2;
  margin-top: 6px;
  font-size: 27px;
  font-weight: 1000;
  letter-spacing: -1px;
  filter: drop-shadow(0 4px 12px currentColor);
}

.podium-score {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: baseline;
  gap: 2px;
  margin-top: 2px;
}

.podium-score strong {
  color: #fff;
  font-size: 18px;
  font-weight: 950;
}

.podium-score span {
  color: #707084;
  font-size: 9px;
  font-weight: 800;
}

.podium-base {
  position: relative;
  z-index: 2;
  width: calc(100% + 30px);
  margin-top: auto;
  padding: 11px;
  border-top: 1px solid rgba(255, 255, 255, 0.055);
  background: rgba(255, 255, 255, 0.018);
  color: #676778;
  font-size: 7px;
  font-weight: 950;
  letter-spacing: 1.5px;
  text-align: center;
}

/* ================================================================
   PLAYER PODIUM
================================================================ */

.podium-card--first {
  min-height: 380px;
  padding-top: 35px;
  border-color: rgba(250, 204, 21, 0.28);
  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(250, 204, 21, 0.13),
          transparent 48%
      ),
      linear-gradient(
          180deg,
          rgba(250, 204, 21, 0.055),
          rgba(250, 204, 21, 0.008)
      );
  box-shadow:
      0 25px 70px rgba(0, 0, 0, 0.25),
      0 0 60px rgba(250, 204, 21, 0.045);
}

.podium-card--first:hover {
  border-color: rgba(250, 204, 21, 0.48);
  box-shadow:
      0 32px 80px rgba(0, 0, 0, 0.3),
      0 0 70px rgba(250, 204, 21, 0.08);
}

.podium-card--first .podium-card__ambient {
  background: #facc15;
}

.podium-card--first .podium-rank {
  color: #422006;
  background: linear-gradient(
      135deg,
      #fde68a,
      #f59e0b
  );
  box-shadow: 0 5px 20px rgba(250, 204, 21, 0.28);
}

.podium-card--first .podium-avatar {
  width: 98px;
  height: 98px;
  border: 2px solid rgba(250, 204, 21, 0.5);
  box-shadow:
      0 12px 40px rgba(250, 204, 21, 0.18),
      0 0 0 6px rgba(250, 204, 21, 0.045);
}

.podium-card--first .podium-card__stars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.podium-card__stars i {
  position: absolute;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #fde68a;
  box-shadow: 0 0 9px #facc15;
  animation: starFloat 3s infinite ease-in-out;
}

.podium-card__stars i:nth-child(1) {
  top: 27%;
  left: 17%;
}

.podium-card__stars i:nth-child(2) {
  top: 42%;
  right: 13%;
  animation-delay: 0.8s;
}

.podium-card__stars i:nth-child(3) {
  top: 17%;
  right: 23%;
  animation-delay: 1.4s;
}

.podium-card__stars i:nth-child(4) {
  top: 32%;
  left: 28%;
  animation-delay: 2s;
}

.podium-card__crown {
  position: absolute;
  z-index: 4;
  top: 5px;
  left: 50%;
  transform: translateX(-50%);
  color: #facc15;
  filter: drop-shadow(
      0 0 12px rgba(250, 204, 21, 0.65)
  );
  animation: crownFloat 3s ease-in-out infinite;
}

.podium-card--first .podium-base {
  color: #facc15;
  background: rgba(250, 204, 21, 0.035);
}

.podium-card--second {
  min-height: 315px;
  border-color: rgba(203, 213, 225, 0.16);
}

.podium-card--second .podium-card__ambient {
  background: #cbd5e1;
}

.podium-card--second .podium-rank {
  color: #1e293b;
  background: linear-gradient(
      135deg,
      #f1f5f9,
      #94a3b8
  );
}

.podium-card--second .podium-avatar {
  border: 2px solid rgba(203, 213, 225, 0.27);
}

.podium-card--second .podium-base {
  color: #aab4c3;
}

/*
 * Порядок мест: в разметке карточки идут 01, 02, 03, но на широком
 * экране второе место стоит слева, а первое — по центру.
 * На узком экране порядок возвращается к 01, 02, 03.
 */
.podium-card--first {
  order: 2;
}

.podium-card--second {
  order: 1;
}

.podium-card--third {
  order: 3;
}

.clan-podium--first {
  order: 2;
}

.clan-podium--second {
  order: 1;
}

.clan-podium--third {
  order: 3;
}

.podium-card--third {
  min-height: 290px;
  border-color: rgba(217, 119, 6, 0.17);
}

.podium-card--third .podium-card__ambient {
  background: #d97706;
}

.podium-card--third .podium-rank {
  color: #2a1006;
  background: linear-gradient(
      135deg,
      #fdba74,
      #b45309
  );
}

.podium-card--third .podium-avatar {
  border: 2px solid rgba(217, 119, 6, 0.3);
}

.podium-card--third .podium-base {
  color: #c68143;
}

.clan-podium__glow {
  position: absolute;
  width: 200px;
  height: 160px;
  top: -80px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 50%;
  background: var(--clan-color);
  filter: blur(35px);
  opacity: 0.13;
}

.clan-podium__rank {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 43px;
  height: 21px;
  border: 1px solid color-mix(
      in srgb,
      var(--clan-color) 25%,
      transparent
  );
  border-radius: 999px;
  color: color-mix(
      in srgb,
      var(--clan-color) 80%,
      white
  );
  background: color-mix(
      in srgb,
      var(--clan-color) 7%,
      transparent
  );
  font-size: 8px;
  font-weight: 950;
  letter-spacing: 1px;
}

.clan-podium__avatar {
  position: relative;
  z-index: 2;
  width: 76px;
  height: 76px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 18px;
  overflow: hidden;
  border: 2px solid color-mix(
      in srgb,
      var(--clan-color) 35%,
      transparent
  );
  border-radius: 18px;
  color: #fff;
  background: var(--clan-color);
  box-shadow:
      0 12px 30px color-mix(
          in srgb,
          var(--clan-color) 18%,
          transparent
      );
  font-size: 27px;
  font-weight: 1000;
}

.clan-podium__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.clan-podium__avatar-ring {
  position: absolute;
  inset: -7px;
  border: 1px solid color-mix(
      in srgb,
      var(--clan-color) 20%,
      transparent
  );
  border-radius: 23px;
  pointer-events: none;
}

.clan-podium__tag {
  position: relative;
  z-index: 2;
  margin-top: 14px;
  color: color-mix(
      in srgb,
      var(--clan-color) 80%,
      white
  );
  font-size: 9px;
  font-weight: 950;
  letter-spacing: 1px;
}

.clan-podium h3 {
  position: relative;
  z-index: 2;
  max-width: 100%;
  margin: 4px 0 0;
  overflow: hidden;
  color: #f4f4f8;
  font-size: 15px;
  font-weight: 900;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-podium__stats {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 25px;
  margin-top: 18px;
}

.clan-podium__stats div {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.clan-podium__stats strong {
  color: #f3f3f8;
  font-size: 15px;
  font-weight: 950;
}

.clan-podium__stats span {
  color: #5e5e6f;
  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

.clan-podium__base {
  position: relative;
  z-index: 2;
  width: calc(100% + 30px);
  margin-top: auto;
  padding: 11px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  color: color-mix(
      in srgb,
      var(--clan-color) 65%,
      #777
  );
  background: rgba(255, 255, 255, 0.018);
  font-size: 7px;
  font-weight: 950;
  letter-spacing: 1.5px;
  text-align: center;
}

.clan-podium--first .clan-podium__avatar {
  width: 96px;
  height: 96px;
  border-radius: 22px;
  box-shadow:
      0 15px 40px color-mix(
          in srgb,
          var(--clan-color) 22%,
          transparent
      ),
      0 0 0 5px color-mix(
          in srgb,
          var(--clan-color) 5%,
          transparent
      );
}

.clan-podium__crown {
  position: absolute;
  z-index: 4;
  top: 3px;
  left: 50%;
  transform: translateX(-50%);
  color: var(--clan-color);
  filter: drop-shadow(
      0 0 12px color-mix(
          in srgb,
          var(--clan-color) 60%,
          transparent
      )
  );
  animation: crownFloat 3s ease-in-out infinite;
}
/* Медиа-запросы пьедестала: правила скопированы из HomeView.css */
@media (max-width: 900px) {
  .home-content {
    width: min(100% - 28px, 760px);
    padding-top: 20px;
  }

  .hero {
    min-height: 480px;
  }

  .stats__grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .news-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .news-card--featured {
    grid-column: span 2;
  }

  .podium-card--first {
    min-height: 350px;
  }

  .podium-card--second {
    min-height: 292px;
  }

  .podium-card--third {
    min-height: 275px;
  }

  .rank-row {
    grid-template-columns: 32px 40px minmax(0, 1fr) 35px 55px 13px;
    gap: 9px;
  }

  .clan-row {
    grid-template-columns: 32px 40px minmax(0, 1fr) 48px 48px 48px 13px;
    gap: 8px;
  }
}

@media (max-width: 680px) {
  .home-content {
    width: calc(100% - 20px);
    padding: 12px 0 35px;
  }

  .hero {
    min-height: 470px;
    padding: 70px 15px 65px;
    border-radius: 21px;
  }

  .hero__side {
    display: none;
  }

  .hero__bottom {
    left: 15px;
    right: 15px;
  }

  .hero__bottom-center {
    display: none !important;
  }

  .hero__title-main {
    font-size: 66px;
  }

  .hero__title-sub {
    margin-left: 15px;
    font-size: 43px;
    letter-spacing: 7px;
  }

  .hero__subtitle {
    max-width: 310px;
    font-size: 12px;
  }

  .hero-actions {
    width: min(100%, 300px);
    flex-direction: column;
  }

  .hero-btn {
    width: 100%;
  }

  .stats {
    margin-bottom: 45px;
  }

  .stats__grid {
    gap: 6px;
  }

  .stat-card {
    min-height: 76px;
    gap: 9px;
    padding: 11px;
  }

  .stat-card__icon {
    width: 35px;
    height: 35px;
    flex-basis: 35px;
  }

  .stat-card__icon svg {
    width: 16px;
    height: 16px;
  }

  .stat-card__content strong {
    font-size: 17px;
  }

  .stat-card__content span {
    font-size: 7px;
  }

  .stat-card__number {
    display: none;
  }

  .sections {
    gap: 52px;
  }

  .section-head {
    align-items: flex-end;
  }

  .section-head__mark {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }

  .section-head h2 {
    font-size: 18px;
  }

  .section-count {
    display: none;
  }

  .section-link {
    padding: 6px;
    font-size: 8px;
  }

  .section-link svg {
    width: 12px;
  }

  .news-grid {
    grid-template-columns: 1fr;
  }

  .news-card--featured {
    grid-column: auto;
  }

  .news-card__cover,
  .news-card--featured .news-card__cover {
    height: 175px;
  }

  .podium-card--first,
  .podium-card--second,
  .podium-card--third {
    min-height: 0;
    padding-top: 20px;
  }

  .podium-card--first {
    order: -1;
    padding-top: 34px;
  }

  /* На узком экране места идут по порядку: сброс порядка широкого экрана */
  .podium-card--second,
  .podium-card--third,
  .clan-podium--second,
  .clan-podium--third {
    order: 0;
  }

  .podium-card--first .podium-avatar {
    width: 82px;
    height: 82px;
  }

  .podium-card--second,
  .podium-card--third {
    display: grid;
    grid-template-columns: 38px 60px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 13px;
  }

  .podium-card--second .podium-rank,
  .podium-card--third .podium-rank {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  .podium-card--second .podium-avatar,
  .podium-card--third .podium-avatar {
    grid-column: 2;
    grid-row: 1 / 4;
    width: 58px;
    height: 58px;
    margin: 0;
  }

  .podium-card--second .podium-name,
  .podium-card--third .podium-name {
    grid-column: 3;
    grid-row: 1;
    margin: 0;
    text-align: left;
  }

  .podium-card--second .podium-tier,
  .podium-card--third .podium-tier {
    grid-column: 4;
    grid-row: 1 / 3;
    margin: 0;
  }

  .podium-card--second .podium-score,
  .podium-card--third .podium-score {
    grid-column: 3;
    grid-row: 2;
    justify-content: flex-start;
    margin: 0;
  }

  .podium-card--second .podium-base,
  .podium-card--third .podium-base {
    grid-column: 3 / 5;
    grid-row: 3;
    width: auto;
    margin-top: 5px;
    padding: 5px 0 0;
    text-align: left;
  }

  .rank-row {
    grid-template-columns: 28px 38px minmax(0, 1fr) 31px 12px;
    gap: 8px;
    min-height: 60px;
    padding: 8px 10px;
  }

  .rank-row__avatar {
    width: 38px;
    height: 38px;
  }

  .rank-row__score {
    display: none;
  }

  .rank-row__arrow {
    grid-column: 5;
  }

  .rank-row__tier {
    font-size: 17px;
  }

  .clan-podium__rank {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  .clan-podium__avatar,
  .clan-podium--first .clan-podium__avatar {
    grid-column: 2;
    grid-row: 1 / 4;
    width: 58px;
    height: 58px;
    margin: 0;
    border-radius: 14px;
  }

  .clan-podium__tag {
    grid-column: 3;
    grid-row: 1;
    margin: 0;
  }

  .clan-podium h3 {
    grid-column: 3;
    grid-row: 2;
    margin: 0;
    text-align: left;
  }

  .clan-podium__stats {
    grid-column: 4;
    grid-row: 1 / 3;
    margin: 0;
    gap: 10px;
  }

  .clan-podium__base {
    grid-column: 3 / 5;
    grid-row: 3;
    width: auto;
    margin-top: 5px;
    padding: 5px 0 0;
    text-align: left;
  }

  .clan-podium__crown {
    top: 3px;
  }

  .clans-list {
    gap: 6px;
  }

  .clan-row {
    grid-template-columns: 28px 40px minmax(0, 1fr) 13px;
    gap: 8px;
    min-height: 62px;
    padding: 8px 10px;
  }

  .clan-row__avatar {
    width: 40px;
    height: 40px;
  }

  .clan-row__meta {
    font-size: 7px;
  }

  .clan-row__metric {
    display: none;
  }

  .clan-row__arrow {
    grid-column: 4;
  }

  .home-footer__top {
    flex-direction: column;
    align-items: flex-start;
  }

  .footer-socials {
    justify-content: flex-start;
  }

  .home-footer__bottom {
    flex-direction: column;
  }
}
</style>
