<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/api.js'
import FriendButton from '@/components/FriendButton.vue'
import UserName from '@/components/UserName.vue'

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
    <!-- HEAD -->
    <header class="page-head">
      <div class="page-head__text">
        <h1>Игроки</h1>
        <p class="page-head__sub">
          Найдено: <strong>{{ total }}</strong>
        </p>
      </div>
    </header>

    <!-- FILTERS -->
    <div class="filters">
      <div class="filters__search">
        <svg class="filters__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7" />
          <path d="m21 21-4.3-4.3" stroke-linecap="round" />
        </svg>

        <input
            v-model="search"
            type="text"
            placeholder="Поиск по нику..."
            class="search-input"
        />
      </div>

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

    <!-- STATES -->
    <div v-if="loading" class="state">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <div v-else-if="!players.length" class="state state--empty">
      <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
        <circle cx="11" cy="11" r="7" />
        <path d="m21 21-4.3-4.3" stroke-linecap="round" />
      </svg>
      <span>Никого не найдено</span>
    </div>

    <!-- LIST -->
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
          <div class="player-avatar" :class="`tier-bg-${player.tier}`">
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
            <div class="player-name">
              <UserName :user="player" />
            </div>

            <div class="player-bio">
              {{ player.bio || 'Без описания' }}
            </div>
          </div>

          <div class="player-stats">
            <div class="player-tier" :class="`tier-${player.tier}`">
              {{ player.tier }}
            </div>

            <div class="player-score">
              <span class="player-score__value">{{ player.tier_score }}</span>
              <span class="player-score__unit">%</span>
            </div>
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

    <!-- PAGINATION -->
    <nav v-if="lastPage > 1" class="pagination">
      <button
          class="pagination__btn"
          :disabled="page <= 1"
          @click="page--"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Назад
      </button>

      <span class="pagination__page">
        <strong>{{ page }}</strong> / {{ lastPage }}
      </span>

      <button
          class="pagination__btn"
          :disabled="page >= lastPage"
          @click="page++"
      >
        Вперёд
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </nav>
  </div>
</template>

<style scoped>
/* ============================================
   PAGE
   ============================================ */

.players-page {
  width: min(1000px, calc(100% - 40px));
  margin: 40px auto;
}

.page-head {
  margin-bottom: 22px;
}

.page-head__text h1 {
  margin: 0 0 4px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.3px;
}

.page-head__sub {
  margin: 0;
  color: var(--text-dim);
  font-size: 13.5px;
}

.page-head__sub strong {
  color: var(--text);
  font-weight: 800;
}

/* ============================================
   FILTERS
   ============================================ */

.filters {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.filters__search {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
}

.filters__icon {
  position: absolute;
  left: 14px;
  color: var(--text-muted);
  pointer-events: none;
}

.search-input {
  width: 100%;
  min-height: 44px;
  padding: 0 16px 0 40px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
  font: inherit;
  font-size: 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.search-input::placeholder {
  color: var(--text-muted);
}

.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}

.tier-select {
  min-height: 44px;
  padding: 0 36px 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238a8a97' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.tier-select:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}

/* ============================================
   STATES
   ============================================ */

.state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 20px;
  text-align: center;
  color: var(--text-dim);
  font-size: 14px;
}

.state--empty svg {
  color: var(--text-muted);
  opacity: 0.6;
}

.spinner {
  width: 26px;
  height: 26px;
  border: 2.5px solid color-mix(in srgb, var(--accent) 25%, transparent);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ============================================
   PLAYERS LIST
   ============================================ */

.players-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: border-color 0.15s, background 0.15s, transform 0.15s;
}

.player-row:hover {
  border-color: var(--border-hover);
  background: var(--bg-card-hover);
  transform: translateY(-1px);
}

.player-main {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
  text-decoration: none;
  color: inherit;
}

/* === AVATAR === */

.player-avatar {
  position: relative;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 11px;
  color: #fff;
  font-size: 18px;
  font-weight: 800;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

/* фоны по тиру, если нет аватарки */
.tier-bg-S { background: linear-gradient(135deg, #facc15, #d97706); }
.tier-bg-A { background: linear-gradient(135deg, #f97316, #c2410c); }
.tier-bg-B { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.tier-bg-C { background: linear-gradient(135deg, #06b6d4, #0e7490); }
.tier-bg-D { background: linear-gradient(135deg, #22c55e, #15803d); }
.tier-bg-E { background: linear-gradient(135deg, #6b7280, #374151); }

.player-avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

/* === INFO === */

.player-info {
  flex: 1;
  min-width: 0;
}

.player-name {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.player-bio {
  font-size: 12.5px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* === STATS === */

.player-stats {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
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
  border: 1px solid currentColor;
}

.tier-S { color: #facc15; background: rgba(250, 204, 21, 0.08); }
.tier-A { color: #f97316; background: rgba(249, 115, 22, 0.08); }
.tier-B { color: #8b5cf6; background: rgba(139, 92, 246, 0.08); }
.tier-C { color: #06b6d4; background: rgba(6, 182, 212, 0.08); }
.tier-D { color: #22c55e; background: rgba(34, 197, 94, 0.08); }
.tier-E { color: #6b7280; background: rgba(107, 114, 128, 0.08); }

.player-score {
  display: flex;
  align-items: baseline;
  gap: 1px;
  min-width: 52px;
  justify-content: flex-end;
  color: var(--text-dim);
  font-weight: 800;
}

.player-score__value {
  font-size: 14px;
  color: var(--text);
}

.player-score__unit {
  font-size: 11px;
  color: var(--text-muted);
}

/* === ACTION === */

.player-action {
  flex-shrink: 0;
}

/* ============================================
   PAGINATION
   ============================================ */

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 26px;
}

.pagination__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 16px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.pagination__btn:hover:not(:disabled) {
  border-color: var(--accent);
  color: color-mix(in srgb, var(--accent) 60%, #fff);
  transform: translateY(-1px);
}

.pagination__btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination__page {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
  min-width: 60px;
  text-align: center;
}

.pagination__page strong {
  color: var(--text);
  font-weight: 800;
}

/* ============================================
   MOBILE
   ============================================ */

@media (max-width: 640px) {
  .players-page {
    width: calc(100% - 24px);
    margin: 20px auto;
  }

  .page-head__text h1 {
    font-size: 22px;
  }

  .filters {
    flex-direction: column;
    gap: 8px;
  }

  .search-input,
  .tier-select {
    min-height: 42px;
    font-size: 14px;
  }

  .player-row {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    padding: 12px 14px;
  }

  .player-main {
    display: grid;
    grid-template-columns: 46px 1fr auto;
    align-items: center;
    gap: 12px;
  }

  .player-avatar {
    width: 44px;
    height: 44px;
    font-size: 16px;
    border-radius: 10px;
  }

  .player-info {
    min-width: 0;
  }

  .player-name {
    font-size: 14.5px;
  }

  .player-bio {
    font-size: 11.5px;
  }

  .player-stats {
    gap: 8px;
  }

  .player-tier {
    width: 36px;
    height: 36px;
    font-size: 14px;
    border-radius: 8px;
  }

  .player-score {
    min-width: 42px;
  }

  .player-score__value {
    font-size: 13px;
  }

  /* FriendButton на всю ширину */
  .player-action {
    display: flex;
  }

  .player-action :deep(button),
  .player-action :deep(a) {
    width: 100%;
    justify-content: center;
  }

  .pagination {
    gap: 8px;
  }

  .pagination__btn {
    flex: 1;
    justify-content: center;
    min-height: 42px;
  }

  .pagination__page {
    min-width: auto;
  }
}
</style>