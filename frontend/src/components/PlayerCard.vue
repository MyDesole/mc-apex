<script setup>
import { computed, onMounted, ref, watch } from 'vue'

import TierHistoryChart from '@/components/TierHistoryChart.vue'
import ProfileRecommendations from '@/components/ProfileRecommendations.vue'

import { api } from '@/services/api.js'
import { tierColor } from '@/composables/useTier.js'

import PlayerProfileHeader from '@/components/player-profile/PlayerProfileHeader.vue'
import PlayerProfileSocials from '@/components/player-profile/PlayerProfileSocials.vue'
import PlayerProfileAspects from '@/components/player-profile/PlayerProfileAspects.vue'
import PlayerProfileShowcase from '@/components/player-profile/PlayerProfileShowcase.vue'
import PlayerProfileFriends from '@/components/player-profile/PlayerProfileFriends.vue'
import PlayerFriendHoverCard from '@/components/player-profile/PlayerFriendHoverCard.vue'
import AchievementModal from '@/components/player-profile/AchievementModal.vue'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },

  editable: {
    type: Boolean,
    default: false,
  },

  recommendations: {
    type: Array,
    default: () => [],
  },

  myRecommendation: {
    type: Object,
    default: null,
  },

  canRecommend: {
    type: Boolean,
    default: false,
  },

  isOwner: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'edit',
  'recommendationsUpdated',
])

/* =========================================================
   ACCENT / BACKGROUND
   ========================================================= */

const accent = computed(() =>
    props.user.accent_color ||
    props.user.banner_color ||
    tierColor(props.user.tier)
)

const cardStyle = computed(() => {
  const url = props.user.card_background_url

  return {
    ...(url
        ? {
          backgroundImage: `url(${url})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        }
        : {}),

    '--accent-color': accent.value,
  }
})

const accentStyle = computed(() => ({
  '--accent-color': accent.value,
}))

/* =========================================================
   DATA
   ========================================================= */

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

watch(
    () => props.user.id,
    (id) => {
      closeAchievement()
      hoveredFriend.value = null
      loadHistory(id)
    }
)

/* =========================================================
   ACHIEVEMENTS
   ========================================================= */

const allAchievements = computed(() =>
    props.user.all_achievements ??
    props.user.achievements ??
    []
)

const openedAchievement = ref(null)
const showAchievement = ref(false)

function openAchievement(achievement) {
  openedAchievement.value = achievement
  showAchievement.value = true
}

function closeAchievement() {
  showAchievement.value = false
  openedAchievement.value = null
}

/* =========================================================
   FRIENDS
   ========================================================= */

const allFriends = computed(() =>
    props.user.friends_all ??
    props.user.friends ??
    []
)

const hoveredFriend = ref(null)

const hoverPosition = ref({
  x: 0,
  y: 0,
})

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

  if (y < gap) {
    y = gap
  }

  if (y > maxY) {
    y = maxY
  }

  hoverPosition.value = {
    x,
    y,
  }
}

function onFriendLeave() {
  hoveredFriend.value = null
}

/* =========================================================
   PROFILE STATS
   ========================================================= */

const profileStats = computed(() => [
  {
    key: 'tier',
    value: props.user.tier || '—',
    label: 'Current tier',
    icon: '◆',
    accent: true,
  },

  {
    key: 'achievements',
    value: allAchievements.value.length,
    label: 'Achievements',
    icon: '✦',
  },

  {
    key: 'friends',
    value: allFriends.value.length,
    label: 'Friends',
    icon: '♢',
  },

  {
    key: 'recommendations',
    value: props.recommendations.length,
    label: 'Recommendations',
    icon: '↗',
  },
])

/* =========================================================
   OPTIONAL PROFILE DATA
   ========================================================= */

const hasSocials = computed(() =>
    props.user.socials &&
    Object.keys(props.user.socials).length > 0
)

const hasAspects = computed(() =>
    Array.isArray(props.user.aspects)
        ? props.user.aspects.length > 0
        : !!props.user.aspects
)

const hasBio = computed(() =>
    !!(
        props.user.bio ||
        props.user.description ||
        props.user.about
    )
)

const bioText = computed(() =>
    props.user.bio ||
    props.user.description ||
    props.user.about ||
    ''
)

const hasAnyExtraInfo = computed(() =>
    hasSocials.value ||
    hasAspects.value ||
    hasBio.value ||
    history.value.length > 0
)

/* =========================================================
   PROFILE COMPLETENESS
   ========================================================= */

const profileCompleteness = computed(() => {
  const checks = [
    !!props.user.avatar_url,
    !!props.user.cover_url,
    !!props.user.tier,
    hasBio.value,
    hasSocials.value,
    hasAspects.value,
    allAchievements.value.length > 0,
    allFriends.value.length > 0,
  ]

  const completed = checks.filter(Boolean).length

  return Math.round((completed / checks.length) * 100)
})
</script>

<template>
  <div
      class="profile-layout"
      :style="accentStyle"
  >
    <!-- =====================================================
         MAIN PROFILE CARD
         ===================================================== -->

    <main
        class="player-card"
        :style="cardStyle"
    >
      <div class="player-card__grid"></div>

      <!-- Header -->
      <PlayerProfileHeader
          :user="user"
          :editable="editable"
          :cover-url="user.cover_url"
          :accent="accent"
          @edit="emit('edit')"
      />

      <!-- ===================================================
           PROFILE SUMMARY
           =================================================== -->


      <!-- ===================================================
           STATS
           =================================================== -->

      <section class="profile-stats">
        <div
            v-for="stat in profileStats"
            :key="stat.key"
            class="profile-stat"
            :class="{ 'profile-stat--accent': stat.accent }"
        >
          <div class="profile-stat__icon">
            {{ stat.icon }}
          </div>

          <div class="profile-stat__content">
            <strong>
              {{ stat.value }}
            </strong>

            <span>
              {{ stat.label }}
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           SOCIALS
           =================================================== -->

      <section
          v-if="hasSocials"
          class="profile-section profile-section--socials"
      >
        <div class="section-heading">
          <div>
            <span class="section-heading__eyebrow">
              CONNECT
            </span>

            <h3>Socials</h3>
          </div>
        </div>

        <PlayerProfileSocials
            :socials="user.socials"
        />
      </section>

      <!-- Empty socials -->
      <section
          v-else-if="editable"
          class="profile-section profile-empty"
      >
        <div class="profile-empty__icon">
          @
        </div>

        <div class="profile-empty__content">
          <strong>Add your socials</strong>
          <span>
            Добавь ссылки на свои социальные сети, чтобы друзья могли тебя найти.
          </span>
        </div>

        <button
            class="profile-empty__action"
            type="button"
            @click="emit('edit')"
        >
          Add
        </button>
      </section>

      <!-- ===================================================
           TIER HISTORY
           =================================================== -->

      <section class="profile-section">
        <div class="section-heading">
          <div>
            <span class="section-heading__eyebrow">
              PROGRESSION
            </span>

            <h3>Tier history</h3>
          </div>

          <span
              v-if="history.length"
              class="section-heading__count"
          >
            {{ history.length }} changes
          </span>
        </div>

        <div
            v-if="history.length"
            class="chart-section"
        >
          <TierHistoryChart
              :history="history"
          />
        </div>

        <div
            v-else
            class="profile-empty profile-empty--large"
        >
          <div class="profile-empty__icon profile-empty__icon--chart">
            ↗
          </div>

          <div class="profile-empty__content">
            <strong>No tier history yet</strong>

            <span>
              Здесь появится история изменения рейтинга игрока.
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           ASPECTS
           =================================================== -->

      <section
          v-if="hasAspects"
          class="profile-section"
      >
        <div class="section-heading">
          <div>
            <span class="section-heading__eyebrow">
              CHARACTER
            </span>

            <h3>Aspects</h3>
          </div>
        </div>

        <PlayerProfileAspects
            :aspects="user.aspects"
        />
      </section>

      <!-- ===================================================
           EMPTY PROFILE INFO
           =================================================== -->

      <section
          v-else-if="!hasAnyExtraInfo"
          class="profile-empty profile-empty--profile"
      >
        <div class="profile-empty__visual">
          <span></span>
          <span></span>
          <span></span>
        </div>

        <div class="profile-empty__content">
          <strong>
            {{ editable ? 'Make this profile yours' : 'New player profile' }}
          </strong>

          <span>
            {{
              editable
                  ? 'Добавь описание, socials и aspects — так профиль будет выглядеть намного живее.'
                  : 'У этого игрока пока недостаточно информации для заполненного профиля.'
            }}
          </span>
        </div>

        <button
            v-if="editable"
            class="profile-empty__action"
            type="button"
            @click="emit('edit')"
        >
          Edit profile
        </button>
      </section>

      <!-- Bottom decorative footer -->
      <div class="profile-card__footer">
        <span></span>
        <small>
          PLAYER / {{ user.username || user.id || 'PROFILE' }}
        </small>
        <span></span>
      </div>
    </main>

    <!-- =====================================================
         SIDE COLUMN
         ===================================================== -->

    <aside class="side-column">
      <!-- Achievements -->
      <section class="side-panel">
        <div class="side-panel__accent"></div>

        <div class="side-panel__heading">
          <div>
            <span class="side-panel__eyebrow">
              COLLECTION
            </span>

            <h3>Achievements</h3>
          </div>

          <span class="side-panel__number">
            {{ allAchievements.length }}
          </span>
        </div>

        <PlayerProfileShowcase
            v-if="allAchievements.length"
            :achievements="allAchievements"
            @open="openAchievement"
        />

        <div
            v-else
            class="side-empty"
        >
          <div class="side-empty__icon">
            ✦
          </div>

          <strong>No achievements yet</strong>

          <span>
            Новые достижения появятся здесь.
          </span>
        </div>
      </section>

      <!-- Friends -->
      <section class="side-panel">
        <div class="side-panel__heading">
          <div>
            <span class="side-panel__eyebrow">
              SOCIAL
            </span>

          </div>

        </div>

        <PlayerProfileFriends
            v-if="allFriends.length"
            :friends="allFriends"
            @enter="onFriendEnter"
            @leave="onFriendLeave"
        />

        <div
            v-else
            class="side-empty"
        >
          <div class="side-empty__icon">
            ♢
          </div>

          <strong>No friends yet</strong>

          <span>
            Когда появятся друзья, они будут отображаться здесь.
          </span>
        </div>
      </section>

      <!-- Recommendations -->
      <section
          v-if="!isOwner || recommendations.length || canRecommend"
          class="side-panel side-panel--recommendations"
      >
        <div class="side-panel__heading">
          <div>
            <span class="side-panel__eyebrow">
              COMMUNITY
            </span>
          </div>


        </div>

        <ProfileRecommendations
            :target-user="user"
            :recommendations="recommendations"
            :my-recommendation="myRecommendation"
            :can-recommend="canRecommend"
            :is-owner="isOwner"
            @updated="emit('recommendationsUpdated')"
        />
      </section>
    </aside>

    <!-- =====================================================
         FRIEND HOVER
         ===================================================== -->

    <Teleport to="body">
      <Transition name="friend-hover">
        <PlayerFriendHoverCard
            v-if="hoveredFriend"
            :friend="hoveredFriend"
            :x="hoverPosition.x"
            :y="hoverPosition.y"
        />
      </Transition>
    </Teleport>

    <!-- =====================================================
         ACHIEVEMENT MODAL
         ===================================================== -->

    <Teleport to="body">
      <AchievementModal
          v-if="showAchievement && openedAchievement"
          :achievement="openedAchievement"
          @close="closeAchievement"
      />
    </Teleport>
  </div>
</template>

<style scoped>
/* =========================================================
   ROOT
   ========================================================= */

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 20px;
  align-items: start;
}

/* =========================================================
   PLAYER CARD
   ========================================================= */

.player-card {
  --profile-line: color-mix(
      in srgb,
      var(--accent-color) 25%,
      var(--border)
  );

  position: relative;
  overflow: hidden;

  min-width: 0;

  padding: 24px;

  border: 1px solid var(--border);
  border-radius: 18px;

  background-color: var(--bg-card);
  background-size: cover;
  background-position: center;

  isolation: isolate;
}

.player-card::before {
  content: '';

  position: absolute;
  inset: 0;

  z-index: -2;

  background:
      linear-gradient(
          135deg,
          rgba(13, 13, 20, 0.96) 0%,
          rgba(13, 13, 20, 0.88) 48%,
          rgba(13, 13, 20, 0.95) 100%
      );
}

.player-card__glow {
  position: absolute;

  width: 420px;
  height: 420px;

  top: -250px;
  right: -160px;

  border-radius: 50%;

  background: var(--accent-color);

  opacity: 0.10;

  filter: blur(80px);

  pointer-events: none;

  z-index: -1;
}

.player-card__grid {
  position: absolute;
  inset: 0;

  opacity: 0.035;

  background-image:
      linear-gradient(var(--text) 1px, transparent 1px),
      linear-gradient(90deg, var(--text) 1px, transparent 1px);

  background-size: 32px 32px;

  mask-image: linear-gradient(
      to bottom,
      black,
      transparent 65%
  );

  pointer-events: none;

  z-index: -1;
}

.player-card > * {
  position: relative;
  z-index: 1;
}

/* =========================================================
   SUMMARY
   ========================================================= */

.profile-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 24px;

  margin-top: 24px;
  padding: 20px;

  border: 1px solid var(--profile-line);
  border-radius: 14px;

  background:
      linear-gradient(
          135deg,
          color-mix(
              in srgb,
              var(--accent-color) 7%,
              transparent
          ),
          rgba(255, 255, 255, 0.025)
      );
}

.profile-summary__main {
  min-width: 0;
}

.profile-summary__eyebrow {
  margin-bottom: 7px;

  font-size: 9px;
  font-weight: 900;

  letter-spacing: 1.8px;
  text-transform: uppercase;

  color: var(--accent-color);
}

.profile-summary h2 {
  margin: 0;

  font-size: 20px;
  line-height: 1.2;

  color: var(--text);
}

.profile-summary__bio,
.profile-summary__empty {
  max-width: 620px;

  margin: 7px 0 0;

  font-size: 12px;
  line-height: 1.55;

  color: var(--text-muted);
}

.profile-summary__empty {
  opacity: 0.65;
}

.profile-summary__completion {
  display: flex;
  align-items: center;

  flex-shrink: 0;

  gap: 10px;
}

.profile-summary__completion strong,
.profile-summary__completion span {
  display: block;
}

.profile-summary__completion strong {
  font-size: 11px;
  color: var(--text);
}

.profile-summary__completion span {
  margin-top: 2px;

  font-size: 9px;

  color: var(--text-muted);

  text-transform: uppercase;
  letter-spacing: 0.7px;
}

.completion-ring {
  display: grid;
  place-items: center;

  width: 42px;
  height: 42px;

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          var(--bg-card) 58%,
          transparent 60%
      );

  border: 2px solid
  color-mix(
      in srgb,
      var(--accent-color) 60%,
      transparent
  );

  box-shadow:
      0 0 0 4px
      color-mix(
          in srgb,
          var(--accent-color) 8%,
          transparent
      );
}

.completion-ring span {
  font-size: 9px;
  font-weight: 900;

  color: var(--accent-color);
}

/* =========================================================
   STATS
   ========================================================= */

.profile-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 8px;

  margin-top: 10px;
}

.profile-stat {
  display: flex;
  align-items: center;

  min-width: 0;

  gap: 10px;

  padding: 13px;

  border: 1px solid var(--border);
  border-radius: 12px;

  background: rgba(255, 255, 255, 0.025);

  transition:
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.profile-stat:hover {
  transform: translateY(-1px);

  border-color:
      color-mix(
          in srgb,
          var(--accent-color) 35%,
          var(--border)
      );

  background:
      color-mix(
          in srgb,
          var(--accent-color) 5%,
          transparent
      );
}

.profile-stat__icon {
  display: grid;
  place-items: center;

  width: 30px;
  height: 30px;

  flex-shrink: 0;

  border-radius: 9px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 9%,
          transparent
      );

  color: var(--text-muted);

  font-size: 11px;
}

.profile-stat--accent .profile-stat__icon {
  color: var(--accent-color);
}

.profile-stat__content {
  min-width: 0;
}

.profile-stat__content strong {
  display: block;

  font-size: 15px;
  line-height: 1;

  color: var(--text);
}

.profile-stat--accent .profile-stat__content strong {
  color: var(--accent-color);
}

.profile-stat__content span {
  display: block;

  overflow: hidden;

  margin-top: 4px;

  font-size: 8px;
  font-weight: 800;

  color: var(--text-muted);

  text-transform: uppercase;
  letter-spacing: 0.55px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

/* =========================================================
   SECTIONS
   ========================================================= */

.profile-section {
  margin-top: 22px;

  padding-top: 22px;

  border-top: 1px solid var(--border);
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 12px;

  margin-bottom: 12px;
}

.section-heading__eyebrow {
  display: block;

  margin-bottom: 4px;

  font-size: 8px;
  font-weight: 900;

  color: var(--accent-color);

  text-transform: uppercase;
  letter-spacing: 1.4px;
}

.section-heading h3 {
  margin: 0;

  font-size: 15px;
  font-weight: 850;

  color: var(--text);
}

.section-heading__count {
  padding: 4px 8px;

  border-radius: 6px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 8%,
          transparent
      );

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* =========================================================
   EMPTY STATES
   ========================================================= */

.profile-empty {
  display: flex;
  align-items: center;

  gap: 13px;

  padding: 15px;

  border: 1px dashed
  color-mix(
      in srgb,
      var(--border) 90%,
      transparent
  );

  border-radius: 12px;

  background:
      rgba(255, 255, 255, 0.015);
}

.profile-empty--large {
  min-height: 100px;
}

.profile-empty--profile {
  margin-top: 22px;

  padding: 22px;
}

.profile-empty__icon {
  display: grid;
  place-items: center;

  width: 34px;
  height: 34px;

  flex-shrink: 0;

  border-radius: 9px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 9%,
          transparent
      );

  color: var(--accent-color);

  font-size: 14px;
  font-weight: 900;
}

.profile-empty__icon--chart {
  font-size: 17px;
}

.profile-empty__content {
  min-width: 0;
}

.profile-empty__content strong {
  display: block;

  font-size: 11px;

  color: var(--text);
}

.profile-empty__content span {
  display: block;

  margin-top: 4px;

  font-size: 10px;
  line-height: 1.45;

  color: var(--text-muted);
}

.profile-empty__action {
  margin-left: auto;

  flex-shrink: 0;

  padding: 7px 11px;

  border: 1px solid
  color-mix(
      in srgb,
      var(--accent-color) 35%,
      var(--border)
  );

  border-radius: 7px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 8%,
          transparent
      );

  color: var(--accent-color);

  font-size: 9px;
  font-weight: 850;

  cursor: pointer;

  transition:
      background 0.2s ease,
      transform 0.2s ease;
}

.profile-empty__action:hover {
  transform: translateY(-1px);

  background:
      color-mix(
          in srgb,
          var(--accent-color) 14%,
          transparent
      );
}

.profile-empty__visual {
  display: flex;

  align-items: flex-end;

  width: 38px;
  height: 34px;

  gap: 3px;
}

.profile-empty__visual span {
  display: block;

  width: 8px;

  border-radius: 3px 3px 0 0;

  background: var(--accent-color);

  opacity: 0.2;
}

.profile-empty__visual span:nth-child(1) {
  height: 40%;
}

.profile-empty__visual span:nth-child(2) {
  height: 70%;
  opacity: 0.45;
}

.profile-empty__visual span:nth-child(3) {
  height: 100%;
  opacity: 0.8;
}

/* =========================================================
   CHART
   ========================================================= */

.chart-section {
  min-height: 120px;
}

/* =========================================================
   SIDE COLUMN
   ========================================================= */

.side-column {
  display: flex;
  flex-direction: column;

  gap: 14px;

  position: sticky;
  top: 20px;
}

.side-panel {
  position: relative;

  overflow: hidden;

  padding: 16px;

  border: 1px solid var(--border);
  border-radius: 14px;

  background: var(--bg-card);
}

.side-panel__accent {
  position: absolute;

  width: 100px;
  height: 100px;

  top: -70px;
  right: -40px;

  border-radius: 50%;

  background: var(--accent-color);

  opacity: 0.08;

  filter: blur(25px);

  pointer-events: none;
}

.side-panel__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 10px;

  margin-bottom: 13px;
}

.side-panel__eyebrow {
  display: block;

  margin-bottom: 4px;

  font-size: 7px;
  font-weight: 900;

  color: var(--accent-color);

  letter-spacing: 1.3px;
  text-transform: uppercase;
}

.side-panel h3 {
  margin: 0;

  font-size: 14px;
  font-weight: 850;

  color: var(--text);
}

.side-panel__number {
  display: grid;
  place-items: center;

  min-width: 27px;
  height: 27px;

  padding: 0 7px;

  border-radius: 7px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 9%,
          transparent
      );

  color: var(--accent-color);

  font-size: 10px;
  font-weight: 900;
}

.side-empty {
  display: flex;
  flex-direction: column;
  align-items: center;

  padding: 18px 10px;

  text-align: center;
}

.side-empty__icon {
  display: grid;
  place-items: center;

  width: 38px;
  height: 38px;

  margin-bottom: 9px;

  border-radius: 10px;

  background:
      color-mix(
          in srgb,
          var(--accent-color) 7%,
          transparent
      );

  color: var(--accent-color);

  font-size: 15px;
}

.side-empty strong {
  font-size: 10px;
  color: var(--text);
}

.side-empty span {
  max-width: 200px;

  margin-top: 5px;

  font-size: 9px;
  line-height: 1.45;

  color: var(--text-muted);
}

/* =========================================================
   FOOTER
   ========================================================= */

.profile-card__footer {
  display: flex;
  align-items: center;

  gap: 9px;

  margin-top: 24px;

  opacity: 0.45;
}

.profile-card__footer span {
  height: 1px;

  flex: 1;

  background: var(--border);
}

.profile-card__footer small {
  font-size: 7px;
  font-weight: 800;

  color: var(--text-muted);

  letter-spacing: 1.2px;
}

/* =========================================================
   FRIEND HOVER
   ========================================================= */

.friend-hover-enter-active,
.friend-hover-leave-active {
  transition:
      opacity 0.15s ease,
      transform 0.15s ease;
}

.friend-hover-enter-from,
.friend-hover-leave-to {
  opacity: 0;
  transform: translateY(4px) scale(0.98);
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {
  .profile-layout {
    grid-template-columns: minmax(0, 1fr) 280px;
  }

  .profile-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .side-column {
    position: static;

    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .side-panel--recommendations {
    grid-column: 1 / -1;
  }
}

@media (max-width: 600px) {
  .player-card {
    padding: 14px;

    border-radius: 14px;
  }

  .profile-summary {
    align-items: flex-start;
    flex-direction: column;

    padding: 15px;
  }

  .profile-summary__completion {
    width: 100%;
  }

  .profile-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .profile-stat {
    padding: 10px;
  }

  .profile-stat__icon {
    width: 27px;
    height: 27px;
  }

  .profile-stat__content strong {
    font-size: 13px;
  }

  .side-column {
    display: flex;
    flex-direction: column;
  }

  .profile-empty__action {
    align-self: flex-start;
  }
}

@media (max-width: 400px) {
  .profile-stats {
    grid-template-columns: 1fr;
  }

  .profile-stat {
    min-height: 48px;
  }
}
</style>