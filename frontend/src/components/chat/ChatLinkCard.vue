<script setup>
/**
 * Карточка «поделиться» под сообщением: ссылка на игрока, питомца, клан
 * или лот аукциона в живом виде. Данные собирает сервер при выдаче
 * истории, поэтому цена лота и тир игрока всегда свежие.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import UserName from '@/components/players/UserName.vue'
import { profilePet } from '@/data/players/flair.js'
import { petModelStyle } from '@/data/players/petTraits.js'
import AppIcon from '@/components/core/AppIcon.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

const props = defineProps({
  card: { type: Object, required: true },
})

const LABELS = { player: 'Игрок', pet: 'Питомец', clan: 'Клан', lot: 'Лот аукциона' }

const label = computed(() => LABELS[props.card.type] ?? 'Ссылка')

const petTraits = computed(() => props.card.pet?.pet ?? null)

const rarity = computed(() => PET_RARITY[petTraits.value?.rarity] ?? null)

// Спрайт вида и окрас экземпляра (фильтр модели), без анимации прогулки
const petSprite = computed(() => profilePet(props.card.pet?.effect_value)?.sprite ?? null)
const petStyle = computed(() => {
  const { filter, opacity } = petModelStyle(petTraits.value)

  return { filter, opacity }
})

// «1 ставка», «3 ставки», «5 ставок»
const bidsLabel = computed(() => {
  const n = Number(props.card.bids_count) || 0
  const mod10 = n % 10
  const mod100 = n % 100

  if (n === 0) return 'без ставок'
  if (mod10 === 1 && mod100 !== 11) return `${n} ставка`
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${n} ставки`

  return `${n} ставок`
})

const lotState = computed(() => {
  if (props.card.type !== 'lot') return ''
  if (props.card.status !== 'active') return 'Торги завершены'

  const ms = new Date(props.card.ends_at).getTime() - Date.now()

  if (ms <= 0) return 'Завершается'

  const hours = Math.floor(ms / 3_600_000)

  return hours >= 1 ? `Ещё ${hours} ч` : `Ещё ${Math.max(1, Math.floor(ms / 60_000))} мин`
})
</script>

<template>
  <RouterLink :to="card.url" class="link-card" :class="`link-card--${card.type}`">
    <!-- Игрок -->
    <template v-if="card.type === 'player'">
      <span class="link-card__avatar">
        <img v-if="card.avatar_url" :src="card.avatar_url" :alt="card.username">
        <template v-else>{{ (card.username || '?').charAt(0).toUpperCase() }}</template>
      </span>
      <span class="link-card__body">
        <span class="link-card__label">{{ label }}</span>
        <span class="link-card__title"><UserName :user="card" compact /></span>
        <span class="link-card__sub">
          <template v-if="card.tier">Тир {{ card.tier }}</template>
          <template v-else>Без тира</template>
          <template v-if="card.profile_title"> · {{ card.profile_title }}</template>
        </span>
      </span>
    </template>

    <!-- Питомец и лот: питомец крупно -->
    <template v-else-if="card.type === 'pet' || card.type === 'lot'">
      <!--
        Сам питомец, без обёртки превью магазина: та рассчитана на
        высоту 132px и в маленькой рамке обрезалась до пустоты.
      -->
      <span class="link-card__pet">
        <PixelSprite v-if="petSprite" :sprite="petSprite" :size="32" :style="petStyle" />
      </span>
      <span class="link-card__body">
        <span class="link-card__label">
          {{ label }}
          <b v-if="rarity" :style="{ color: rarity.color }">· {{ rarity.label }}</b>
        </span>
        <span class="link-card__title">
          {{ petTraits?.name || card.pet?.name || 'Питомец' }}
          <small v-if="petTraits?.level">ур. {{ petTraits.level }}</small>
        </span>
        <span v-if="card.type === 'pet'" class="link-card__sub">Хозяин: {{ card.owner?.username }}</span>
        <span v-else class="link-card__sub link-card__sub--price">
          <PixelSprite sprite="diamond" :size="12" />
          {{ Number(card.price).toLocaleString('ru-RU') }} ApexCoin
          <span class="link-card__dot">·</span>
          {{ bidsLabel }}
          <span class="link-card__dot">·</span>
          {{ lotState }}
        </span>
      </span>
    </template>

    <!-- Клан -->
    <template v-else-if="card.type === 'clan'">
      <span class="link-card__avatar link-card__avatar--clan" :style="{ background: card.banner_color || '#7c3aed' }">
        <img v-if="card.avatar_url" :src="card.avatar_url" :alt="card.name">
        <template v-else>{{ (card.tag || 'C').charAt(0) }}</template>
      </span>
      <span class="link-card__body">
        <span class="link-card__label">{{ label }}</span>
        <span class="link-card__title">[{{ card.tag }}] {{ card.name }}</span>
        <span class="link-card__sub">Участников: {{ card.members_count }}</span>
      </span>
    </template>

    <AppIcon icon="send" :size="14" class="link-card__go" />
  </RouterLink>
</template>

<style scoped>
.link-card {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(300px, 100%);
  margin-top: 6px;
  padding: 8px 10px;

  color: inherit;
  text-decoration: none;

  background: rgba(0, 0, 0, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-left: 3px solid var(--card-accent, #a78bfa);
  border-radius: 8px;

  transition: background 0.15s ease, border-color 0.15s ease;
}

.link-card:hover {
  background: rgba(0, 0, 0, 0.32);
  border-color: rgba(255, 255, 255, 0.22);
}

.link-card--player { --card-accent: #60a5fa; }
.link-card--pet { --card-accent: #f472b6; }
.link-card--clan { --card-accent: #a78bfa; }
.link-card--lot { --card-accent: #fbbf24; }

.link-card__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 38px;
  height: 38px;
  overflow: hidden;

  font-weight: 800;

  background: rgba(255, 255, 255, 0.08);
  border-radius: 6px;
}

.link-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  image-rendering: pixelated;
}

.link-card__pet {
  position: relative;

  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 52px;
  height: 52px;

  background: rgba(0, 0, 0, 0.18);
  border-radius: 6px;
}

.link-card__body {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  gap: 1px;
}

.link-card__label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.6;
}

.link-card__title {
  overflow: hidden;

  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.link-card__title small {
  font-weight: 600;
  opacity: 0.6;
}

.link-card__sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;

  font-size: 11px;
  opacity: 0.75;
}

.link-card__dot {
  opacity: 0.5;
}

.link-card__go {
  flex-shrink: 0;
  opacity: 0.45;
}
</style>
