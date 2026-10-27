<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PlayerCard from '@/components/players/PlayerCard.vue'
import ClanBadge from '@/components/clan/ClanBadge.vue'
import RankBadge from '@/components/players/RankBadge.vue'
import BridgeTechniques from '@/components/bridge/BridgeTechniques.vue'
import FriendButton from '@/components/friends/FriendButton.vue'
import PlayerShowcase from '@/components/players/PlayerShowcase.vue'
import PlayerSeasons from '@/components/players/profile/PlayerSeasons.vue'
import PlayerRatingBreakdown from '@/components/players/profile/PlayerRatingBreakdown.vue'
import { openReport } from '@/composables/core/useReport.js'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

const route = useRoute()
const auth = useAuthStore()

const user = ref(null)
const aspects = ref([])
const friendship = ref(null)
const rank = ref({ position: null, total: 0 })
const rating = ref(null)
const bridge = ref({ techniques: [], summary: {}, rank: { position: null, total: 0 } })

// Режим профиля владельца: в бридж-профиле виды бриджа идут первыми
const profileMode = ref('pvp')
const bridgeRank = ref(null)

// Профиль собран в конструкторе: витрина и факты стоят в его сетке
const builderOn = ref(false)

const isBridgeProfile = computed(() => profileMode.value === 'bridge')
const recommendations = ref([])
const myRecommendation = ref(null)
const canRecommend = ref(false)
const loading = ref(true)
const error = ref(null)

const clanMember = computed(() => user.value?.clan_member ?? null)

/*
 * Персонал не участвует в рейтинге, поэтому блок места в топе ему не
 * показываем: места у него нет и не будет.
 */
const isStaff = computed(() => Boolean(user.value?.is_staff))
const isMe = computed(() => auth.user?.id === user.value?.id)

async function load(id) {
  loading.value = true
  error.value = null

  user.value = null
  aspects.value = []
  friendship.value = null
  rank.value = { position: null, total: 0 }
  rating.value = null
  bridge.value = { techniques: [], summary: {}, rank: { position: null, total: 0 } }
  profileMode.value = 'pvp'
  bridgeRank.value = null
  recommendations.value = []
  myRecommendation.value = null
  canRecommend.value = false

  try {
    const data = await api.get(`/players/${id}`)
    user.value = data.user
    aspects.value = data.user.aspects ?? []
    friendship.value = data.friendship
    rank.value = data.rank ?? { position: null, total: 0 }
    rating.value = data.rating ?? null
    bridge.value = data.bridge ?? { techniques: [], summary: {}, rank: { position: null, total: 0 } }
    profileMode.value = data.profile_mode ?? 'pvp'
    bridgeRank.value = data.bridge_rank ?? null
    recommendations.value = data.recommendations ?? []
    myRecommendation.value = data.my_recommendation ?? null
    canRecommend.value = data.can_recommend ?? false
  } catch (e) {
    error.value = e.status === 404
        ? 'Игрок не найден'
        : (e.message || 'Ошибка загрузки')
  } finally {
    loading.value = false
  }
}

function onFriendshipUpdate(state) {
  friendship.value = state
}

watch(
    () => route.params.id,
    (newId) => {
      if (newId) load(newId)
    },
    { immediate: true }
)
</script>

<template>
  <div v-if="loading" class="loading">
    <div class="spinner" />
    <span>Загрузка...</span>
  </div>

  <div v-else-if="error" class="error-page">
    <div class="error-page__icon">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="M12 8v4M12 16h.01" />
      </svg>
    </div>
    <h1>{{ error.includes('не найден') ? '404' : 'Ошибка' }}</h1>
    <p>{{ error }}</p>
    <RouterLink to="/players" class="btn-back">Все игроки</RouterLink>
  </div>

  <div v-else-if="user" class="container">
    <PlayerCard
        :user="user"
        :recommendations="recommendations"
        :my-recommendation="myRecommendation"
        :can-recommend="canRecommend"
        :bridge-mode="isBridgeProfile"
        :bridge-rank="bridgeRank"
        :bridge-techniques="bridge.techniques"
        @recommendations-updated="load(route.params.id)"
        @builder="builderOn = $event"
    >
      <!-- Части страницы для конструктора профиля -->
      <template #block="{ block }">
        <PlayerShowcase v-if="block.type === 'showcase'" :user="user" :rank="rank" :is-me="isMe" />
        <ClanBadge v-else-if="block.type === 'clan'" :clan-member="clanMember" />
        <template v-else-if="isStaff">
          <p class="builder-staff">Персонал не участвует в рейтинге.</p>
        </template>
        <RankBadge
            v-else-if="block.type === 'rank'"
            :position="isBridgeProfile ? bridge.rank.position : rank.position"
            :total="isBridgeProfile ? bridge.rank.total : rank.total"
        />
        <PlayerRatingBreakdown v-else-if="block.type === 'rating'" :rating="rating" title-class="block-title" />
        <PlayerSeasons v-else-if="block.type === 'seasons'" :user-id="user.id" title-class="block-title" />
      </template>

      <!-- Профиль из конструктора: дружба и жалоба — в шапке -->
      <template v-if="!isMe" #header-actions>
        <div class="profile-friend-actions">
        <FriendButton
            :user-id="user.id"
            :friendship="friendship"
            @update="onFriendshipUpdate"
        />
        <button
            v-if="auth.isAuthenticated"
            type="button"
            class="profile-header-report"
            title="Пожаловаться на игрока"
            aria-label="Пожаловаться на игрока"
            @click="openReport({ type: 'user', id: user.id, name: user.username })"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 22V4" />
            <path d="M4 4h13l-2 4 2 4H4" />
          </svg>
        </button>
        </div>
      </template>
    </PlayerCard>

    <!--
      Minecraft-витрина и факты об игроке одним рядом: раньше клан, место
      и дружба шли отдельными блоками друг под другом.
    -->
    <div v-if="!builderOn" class="profile-row">
      <PlayerShowcase
          :user="user"
          :rank="rank"
          :is-me="isMe"
      />

      <aside class="profile-facts">
        <section class="fact">
          <h3 class="block-title">Клан</h3>
          <ClanBadge :clan-member="clanMember" />
        </section>

        <section v-if="!isStaff" class="fact">
          <h3 class="block-title">
            {{ isBridgeProfile ? 'Место в бридже' : 'Место в топе' }}
          </h3>

          <!-- В бридж-профиле показываем место среди бриджеров -->
          <RankBadge
              :position="isBridgeProfile ? bridge.rank.position : rank.position"
              :total="isBridgeProfile ? bridge.rank.total : rank.total"
          />
        </section>

        <PlayerRatingBreakdown v-if="!isStaff" :rating="rating" />

        <PlayerSeasons v-if="!isStaff" :user-id="user.id" />

        <section v-if="!isMe" class="fact">
          <h3 class="block-title">Дружба</h3>
          <FriendButton
              :user-id="user.id"
              :friendship="friendship"
              @update="onFriendshipUpdate"
          />
        </section>

        <button
            v-if="!isMe && auth.isAuthenticated"
            type="button"
            class="report-link"
            @click="openReport({ type: 'user', id: user.id, name: user.username })"
        >
          Пожаловаться на игрока
        </button>
      </aside>
    </div>

    <div
        v-if="!isBridgeProfile && bridge.techniques.length"
        class="bridge-wrap"
    >
      <BridgeTechniques
          :techniques="bridge.techniques"
          :summary="bridge.summary"
          :rank="bridge.rank"
      />
    </div>
  </div>
</template>

<style scoped>
@import "@/views/players/PlayerView.css";

.report-link {
  align-self: flex-start;

  padding: 0;

  color: var(--text-muted);
  background: none;
  border: 0;

  font: inherit;
  font-size: 13px;

  cursor: pointer;
}

.report-link:hover {
  color: #fca5a5;
  text-decoration: underline;
}
</style>
