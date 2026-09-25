<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PlayerCard from '@/components/PlayerCard.vue'
import ClanBadge from '@/components/ClanBadge.vue'
import RankBadge from '@/components/RankBadge.vue'
import FriendButton from '@/components/FriendButton.vue'
import { api } from '@/services/api.js'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const user = ref(null)
const aspects = ref([])
const friendship = ref(null)
const rank = ref({ position: null, total: 0 })
const recommendations = ref([])
const myRecommendation = ref(null)
const canRecommend = ref(false)
const loading = ref(true)
const error = ref(null)

const clanMember = computed(() => user.value?.clan_member ?? null)
const isMe = computed(() => auth.user?.id === user.value?.id)

async function load(id) {
  loading.value = true
  error.value = null

  user.value = null
  aspects.value = []
  friendship.value = null
  rank.value = { position: null, total: 0 }
  recommendations.value = []
  myRecommendation.value = null
  canRecommend.value = false

  try {
    const data = await api.get(`/players/${id}`)
    user.value = data.user
    aspects.value = data.user.aspects ?? []
    friendship.value = data.friendship
    rank.value = data.rank ?? { position: null, total: 0 }
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
        @recommendations-updated="load(route.params.id)"
    />

    <section class="blocks">
      <div class="block-col">
        <h3 class="block-title">Клан</h3>
        <ClanBadge :clan-member="clanMember" />
      </div>

      <div class="block-col">
        <h3 class="block-title">Место в топе</h3>
        <RankBadge
            :position="rank.position"
            :total="rank.total"
        />
      </div>
    </section>

    <div v-if="!isMe" class="friend-block">
      <h3 class="block-title">Дружба</h3>
      <FriendButton
          :user-id="user.id"
          :friendship="friendship"
          @update="onFriendshipUpdate"
      />
    </div>
  </div>
</template>

<style scoped>
.container {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.blocks {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.block-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.block-title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.friend-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 100px 20px;
  text-align: center;
}

.error-page__icon {
  color: var(--text-muted);
  opacity: 0.6;
  margin-bottom: 8px;
}

.error-page h1 {
  font-size: 72px;
  color: #f87171;
  margin: 0;
  letter-spacing: -2px;
}

.error-page p {
  color: var(--text-dim);
  margin: 0 0 20px;
  font-size: 15px;
}

.btn-back {
  display: inline-flex;
  align-items: center;
  padding: 11px 22px;
  color: #fff;
  background: var(--accent);
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.2s;
}

.btn-back:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

@media (max-width: 700px) {
  .blocks {
    grid-template-columns: 1fr;
  }
}
</style>