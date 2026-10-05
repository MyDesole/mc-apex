<script setup>
import { computed } from 'vue'
import { tierColor } from '@/composables/players/useTier.js'
import AppIcon from '@/components/core/AppIcon.vue'
import {
  AVATAR_FRAMES,
  PROFILE_EFFECTS,
} from '@/data/players/profileCustomization.js'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },

  editable: {
    type: Boolean,
    default: false,
  },

  coverUrl: {
    type: String,
    default: '',
  },

  accent: {
    type: String,
    default: '',
  },

  /**
   * Компактная версия профиля.
   *
   * false — полноценный header
   * true  — компактный header до ~200px
   */
  compact: {
    type: Boolean,
    default: false,
  },

  /*
   * Бридж-профиль: вместо тира показываем звание бриджера.
   * Подменяем значения, и шапка сама рисует ранг — переписывать каждое
   * место с тиром не нужно.
   */
  bridgeMode: {
    type: Boolean,
    default: false,
  },

  bridgeRank: {
    type: Object,
    default: null,
  },

  // Подтверждённые виды: в бридже занимают место прогресса тира
  bridgeTechniques: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['edit'])

/* =========================================================
   PLAYER
   ========================================================= */

const playerAccent = computed(() =>
    props.accent ||
    props.user.accent_color ||
    props.user.banner_color ||
    // У звания бриджера свой цвет с сервера
    (props.bridgeMode ? props.bridgeRank?.color : null) ||
    tierColor(props.user.tier)
)

const displayName = computed(() =>
    props.user.nickname ||
    props.user.username ||
    props.user.name ||
    'Игрок'
)

const avatarUrl = computed(() =>
    props.user.avatar_url ||
    props.user.avatar ||
    ''
)

/* =========================================================
   CLAN
   ========================================================= */

const clanTag = computed(() => {
  const tag = props.user.clan_tag

  if (!tag) {
    return ''
  }

  return String(tag).trim()
})

const displayNameWithClan = computed(() => {
  if (!clanTag.value) {
    return displayName.value
  }

  return `[${clanTag.value}] ${displayName.value}`
})

/* =========================================================
   ROLE
   ========================================================= */

const roleLabel = computed(() => {
  const roles = {
    admin: 'Администратор',
    tester: 'Тестер',
    moderator: 'Модератор',
    media: 'Медийка',
  }

  return roles[props.user.role] || null
})

/** Медийка: ютубер или стример — отдельный акцент в профиле. */
const isMedia = computed(() => props.user?.role === 'media')

/* =========================================================
   STATUS
   ========================================================= */

const playerBadges = computed(() => {
  const list = props.user?.equipped_badges

  return Array.isArray(list) ? list.filter((b) => b && b.slug) : []
})

/**
 * Короткое имя бейджа: «Бейдж «Ветеран»» -> «Ветеран».
 */
function badgeLabel(badge) {
  const name = String(badge?.name || badge?.slug || '').trim()

  return name
      .replace(/^бейдж\s*/i, '')
      .replace(/[«»"]/g, '')
      .trim() || name
}

const playerStatus = computed(() => {
  const value = props.user.status

  if (!value) {
    return null
  }

  return String(value).trim() || null
})

/* =========================================================
   QUOTE
   ========================================================= */

const playerQuote = computed(() => {
  const value = props.user.quote

  if (!value) {
    return null
  }

  return String(value).trim() || null
})

/* =========================================================
   AVATAR FRAME
   avatar_frame affects ONLY avatar
   ========================================================= */

const avatarFrame = computed(() =>
    AVATAR_FRAMES.find(
        frame => frame.id === props.user.avatar_frame
    ) ?? AVATAR_FRAMES[0]
)

const hasAvatarFrame = computed(() =>
    avatarFrame.value?.id !== 'default'
)

const avatarFrameStyle = computed(() => {
  const frame = avatarFrame.value

  if (!frame || frame.id === 'default') {
    return {}
  }

  if (frame.gradient) {
    return {
      background: frame.gradient,
    }
  }

  if (frame.color) {
    return {
      background: frame.color,
    }
  }

  return {}
})

const avatarFrameGlow = computed(() => {
  const frame = avatarFrame.value

  if (!frame || frame.id === 'default') {
    return playerAccent.value
  }

  if (frame.id === 'legendary') {
    return '#facc15'
  }

  if (frame.id === 'rainbow') {
    return '#a855f7'
  }

  if (frame.id === 'season1') {
    return '#7c3aed'
  }

  return frame.color || playerAccent.value
})

/* =========================================================
   PROFILE EFFECT
   profile_effect affects ENTIRE HEADER
   ========================================================= */

const profileEffect = computed(() =>
    PROFILE_EFFECTS.find(
        effect => effect.id === props.user.profile_effect
    ) ?? PROFILE_EFFECTS[0]
)

const effectId = computed(() =>
    profileEffect.value?.id || null
)

const hasProfileEffect = computed(() =>
    !!effectId.value
)

const profileEffectStyle = computed(() => {
  const effect = profileEffect.value

  if (!effect) {
    return {}
  }

  return {
    '--profile-effect-color':
        effect.color || playerAccent.value,

    '--profile-effect-gradient':
        effect.gradient || 'none',
  }
})

/* =========================================================
   TIER
   ========================================================= */

const tier = computed(() =>
    props.user.tier || 'UNRANKED'
)

const tierLabel = computed(() => {
  if (props.bridgeMode) {
    return String(props.bridgeRank?.label ?? 'БЕЗ ЗВАНИЯ').toUpperCase()
  }

  return String(tier.value)
      .replace(/_/g, ' ')
      .toUpperCase()
})

const tierShort = computed(() => {
  const value = tierLabel.value

  if (value === 'UNRANKED') {
    return '—'
  }

  // У звания убираем приставку Bridge: в водяном знаке она лишняя
  if (props.bridgeMode) {
    const short = value.replace('BRIDGE', '').trim()

    return (short || value).slice(0, 3)
  }

  return value
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .slice(0, 3)
})

const tierProgress = computed(() => {
  // У бриджа прогресса нет: звание выдаёт тестер, а не очки
  if (props.bridgeMode) {
    return null
  }

  const value =
      props.user.tier_progress ??
      props.user.rank_progress ??
      props.user.progress ??
      props.user.tier_score ??
      null

  if (value === null || value === '') {
    return null
  }

  const number = Number(value)

  if (!Number.isFinite(number)) {
    return null
  }

  return Math.max(0, Math.min(100, number))
})

const tierSubtitle = computed(() => {
  if (props.bridgeMode) {
    return props.bridgeRank ? 'BRIDGE RANK' : 'ЗВАНИЕ НЕ ВЫДАНО'
  }

  if (tier.value === 'UNRANKED') {
    return 'AWAITING RANK'
  }

  return props.user.tier_title ||
      props.user.tier_description ||
      'CURRENT RANK'
})

/* =========================================================
   OTHER DATA
   ========================================================= */

const rankPosition = computed(() =>
    props.user.rank_position ??
    props.user.position ??
    props.user.rank?.position ??
    null
)

const level = computed(() =>
    props.user.level ??
    props.user.player_level ??
    null
)

const coverStyle = computed(() => {
  if (!props.coverUrl) {
    return {}
  }

  return {
    backgroundImage: `url(${props.coverUrl})`,
  }
})
</script>

<template>
  <section
      class="profile-header"
      :class="{
        'profile-header--compact': compact,

        // Медийка: уникальная подсветка карточки
        'profile-header--media': isMedia,

        [`profile-header--effect-${effectId}`]:
            hasProfileEffect,

        'profile-header--effect-animated':
            profileEffect.animated,
      }"
      :style="{
        '--player-accent': playerAccent,
        ...profileEffectStyle,
      }"
  >

    <!-- =====================================================
         BACKGROUND
         ===================================================== -->

    <div
        class="profile-header__cover"
        :class="{
          'profile-header__cover--image': !!coverUrl,
        }"
        :style="coverStyle"
    />

    <div class="profile-header__aurora" />

    <div class="profile-header__grid" />

    <div class="profile-header__noise" />

    <!-- =====================================================
         FULL PROFILE EFFECT
         Works for both normal and compact modes
         ===================================================== -->

    <div
        v-if="hasProfileEffect"
        class="profile-header__effect"
        aria-hidden="true"
    />

    <!-- Legendary particles -->
    <div
        v-if="effectId === 'legendary' && !compact"
        class="profile-header__effect-particles"
        aria-hidden="true"
    >
      <span />
      <span />
      <span />
      <span />
      <span />
      <span />
      <span />
      <span />
    </div>

    <!-- =====================================================
         TIER WATERMARK
         Normal mode only
         ===================================================== -->

    <div
        v-if="!compact"
        class="profile-header__tier-watermark"
        aria-hidden="true"
    >
      {{ tierShort }}
    </div>

    <!-- =====================================================
         TOP BAR
         ===================================================== -->

    <div class="profile-header__topline">

      <div class="profile-header__eyebrow">
        <span class="profile-header__eyebrow-dot" />
        {{ compact ? 'PLAYER' : 'PLAYER PROFILE' }}
      </div>

      <button
          v-if="editable"
          class="profile-header__edit"
          type="button"
          @click="emit('edit')"
      >
        <svg
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
          <path d="M12 20h9" />

          <path
              d="M16.5 3.5a2.121 2.121 0 0 1 3 3L8 18l-4 1 1-4Z"
          />
        </svg>

        Настроить
      </button>

    </div>

    <!-- =====================================================
         COMPACT CONTENT
         ===================================================== -->

    <div
        v-if="compact"
        class="profile-header__compact-content"
    >

      <!-- Avatar -->
      <div class="profile-header__compact-avatar-wrap">

        <div
            class="profile-header__avatar-glow"
            :style="{
              '--avatar-frame-color': avatarFrameGlow,
            }"
        />

        <div
            class="profile-header__avatar-frame profile-header__avatar-frame--compact"
            :class="{
              'profile-header__avatar-frame--custom':
                  hasAvatarFrame,

              'profile-header__avatar-frame--glow':
                  avatarFrame.glow,
            }"
            :style="{
              ...avatarFrameStyle,
              '--avatar-frame-color': avatarFrameGlow,
            }"
        >
          <div class="profile-header__avatar-frame-inner">

            <div class="profile-header__avatar">

              <img
                  v-if="avatarUrl"
                  :src="avatarUrl"
                  :alt="displayName"
              >

              <span v-else>
                {{ displayName.charAt(0).toUpperCase() }}
              </span>

            </div>

          </div>
        </div>

      </div>

      <!-- Compact identity -->
      <div class="profile-header__compact-identity">

        <div class="profile-header__compact-tier">

          <span class="profile-header__compact-tier-icon">
            {{ tierShort }}
          </span>

          <span class="profile-header__compact-tier-info">
            <span class="profile-header__compact-tier-name">
              {{ tierLabel }}
            </span>

            <span class="profile-header__compact-tier-subtitle">
              {{ tierSubtitle }}
            </span>
          </span>

        </div>

        <div class="profile-header__compact-name-row">

          <h1 class="profile-header__compact-name">
            {{ displayNameWithClan }}
          </h1>

          <span
              v-if="roleLabel"
              class="profile-header__role"
              :class="`profile-header__role--${user.role}`"
          >
            {{ roleLabel }}
          </span>

        </div>

        <div
            v-if="user.username && user.username !== displayName"
            class="profile-header__compact-username"
        >
          @{{ user.username }}
        </div>

        <div class="profile-header__compact-meta">

          <span
              v-if="playerStatus"
              class="profile-header__compact-status"
          >
            <i />
            {{ playerStatus }}
          </span>

          <span
              v-if="level !== null"
              class="profile-header__compact-chip"
          >
            LVL {{ level }}
          </span>

          <span
              v-if="rankPosition !== null"
              class="profile-header__compact-chip"
          >
            #{{ rankPosition }}
          </span>

          <span
              v-if="user.days_on_platform !== undefined"
              class="profile-header__compact-chip"
          >
            {{ user.days_on_platform }}D
          </span>

        </div>

      </div>

      <!-- Compact progress -->
      <div
          v-if="tierProgress !== null"
          class="profile-header__compact-progress"
      >
        <div
            class="profile-header__compact-progress-value"
            :style="{
              height: `${tierProgress}%`,
            }"
        />

        <span>
          {{ Number(tierProgress).toFixed(0) }}
        </span>
      </div>

    </div>

    <!-- =====================================================
         NORMAL CONTENT
         ===================================================== -->

    <div
        v-else
        class="profile-header__content"
    >

      <!-- ===================================================
           AVATAR
           =================================================== -->

      <div class="profile-header__avatar-wrap">

        <div
            class="profile-header__avatar-glow"
            :style="{
              '--avatar-frame-color': avatarFrameGlow,
            }"
        />

        <div
            class="profile-header__avatar-frame"
            :class="{
              'profile-header__avatar-frame--custom':
                  hasAvatarFrame,

              'profile-header__avatar-frame--glow':
                  avatarFrame.glow,
            }"
            :style="{
              ...avatarFrameStyle,
              '--avatar-frame-color': avatarFrameGlow,
            }"
        >

          <div class="profile-header__avatar-frame-inner">

            <div class="profile-header__avatar">

              <img
                  v-if="avatarUrl"
                  :src="avatarUrl"
                  :alt="displayName"
              >

              <span v-else>
                {{ displayName.charAt(0).toUpperCase() }}
              </span>

            </div>

          </div>

        </div>

      </div>

      <!-- ===================================================
           IDENTITY
           =================================================== -->

      <div class="profile-header__identity">

        <!-- Tier -->
        <!-- Name -->
        <div class="profile-header__name-row">

          <h1 class="profile-header__name">
            {{ displayNameWithClan }}
          </h1>

          <span
              v-if="user.is_verified"
              class="profile-header__verified"
              title="Подтверждённый профиль"
              aria-label="Подтверждённый профиль"
          >
    <svg
        width="11"
        height="11"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
      <path d="m5 12 4 4L19 6" />
    </svg>
  </span>

          <span
              v-if="roleLabel"
              class="profile-header__role"
              :class="{
      [`profile-header__role--${user.role}`]: true,
      'profile-header__role--media': isMedia,
    }"
          >
    {{ roleLabel }}
  </span>

        </div>




        <!-- Badges (купленные в магазине) -->
        <div
            v-if="playerBadges.length"
            class="profile-header__badges"
        >
  <span
      v-for="badge in playerBadges"
      :key="badge.slug"
      class="profile-badge"
      :style="{ '--badge-color': badge.color || '#7c3aed' }"
      :title="badge.name"
  >
    <span class="profile-badge__icon">
      <AppIcon :icon="badge.icon" :size="12" />
    </span>

    <span class="profile-badge__text">
      {{ badgeLabel(badge) }}
    </span>
  </span>
        </div>

        <!-- Status -->
        <div
            v-if="playerStatus"
            class="profile-header__status-text"
        >
          <span class="profile-header__status-icon" />
          {{ playerStatus }}
        </div>

        <!-- Bio -->
        <p
            v-if="user.bio"
            class="profile-header__bio"
        >
          {{ user.bio }}
        </p>

        <!-- Quote -->
        <div
            v-if="playerQuote"
            class="profile-header__quote"
        >
          <span class="profile-header__quote-mark">
            “
          </span>

          <span class="profile-header__quote-text">
            {{ playerQuote }}
          </span>

          <span
              class="profile-header__quote-mark profile-header__quote-mark--end"
          >
            ”
          </span>
        </div>

        <!-- Stats -->
        <div class="profile-header__stats">

          <div
              v-if="rankPosition !== null"
              class="profile-header__stat"
          >
            <span class="profile-header__stat-label">
              RANK
            </span>

            <strong>
              #{{ rankPosition }}
            </strong>
          </div>

          <div
              v-if="level !== null"
              class="profile-header__stat"
          >
            <span class="profile-header__stat-label">
              LEVEL
            </span>

            <strong>
              {{ level }}
            </strong>
          </div>

          <div
              v-if="user.clan_tag"
              class="profile-header__stat profile-header__stat--clan"
          >
            <span class="profile-header__stat-label">
              CLAN
            </span>

            <strong>
              [{{ user.clan_tag }}]
            </strong>
          </div>

          <div
              v-if="user.days_on_platform !== undefined"
              class="profile-header__stat"
          >
            <span class="profile-header__stat-label">
              DAYS
            </span>

            <strong>
              {{ user.days_on_platform }}
            </strong>
          </div>

        </div>

        <!--
          Бридж-режим: вместо прогресса тира показываем звание бриджера и
          подтверждённые виды — по ним и видно уровень игрока.
        -->
        <div
            v-if="bridgeMode"
            class="bridge-rank-block"
        >
          <div class="bridge-rank-block__rank">
            <span class="bridge-rank-block__label">
              BRIDGE RANK
            </span>

            <strong
                class="bridge-rank-block__value"
                :style="{ color: bridgeRank?.color || '#8b5cf6' }"
            >
              {{ bridgeRank?.label ?? 'Без звания' }}
            </strong>
          </div>






        </div>

        <!-- Tier Progress -->
        <div
            v-else-if="tierProgress !== null"
            class="tier-progress"
        >

          <div class="tier-progress__head">

            <span>
              TIER SCORE
            </span>

            <strong>
              {{ Number(tierProgress).toFixed(0) }}%
            </strong>

          </div>

          <div class="tier-progress__track">

            <div
                class="tier-progress__value"
                :style="{
                  width: `${tierProgress}%`,
                }"
            />

          </div>

        </div>

      </div>
    </div>

    <!-- =====================================================
         RIGHT RANK EMBLEM
         Normal mode only
         ===================================================== -->



    <!-- =====================================================
         BOTTOM DECORATION
         ===================================================== -->

    <div
        v-if="!compact"
        class="profile-header__peak"
        aria-hidden="true"
    >
      <span />
      <span />
      <span />
    </div>

    <div
        class="profile-header__accent-line"
        aria-hidden="true"
    />

  </section>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileHeader.css";
</style>
