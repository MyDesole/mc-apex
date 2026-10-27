<script setup>
import { computed, ref } from 'vue'
import { tierColor } from '@/composables/players/useTier.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { shopApi } from '@/services/shop/shop.js'
import AppIcon from '@/components/core/AppIcon.vue'
import ProfileEffectLayer from '@/components/players/ProfileEffectLayer.vue'
import AvatarFrameDecor from '@/components/players/AvatarFrameDecor.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import '@/components/shop/badgeStyles.css'
import ProfilePet from '@/components/players/ProfilePet.vue'
import ProfileTitle from '@/components/players/ProfileTitle.vue'
import RespectBadge from '@/components/players/profile/RespectBadge.vue'
import { nameIcon } from '@/data/players/flair.js'
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
   * Шапка, разобранная на блоки: показать только одну часть —
   * avatar | name | badges | about | tier. Декор, питомцы и кнопки
   * остаются у полной шапки.
   */
  part: {
    type: String,
    default: null,
  },

  // Части стоят в сетке блоками — здесь остаётся лента: обложка, питомцы, кнопки
  slim: {
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

const emit = defineEmits(['edit', 'build'])

/*
 * «Рандом» на своём профиле: сервер надевает случайные предметы из
 * инвентаря, профиль берёт свежие данные из стора после fetchMe().
 */
const auth = useAuthStore()

// Значок у ника из магазина
const nameIconData = computed(() => nameIcon(props.user?.name_icon))

const randomizing = ref(false)
const randomMessage = ref('')

async function randomize() {
  if (randomizing.value) return

  randomizing.value = true
  randomMessage.value = ''

  try {
    const result = await shopApi.randomize()
    randomMessage.value = result.message
    await auth.fetchMe()
  } catch (e) {
    randomMessage.value = e.message || 'Не удалось перемешать образ.'
  } finally {
    randomizing.value = false
    // Подсказка висит пару секунд и пропадает
    setTimeout(() => { randomMessage.value = '' }, 3500)
  }
}

/* ---------- Звание бриджера: сводка для эмблемы в шапке ---------- */

// Серое звание («Новичок») на тёмном фоне почти не видно — осветляем
const bridgeColor = computed(() => props.bridgeRank?.color || '#8b5cf6')

const bridgeStats = computed(() => {
  const rows = props.bridgeTechniques ?? []

  return {
    count: rows.length,
    best: rows.reduce((max, row) => Math.max(max, Number(row.score) || 0), 0),
  }
})

function pluralTechniques(n) {
  const mod10 = n % 10
  const mod100 = n % 100

  if (mod10 === 1 && mod100 !== 11) return 'вид'
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'вида'
  return 'видов'
}

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

/*
 * Ник до 32 символов без пробелов: на узком экране длинный ник
 * уменьшаем, чтобы он занимал две строки, а не четыре.
 */
const nameLengthClass = computed(() => {
  const length = displayNameWithClan.value.length
  if (length > 26) return 'is-xlong'
  if (length > 18) return 'is-long'
  return null
})

/*
 * Питомцы на профиле: список до трёх (каждый — свой экземпляр с
 * чертами и именем), для старых профилей — один profile_pet.
 * Черты из профиля — просто id; превращаем в вид { model: { id } }.
 */
const walkingPets = computed(() => {
  const toTraits = (raw) => raw
      ? Object.fromEntries(['model', 'backdrop', 'accessory'].map((k) => [k, { id: raw[k] }]))
      : null

  if (Array.isArray(props.user.profile_pets) && props.user.profile_pets.length) {
    return props.user.profile_pets
        .filter((p) => p?.pet)
        .slice(0, 3)
        .map((p) => ({ ...p, traits: toTraits(p.traits) }))
  }

  return props.user.profile_pet
      ? [{ pet: props.user.profile_pet, inventory_id: null, traits: toTraits(props.user.pet_traits), name: null }]
      : []
})

// «33 дня», «21 день», «11 дней»
const daysWord = computed(() => {
  const n = Math.abs(Number(props.user.days_on_platform) || 0)
  const last = n % 10
  const lastTwo = n % 100

  if (last === 1 && lastTwo !== 11) return 'день'
  if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 'дня'
  return 'дней'
})

const tierSubtitle = computed(() => {
  if (props.bridgeMode) {
    return props.bridgeRank ? 'Звание бриджера' : 'Звание не выдано'
  }

  if (tier.value === 'UNRANKED') {
    return 'Тир ещё не получен'
  }

  return props.user.tier_title ||
      props.user.tier_description ||
      'Текущий тир'
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
// Пустая часть шапки в блоке: подпись вместо пустой коробки
const partEmpty = computed(() => {
  if (props.part === 'badges' && !playerBadges.value.length) return 'Бейджей пока нет'
  if (props.part === 'about' && !playerStatus.value && !props.user.bio && !playerQuote.value) {
    return 'Статуса, описания и цитаты пока нет'
  }
  return ''
})
</script>

<template>
  <section
      class="profile-header"
      :class="{
        'profile-header--compact': compact && !part,
        'profile-header--part': !!part,
        'profile-header--slim': slim && !part,
        // Питомцы гуляют по низу шапки: на телефоне им нужна своя полоса
        'profile-header--pets': !part && !compact && walkingPets.length > 0,
        [`profile-header--part-${part}`]: !!part,

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

    <!-- Питомец живёт в полной шапке, в компактной ему тесно -->
    <!-- Питомцы (до трёх): гуляют по шапке, по клику — карточка с действиями -->
    <ProfilePet
        v-for="(pet, i) in (part ? [] : walkingPets)"
        :key="`pet-${user.id}-${pet.inventory_id ?? pet.pet}`"
        :pet="pet.pet"
        :owner="user"
        :editable="editable"
        :instance-id="pet.inventory_id"
        :traits="pet.traits"
        :name="pet.name"
        :lane="i"
        :size="compact ? 30 : 38"
        interactive
    />

    <!-- Эффекты-слои (снег, искры, сияние…) — свой компонент с частицами -->
    <ProfileEffectLayer
        v-if="hasProfileEffect && profileEffect.layer && !part"
        :effect="effectId"
        :density="compact ? 0.5 : 1"
    />

    <div
        v-else-if="hasProfileEffect"
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
        v-if="!compact && !part"
        class="profile-header__tier-watermark"
        aria-hidden="true"
    >
      {{ tierShort }}
    </div>

    <!-- =====================================================
         TOP BAR
         ===================================================== -->

    <div v-if="!part" class="profile-header__topline">

      <!-- Респекты: гость даёт раз в день, хозяин видит счёт и гостей -->
      <RespectBadge v-if="user.id" :key="`respect-${user.id}`" :user="user" :editable="editable" />

      <!-- Рандом: случайный образ из своих предметов -->
      <button
          v-if="editable"
          class="profile-header__edit profile-header__random"
          :class="{ 'is-rolling': randomizing }"
          type="button"
          :disabled="randomizing"
          :title="randomMessage || 'Надеть случайные рамку, эффект, фон, макет и бейджи из своих'"
          @click="randomize"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="3" width="18" height="18" rx="4" />
          <circle cx="8.5" cy="8.5" r="1.2" fill="currentColor" />
          <circle cx="15.5" cy="15.5" r="1.2" fill="currentColor" />
          <circle cx="15.5" cy="8.5" r="1.2" fill="currentColor" />
          <circle cx="8.5" cy="15.5" r="1.2" fill="currentColor" />
          <circle cx="12" cy="12" r="1.2" fill="currentColor" />
        </svg>
        Рандом
      </button>

      <!-- Студия: всё оформление и настройки профиля в одном месте -->
      <button
          v-if="editable"
          class="profile-header__edit profile-header__build"
          type="button"
          title="Студия профиля: блоки, шапка, тема, данные профиля"
          @click="emit('build')"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="3" width="7" height="9" rx="1.5" />
          <rect x="14" y="3" width="7" height="5" rx="1.5" />
          <rect x="14" y="12" width="7" height="9" rx="1.5" />
          <rect x="3" y="16" width="7" height="5" rx="1.5" />
        </svg>
        Студия
      </button>

      <Transition name="random-note">
        <span v-if="randomMessage" class="profile-header__random-note">{{ randomMessage }}</span>
      </Transition>

      <!-- Действия гостя: дружба, жалоба (передаёт страница игрока) -->
      <div v-if="!editable && $slots.actions" class="profile-header__actions">
        <slot name="actions" />
      </div>

    </div>

    <!-- =====================================================
         COMPACT CONTENT
         ===================================================== -->

    <div
        v-if="compact && !part"
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
          <AvatarFrameDecor :frame="user.avatar_frame" :size="70" />

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

        <div class="profile-header__compact-name-row">

          <h1 class="profile-header__compact-name" :class="nameLengthClass">
            <!-- Эффект ника из магазина — как в полной шапке -->
            <span v-if="user.name_effect" class="name-fx" :class="`name-fx--${user.name_effect}`">
              <template v-if="user.name_effect === 'wave'">
                <span
                    v-for="(char, i) in [...displayNameWithClan]"
                    :key="i"
                    class="name-fx__char"
                    :style="{ '--i': i }"
                >{{ char }}</span>
              </template>
              <template v-else>{{ displayNameWithClan }}</template>
            </span>
            <template v-else>{{ displayNameWithClan }}</template>
            <PixelSprite
                v-if="nameIconData"
                class="profile-header__name-icon"
                :sprite="nameIconData.sprite"
                :anim="nameIconData.anim"
                :glow="nameIconData.glow"
                :size="20"
            />
          </h1>

          <span
              v-if="user.is_verified"
              class="profile-header__verified"
              title="Подтверждённый профиль"
              aria-label="Подтверждённый профиль"
          >
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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

        <ProfileTitle v-if="user.profile_title" :title="user.profile_title" class="profile-header__compact-title" />

        <div class="profile-header__compact-meta">

          <span
              v-if="playerStatus"
              class="profile-header__compact-status"
          >
            <i />
            {{ playerStatus }}
          </span>

          <span
              v-if="rankPosition !== null"
              class="profile-header__compact-chip"
              title="Место в рейтинге"
          >
            <b>#{{ rankPosition }}</b> в топе
          </span>

          <span
              v-if="level !== null"
              class="profile-header__compact-chip"
          >
            <b>{{ level }}</b> уровень
          </span>

          <span
              v-if="user.days_on_platform !== undefined && user.days_on_platform !== null"
              class="profile-header__compact-chip"
              title="Дней на сайте"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="5" width="18" height="16" rx="3" />
              <path d="M16 3v4M8 3v4M3 11h18" />
            </svg>
            <b>{{ user.days_on_platform }}</b> {{ daysWord }} с нами
          </span>

        </div>

      </div>

      <!-- Тир: буква в кольце, кольцо — очки до следующего тира -->
      <div
          class="profile-header__compact-tier-ring"
          :class="{ 'is-empty': tier === 'UNRANKED' && !bridgeMode }"
          :style="{ '--progress': tierProgress ?? 100 }"
          :title="tierProgress !== null ? `${tierLabel} · очки тира ${Math.round(tierProgress)}%` : `${tierLabel} · ${tierSubtitle}`"
      >
        <span class="profile-header__compact-tier-letter">{{ tierShort }}</span>
        <small>{{ bridgeMode ? 'звание' : 'тир' }}</small>
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

          <AvatarFrameDecor :frame="user.avatar_frame" :size="118" />

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

          <h1 class="profile-header__name" :class="nameLengthClass">
            <!-- Эффект ника из магазина — тот же, что в UserName -->
            <span v-if="user.name_effect" class="name-fx" :class="`name-fx--${user.name_effect}`">
              <template v-if="user.name_effect === 'wave'">
                <span
                    v-for="(char, i) in [...displayNameWithClan]"
                    :key="i"
                    class="name-fx__char"
                    :style="{ '--i': i }"
                >{{ char }}</span>
              </template>
              <template v-else>{{ displayNameWithClan }}</template>
            </span>
            <template v-else>{{ displayNameWithClan }}</template>
            <PixelSprite
                v-if="nameIconData"
                class="profile-header__name-icon"
                :sprite="nameIconData.sprite"
                :anim="nameIconData.anim"
                :glow="nameIconData.glow"
                :size="26"
            />
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

        <!-- Титул под ником (магазин) -->
        <ProfileTitle v-if="user.profile_title" :title="user.profile_title" class="profile-header__title" />

        <!-- Часть шапки в блоке, а показать нечего -->
        <p v-if="partEmpty" class="profile-header__part-empty">{{ partEmpty }}</p>

        <!-- Badges (купленные в магазине) -->
        <div
            v-if="playerBadges.length"
            class="profile-header__badges"
        >
  <span
      v-for="badge in playerBadges"
      :key="badge.slug"
      class="profile-badge"
      :class="badge.style ? `badge-fx--${badge.style}` : null"
      :style="{ '--badge-color': badge.color || '#7c3aed' }"
      :title="badge.name"
  >
    <span class="profile-badge__icon" :class="{ 'profile-badge__icon--sprite': badge.sprite }">
      <!-- Новые бейджи — пиксельная картинка с анимацией -->
      <PixelSprite v-if="badge.sprite" :sprite="badge.sprite" :size="14" :anim="badge.anim" :glow="badge.color" />
      <AppIcon v-else :icon="badge.icon" :size="12" />
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
              Место
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
              Уровень
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
              Клан
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
              Дней
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
            class="bridge-hero"
            :class="{ 'bridge-hero--empty': !bridgeRank }"
            :style="{ '--rank': bridgeColor }"
        >
          <!-- Эмблема: блок в цвете звания -->
          <span class="bridge-hero__emblem" aria-hidden="true">
            <span class="bridge-hero__cube" />
          </span>

          <span class="bridge-hero__info">
            <span class="bridge-hero__eyebrow">Звание бриджера</span>

            <strong class="bridge-hero__name">
              {{ bridgeRank?.label ?? 'Звание не выдано' }}
            </strong>

            <span class="bridge-hero__meta">
              <template v-if="bridgeStats.count">
                {{ bridgeStats.count }} {{ pluralTechniques(bridgeStats.count) }} подтверждено
                <span class="bridge-hero__dot">·</span>
                лучшая оценка <b>{{ bridgeStats.best }}/10</b>
              </template>
              <template v-else>Пока нет подтверждённых видов</template>
            </span>
          </span>
        </div>
        <!-- Tier Progress -->
        <div
            v-else-if="tierProgress !== null"
            class="tier-progress"
        >

          <div class="tier-progress__head">

            <span>
              Очки тира
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
        v-if="!compact && !part"
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
