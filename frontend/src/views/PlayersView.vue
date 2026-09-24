<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/api.js'
import FriendButton from '@/components/FriendButton.vue'

const players = ref([])
const loading = ref(true)
const search = ref('')
const tierFilter = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

let debounceTimer = null

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (search.value) params.append('search', search.value)
    if (tierFilter.value) params.append('tier', tierFilter.value)
    params.append('page', page.value)

    const data = await api.get(`/players?${params.toString()}`)

    players.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

watch(tierFilter, () => {
  page.value = 1
  load()
})

watch(page, load)

function onFriendshipUpdate(playerId, newState) {
  const player = players.value.find(p => p.id === playerId)
  if (player) player.friendship = newState
}

onMounted(load)
</script>

<template>
  <div class="players-page">
    <div class="page-head">
      <div>
        <h1>Игроки</h1>
        <p class="subtitle">Найдено: {{ total }}</p>
      </div>
    </div>

    <div class="filters">
      <input
          v-model="search"
          type="text"
          placeholder="Поиск по нику..."
          class="search-input"
      />

      <select v-model="tierFilter" class="tier-select">
        <option value="">Все тиры</option>
        <option value="S">S</option>
        <option value="A">A</option>
        <option value="B">B</option>
        <option value="C">C</option>
        <option value="D">D</option>
        <option value="E">E</option>
      </select>
    </div>

    <div v-if="loading" class="loading">Загрузка...</div>

    <div v-else-if="!players.length" class="empty">
      Никого не найдено
    </div>

    <div v-else class="players-list">
      <div
          v-for="player in players"
          :key="player.id"
          class="player-row"
      >
        <RouterLink
            :to="`/players/${player.id}`"
            class="player-main"
        >
          <div class="player-avatar">
            <img
                v-if="player.avatar_url"
                :src="player.avatar_url"
                :alt="player.username"
                class="player-avatar-img"
            />
            <template v-else>
              {{ (player.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>
          <div class="player-info">
            <div class="player-name">{{ player.username }}</div>
            <div class="player-bio">
              {{ player.bio || 'Без описания' }}
            </div>
          </div>

          <div class="player-tier" :class="`tier-${player.tier}`">
            {{ player.tier }}
          </div>

          <div class="player-score">
            {{ player.tier_score }}%
          </div>
        </RouterLink>

        <div class="player-action">
          <FriendButton
              :user-id="player.id"
              :friendship="player.friendship"
              @update="(state) => onFriendshipUpdate(player.id, state)"
          />
        </div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button
          :disabled="page <= 1"
          @click="page--"
      >
        ← Назад
      </button>

      <span>{{ page }} / {{ lastPage }}</span>

      <button
          :disabled="page >= lastPage"
          @click="page++"
      >
        Вперёд →
      </button>
    </div>
  </div>
</template>

<style scoped>
.players-page {
  width: min(1000px, calc(100% - 40px));
  margin: 40px auto;
}

.page-head {
  margin-bottom: 24px;
}

.page-head h1 {
  margin: 0 0 4px;
  font-size: 28px;
  font-weight: 800;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  min-height: 44px;
  padding: 0 16px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
  transition: border-color 0.2s;
}

.search-input:focus {
  border-color: var(--accent);
}

.tier-select {
  min-height: 44px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
}

.loading,
.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

.players-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s ease;
}

.player-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.player-main {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
}

.player-avatar {
  position: relative;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-size: 18px;
  font-weight: 800;
  overflow: hidden;
}

.player-avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.player-info {
  flex: 1;
  min-width: 0;
}

.player-name {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 2px;
}

.player-bio {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.player-tier {
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 17px;
  font-weight: 900;
  flex-shrink: 0;
}

.tier-S { color: #facc15; background: rgba(250, 204, 21, 0.1); }
.tier-A { color: #f97316; background: rgba(249, 115, 22, 0.1); }
.tier-B { color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }
.tier-C { color: #06b6d4; background: rgba(6, 182, 212, 0.1); }
.tier-D { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.tier-E { color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.player-score {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 700;
  min-width: 52px;
  text-align: right;
}

.player-action {
  flex-shrink: 0;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 24px;
}

.pagination button {
  min-height: 38px;
  padding: 0 16px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination button:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--accent-light);
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination span {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}
</style>