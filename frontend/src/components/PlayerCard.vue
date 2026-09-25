<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import UserName from '@/components/UserName.vue'
import TierHistoryChart from '@/components/TierHistoryChart.vue'
import { api } from '@/services/api.js'
import { AVATAR_FRAMES, PROFILE_EFFECTS } from '@/data/profileCustomization'

const props = defineProps({
  user: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})

const emit = defineEmits(['edit'])

// === TIER ===
const tierColors = {
  S: '#facc15', A: '#f97316', B: '#8b5cf6',
  C: '#06b6d4', D: '#22c55e', E: '#6b7280',
}

const tierColor = computed(() => tierColors[props.user.tier] || '#6b7280')

// === АСПЕКТЫ ===
const ASPECT_LABELS = {
  pvp: {
    block_placing: 'БП',
    rotka: 'Ротка',
    movement: 'Мувмент',
    aim: 'Аим',
    game_sense: 'Понимание боя',
  },
  bedwars: {
    pvp: 'PvP',
    game_sense: 'Понимание игры',
    bed_play: 'Игра на кровати',
    teamplay: 'Командная игра',
    building: 'Строительство',
  },
}

const ASPECT_KEYS = {
  pvp: ['block_placing', 'rotka', 'movement', 'aim', 'game_sense'],
  bedwars: ['pvp', 'game_sense', 'bed_play', 'teamplay', 'building'],
}

const EMPTY_PVP = {
  block_placing: 0, rotka: 0, movement: 0, aim: 0, game_sense: 0,
}
const EMPTY_BEDWARS = {
  pvp: 0, game_sense: 0, bed_play: 0, teamplay: 0, building: 0,
}

const allAspects = computed(() => {
  const ua = props.user.aspects ?? {}

  const pvp = ua.pvp ?? EMPTY_PVP
  const bw = ua.bedwars ?? EMPTY_BEDWARS

  const pvpSum = (pvp.block_placing ?? 0) + (pvp.rotka ?? 0)
      + (pvp.movement ?? 0) + (pvp.aim ?? 0) + (pvp.game_sense ?? 0)

  const bwSum = (bw.pvp ?? 0) + (bw.game_sense ?? 0)
      + (bw.bed_play ?? 0) + (bw.teamplay ?? 0) + (bw.building ?? 0)

  return [
    {
      mode: 'pvp',
      ...pvp,
      percent: pvpSum * 2,
      hasData: !!ua.pvp,
    },
    {
      mode: 'bedwars',
      ...bw,
      percent: bwSum * 2,
      hasData: !!ua.bedwars,
    },
  ]
})

function totalScore(aspect) {
  if (aspect.mode === 'bedwars') {
    return (aspect.pvp ?? 0) + (aspect.game_sense ?? 0)
        + (aspect.bed_play ?? 0) + (aspect.teamplay ?? 0) + (aspect.building ?? 0)
  }
  return (aspect.block_placing ?? 0) + (aspect.rotka ?? 0)
      + (aspect.movement ?? 0) + (aspect.aim ?? 0) + (aspect.game_sense ?? 0)
}

function aspectColor(value) {
  if (value >= 8) return 'linear-gradient(90deg, #22c55e, #4ade80)'
  if (value >= 6) return 'linear-gradient(90deg, #7c3aed, #a78bfa)'
  if (value >= 4) return 'linear-gradient(90deg, #f59e0b, #fbbf24)'
  if (value >= 1) return 'linear-gradient(90deg, #ef4444, #f87171)'
  return 'rgba(255, 255, 255, 0.06)'
}

function percentColor(percent) {
  if (percent >= 90) return '#facc15'
  if (percent >= 80) return '#f97316'
  if (percent >= 70) return '#8b5cf6'
  if (percent >= 60) return '#06b6d4'
  if (percent >= 50) return '#22c55e'
  return '#6b7280'
}

// === ФРЕЙМ И ЭФФЕКТ ===
const frame = computed(() =>
    AVATAR_FRAMES.find(f => f.id === props.user.avatar_frame) ?? AVATAR_FRAMES[0]
)

const hasFrame = computed(() => frame.value.id !== 'default')

const frameStyle = computed(() => {
  if (!frame.value) return {}
  if (frame.value.gradient) return { background: frame.value.gradient }
  return { background: frame.value.color }
})

const effectClass = computed(() => {
  if (!props.user.profile_effect) return ''
  return `effect-${props.user.profile_effect}`
})

const accent = computed(() =>
    props.user.accent_color || props.user.banner_color || tierColor.value
)

// === ФОН ===
const headerStyle = computed(() => {
  const url = props.user.cover_url
  return {
    ...(url ? {
      backgroundImage: `linear-gradient(rgba(10,10,15,0.45), rgba(10,10,15,0.85)), url(${url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    } : {}),
    '--accent-color': accent.value,
  }
})

const cardStyle = computed(() => {
  const url = props.user.card_background_url
  return {
    ...(url ? {
      backgroundImage: `url(${url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    } : {}),
    '--accent-color': accent.value,
  }
})

// === VERIFIED ===
const isVerified = computed(() => props.user.is_verified ?? false)

// === ДНИ ===
const daysOnPlatform = computed(() => props.user.days_on_platform ?? 0)

function pluralDays(n) {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return 'день'
  if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'дня'
  return 'дней'
}

// === ДОСТИЖЕНИЯ ===
const allAchievements = computed(() => {
  return props.user.all_achievements ?? props.user.achievements ?? []
})

const showcaseRows = computed(() => {
  const list = allAchievements.value
  const rows = []
  for (let i = 0; i < list.length; i += 4) {
    rows.push(list.slice(i, i + 4))
  }
  return rows
})

// === ДРУЗЬЯ ===
const allFriends = computed(() => {
  return props.user.friends_all ?? props.user.friends ?? []
})

const hoveredFriend = ref(null)
const hoverPosition = ref({ x: 0, y: 0 })

function onFriendEnter(friend, event) {
  hoveredFriend.value = friend
  updateHoverPosition(event)
}

function updateHoverPosition(event) {
  const rect = event.currentTarget.getBoundingClientRect()
  const cardWidth = 320
  const cardHeight = 200
  const gap = 12

  let x = rect.left - cardWidth - gap
  let y = rect.top + rect.height / 2 - cardHeight / 2

  if (x < gap) {
    x = rect.right + gap
  }

  const maxY = window.innerHeight - cardHeight - gap
  if (y < gap) y = gap
  if (y > maxY) y = maxY

  hoverPosition.value = { x, y }
}

function onFriendLeave() {
  hoveredFriend.value = null
}

// === МОДАЛКА ДОСТИЖЕНИЯ ===
const openedAchievement = ref(null)
const showAchievement = ref(false)

function openAchievement(a) {
  openedAchievement.value = a
  showAchievement.value = true
}

function closeAchievement() {
  showAchievement.value = false
  openedAchievement.value = null
}

const rarityColors = {
  common: '#7c3aed',
  rare: '#06b6d4',
  epic: '#f97316',
  legendary: '#facc15',
}

function achievementColor(a) {
  return a?.color || rarityColors[a?.rarity] || '#7c3aed'
}

const rarityLabels = {
  common: 'Обычное',
  rare: 'Редкое',
  epic: 'Эпическое',
  legendary: 'Легендарное',
}

function formatEarnedAt(a) {
  const raw = a?.pivot?.earned_at ?? a?.earned_at
  if (!raw) return null
  try {
    return new Date(raw).toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return null
  }
}

// === КЛАН ===
const clanJoinedAt = computed(() => props.user.clan_joined_at)

function formatDate(date) {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

// === РЕЖИМЫ ===
const MODE_LABELS = {
  bedwars: 'BedWars', skywars: 'SkyWars', duels: 'Duels',
  pvp: 'PvP', survival: 'Survival', other: 'Other',
}

const MODE_COLORS = {
  bedwars: '#8b5cf6', skywars: '#06b6d4', duels: '#f97316',
  pvp: '#ef4444', survival: '#22c55e', other: '#6b7280',
}

const modes = computed(() => props.user.favorite_modes ?? [])

// === СОЦСЕТИ ===
const hasSocials = computed(() => {
  return props.user.socials && Object.values(props.user.socials).some(v => v)
})

const socialLabels = {
  discord: 'Discord', telegram: 'Telegram',
  youtube: 'YouTube', vk: 'VK', website: 'Сайт',
}

// === ГРАФИК ===
const history = ref([])

async function loadHistory(userId) {
  if (!userId) {
    history.value = []
    return
  }

  try {
    const data = await api.get(`/players/${userId}/tier-history`)
    history.value = data.history ?? []
  } catch {
    history.value = []
  }
}

onMounted(() => {
  loadHistory(props.user.id)
})

// Следим за сменой пользователя — при переходе между профилями всё перезагружается
watch(() => props.user.id, (newId) => {
  // сбрасываем модалки и ховер
  closeAchievement()
  onFriendLeave()

  // перезагружаем историю тира
  loadHistory(newId)
})
</script>

<template>
  <div class="profile-layout">
    <!-- ====== ОСНОВНАЯ КАРТОЧКА ====== -->
    <div class="player-card" :style="cardStyle">
      <header
          class="player-card__header"
          :class="effectClass"
          :style="headerStyle"
      >
        <div class="player-card__left">
          <div class="avatar-wrap" :class="{ 'avatar-wrap--framed': hasFrame }">
            <div v-if="hasFrame" class="avatar-ring" :style="frameStyle" />

            <div class="player-card__avatar" :style="{ background: accent }">
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
          </div>

          <div class="player-card__info">
            <h2 class="player-card__name">
              <UserName :user="user" />

              <span
                  v-if="isVerified"
                  class="verified"
                  :title="user.verified_reason || 'Подтверждённый аккаунт'"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" fill="#1da1f2" />
                  <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                </svg>
              </span>
            </h2>

            <p v-if="user.status" class="status">{{ user.status }}</p>
            <p v-else-if="user.bio" class="bio">{{ user.bio }}</p>

            <p v-if="user.quote" class="quote">"{{ user.quote }}"</p>

            <div v-if="modes.length" class="modes">
              <span
                  v-for="m in modes"
                  :key="m"
                  class="mode-badge"
                  :style="{ '--color': MODE_COLORS[m] || '#7c3aed' }"
              >
                {{ MODE_LABELS[m] ?? m }}
              </span>
            </div>

            <div class="meta-row">
              <span v-if="daysOnPlatform" class="meta-pill">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M12 6v6l4 2" stroke-linecap="round" />
                </svg>
                С нами {{ daysOnPlatform }} {{ pluralDays(daysOnPlatform) }}
              </span>

              <span v-if="clanJoinedAt && user.clan_member?.clan" class="meta-pill">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
                </svg>
                В [{{ user.clan_member.clan.tag }}] с {{ formatDate(clanJoinedAt) }}
              </span>

              <span v-if="user.discord_tag" class="meta-pill meta-pill--discord">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="#5865f2">
                  <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
                </svg>
                {{ user.discord_tag }}
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round" />
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

      <div v-if="history.length" class="chart-section">
        <div class="chart-section__head">
          <h3>Прогресс тира</h3>
          <span class="chart-section__count">{{ history.length }} тестов</span>
        </div>
        <TierHistoryChart :history="history" />
      </div>

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
                'aspect-card--empty': !aspect.hasData,
                [`aspect-card--${aspect.mode}`]: true,
              }"
          >
            <header class="aspect-card__head">
              <div class="aspect-card__head-left">
                <span class="aspect-card__mode">
                  {{ aspect.mode === 'pvp' ? 'PvP' : 'BedWars' }}
                </span>
                <span class="aspect-card__sub">
                  {{ aspect.mode === 'pvp' ? 'p-ранг' : 'b-ранг' }}
                </span>
                <span v-if="!aspect.hasData" class="badge-empty">
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

            <div class="aspect-card__grid">
              <div
                  v-for="key in ASPECT_KEYS[aspect.mode]"
                  :key="key"
                  class="aspect"
              >
                <div class="aspect__top">
                  <span class="aspect__label">{{ ASPECT_LABELS[aspect.mode][key] }}</span>
                  <span
                      class="aspect__value"
                      :class="{ 'aspect__value--zero': !aspect[key] }"
                  >
                    {{ aspect[key] ?? 0 }}
                  </span>
                </div>

                <div class="aspect__bar">
                  <div
                      class="aspect__fill"
                      :style="{
                        width: ((aspect[key] ?? 0) / 10 * 100) + '%',
                        background: aspectColor(aspect[key] ?? 0),
                      }"
                  />
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>

    <!-- ====== ПРАВАЯ КОЛОНКА ====== -->
    <div class="side-column">
      <aside class="showcase" v-if="allAchievements.length">
        <header class="showcase__head">
          <h3 class="showcase__title">Достижения</h3>
          <span class="showcase__count">{{ allAchievements.length }}</span>
        </header>

        <div class="showcase__rows">
          <div
              v-for="(row, ri) in showcaseRows"
              :key="ri"
              class="showcase__row"
          >
            <button
                v-for="a in row"
                :key="a.id"
                type="button"
                class="showcase__cell"
                :style="{ '--color': achievementColor(a) }"
                @click="openAchievement(a)"
            >
              <div class="showcase__icon">{{ a.icon }}</div>
              <div class="showcase__meta">
                <span class="showcase__name">{{ a.name }}</span>
              </div>
            </button>

            <div
                v-for="n in (4 - row.length)"
                :key="'empty-' + ri + '-' + n"
                class="showcase__cell showcase__cell--empty"
            >
              <div class="showcase__icon showcase__icon--empty">?</div>
            </div>
          </div>
        </div>
      </aside>

      <aside class="friends" v-if="allFriends.length">
        <header class="friends__head">
          <h3 class="friends__title">Друзья</h3>
          <span class="friends__count">{{ allFriends.length }}</span>
        </header>

        <div class="friends__grid">
          <RouterLink
              v-for="f in allFriends"
              :key="f.id"
              :to="`/players/${f.id}`"
              class="friend-tile"
              @mouseenter="onFriendEnter(f, $event)"
              @mouseleave="onFriendLeave"
          >
            <img
                v-if="f.avatar_url"
                :src="f.avatar_url"
                :alt="f.username"
                class="friend-tile__img"
            />
            <span v-else class="friend-tile__letter">
              {{ (f.username || 'И').charAt(0).toUpperCase() }}
            </span>

            <span
                class="friend-tile__tier"
                :style="{ background: tierColors[f.tier] || '#6b7280' }"
            />
          </RouterLink>
        </div>
      </aside>
    </div>

    <!-- ====== ВСПЛЫВАЮЩАЯ КАРТОЧКА ДРУГА ====== -->
    <Teleport to="body">
      <Transition name="friend-hover">
        <div
            v-if="hoveredFriend"
            class="friend-hover"
            :style="{
              left: hoverPosition.x + 'px',
              top: hoverPosition.y + 'px',
            }"
        >
          <div
              class="friend-hover__card"
              :style="{
                '--accent-color': hoveredFriend.accent_color
                    || hoveredFriend.banner_color
                    || tierColors[hoveredFriend.tier]
                    || '#7c3aed',
              }"
          >
            <!-- Обложка -->
            <div
                class="friend-hover__cover"
                :style="hoveredFriend.cover_url ? {
                  backgroundImage: `linear-gradient(rgba(10,10,15,0.45), rgba(10,10,15,0.85)), url(${hoveredFriend.cover_url})`
                } : {}"
            >
              <div class="friend-hover__header">
                <div class="friend-hover__avatar">
                  <img
                      v-if="hoveredFriend.avatar_url"
                      :src="hoveredFriend.avatar_url"
                      :alt="hoveredFriend.username"
                  />
                  <template v-else>
                    {{ (hoveredFriend.username || 'И').charAt(0).toUpperCase() }}
                  </template>
                </div>

                <div class="friend-hover__body">
                  <div class="friend-hover__name">
                    <UserName :user="hoveredFriend" />

                    <span
                        v-if="hoveredFriend.is_verified"
                        class="verified"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" fill="#1da1f2" />
                        <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                      </svg>
                    </span>
                  </div>

                  <p v-if="hoveredFriend.status" class="friend-hover__status">
                    {{ hoveredFriend.status }}
                  </p>
                  <p v-else-if="hoveredFriend.bio" class="friend-hover__bio">
                    {{ hoveredFriend.bio }}
                  </p>

                  <p v-if="hoveredFriend.quote" class="friend-hover__quote">
                    "{{ hoveredFriend.quote }}"
                  </p>
                </div>

                <div
                    class="friend-hover__tier"
                    :style="{
                      borderColor: tierColors[hoveredFriend.tier] || '#6b7280',
                      color: tierColors[hoveredFriend.tier] || '#6b7280',
                      boxShadow: `0 0 16px ${(tierColors[hoveredFriend.tier] || '#6b7280')}40`,
                    }"
                >
                  {{ hoveredFriend.tier ?? '—' }}
                </div>
              </div>
            </div>

            <!-- Нижняя часть: режимы + мета -->
            <div class="friend-hover__footer">
              <div
                  v-if="Array.isArray(hoveredFriend.favorite_modes) && hoveredFriend.favorite_modes.length"
                  class="friend-hover__modes"
              >
                <span
                    v-for="m in hoveredFriend.favorite_modes"
                    :key="m"
                    class="mode-badge"
                    :style="{ '--color': MODE_COLORS[m] || '#7c3aed' }"
                >
                  {{ MODE_LABELS[m] ?? m }}
                </span>
              </div>

              <div class="friend-hover__meta">
                <span v-if="hoveredFriend.days_on_platform" class="meta-pill">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 6v6l4 2" stroke-linecap="round" />
                  </svg>
                  С нами {{ hoveredFriend.days_on_platform }} {{ pluralDays(hoveredFriend.days_on_platform) }}
                </span>

                <span
                    v-if="hoveredFriend.clan_joined_at && hoveredFriend.clan_member?.clan"
                    class="meta-pill"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
                  </svg>
                  В [{{ hoveredFriend.clan_member.clan.tag }}] с {{ formatDate(hoveredFriend.clan_joined_at) }}
                </span>

                <span v-else-if="hoveredFriend.clan_member?.clan" class="meta-pill">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
                  </svg>
                  [{{ hoveredFriend.clan_member.clan.tag }}] {{ hoveredFriend.clan_member.clan.name }}
                </span>

                <span
                    v-if="hoveredFriend.discord_tag"
                    class="meta-pill meta-pill--discord"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="#5865f2">
                    <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
                  </svg>
                  {{ hoveredFriend.discord_tag }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ====== МОДАЛКА ДОСТИЖЕНИЯ ====== -->
    <Teleport to="body">
      <div
          v-if="showAchievement && openedAchievement"
          class="ach-modal-bg"
          @click.self="closeAchievement"
      >
        <div
            class="ach-modal"
            :style="{ '--color': achievementColor(openedAchievement) }"
        >
          <button
              class="ach-modal__close"
              type="button"
              aria-label="Закрыть"
              @click="closeAchievement"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>

          <div class="ach-modal__glow" />

          <div class="ach-modal__icon">
            {{ openedAchievement.icon }}
          </div>

          <h3 class="ach-modal__name">
            {{ openedAchievement.name }}
          </h3>

          <div class="ach-modal__meta">
            <span
                v-if="openedAchievement.rarity"
                class="ach-chip ach-chip--rarity"
            >
              {{ rarityLabels[openedAchievement.rarity] ?? openedAchievement.rarity }}
            </span>

            <span
                v-if="openedAchievement.points"
                class="ach-chip ach-chip--points"
            >
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
              </svg>
              {{ openedAchievement.points }} очков
            </span>
          </div>

          <p v-if="openedAchievement.description" class="ach-modal__desc">
            {{ openedAchievement.description }}
          </p>

          <div
              v-if="formatEarnedAt(openedAchievement)"
              class="ach-modal__date"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path d="M16 2v4M8 2v4M3 10h18" />
            </svg>
            Получено {{ formatEarnedAt(openedAchievement) }}
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* ============================================
   LAYOUT
   ============================================ */

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 20px;
  align-items: start;
}

.side-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 20px;
}

/* ============================================
   PLAYER CARD
   ============================================ */

.player-card {
  position: relative;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  overflow: hidden;
  background-size: cover;
  background-position: center;
}

.player-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(13, 13, 20, 0.82);
  z-index: 0;
  pointer-events: none;
}

.player-card > * {
  position: relative;
  z-index: 1;
}

/* === HEADER === */

.player-card__header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
  padding: 20px;
  background: #0d0d14;
  background-size: cover;
  background-position: center;
  border-radius: 12px;
  overflow: hidden;
  min-height: 110px;
}

.player-card__left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
  flex: 1;
}

/* === AVATAR === */

.avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.avatar-wrap--framed {
  padding: 3px;
  border-radius: 16px;
}

.avatar-ring {
  position: absolute;
  inset: 0;
  border-radius: 16px;
  z-index: 0;
  pointer-events: none;
}

.avatar-wrap--framed .player-card__avatar {
  position: relative;
  z-index: 1;
  border: 2px solid var(--bg-card);
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

/* === INFO === */

.player-card__info {
  flex: 1;
  min-width: 0;
}

.player-card__name {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.verified {
  display: inline-flex;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(29, 161, 242, 0.6));
}

.status {
  margin: 0;
  color: var(--accent-color);
  font-size: 13px;
  font-weight: 700;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.bio {
  margin: 0;
  color: #d1d1db;
  font-size: 13px;
  line-height: 1.5;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

.quote {
  margin: 4px 0 0;
  color: #b8b8c7;
  font-size: 12px;
  font-style: italic;
  line-height: 1.4;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

/* === MODES === */

.modes {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 8px;
}

.mode-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color) 35%, transparent);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* === META === */

.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  color: #d1d1db;
  backdrop-filter: blur(6px);
}

.meta-pill--discord {
  color: #8895f5;
  border-color: rgba(88, 101, 242, 0.3);
}

/* === RIGHT === */

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

/* === EFFECTS === */

.player-card__header.effect-glow {
  box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 20%, transparent);
}

.player-card__header.effect-pulse {
  animation: profilePulse 3s infinite;
}

@keyframes profilePulse {
  0%, 100% { box-shadow: inset 0 0 40px color-mix(in srgb, var(--accent-color) 15%, transparent); }
  50% { box-shadow: inset 0 0 60px color-mix(in srgb, var(--accent-color) 35%, transparent); }
}

.player-card__header.effect-gradient::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg,
  color-mix(in srgb, var(--accent-color) 15%, transparent) 0%,
  transparent 40%,
  color-mix(in srgb, var(--accent-color) 15%, transparent) 100%);
  pointer-events: none;
}

.player-card__header.effect-fire::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 100% 0%, rgba(239, 68, 68, 0.3), transparent 50%);
  pointer-events: none;
  animation: fireFlicker 2s infinite;
}

@keyframes fireFlicker {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}

.player-card__header.effect-ice::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 100%, rgba(6, 182, 212, 0.3), transparent 50%);
  pointer-events: none;
}

.player-card__header.effect-legendary {
  border: 1px solid rgba(250, 204, 21, 0.4);
}

.player-card__header.effect-legendary::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(250, 204, 21, 0.15), transparent 40%, rgba(249, 115, 22, 0.15));
  pointer-events: none;
  animation: legendaryShift 4s infinite;
  background-size: 200% 200%;
}

@keyframes legendaryShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
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

.social-link.discord:hover { background: #5865f2; border-color: #5865f2; }
.social-link.telegram:hover { background: #229ed9; border-color: #229ed9; }
.social-link.youtube:hover { background: #ff0000; border-color: #ff0000; }
.social-link.vk:hover { background: #0077ff; border-color: #0077ff; }
.social-link.website:hover { background: var(--accent); border-color: var(--accent); }

/* === CHART === */

.chart-section {
  margin-bottom: 20px;
}

.chart-section__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 12px;
  padding: 0 2px;
}

.chart-section__head h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.chart-section__count {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

/* === АСПЕКТЫ === */

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

.aspect-card {
  position: relative;
  padding: 16px 18px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.2s ease;
}

.aspect-card:hover {
  border-color: var(--border-hover);
}

.aspect-card--empty {
  opacity: 0.75;
}

.aspect-card--pvp {
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.04), transparent 40%), #0d0d14;
}

.aspect-card--bedwars {
  background: linear-gradient(180deg, rgba(6, 182, 212, 0.04), transparent 40%), #0d0d14;
}

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
}

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
}

.aspect__value {
  color: var(--text);
  font-size: 12px;
  font-weight: 800;
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

/* ============================================
   SHOWCASE
   ============================================ */

.showcase {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.showcase__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.showcase__title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: #c7d5e0;
  text-transform: uppercase;
  letter-spacing: 1.2px;
}

.showcase__count {
  font-size: 11px;
  color: #4a5568;
  font-weight: 700;
}

.showcase__rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.showcase__row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.showcase__cell {
  position: relative;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  font: inherit;
  color: inherit;
  border-radius: 6px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
  border: 1px solid rgba(255, 255, 255, 0.06);
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
}

.showcase__cell:hover {
  transform: translateY(-2px);
  border-color: var(--color);
  box-shadow:
      0 6px 18px color-mix(in srgb, var(--color) 35%, transparent),
      inset 0 0 0 1px color-mix(in srgb, var(--color) 60%, transparent);
}

.showcase__cell::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 50%;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent);
  pointer-events: none;
}

.showcase__cell::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at center, color-mix(in srgb, var(--color) 30%, transparent), transparent 70%);
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}

.showcase__cell:hover::after { opacity: 1; }

.showcase__icon {
  font-size: 28px;
  line-height: 1;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.6));
  position: relative;
  z-index: 1;
}

.showcase__cell--empty {
  opacity: 0.35;
  cursor: default;
}

.showcase__cell--empty:hover {
  transform: none;
  border-color: rgba(255, 255, 255, 0.06);
  box-shadow: none;
}

.showcase__cell--empty::after { display: none; }

.showcase__icon--empty {
  font-size: 16px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.15);
}

.showcase__meta {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 6px 8px;
  background: linear-gradient(0deg, rgba(0, 0, 0, 0.9), transparent);
  font-size: 10px;
  color: #c7d5e0;
  transform: translateY(100%);
  transition: transform 0.2s ease;
  z-index: 2;
  pointer-events: none;
}

.showcase__cell:hover .showcase__meta { transform: translateY(0); }

.showcase__name {
  display: block;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ============================================
   FRIENDS
   ============================================ */

.friends {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.friends__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.friends__title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: #c7d5e0;
  text-transform: uppercase;
  letter-spacing: 1.2px;
}

.friends__count {
  font-size: 11px;
  color: #4a5568;
  font-weight: 700;
}

.friends__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.friend-tile {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: 6px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  text-decoration: none;
  color: #fff;
  font-size: 16px;
  font-weight: 900;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.friend-tile:hover {
  transform: translateY(-2px) scale(1.06);
  box-shadow: 0 8px 20px rgba(124, 58, 237, 0.5);
  z-index: 5;
}

.friend-tile__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
  display: block;
}

.friend-tile__letter {
  position: relative;
}

.friend-tile__tier {
  position: absolute;
  right: -3px;
  bottom: -3px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #171a21;
  z-index: 2;
}

/* ============================================
   FRIEND HOVER — точная копия player-card
   ============================================ */

.friend-hover {
  position: fixed;
  width: 380px;
  z-index: 3000;
  pointer-events: none;
}

.friend-hover__card {
  position: relative;
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #23232e);
  border-radius: 14px;
  overflow: hidden;
  box-shadow:
      0 24px 60px -12px rgba(0, 0, 0, 0.7),
      0 0 0 1px rgba(139, 92, 246, 0.2) inset;
}

.friend-hover__cover {
  position: relative;
  padding: 16px;
  background: #0d0d14;
  background-size: cover;
  background-position: center;
  border-bottom: 1px solid var(--border, #23232e);
}

.friend-hover__header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.friend-hover__avatar {
  position: relative;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 12px;
  background: var(--accent-color, #7c3aed);
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}

.friend-hover__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.friend-hover__body {
  flex: 1;
  min-width: 0;
}

.friend-hover__name {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 3px;
  font-size: 15px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-hover__status {
  margin: 0;
  color: var(--accent-color, #a78bfa);
  font-size: 12px;
  font-weight: 700;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-hover__bio {
  margin: 0;
  color: #d1d1db;
  font-size: 11.5px;
  line-height: 1.4;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.friend-hover__quote {
  margin: 3px 0 0;
  color: #b8b8c7;
  font-size: 11px;
  font-style: italic;
  line-height: 1.4;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.friend-hover__tier {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid;
  border-radius: 10px;
  font-size: 19px;
  font-weight: 900;
  background: rgba(10, 10, 15, 0.85);
  backdrop-filter: blur(8px);
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}

/* Нижняя часть — режимы + мета */
.friend-hover__footer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  background: rgba(13, 13, 20, 0.6);
}

.friend-hover__modes {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.friend-hover__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

/* Transition */

.friend-hover-enter-active,
.friend-hover-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.friend-hover-enter-from,
.friend-hover-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

/* ============================================
   ACHIEVEMENT MODAL
   ============================================ */

.ach-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 6, 10, 0.72);
  backdrop-filter: blur(8px);
  animation: achFade 0.15s ease;
}

@keyframes achFade {
  from { opacity: 0; }
  to { opacity: 1; }
}

.ach-modal {
  position: relative;
  width: 100%;
  max-width: 380px;
  padding: 32px 26px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid color-mix(in srgb, var(--color) 45%, var(--border));
  border-radius: 18px;
  box-shadow:
      0 30px 80px -20px rgba(0, 0, 0, 0.7),
      inset 0 0 0 1px color-mix(in srgb, var(--color) 20%, transparent);
  animation: achPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}

@keyframes achPop {
  from { opacity: 0; transform: scale(0.94) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

.ach-modal__glow {
  position: absolute;
  top: -80px;
  left: 50%;
  width: 320px;
  height: 320px;
  transform: translateX(-50%);
  background: radial-gradient(circle, color-mix(in srgb, var(--color) 40%, transparent) 0%, transparent 65%);
  pointer-events: none;
  opacity: 0.65;
  z-index: 0;
}

.ach-modal__close {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 3;
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}

.ach-modal__close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text);
}

.ach-modal__icon {
  position: relative;
  z-index: 1;
  width: 104px;
  height: 104px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 56px;
  line-height: 1;
  border-radius: 24px;
  background:
      radial-gradient(circle at 50% 30%, color-mix(in srgb, var(--color) 30%, transparent) 0%, transparent 70%),
      rgba(255, 255, 255, 0.03);
  border: 1px solid color-mix(in srgb, var(--color) 55%, transparent);
  box-shadow:
      0 12px 40px color-mix(in srgb, var(--color) 35%, transparent),
      inset 0 0 0 1px rgba(255, 255, 255, 0.04);
  filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
}

.ach-modal__name {
  position: relative;
  z-index: 1;
  margin: 6px 0 0;
  font-size: 20px;
  font-weight: 900;
  color: #f3f4f6;
  letter-spacing: -0.3px;
  text-align: center;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
}

.ach-modal__meta {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 2px;
}

.ach-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.ach-chip--rarity {
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color) 45%, transparent);
}

.ach-chip--points {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border: 1px solid rgba(250, 204, 21, 0.35);
}

.ach-modal__desc {
  position: relative;
  z-index: 1;
  margin: 6px 0 0;
  color: #b8c2cf;
  font-size: 13.5px;
  line-height: 1.6;
  text-align: center;
  max-width: 300px;
}

.ach-modal__date {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding-top: 14px;
  width: 100%;
  justify-content: center;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  color: #6b7280;
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 1000px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .side-column {
    position: static;
  }
}

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

  .player-card__name {
    font-size: 16px;
  }

  .bio,
  .status {
    font-size: 11px;
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

  .showcase {
    padding: 12px;
  }

  .showcase__icon {
    font-size: 22px;
  }

  .friends {
    padding: 12px;
  }

  .friends__grid {
    grid-template-columns: repeat(5, 1fr);
    gap: 5px;
  }

  .friend-tile {
    font-size: 14px;
  }

  .friend-tile__tier {
    width: 10px;
    height: 10px;
    right: -2px;
    bottom: -2px;
  }

  .ach-modal {
    padding: 26px 18px 18px;
    max-width: 100%;
  }

  .ach-modal__icon {
    width: 88px;
    height: 88px;
    font-size: 46px;
    border-radius: 20px;
  }

  .ach-modal__name {
    font-size: 18px;
  }
}
</style>