<script setup>
import { computed } from 'vue'
import { tierColor } from '@/composables/useTier.js'
import AppIcon from '@/components/AppIcon.vue'
import {
  AVATAR_FRAMES,
  PROFILE_EFFECTS,
} from '@/data/profileCustomization'

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
})

const emit = defineEmits(['edit'])

/* =========================================================
   PLAYER
   ========================================================= */

const playerAccent = computed(() =>
    props.accent ||
    props.user.accent_color ||
    props.user.banner_color ||
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

const tierLabel = computed(() =>
    String(tier.value)
        .replace(/_/g, ' ')
        .toUpperCase()
)

const tierShort = computed(() => {
  const value = tierLabel.value

  if (value === 'UNRANKED') {
    return '—'
  }

  return value
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .slice(0, 3)
})

const tierProgress = computed(() => {
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

        <!-- Tier Progress -->
        <div
            v-if="tierProgress !== null"
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
/* =========================================================
   PROFILE HEADER — PREMIUM DARK / GLASS
   ========================================================= */

.profile-header {
  --player-accent: #7c3aed;
  --player-accent-soft: rgba(124, 58, 237, 0.16);

  position: relative;
  min-height: 390px;

  margin: -24px -24px 24px;
  padding: 28px;

  overflow: hidden;
  isolation: isolate;

  background:
      radial-gradient(
          circle at 76% 22%,
          color-mix(in srgb, var(--player-accent) 26%, transparent),
          transparent 34%
      ),
      linear-gradient(
          135deg,
          #090912 0%,
          #0d0e1e 48%,
          #07070f 100%
      );

  border-bottom: 1px solid
  color-mix(
      in srgb,
      var(--player-accent) 32%,
      rgba(255, 255, 255, 0.06)
  );

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 20px 60px rgba(0, 0, 0, 0.18);
}

/* =========================================================
   COVER
   ========================================================= */

.profile-header__cover {
  position: absolute;
  inset: 0;
  z-index: 0;

  background:
      radial-gradient(
          ellipse at 78% 8%,
          color-mix(in srgb, var(--player-accent) 34%, transparent),
          transparent 46%
      ),
      radial-gradient(
          ellipse at 12% 92%,
          color-mix(in srgb, var(--player-accent) 10%, transparent),
          transparent 42%
      );

  background-size: cover;
  background-position: center;

  opacity: 0.9;
}

.profile-header__cover--image {
  opacity: 0.7;
}

.profile-header__cover::after {
  content: '';

  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          90deg,
          rgba(5, 5, 13, 0.98) 0%,
          rgba(5, 5, 13, 0.82) 42%,
          rgba(5, 5, 13, 0.3) 100%
      ),
      linear-gradient(
          0deg,
          rgba(5, 5, 13, 0.98) 0%,
          transparent 70%
      );
}

/* =========================================================
   ATMOSPHERE
   ========================================================= */

.profile-header__aurora {
  position: absolute;
  z-index: 1;

  width: 620px;
  height: 280px;

  right: -140px;
  top: 0;

  background:
      radial-gradient(
          ellipse,
          color-mix(in srgb, var(--player-accent) 34%, transparent),
          transparent 70%
      );

  filter: blur(32px);
  transform: rotate(-12deg);

  pointer-events: none;
}

.profile-header__grid {
  position: absolute;
  inset: 0;
  z-index: 2;

  background-image:
      linear-gradient(
          rgba(255, 255, 255, 0.032) 1px,
          transparent 1px
      ),
      linear-gradient(
          90deg,
          rgba(255, 255, 255, 0.032) 1px,
          transparent 1px
      );

  background-size: 42px 42px;

  mask-image:
      linear-gradient(
          to bottom,
          rgba(0, 0, 0, 0.7),
          transparent 92%
      );

  opacity: 0.42;

  pointer-events: none;
}

.profile-header__noise {
  position: absolute;
  inset: 0;
  z-index: 3;

  background-image:
      radial-gradient(
          rgba(255, 255, 255, 0.07) 0.6px,
          transparent 0.6px
      );

  background-size: 5px 5px;
  opacity: 0.055;

  pointer-events: none;
}

/* =========================================================
   PROFILE EFFECTS
   ========================================================= */

.profile-header__effect {
  position: absolute;
  inset: -20%;
  z-index: 4;

  pointer-events: none;

  opacity: 0.6;
  mix-blend-mode: screen;

  will-change: transform, opacity;
}

.profile-header--effect-glow .profile-header__effect {
  background:
      radial-gradient(
          ellipse at 72% 38%,
          color-mix(
              in srgb,
              var(--profile-effect-color) 40%,
              transparent
          ),
          transparent 52%
      ),
      radial-gradient(
          ellipse at 20% 75%,
          color-mix(
              in srgb,
              var(--profile-effect-color) 22%,
              transparent
          ),
          transparent 55%
      );

  filter: blur(30px);
  opacity: 0.68;
}

.profile-header--effect-pulse .profile-header__effect {
  background:
      radial-gradient(
          ellipse at 75% 30%,
          color-mix(
              in srgb,
              var(--profile-effect-color) 48%,
              transparent
          ),
          transparent 50%
      ),
      radial-gradient(
          ellipse at 20% 80%,
          color-mix(
              in srgb,
              var(--profile-effect-color) 22%,
              transparent
          ),
          transparent 52%
      );

  filter: blur(34px);
  opacity: 0.5;

  animation: profilePulse 2.4s ease-in-out infinite;
}

.profile-header--effect-gradient .profile-header__effect {
  inset: -50%;

  background: var(--profile-effect-gradient);

  opacity: 0.16;
  filter: blur(50px);

  transform: rotate(-10deg) scale(1.2);

  animation: profileGradient 8s ease-in-out infinite;
}

.profile-header--effect-fire .profile-header__effect {
  inset: 15% -15% -40%;

  background:
      radial-gradient(
          ellipse at 50% 100%,
          rgba(250, 204, 21, 0.62) 0%,
          rgba(249, 115, 22, 0.48) 20%,
          rgba(239, 68, 68, 0.26) 42%,
          transparent 72%
      ),
      radial-gradient(
          ellipse at 20% 100%,
          rgba(249, 115, 22, 0.25),
          transparent 45%
      ),
      radial-gradient(
          ellipse at 80% 100%,
          rgba(239, 68, 68, 0.22),
          transparent 45%
      );

  filter: blur(21px);
  opacity: 0.78;

  transform-origin: 50% 100%;

  animation: profileFire 1.5s ease-in-out infinite alternate;
}

.profile-header--effect-ice .profile-header__effect {
  background:
      radial-gradient(
          ellipse at 75% 20%,
          rgba(165, 243, 252, 0.5),
          transparent 30%
      ),
      radial-gradient(
          ellipse at 25% 70%,
          rgba(6, 182, 212, 0.34),
          transparent 45%
      ),
      linear-gradient(
          135deg,
          rgba(6, 182, 212, 0.1),
          rgba(165, 243, 252, 0.08)
      );

  filter: blur(25px);
  opacity: 0.72;

  animation: profileIce 4.5s ease-in-out infinite;
}

.profile-header--effect-legendary .profile-header__effect {
  inset: -45%;

  background:
      radial-gradient(
          ellipse at 72% 25%,
          rgba(250, 204, 21, 0.5),
          transparent 30%
      ),
      radial-gradient(
          ellipse at 28% 75%,
          rgba(249, 115, 22, 0.38),
          transparent 38%
      ),
      radial-gradient(
          ellipse at 52% 52%,
          rgba(239, 68, 68, 0.14),
          transparent 55%
      ),
      conic-gradient(
          from 0deg,
          rgba(250, 204, 21, 0.06),
          rgba(249, 115, 22, 0.15),
          rgba(239, 68, 68, 0.06),
          rgba(250, 204, 21, 0.12),
          rgba(249, 115, 22, 0.06)
      );

  filter: blur(28px);
  opacity: 0.68;

  animation:
      legendaryRotate 11s linear infinite,
      legendaryPulse 3s ease-in-out infinite;
}

/* =========================================================
   PARTICLES
   ========================================================= */

.profile-header__effect-particles {
  position: absolute;
  inset: 0;
  z-index: 5;

  pointer-events: none;
}

.profile-header__effect-particles span {
  position: absolute;

  width: 3px;
  height: 3px;

  border-radius: 50%;

  background: #facc15;

  box-shadow:
      0 0 8px #facc15,
      0 0 18px rgba(249, 115, 22, 0.65);

  animation: legendaryParticle 4s ease-in-out infinite;
}

.profile-header__effect-particles span:nth-child(1) {
  left: 58%;
  top: 22%;
  animation-delay: -0.5s;
}

.profile-header__effect-particles span:nth-child(2) {
  left: 72%;
  top: 48%;
  animation-delay: -2.2s;
}

.profile-header__effect-particles span:nth-child(3) {
  left: 53%;
  top: 70%;
  animation-delay: -1.1s;
}

.profile-header__effect-particles span:nth-child(4) {
  left: 87%;
  top: 30%;
  animation-delay: -3s;
}

.profile-header__effect-particles span:nth-child(5) {
  left: 43%;
  top: 18%;
  animation-delay: -1.8s;
}

.profile-header__effect-particles span:nth-child(6) {
  left: 80%;
  top: 76%;
  animation-delay: -3.5s;
}

.profile-header__effect-particles span:nth-child(7) {
  left: 68%;
  top: 82%;
  animation-delay: -2.8s;
}

.profile-header__effect-particles span:nth-child(8) {
  left: 92%;
  top: 64%;
  animation-delay: -1.5s;
}

/* =========================================================
   WATERMARK
   ========================================================= */

.profile-header__tier-watermark {
  position: absolute;

  right: 2%;
  bottom: -15%;

  z-index: 6;

  color:
      color-mix(
          in srgb,
          var(--player-accent) 10%,
          transparent
      );

  font-size: clamp(180px, 23vw, 330px);
  font-weight: 1000;
  line-height: 0.8;

  letter-spacing: -0.08em;

  user-select: none;
  pointer-events: none;

  -webkit-text-stroke:
      1px
      color-mix(
          in srgb,
          var(--player-accent) 14%,
          transparent
      );

  text-shadow:
      0 0 70px
      color-mix(
          in srgb,
          var(--player-accent) 18%,
          transparent
      );

  opacity: 0.62;
}

/* =========================================================
   TOP BAR
   ========================================================= */

.profile-header__topline {
  position: relative;
  z-index: 20;

  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 16px;
}

.profile-header__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  color: rgba(255, 255, 255, 0.42);

  font-size: 10px;
  font-weight: 900;

  letter-spacing: 1.8px;
  text-transform: uppercase;
}

.profile-header__eyebrow-dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: var(--player-accent);

  box-shadow:
      0 0 12px
      color-mix(
          in srgb,
          var(--player-accent) 85%,
          transparent
      );

  animation: pulse 2s ease-in-out infinite;
}

.profile-header__edit {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  min-height: 34px;
  padding: 0 12px;

  color: rgba(255, 255, 255, 0.68);

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.018)
      );

  border: 1px solid rgba(255, 255, 255, 0.085);
  border-radius: 8px;

  font-size: 11px;
  font-weight: 800;

  cursor: pointer;

  backdrop-filter: blur(14px);

  transition:
      color 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease,
      box-shadow 0.18s ease;
}

.profile-header__edit:hover {
  color: #fff;

  border-color:
      color-mix(
          in srgb,
          var(--player-accent) 48%,
          rgba(255, 255, 255, 0.1)
      );

  background:
      color-mix(
          in srgb,
          var(--player-accent) 10%,
          rgba(8, 8, 18, 0.72)
      );

  transform: translateY(-1px);

  box-shadow:
      0 8px 22px
      color-mix(
          in srgb,
          var(--player-accent) 10%,
          transparent
      );
}

/* =========================================================
   NORMAL CONTENT
   ========================================================= */

.profile-header__content {
  position: relative;
  z-index: 15;

  display: flex;
  align-items: center;

  gap: 25px;

  min-height: 280px;
  padding-top: 28px;
}

/* =========================================================
   AVATAR
   ========================================================= */

.profile-header__avatar-wrap {
  position: relative;

  flex: 0 0 auto;

  width: 122px;
  height: 150px;

  display: flex;
  justify-content: center;
  align-items: flex-start;
}

.profile-header__avatar-glow {
  position: absolute;
  inset: -18px;
  z-index: 0;

  border-radius: 50%;

  background:
      color-mix(
          in srgb,
          var(--avatar-frame-color) 26%,
          transparent
      );

  filter: blur(25px);
  opacity: 0.68;

  pointer-events: none;
}

.profile-header__avatar-frame {
  position: relative;
  z-index: 2;

  width: 118px;
  height: 118px;

  display: grid;
  place-items: center;

  padding: 4px;

  flex-shrink: 0;

  border-radius: 50%;

  background: transparent;

  transition:
      transform 0.25s ease,
      filter 0.25s ease;
}

.profile-header__avatar-frame:hover {
  transform: translateY(-2px) scale(1.015);
}

.profile-header__avatar-frame--custom {
  box-shadow:
      0 0 0 5px rgba(5, 5, 13, 0.72);
}

.profile-header__avatar-frame--glow {
  filter:
      drop-shadow(
          0 0 10px
          color-mix(
              in srgb,
              var(--avatar-frame-color) 75%,
              transparent
          )
      )
      drop-shadow(
          0 0 24px
          color-mix(
              in srgb,
              var(--avatar-frame-color) 40%,
              transparent
          )
      );
}

.profile-header__avatar-frame-inner {
  width: 100%;
  height: 100%;

  padding: 3px;

  display: grid;
  place-items: center;

  border-radius: 50%;

  background: #080811;
}

.profile-header__avatar {
  width: 100%;
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  border-radius: 50%;

  background:
      linear-gradient(
          145deg,
          color-mix(
              in srgb,
              var(--player-accent) 25%,
              #111225
          ),
          #080811
      );

  color: #fff;

  font-size: 40px;
  font-weight: 950;
}

.profile-header__avatar img {
  display: block;

  width: 100%;
  height: 100%;

  aspect-ratio: 1 / 1;

  object-fit: cover;
  object-position: center;
}

/* =========================================================
   IDENTITY
   ========================================================= */

.profile-header__identity {
  min-width: 0;
  max-width: 650px;
}

/* =========================================================
   TIER
   ========================================================= */

.tier-display {
  position: relative;

  display: flex;
  align-items: center;

  gap: 12px;

  width: fit-content;

  margin-bottom: 12px;
  padding: 7px 14px 7px 7px;

  border: 1px solid
  color-mix(
      in srgb,
      var(--player-accent) 38%,
      rgba(255, 255, 255, 0.08)
  );

  border-radius: 10px;

  background:
      linear-gradient(
          135deg,
          color-mix(
              in srgb,
              var(--player-accent) 11%,
              rgba(8, 8, 18, 0.84)
          ),
          rgba(8, 8, 18, 0.68)
      );

  box-shadow:
      0 8px 30px
      color-mix(
          in srgb,
          var(--player-accent) 10%,
          transparent
      ),
      inset 0 1px 0 rgba(255, 255, 255, 0.055);

  backdrop-filter: blur(14px);
}

.tier-display__icon {
  position: relative;

  display: grid;
  place-items: center;

  width: 52px;
  height: 52px;

  flex-shrink: 0;

  background:
      conic-gradient(
          from 45deg,
          transparent,
          var(--player-accent),
          rgba(255, 255, 255, 0.7),
          var(--player-accent),
          transparent
      );

  clip-path: polygon(
      50% 0%,
      88% 20%,
      100% 58%,
      76% 94%,
      24% 94%,
      0% 58%,
      12% 20%
  );

  filter:
      drop-shadow(
          0 0 12px
          color-mix(
              in srgb,
              var(--player-accent) 50%,
              transparent
          )
      );
}

.tier-display__icon-inner {
  display: grid;
  place-items: center;

  width: 44px;
  height: 44px;

  clip-path: inherit;

  background:
      linear-gradient(
          145deg,
          color-mix(
              in srgb,
              var(--player-accent) 28%,
              #121322
          ),
          #07070f
      );

  color: #fff;

  font-size: 15px;
  font-weight: 1000;

  letter-spacing: -0.5px;

  text-shadow:
      0 0 12px
      color-mix(
          in srgb,
          var(--player-accent) 75%,
          transparent
      );
}

.tier-display__info {
  min-width: 100px;
}

.tier-display__eyebrow {
  margin-bottom: 2px;

  color: rgba(255, 255, 255, 0.34);

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.6px;
}

.tier-display__name {
  color: #fff;

  font-size: 17px;
  line-height: 1.05;

  font-weight: 950;

  letter-spacing: 0.5px;

  text-shadow:
      0 0 20px
      color-mix(
          in srgb,
          var(--player-accent) 25%,
          transparent
      );
}

.tier-display__subtitle {
  margin-top: 4px;

  color:
      color-mix(
          in srgb,
          var(--player-accent) 78%,
          white
      );

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.1px;
}

.tier-display__ornament {
  display: flex;
  align-items: center;

  gap: 3px;
  margin-left: 5px;
}

.tier-display__ornament span {
  display: block;

  width: 2px;
  height: 12px;

  background: var(--player-accent);
  opacity: 0.25;
}

.tier-display__ornament span:nth-child(2) {
  height: 19px;
  opacity: 0.55;
}

.tier-display__ornament span:nth-child(3) {
  height: 27px;
  opacity: 0.9;
}

/* =========================================================
   NAME + VERIFIED
   ========================================================= */

.profile-header__name-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;

  gap: 8px;

  min-width: 0;
}

.profile-header__name {
  margin: 0;

  color: #fff;

  font-size: clamp(32px, 4vw, 50px);

  line-height: 0.95;

  font-weight: 1000;

  letter-spacing: -2px;

  text-shadow:
      0 3px 25px rgba(0, 0, 0, 0.5),
      0 0 40px
      color-mix(
          in srgb,
          var(--player-accent) 14%,
          transparent
      );
}

/* Instagram-like verification */

.profile-header__verified {
  position: relative;

  width: 18px;
  height: 18px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #60a5fa,
          #3b82f6 55%,
          #2563eb
      );

  border-radius: 50%;

  box-shadow:
      0 0 0 2px rgba(59, 130, 246, 0.08),
      0 3px 12px rgba(37, 99, 235, 0.28);

  transform: translateY(1px);

  cursor: default;
}

.profile-header__verified::after {
  content: '';

  position: absolute;
  inset: 2px;

  border: 1px solid rgba(255, 255, 255, 0.17);
  border-radius: 50%;

  pointer-events: none;
}

.profile-header__verified svg {
  position: relative;
  z-index: 1;

  width: 11px;
  height: 11px;
}

/* =========================================================
   BADGES — PURCHASED BADGES
   ========================================================= */

.profile-header__badges {
  display: flex;
  align-items: center;
  flex-wrap: wrap;

  gap: 6px;

  margin-top: 9px;
  margin-bottom: 8px;
}

.profile-badge {
  --badge-color: #8b5cf6;

  position: relative;

  display: inline-flex;
  align-items: center;

  gap: 6px;

  min-height: 26px;

  padding: 0 9px 0 4px;

  color:
      color-mix(
          in srgb,
          var(--badge-color) 82%,
          white
      );

  background:
      linear-gradient(
          100deg,
          color-mix(
              in srgb,
              var(--badge-color) 9%,
              transparent
          ),
          rgba(255, 255, 255, 0.022)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--badge-color) 27%,
      rgba(255, 255, 255, 0.055)
  );

  border-radius: 7px;

  font-size: 9px;
  font-weight: 800;

  line-height: 1;

  white-space: nowrap;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.045);

  transition:
      transform 0.16s ease,
      border-color 0.16s ease,
      background 0.16s ease,
      box-shadow 0.16s ease;
}

.profile-badge:hover {
  transform: translateY(-1px);

  background:
      linear-gradient(
          100deg,
          color-mix(
              in srgb,
              var(--badge-color) 14%,
              transparent
          ),
          rgba(255, 255, 255, 0.035)
      );

  border-color:
      color-mix(
          in srgb,
          var(--badge-color) 48%,
          rgba(255, 255, 255, 0.08)
      );

  box-shadow:
      0 5px 16px
      color-mix(
          in srgb,
          var(--badge-color) 11%,
          transparent
      ),
      inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

.profile-badge__icon {
  width: 19px;
  height: 19px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: var(--badge-color);

  background:
      color-mix(
          in srgb,
          var(--badge-color) 11%,
          rgba(5, 5, 10, 0.72)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--badge-color) 32%,
      transparent
  );

  border-radius: 5px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.profile-badge__text {
  max-width: 120px;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;
}

/* =========================================================
   ROLE
   ========================================================= */

.profile-header__role {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-height: 23px;

  padding: 0 8px;

  color: var(--text-dim, rgba(255, 255, 255, 0.65));

  background: rgba(255, 255, 255, 0.035);

  border: 1px solid rgba(255, 255, 255, 0.075);

  border-radius: 6px;

  font-size: 8px;
  font-weight: 900;

  line-height: 1;

  letter-spacing: 0.55px;

  text-transform: uppercase;

  white-space: nowrap;

  backdrop-filter: blur(10px);
}

.profile-header__role--admin {
  color: #facc15;

  background: rgba(250, 204, 21, 0.055);

  border-color: rgba(250, 204, 21, 0.18);

  box-shadow:
      0 0 14px rgba(250, 204, 21, 0.08);
}

.profile-header__role--tester {
  color: #67e8f9;

  background: rgba(6, 182, 212, 0.055);

  border-color: rgba(6, 182, 212, 0.18);

  box-shadow:
      0 0 14px rgba(6, 182, 212, 0.08);
}

.profile-header__role--moderator {
  color: #c4b5fd;

  background: rgba(168, 85, 247, 0.055);

  border-color: rgba(168, 85, 247, 0.18);

  box-shadow:
      0 0 14px rgba(168, 85, 247, 0.08);
}

/* =========================================================
   MEDIA ROLE
   ========================================================= */

.profile-header--media {
  position: relative;
}

.profile-header--media::after {
  content: '';

  position: absolute;
  inset: 0;

  z-index: 7;

  pointer-events: none;

  background:
      radial-gradient(
          circle at 8% 5%,
          rgba(244, 114, 182, 0.08),
          transparent 34%
      ),
      radial-gradient(
          circle at 94% 92%,
          rgba(168, 85, 247, 0.07),
          transparent 38%
      );
}

.profile-header__role--media {
  position: relative;

  min-height: 23px;

  padding: 0 8px 0 7px;

  color: #fce7f3;

  background:
      linear-gradient(
          135deg,
          rgba(236, 72, 153, 0.13),
          rgba(168, 85, 247, 0.11)
      );

  border: 1px solid rgba(236, 72, 153, 0.28);

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.055),
      0 4px 15px rgba(236, 72, 153, 0.08);
}

.profile-header__role--media::before {
  content: '';

  width: 5px;
  height: 5px;

  margin-right: 5px;

  flex-shrink: 0;

  background: #f472b6;

  border-radius: 50%;

  box-shadow:
      0 0 8px rgba(244, 114, 182, 0.8);
}

.profile-header__role--media::after {
  content: '';

  position: absolute;
  inset: -1px;

  border-radius: inherit;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(244, 114, 182, 0.16),
          transparent
      );

  opacity: 0;

  transition: opacity 0.2s ease;

  pointer-events: none;
}

.profile-header__role--media:hover::after {
  opacity: 1;
}

.profile-header--media .profile-header__accent-line {
  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(236, 72, 153, 0.82),
          rgba(168, 85, 247, 0.76),
          transparent
      );

  box-shadow:
      0 0 18px rgba(236, 72, 153, 0.22);
}

/* =========================================================
   USERNAME
   ========================================================= */

.profile-header__username {
  margin: 8px 0 0;

  color: rgba(255, 255, 255, 0.34);

  font-size: 12px;
  font-weight: 700;
}

/* =========================================================
   STATUS
   ========================================================= */

.profile-header__status-text {
  display: flex;
  align-items: center;

  width: fit-content;

  gap: 7px;

  margin-top: 8px;

  color: rgba(255, 255, 255, 0.46);

  font-size: 11px;
  font-weight: 700;

  font-style: italic;
}

.profile-header__status-icon {
  width: 5px;
  height: 5px;

  flex-shrink: 0;

  border-radius: 50%;

  background: var(--player-accent);

  box-shadow:
      0 0 9px
      color-mix(
          in srgb,
          var(--player-accent) 80%,
          transparent
      );
}

/* =========================================================
   BIO
   ========================================================= */

.profile-header__bio {
  max-width: 560px;

  margin: 11px 0 0;

  color: rgba(255, 255, 255, 0.52);

  font-size: 12px;
  line-height: 1.55;
}

/* =========================================================
   QUOTE
   ========================================================= */

.profile-header__quote {
  display: flex;
  align-items: flex-start;

  max-width: 560px;

  margin-top: 13px;
  padding: 9px 12px;

  color: rgba(255, 255, 255, 0.46);

  background:
      color-mix(
          in srgb,
          var(--player-accent) 4%,
          rgba(255, 255, 255, 0.022)
      );

  border-left: 2px solid
  color-mix(
      in srgb,
      var(--player-accent) 60%,
      transparent
  );

  border-radius: 0 7px 7px 0;

  backdrop-filter: blur(8px);
}

.profile-header__quote-mark {
  flex-shrink: 0;

  margin-right: 4px;

  color:
      color-mix(
          in srgb,
          var(--player-accent) 72%,
          white
      );

  font-family: Georgia, serif;

  font-size: 22px;
  line-height: 14px;

  opacity: 0.7;
}

.profile-header__quote-mark--end {
  margin-right: 0;
  margin-left: 3px;

  align-self: flex-end;
}

.profile-header__quote-text {
  font-size: 10px;
  line-height: 1.5;

  font-weight: 600;

  letter-spacing: 0.1px;
}

/* =========================================================
   STATS
   ========================================================= */

.profile-header__stats {
  display: flex;
  align-items: center;
  flex-wrap: wrap;

  gap: 7px;

  margin-top: 16px;
}

.profile-header__stat {
  display: flex;
  align-items: baseline;

  gap: 7px;

  padding: 7px 10px;

  background: rgba(255, 255, 255, 0.032);

  border: 1px solid rgba(255, 255, 255, 0.06);

  border-radius: 7px;

  backdrop-filter: blur(8px);
}

.profile-header__stat-label {
  color: rgba(255, 255, 255, 0.28);

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1px;
}

.profile-header__stat strong {
  color: rgba(255, 255, 255, 0.84);

  font-size: 11px;
  font-weight: 900;
}

.profile-header__stat--clan strong {
  color:
      color-mix(
          in srgb,
          var(--player-accent) 82%,
          white
      );
}

/* =========================================================
   TIER PROGRESS
   ========================================================= */

.tier-progress {
  width: min(400px, 100%);

  margin-top: 16px;
}

.tier-progress__head {
  display: flex;
  justify-content: space-between;

  margin-bottom: 5px;

  color: rgba(255, 255, 255, 0.26);

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1px;
}

.tier-progress__head strong {
  color:
      color-mix(
          in srgb,
          var(--player-accent) 78%,
          white
      );
}

.tier-progress__track {
  position: relative;

  height: 4px;

  overflow: hidden;

  border-radius: 999px;

  background: rgba(255, 255, 255, 0.065);
}

.tier-progress__value {
  position: relative;

  height: 100%;

  border-radius: inherit;

  background:
      linear-gradient(
          90deg,
          color-mix(
              in srgb,
              var(--player-accent) 60%,
              transparent
          ),
          var(--player-accent),
          rgba(255, 255, 255, 0.85)
      );

  box-shadow:
      0 0 12px
      color-mix(
          in srgb,
          var(--player-accent) 62%,
          transparent
      );
}

.tier-progress__value::after {
  content: '';

  position: absolute;

  right: 0;
  top: -3px;

  width: 10px;
  height: 10px;

  border-radius: 50%;

  background: #fff;

  box-shadow:
      0 0 12px var(--player-accent);
}

/* =========================================================
   RANK MARK
   ========================================================= */

.profile-header__rank-mark {
  position: absolute;

  right: 7%;
  top: 50%;

  z-index: 12;

  display: flex;
  flex-direction: column;
  align-items: center;

  gap: 8px;

  transform:
      translateY(-50%)
      rotate(4deg);

  pointer-events: none;

  opacity: 0.8;
}

.rank-mark__outer {
  display: grid;
  place-items: center;

  width: 180px;
  height: 180px;

  background:
      conic-gradient(
          from 0deg,
          transparent,
          color-mix(
              in srgb,
              var(--player-accent) 76%,
              transparent
          ),
          transparent 90deg,
          rgba(255, 255, 255, 0.35) 180deg,
          transparent 220deg,
          var(--player-accent) 300deg,
          transparent
      );

  clip-path: polygon(
      50% 0%,
      91% 18%,
      100% 50%,
      91% 82%,
      50% 100%,
      9% 82%,
      0% 50%,
      9% 18%
  );

  filter:
      drop-shadow(
          0 0 22px
          color-mix(
              in srgb,
              var(--player-accent) 28%,
              transparent
          )
      );
}

.rank-mark__middle {
  display: grid;
  place-items: center;

  width: 166px;
  height: 166px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.11),
          color-mix(
              in srgb,
              var(--player-accent) 11%,
              #07070f
          )
      );

  clip-path: inherit;
}

.rank-mark__inner {
  display: grid;
  place-items: center;

  width: 135px;
  height: 135px;

  background:
      radial-gradient(
          circle,
          color-mix(
              in srgb,
              var(--player-accent) 17%,
              #080811
          ),
          #050509 72%
      );

  clip-path: inherit;

  color: #fff;

  font-size: 39px;
  font-weight: 1000;

  letter-spacing: -2px;

  text-shadow:
      0 0 20px
      color-mix(
          in srgb,
          var(--player-accent) 88%,
          transparent
      ),
      0 3px 15px rgba(0, 0, 0, 0.8);
}

.rank-mark__label {
  padding: 5px 10px;

  color:
      color-mix(
          in srgb,
          var(--player-accent) 78%,
          white
      );

  background: rgba(5, 5, 12, 0.68);

  border: 1px solid
  color-mix(
      in srgb,
      var(--player-accent) 32%,
      transparent
  );

  border-radius: 5px;

  font-size: 8px;
  font-weight: 950;

  letter-spacing: 1.4px;

  backdrop-filter: blur(8px);
}

/* =========================================================
   BOTTOM DECORATION
   ========================================================= */

.profile-header__peak {
  position: absolute;

  right: 2%;
  bottom: -5px;

  z-index: 8;

  width: 400px;
  height: 150px;

  opacity: 0.28;

  pointer-events: none;
}

.profile-header__peak::before {
  content: '';

  position: absolute;

  left: 50%;
  bottom: 0;

  width: 0;
  height: 0;

  border-left: 150px solid transparent;
  border-right: 150px solid transparent;
  border-bottom: 145px solid rgba(255, 255, 255, 0.045);

  transform: translateX(-50%);
}

.profile-header__peak span {
  position: absolute;

  width: 0;
  height: 0;

  border-left: 45px solid transparent;
  border-right: 45px solid transparent;
  border-bottom: 65px solid rgba(255, 255, 255, 0.055);
}

.profile-header__peak span:nth-child(1) {
  left: 20px;
  bottom: 0;
}

.profile-header__peak span:nth-child(2) {
  right: 10px;
  bottom: 0;

  transform: scale(1.3);
}

.profile-header__peak span:nth-child(3) {
  left: 50%;
  bottom: 55px;

  transform:
      translateX(-50%)
      scale(0.55);
}

/* =========================================================
   ACCENT LINE
   ========================================================= */

.profile-header__accent-line {
  position: absolute;

  left: 0;
  right: 0;
  bottom: 0;

  z-index: 30;

  height: 2px;

  background:
      linear-gradient(
          90deg,
          transparent,
          color-mix(
              in srgb,
              var(--player-accent) 90%,
              transparent
          ),
          rgba(255, 255, 255, 0.72),
          color-mix(
              in srgb,
              var(--player-accent) 90%,
              transparent
          ),
          transparent
      );

  box-shadow:
      0 0 18px
      color-mix(
          in srgb,
          var(--player-accent) 42%,
          transparent
      );
}

/* =========================================================
   COMPACT MODE
   ========================================================= */

.profile-header--compact {
  min-height: 190px;
  height: 190px;

  margin: -24px -24px 20px;

  padding: 18px 22px 20px;
}

.profile-header__compact-content {
  position: relative;
  z-index: 15;

  display: flex;
  align-items: center;

  gap: 16px;

  min-width: 0;

  height: 122px;

  padding-top: 10px;
}

.profile-header__compact-avatar-wrap {
  position: relative;

  flex: 0 0 auto;

  width: 76px;
  height: 76px;

  display: grid;
  place-items: center;
}

.profile-header__avatar-frame--compact {
  width: 70px;
  height: 70px;

  padding: 3px;
}

.profile-header__avatar-frame--compact
.profile-header__avatar {
  font-size: 25px;
}

.profile-header__compact-avatar-wrap
.profile-header__avatar-glow {
  inset: -12px;
}

/* =========================================================
   COMPACT IDENTITY
   ========================================================= */

.profile-header__compact-identity {
  min-width: 0;

  flex: 1 1 auto;

  display: flex;
  flex-direction: column;

  justify-content: center;

  gap: 5px;
}

.profile-header__compact-tier {
  display: inline-flex;
  align-items: center;

  width: fit-content;

  gap: 7px;

  min-width: 0;

  margin-bottom: 1px;
}

.profile-header__compact-tier-icon {
  display: grid;
  place-items: center;

  width: 27px;
  height: 27px;

  flex-shrink: 0;

  border: 1px solid
  color-mix(
      in srgb,
      var(--player-accent) 52%,
      transparent
  );

  background:
      linear-gradient(
          145deg,
          color-mix(
              in srgb,
              var(--player-accent) 27%,
              #11121f
          ),
          #07070f
      );

  clip-path: polygon(
      50% 0%,
      92% 20%,
      100% 50%,
      92% 80%,
      50% 100%,
      8% 80%,
      0% 50%,
      8% 20%
  );

  color: #fff;

  font-size: 8px;
  font-weight: 1000;

  text-shadow:
      0 0 8px var(--player-accent);

  filter:
      drop-shadow(
          0 0 7px
          color-mix(
              in srgb,
              var(--player-accent) 45%,
              transparent
          )
      );
}

.profile-header__compact-tier-info {
  min-width: 0;

  display: flex;
  flex-direction: column;
}

.profile-header__compact-tier-name {
  color: rgba(255, 255, 255, 0.8);

  font-size: 9px;
  line-height: 1;

  font-weight: 950;

  letter-spacing: 0.8px;
}

.profile-header__compact-tier-subtitle {
  margin-top: 2px;

  color:
      color-mix(
          in srgb,
          var(--player-accent) 78%,
          white
      );

  font-size: 6px;
  font-weight: 900;

  letter-spacing: 1px;
}

/* =========================================================
   COMPACT NAME
   ========================================================= */

.profile-header__compact-name-row {
  display: flex;
  align-items: center;

  flex-wrap: wrap;

  gap: 6px;

  min-width: 0;
}

.profile-header__compact-name {
  min-width: 0;

  margin: 0;

  overflow: hidden;

  color: #fff;

  font-size: clamp(20px, 2.4vw, 28px);

  line-height: 1;

  font-weight: 1000;

  letter-spacing: -0.8px;

  white-space: nowrap;

  text-overflow: ellipsis;

  text-shadow:
      0 2px 15px rgba(0, 0, 0, 0.5),
      0 0 25px
      color-mix(
          in srgb,
          var(--player-accent) 11%,
          transparent
      );
}

.profile-header__compact-name-row
.profile-header__verified {
  width: 15px;
  height: 15px;

  transform: translateY(0);
}

.profile-header__compact-name-row
.profile-header__verified svg {
  width: 9px;
  height: 9px;
}

.profile-header--compact
.profile-header__role {
  min-height: 19px;

  flex: 0 0 auto;

  padding: 0 6px;

  font-size: 6px;

  letter-spacing: 0.55px;

  border-radius: 4px;
}

/* =========================================================
   COMPACT USERNAME
   ========================================================= */

.profile-header__compact-username {
  color: rgba(255, 255, 255, 0.27);

  font-size: 8px;
  font-weight: 700;

  line-height: 1;
}

/* =========================================================
   COMPACT META
   ========================================================= */

.profile-header__compact-meta {
  display: flex;
  align-items: center;

  flex-wrap: nowrap;

  gap: 5px;

  min-width: 0;

  overflow: hidden;
}

.profile-header__compact-status {
  display: inline-flex;
  align-items: center;

  min-width: 0;
  max-width: 180px;

  gap: 4px;

  overflow: hidden;

  color: rgba(255, 255, 255, 0.4);

  font-size: 7px;
  font-weight: 700;

  font-style: italic;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.profile-header__compact-status i {
  width: 4px;
  height: 4px;

  flex: 0 0 auto;

  border-radius: 50%;

  background: var(--player-accent);

  box-shadow:
      0 0 7px
      color-mix(
          in srgb,
          var(--player-accent) 80%,
          transparent
      );
}

.profile-header__compact-chip {
  flex: 0 0 auto;

  padding: 3px 5px;

  color: rgba(255, 255, 255, 0.38);

  background: rgba(255, 255, 255, 0.035);

  border: 1px solid rgba(255, 255, 255, 0.065);

  border-radius: 4px;

  font-size: 6px;
  font-weight: 900;

  letter-spacing: 0.5px;
}

/* =========================================================
   COMPACT PROGRESS
   ========================================================= */

.profile-header__compact-progress {
  position: relative;

  flex: 0 0 auto;

  width: 4px;
  height: 72px;

  overflow: hidden;

  border-radius: 999px;

  background: rgba(255, 255, 255, 0.065);

  box-shadow:
      inset 0 0 4px rgba(0, 0, 0, 0.4);
}

.profile-header__compact-progress-value {
  position: absolute;

  left: 0;
  right: 0;
  bottom: 0;

  border-radius: inherit;

  background:
      linear-gradient(
          to top,
          color-mix(
              in srgb,
              var(--player-accent) 52%,
              transparent
          ),
          var(--player-accent),
          rgba(255, 255, 255, 0.88)
      );

  box-shadow:
      0 0 12px
      color-mix(
          in srgb,
          var(--player-accent) 65%,
          transparent
      );
}

.profile-header__compact-progress span {
  position: absolute;

  left: 50%;
  bottom: -18px;

  transform: translateX(-50%);

  color:
      color-mix(
          in srgb,
          var(--player-accent) 72%,
          white
      );

  font-size: 6px;
  font-weight: 900;
}

/* =========================================================
   COMPACT EFFECT TUNING
   ========================================================= */

.profile-header--compact
.profile-header__effect {
  opacity: 0.5;
}

.profile-header--compact.profile-header--effect-fire
.profile-header__effect {
  inset: 5% -20% -45%;
}

.profile-header--compact.profile-header--effect-gradient
.profile-header__effect {
  inset: -80%;
}

.profile-header--compact.profile-header--effect-legendary
.profile-header__effect {
  inset: -70%;
  opacity: 0.45;
}

/* =========================================================
   ANIMATIONS
   ========================================================= */

@keyframes pulse {
  0%,
  100% {
    opacity: 0.5;
    transform: scale(0.85);
  }

  50% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes profilePulse {
  0%,
  100% {
    opacity: 0.3;
    transform: scale(0.95);
  }

  50% {
    opacity: 0.7;
    transform: scale(1.05);
  }
}

@keyframes profileGradient {
  0% {
    transform:
        rotate(-10deg)
        translate(-3%, -2%)
        scale(1.15);
  }

  50% {
    transform:
        rotate(10deg)
        translate(3%, 2%)
        scale(1.25);
  }

  100% {
    transform:
        rotate(-10deg)
        translate(-3%, -2%)
        scale(1.15);
  }
}

@keyframes profileFire {
  0% {
    transform:
        scaleY(0.94)
        translateY(10px);
  }

  100% {
    transform:
        scaleY(1.08)
        translateY(-8px);
  }
}

@keyframes profileIce {
  0%,
  100% {
    transform:
        translate3d(-2%, 0, 0)
        scale(1);
  }

  50% {
    transform:
        translate3d(2%, -2%, 0)
        scale(1.05);
  }
}

@keyframes legendaryRotate {
  from {
    transform: rotate(0deg) scale(1.1);
  }

  to {
    transform: rotate(360deg) scale(1.1);
  }
}

@keyframes legendaryPulse {
  0%,
  100% {
    opacity: 0.45;
  }

  50% {
    opacity: 0.8;
  }
}

@keyframes legendaryParticle {
  0% {
    transform:
        translate3d(0, 15px, 0)
        scale(0.5);

    opacity: 0;
  }

  20% {
    opacity: 1;
  }

  70% {
    opacity: 0.8;
  }

  100% {
    transform:
        translate3d(15px, -55px, 0)
        scale(1);

    opacity: 0;
  }
}

/* =========================================================
   REDUCED MOTION
   ========================================================= */

@media (prefers-reduced-motion: reduce) {
  .profile-header *,
  .profile-header *::before,
  .profile-header *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* =========================================================
   RESPONSIVE — TABLET
   ========================================================= */

@media (max-width: 1000px) {
  .profile-header__rank-mark {
    right: 1%;

    opacity: 0.42;

    transform:
        translateY(-50%)
        rotate(4deg)
        scale(0.78);
  }

  .profile-header__identity {
    max-width: 560px;
  }
}

@media (max-width: 900px) {
  .profile-header__rank-mark {
    opacity: 0.28;

    transform:
        translateY(-50%)
        rotate(4deg)
        scale(0.65);
  }
}

/* =========================================================
   RESPONSIVE — MOBILE
   ========================================================= */

@media (max-width: 700px) {
  .profile-header {
    min-height: 0;

    margin: -16px -16px 20px;

    padding: 20px 18px 24px;
  }

  .profile-header__content {
    align-items: flex-start;

    gap: 18px;

    padding-top: 30px;
  }

  .profile-header__avatar-wrap {
    width: 82px;
    height: 105px;
  }

  .profile-header__avatar-frame {
    width: 78px;
    height: 78px;
  }

  .profile-header__avatar {
    font-size: 27px;
  }

  .profile-header__avatar-glow {
    inset: -12px;
  }

  .profile-header__name {
    font-size: 32px;
    letter-spacing: -1.2px;
  }

  .profile-header__rank-mark {
    right: -25px;
    top: 34%;

    transform:
        rotate(4deg)
        scale(0.55);

    transform-origin: center;
  }

  .profile-header__tier-watermark {
    right: -2%;
    font-size: 180px;
  }

  .tier-display {
    padding-right: 10px;
  }

  .tier-display__ornament {
    display: none;
  }

  .profile-header--effect-fire
  .profile-header__effect {
    inset: 20% -25% -25%;
  }

  .profile-header__quote {
    max-width: 100%;
  }

  .profile-header__badges {
    gap: 5px;
  }

  .profile-badge {
    min-height: 24px;
    padding-right: 7px;
    font-size: 8px;
  }

  .profile-badge__icon {
    width: 18px;
    height: 18px;
  }

  .profile-badge__text {
    max-width: 100px;
  }

  .profile-header__verified {
    width: 16px;
    height: 16px;
  }

  .profile-header__verified svg {
    width: 10px;
    height: 10px;
  }
}

/* =========================================================
   COMPACT RESPONSIVE
   ========================================================= */

@media (max-width: 700px) {
  .profile-header--compact {
    height: 170px;
    min-height: 170px;

    margin: -16px -16px 18px;

    padding: 15px 16px 17px;
  }

  .profile-header__compact-content {
    height: 108px;

    gap: 12px;

    padding-top: 5px;
  }

  .profile-header__compact-avatar-wrap {
    width: 62px;
    height: 62px;
  }

  .profile-header__avatar-frame--compact {
    width: 58px;
    height: 58px;
  }

  .profile-header__compact-name {
    font-size: 21px;
  }

  .profile-header__compact-tier-name {
    font-size: 8px;
  }

  .profile-header__compact-status {
    max-width: 110px;
  }

  .profile-header__compact-progress {
    height: 60px;
  }

  .profile-header__compact-name-row
  .profile-header__verified {
    width: 14px;
    height: 14px;
  }

  .profile-header__compact-name-row
  .profile-header__verified svg {
    width: 8px;
    height: 8px;
  }
}

@media (max-width: 480px) {
  .profile-header--compact {
    height: 155px;
    min-height: 155px;

    padding: 13px 14px 15px;
  }

  .profile-header--compact
  .profile-header__eyebrow {
    font-size: 8px;
  }

  .profile-header__compact-content {
    height: 96px;
    gap: 9px;
  }

  .profile-header__compact-avatar-wrap {
    width: 52px;
    height: 52px;
  }

  .profile-header__avatar-frame--compact {
    width: 49px;
    height: 49px;
  }

  .profile-header__compact-name {
    font-size: 18px;
    letter-spacing: -0.5px;
  }

  .profile-header__compact-tier-icon {
    width: 23px;
    height: 23px;
    font-size: 7px;
  }

  .profile-header__compact-tier-name {
    font-size: 7px;
  }

  .profile-header__compact-tier-subtitle {
    font-size: 5px;
  }

  .profile-header__compact-meta {
    gap: 3px;
  }

  .profile-header__compact-status {
    max-width: 80px;
    font-size: 6px;
  }

  .profile-header__compact-chip {
    padding: 2px 4px;
    font-size: 5px;
  }

  .profile-header__compact-progress {
    width: 3px;
    height: 52px;
  }

  .profile-header__compact-progress span {
    display: none;
  }

  .profile-header--compact
  .profile-header__edit {
    min-height: 28px;
    padding: 0 8px;
    font-size: 9px;
  }

  .profile-header--compact
  .profile-header__edit svg {
    display: none;
  }

  .profile-header__compact-name-row {
    gap: 5px;
  }

  .profile-header__compact-name-row
  .profile-header__role {
    min-height: 18px;
    padding: 0 5px;
    font-size: 5px;
  }
}
</style>

