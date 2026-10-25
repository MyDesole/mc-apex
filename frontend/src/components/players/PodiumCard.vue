<script setup>
/**
 * Карточка пьедестала: игрок или клан на первом, втором или третьем месте.
 *
 * Стили намеренно без scoped: правило Vue применяет атрибут области только
 * к корневому элементу дочернего компонента, поэтому вложенные элементы
 * иначе остались бы без оформления.
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
  // Подпись под карточкой; по умолчанию — «N место»
  baseLabel: { type: String, default: '' },
  // Очки лидера — от них считается длина полоски (очки не ограничены сотней)
  max: { type: Number, default: 0 },
})

const modifiers = {
  1: 'first',
  2: 'second',
  3: 'third',
}

const modifier = computed(() => modifiers[props.position] ?? 'second')

const placeLabel = computed(() => props.baseLabel || `${props.position} место`)

/** Клан или игрок: у клана есть тег, у игрока — ник. */
const isClan = computed(() => Boolean(props.entry?.tag))

/** Ссылка на профиль игрока или на страницу клана. */
const link = computed(() =>
    isClan.value ? clanLink(props.entry) : userLink(props.entry)
)

const tierColor = computed(() => tierColors[props.entry?.tier] || '#a78bfa')

const clanColor = computed(() => props.entry?.banner_color || '#7c3aed')

/**
 * Очки общего рейтинга (RatingService): на главной — rating_points,
 * в списке рейтинга — rating_score.
 */
const score = computed(() => Number(props.entry?.rating_points ?? props.entry?.rating_score ?? 0))

const barWidth = computed(() => {
  const max = props.max || score.value
  return max > 0 ? Math.min(100, (score.value / max) * 100) : 0
})

/** Первая буква ника или тега, когда нет аватарки. */
const initial = computed(() => {
  const source = isClan.value
      ? props.entry?.tag
      : props.entry?.username ?? props.entry?.name

  return (source || '?').charAt(0).toUpperCase()
})
</script>

<template>
  <RouterLink
      :to="link"
      class="podium-card"
      :class="`podium-card--${modifier}`"
      :style="isClan ? { '--clan-color': clanColor } : {}"
  >
    <div class="podium-card__head">
      <span class="podium-card__place">{{ placeLabel }}</span>

      <span
          v-if="!isClan && entry.tier"
          class="tier-block"
          :style="{ '--tier': tierColor }"
      >
        {{ entry.tier }}
      </span>
    </div>

    <div class="podium-card__identity">
      <div
          class="podium-card__avatar"
          :class="{ 'podium-card__avatar--clan': isClan }"
      >
        <img
            v-if="entry.avatar_url"
            :src="entry.avatar_url"
            :alt="isClan ? entry.name : entry.username"
        />
        <span v-else>{{ initial }}</span>
      </div>

      <div class="podium-card__name">
        <template v-if="isClan">
          <span class="podium-card__tag">[{{ entry.tag }}]</span>
          <strong>{{ entry.name }}</strong>
        </template>

        <strong v-else>
          <UserName :user="entry" />
        </strong>
      </div>
    </div>

    <div v-if="isClan" class="podium-card__stats">
      <div>
        <strong>{{ entry.power ?? 0 }}</strong>
        <span>сила</span>
      </div>
      <div>
        <strong>{{ entry.wins ?? 0 }}</strong>
        <span>победы</span>
      </div>
      <div>
        <strong>{{ entry.members_count ?? 0 }}</strong>
        <span>участники</span>
      </div>
    </div>

    <div v-else class="podium-card__score">
      <div class="podium-card__bar" aria-hidden="true">
        <span :style="{ width: `${barWidth}%`, background: tierColor }" />
      </div>
      <span>{{ score }} оч.</span>
    </div>
  </RouterLink>
</template>

<style>
.podium-card {
  --place-color: var(--text-muted);

  min-width: 0;

  display: flex;
  flex-direction: column;
  gap: 18px;

  padding: 18px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-top: 3px solid var(--place-color);
  border-radius: var(--radius);

  transition: border-color 0.15s ease, background 0.15s ease;
}

.podium-card:hover {
  background: var(--bg-card-hover);
}

.podium-card--first {
  --place-color: #facc15;
}

.podium-card--second {
  --place-color: #cbd5e1;
}

.podium-card--third {
  --place-color: #d97706;
}

.podium-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  min-height: 32px;
}

.podium-card__place {
  color: var(--place-color);

  font-family: var(--font-display);
  font-size: 14px;
  font-weight: 700;
}

.podium-card__identity {
  display: flex;
  align-items: center;
  gap: 14px;

  min-width: 0;
}

.podium-card__avatar {
  width: 56px;
  height: 56px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  overflow: hidden;

  color: #fff;
  background: #2a2438;
  border-radius: 4px;

  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 700;
}

.podium-card__avatar--clan {
  background: var(--clan-color, var(--accent));
}

.podium-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.podium-card__name {
  min-width: 0;

  display: flex;
  flex-direction: column;
}

.podium-card__name strong {
  overflow: hidden;

  color: #fff;

  font-size: 17px;
  font-weight: 700;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.podium-card__tag {
  color: var(--clan-color, var(--accent-light));

  font-size: 13px;
  font-weight: 700;
}

.podium-card__score {
  display: flex;
  align-items: center;
  gap: 12px;

  color: var(--text-dim);

  font-size: 14px;
  font-variant-numeric: tabular-nums;
}

.podium-card__bar {
  flex: 1;
  height: 6px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  border-radius: 2px;
}

.podium-card__bar span {
  display: block;
  height: 100%;
}

.podium-card__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.podium-card__stats div {
  display: flex;
  flex-direction: column;
}

.podium-card__stats strong {
  color: var(--text);

  font-size: 17px;
  font-variant-numeric: tabular-nums;
}

.podium-card__stats span {
  color: var(--text-muted);
  font-size: 12px;
}
</style>
