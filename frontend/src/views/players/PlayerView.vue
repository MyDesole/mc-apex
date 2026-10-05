<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import PlayerCard from '@/components/players/PlayerCard.vue'
import ClanBadge from '@/components/clan/ClanBadge.vue'
import RankBadge from '@/components/players/RankBadge.vue'
import FriendButton from '@/components/friends/FriendButton.vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

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

      <div
          v-if="!isStaff"
          class="block-col"
      >
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
@import "@/views/players/PlayerView.css";
</style>
