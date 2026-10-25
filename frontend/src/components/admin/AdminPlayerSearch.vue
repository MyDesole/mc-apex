<script setup>
/** Поиск игрока по нику в шапке админки — открывает досье. */
import { ref, watch } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const emit = defineEmits(['select'])

const query = ref('')
const results = ref([])
const open = ref(false)

let timer = null

watch(query, (value) => {
  clearTimeout(timer)

  if (value.trim().length < 2) {
    results.value = []
    return
  }

  timer = setTimeout(async () => {
    try {
      results.value = (await adminApi.searchPlayers(value.trim())).players ?? []
      open.value = true
    } catch {
      results.value = []
    }
  }, 250)
})

function choose(player) {
  emit('select', player.id)
  query.value = ''
  results.value = []
  open.value = false
}

function close() {
  // Даём клику по результату сработать раньше, чем список закроется
  setTimeout(() => (open.value = false), 150)
}
</script>

<template>
  <div class="player-search">
    <input
        v-model="query"
        type="search"
        placeholder="Найти игрока…"
        aria-label="Найти игрока и открыть досье"
        @focus="open = results.length > 0"
        @blur="close"
        @keydown.enter="results[0] && choose(results[0])"
    />

    <ul v-if="open && results.length" class="player-search__list">
      <li v-for="p in results" :key="p.id">
        <button type="button" @mousedown.prevent="choose(p)">
          <span class="player-search__avatar">
            <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
            <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
          </span>
          <span class="player-search__name">{{ p.username }}</span>
          <small v-if="p.is_banned" class="player-search__ban">бан</small>
          <small v-else>{{ p.tier || '—' }}</small>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.player-search {
  position: relative;
}

.player-search input {
  width: 100%;
  padding: 9px 12px;

  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 14px;
}

.player-search input:focus {
  outline: none;
  border-color: var(--accent);
}

.player-search__list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 30;

  margin: 0;
  padding: 4px;
  list-style: none;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
}

.player-search__list button {
  display: flex;
  align-items: center;
  gap: 10px;

  width: 100%;
  padding: 7px 8px;

  color: var(--text);
  background: none;
  border: 0;
  border-radius: 5px;

  font: inherit;
  font-size: 14px;
  text-align: left;

  cursor: pointer;
}

.player-search__list button:hover {
  background: rgba(255, 255, 255, 0.05);
}

.player-search__avatar {
  width: 24px;
  height: 24px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  background: var(--accent);
  border-radius: 4px;

  font-size: 11px;
  font-weight: 700;
}

.player-search__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.player-search__name {
  flex: 1;
}

.player-search__list small {
  color: var(--text-muted);
}

.player-search__list .player-search__ban {
  color: #fca5a5;
}
</style>
