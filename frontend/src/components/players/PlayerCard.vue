<script setup>
import { computed, onMounted, ref, watch } from 'vue'

import TierHistoryChart from '@/components/tiers/TierHistoryChart.vue'
import ProfileRecommendations from '@/components/players/ProfileRecommendations.vue'

import { api } from '@/services/core/api.js'
import { tierColor } from '@/composables/players/useTier.js'

import PlayerProfileHeader from '@/components/players/profile/PlayerProfileHeader.vue'
import PlayerProfileSocials from '@/components/players/profile/PlayerProfileSocials.vue'
import PlayerProfileAspects from '@/components/players/profile/PlayerProfileAspects.vue'
import PlayerProfileShowcase from '@/components/players/profile/PlayerProfileShowcase.vue'
import PlayerProfileFriends from '@/components/players/profile/PlayerProfileFriends.vue'
import PlayerFriendHoverCard from '@/components/players/profile/PlayerFriendHoverCard.vue'
import AchievementModal from '@/components/players/profile/AchievementModal.vue'

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

  /*
   * Бридж-профиль: в статистике показываем звание бриджера, а блок
   * аспектов скрываем — он про PvP.
   */
  bridgeMode: {
    type: Boolean,
    default: false,
  },

  bridgeRank: {
    type: Object,
    default: null,
  },

  // Подтверждённые виды: хедер показывает их вместо прогресса тира
  bridgeTechniques: {
    type: Array,
    default: () => [],
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
    value: props.bridgeMode
        ? (props.bridgeRank?.label ?? 'Без звания')
        : (props.user.tier || '—'),
    label: props.bridgeMode ? 'Bridge rank' : 'Current tier',
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
          :bridge-mode="bridgeMode"
          :bridge-rank="bridgeRank"
          :bridge-techniques="bridgeTechniques"
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
           BRIDGE TECHNIQUES (бридж-профиль)
           =================================================== -->

      <section
          v-if="bridgeMode"
          class="profile-section"
      >
        <div class="section-heading">
          <div>
            <span class="section-heading__eyebrow">
              BRIDGE MASTERY
            </span>

            <h3>Виды бриджа</h3>
          </div>

          <span
              v-if="bridgeTechniques.length"
              class="section-heading__count"
          >
            {{ bridgeTechniques.length }}
          </span>
        </div>

        <ul
            v-if="bridgeTechniques.length"
            class="bridge-card-list"
        >
          <li
              v-for="row in bridgeTechniques"
              :key="row.id"
              class="bridge-card-item"
          >
            <div class="bridge-card-item__main">
              <span class="bridge-card-item__name">
                {{ row.technique?.label }}
              </span>

              <span
                  v-if="row.variants?.length"
                  class="bridge-card-item__variants"
              >
                <span
                    v-for="variant in row.variants"
                    :key="variant.id"
                    class="bridge-card-item__pill"
                    :class="{ 'bridge-card-item__pill--special': variant.is_special }"
                >
                  {{ variant.label }}
                </span>
              </span>
            </div>

            <div class="bridge-card-item__scores">
              <span class="bridge-card-item__score">
                {{ row.score }}/10
              </span>

              <span class="bridge-card-item__total">
                {{ row.total }}/300
              </span>
            </div>
          </li>
        </ul>

        <div
            v-else
            class="profile-empty profile-empty--large"
        >
          <div class="profile-empty__icon profile-empty__icon--chart">
            ⌂
          </div>

          <div class="profile-empty__content">
            <strong>Пока нет подтверждённых видов</strong>

            <span>
              Подтверди вид роликом — тестер проверит и поставит оценку.
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           TIER HISTORY (PvP-профиль)
           =================================================== -->

      <section
          v-else
          class="profile-section"
      >
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
          v-if="hasAspects && !bridgeMode"
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

          </div>

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
@import "@/components/players/PlayerCard.css";
</style>
