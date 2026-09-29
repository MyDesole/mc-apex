<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import TierHistoryChart from '@/components/TierHistoryChart.vue'
import ProfileRecommendations from '@/components/ProfileRecommendations.vue'
import { api } from '@/services/api.js'
import {tierColor} from "@/composables/useTier.js";
import PlayerProfileHeader from "@/components/player-profile/PlayerProfileHeader.vue";
import PlayerProfileSocials from "@/components/player-profile/PlayerProfileSocials.vue";
import PlayerProfileAspects from "@/components/player-profile/PlayerProfileAspects.vue";
import PlayerProfileShowcase from "@/components/player-profile/PlayerProfileShowcase.vue";
import PlayerProfileFriends from "@/components/player-profile/PlayerProfileFriends.vue";
import PlayerFriendHoverCard from "@/components/player-profile/PlayerFriendHoverCard.vue";
import AchievementModal from "@/components/player-profile/AchievementModal.vue";


const props = defineProps({
  user: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  recommendations: { type: Array, default: () => [] },
  myRecommendation: { type: Object, default: null },
  canRecommend: { type: Boolean, default: false },
  isOwner: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'recommendationsUpdated'])

// === Accent & фон ===
const accent = computed(() =>
    props.user.accent_color || props.user.banner_color || tierColor(props.user.tier)
)

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

// === График ===
const history = ref([])

async function loadHistory(userId) {
  if (!userId) { history.value = []; return }
  try {
    const data = await api.get(`/players/${userId}/tier-history`)
    history.value = data.history ?? []
  } catch { history.value = [] }
}

onMounted(() => loadHistory(props.user.id))
watch(() => props.user.id, (id) => {
  closeAchievement()
  hoveredFriend.value = null
  loadHistory(id)
})

// === Достижения ===
const allAchievements = computed(() =>
    props.user.all_achievements ?? props.user.achievements ?? []
)

const openedAchievement = ref(null)
const showAchievement = ref(false)
function openAchievement(a) { openedAchievement.value = a; showAchievement.value = true }
function closeAchievement() { showAchievement.value = false; openedAchievement.value = null }

// === Друзья (hover) ===
const allFriends = computed(() => props.user.friends_all ?? props.user.friends ?? [])
const hoveredFriend = ref(null)
const hoverPosition = ref({ x: 0, y: 0 })

function onFriendEnter(friend, event) {
  hoveredFriend.value = friend
  updateHoverPosition(event)
}
function updateHoverPosition(event) {
  const rect = event.currentTarget.getBoundingClientRect()
  const cardWidth = 320, cardHeight = 200, gap = 12
  let x = rect.left - cardWidth - gap
  let y = rect.top + rect.height / 2 - cardHeight / 2
  if (x < gap) x = rect.right + gap
  const maxY = window.innerHeight - cardHeight - gap
  if (y < gap) y = gap
  if (y > maxY) y = maxY
  hoverPosition.value = { x, y }
}
function onFriendLeave() { hoveredFriend.value = null }
</script>

<template>
  <div class="profile-layout">
    <div class="player-card" :style="cardStyle">
      <PlayerProfileHeader
          :user="user"
          :editable="editable"
          :cover-url="user.cover_url"
          :accent="accent"
          @edit="emit('edit')"
      />

      <PlayerProfileSocials :socials="user.socials" />

      <div v-if="history.length" class="chart-section">
        <div class="chart-section__head">
          <h3>Прогресс тира</h3>
          <span class="chart-section__count">{{ history.length }} тестов</span>
        </div>
        <TierHistoryChart :history="history" />
      </div>

      <PlayerProfileAspects :aspects="user.aspects" />
    </div>

    <div class="side-column">
      <PlayerProfileShowcase
          :achievements="allAchievements"
          @open="openAchievement"
      />

      <PlayerProfileFriends
          :friends="allFriends"
          @enter="onFriendEnter"
          @leave="onFriendLeave"
      />

      <ProfileRecommendations
          v-if="!isOwner || recommendations.length || canRecommend"
          :target-user="user"
          :recommendations="recommendations"
          :my-recommendation="myRecommendation"
          :can-recommend="canRecommend"
          :is-owner="isOwner"
          @updated="emit('recommendationsUpdated')"
      />
    </div>

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

/* ============================================
   CHART SECTION
   ============================================ */

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
}
</style>