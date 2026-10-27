<script setup>
/**
 * Настройки блока «Тиммейты»: поиск игрока APEX по нику и список
 * добавленных — у каждого роль (дуо, тренер…) и короткая подпись.
 * В раскладку пишем id игрока: аватар, тир и ник блок берёт свежие.
 */
import { computed, ref, watch } from 'vue'
import { searchApi } from '@/services/search/search.js'
import { TEAMMATE_ROLES } from '@/data/players/profileBlocks.js'
import { rankColor, rankLabel } from '@/utils/playerStyling.js'

const props = defineProps({
  block: { type: Object, required: true },
})

const MAX = 8

const query = ref('')
const results = ref([])
const searching = ref(false)
let timer = null
let requestId = 0

// Блок из старой раскладки мог прийти без списка
props.block.settings ??= {}
props.block.settings.items ??= []

const items = computed(() => props.block.settings.items)
const takenIds = computed(() => new Set(items.value.map((i) => i.user_id)))

watch(query, (q) => {
  clearTimeout(timer)
  const value = q.trim()

  if (value.length < 2) {
    results.value = []
    searching.value = false
    return
  }

  searching.value = true
  timer = setTimeout(() => search(value), 250)
})

async function search(value) {
  const id = ++requestId

  try {
    const data = await searchApi.query(value)
    // Ответ на старый запрос пришёл позже нового — не показываем
    if (id === requestId) results.value = data.players ?? []
  } catch {
    if (id === requestId) results.value = []
  } finally {
    if (id === requestId) searching.value = false
  }
}

function add(player) {
  if (takenIds.value.has(player.id) || items.value.length >= MAX) return

  items.value.push({ user_id: player.id, username: player.username, role: 'duo', note: '' })
  query.value = ''
  results.value = []
}

function move(index, delta) {
  const to = index + delta
  if (to < 0 || to >= items.value.length) return
  const [item] = items.value.splice(index, 1)
  items.value.splice(to, 0, item)
}
</script>

<template>
  <div class="tp">
    <!-- Поиск -->
    <div v-if="items.length < MAX" class="tp-search">
      <svg class="tp-search__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
        <circle cx="11" cy="11" r="7" />
        <path d="m20 20-3.5-3.5" />
      </svg>
      <input v-model="query" maxlength="32" placeholder="Найти игрока по нику…" />
      <span v-if="searching" class="tp-search__spinner" />
    </div>
    <p v-else class="tp-hint">В скваде максимум {{ MAX }} игроков.</p>

    <ul v-if="results.length" class="tp-results">
      <li v-for="player in results" :key="player.id">
        <button
            type="button"
            class="tp-result"
            :disabled="takenIds.has(player.id)"
            @click="add(player)"
        >
          <span class="tp-avatar">
            <img v-if="player.avatar_url" :src="player.avatar_url" :alt="player.username" />
            <template v-else>{{ player.username.charAt(0).toUpperCase() }}</template>
          </span>
          <span class="tp-result__name">
            <span v-if="player.clan_tag" class="tp-result__clan" :style="{ color: player.clan_color || '#a78bfa' }">[{{ player.clan_tag }}]</span>
            {{ player.username }}
          </span>
          <span class="tp-tier" :style="{ '--tier': rankColor(player) }">{{ rankLabel(player) }}</span>
          <span class="tp-result__add">{{ takenIds.has(player.id) ? 'Уже в скваде' : '＋' }}</span>
        </button>
      </li>
    </ul>
    <p v-else-if="query.trim().length >= 2 && !searching" class="tp-hint">Никого не нашли.</p>

    <!-- Сквад -->
    <div v-for="(item, i) in items" :key="item.user_id || item.username" class="tp-item">
      <div class="tp-item__head">
        <span class="tp-avatar tp-avatar--small">{{ item.username.charAt(0).toUpperCase() }}</span>
        <strong>{{ item.username }}</strong>
        <div class="tp-item__actions">
          <button type="button" title="Выше" @click="move(i, -1)">↑</button>
          <button type="button" title="Ниже" @click="move(i, 1)">↓</button>
          <button type="button" class="tp-item__remove" title="Убрать" @click="items.splice(i, 1)">✕</button>
        </div>
      </div>

      <div class="tp-roles">
        <button
            v-for="role in TEAMMATE_ROLES"
            :key="role.id"
            type="button"
            class="tp-role"
            :class="{ 'is-on': item.role === role.id }"
            :style="{ '--role': role.color }"
            @click="item.role = role.id"
        >{{ role.icon }} {{ role.label }}</button>
      </div>

      <input v-model="item.note" maxlength="30" placeholder="Подпись: «вместе с 2024», «лучший клатчер»…" />
    </div>

    <p v-if="!items.length" class="tp-hint">Найди игрока выше — он появится в блоке карточкой с аватаром и тиром.</p>
  </div>
</template>

<style scoped>
.tp {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tp-hint {
  margin: 0;
  color: #8a8598;
  font-size: 12px;
}

/* Поиск */
.tp-search {
  position: relative;
}

.tp-search__icon {
  position: absolute;
  top: 50%;
  left: 12px;

  color: #8a8598;

  transform: translateY(-50%);
  pointer-events: none;
}

.tp-search input {
  padding-left: 36px !important;
}

.tp-search__spinner {
  position: absolute;
  top: 50%;
  right: 12px;

  width: 14px;
  height: 14px;
  margin-top: -7px;

  border: 2px solid rgba(255, 255, 255, 0.15);
  border-top-color: #a78bfa;
  border-radius: 50%;

  animation: tp-spin 0.7s linear infinite;
}

@keyframes tp-spin {
  to { transform: rotate(360deg); }
}

.tp-results {
  margin: 0;
  padding: 4px;

  list-style: none;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
}

.tp-result {
  width: 100%;

  display: flex;
  align-items: center;
  gap: 10px;

  padding: 7px 8px;

  color: inherit;
  background: none;
  border: 0;
  border-radius: 9px;

  text-align: left;
  cursor: pointer;
}

.tp-result:hover:not(:disabled) {
  background: rgba(139, 92, 246, 0.14);
}

.tp-result:disabled {
  opacity: 0.5;
  cursor: default;
}

.tp-result__name {
  min-width: 0;
  flex: 1;

  overflow: hidden;

  color: #fff;

  font-size: 13px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tp-result__clan {
  font-weight: 900;
}

.tp-result__add {
  color: #c4b5fd;
  font-size: 12px;
  font-weight: 800;
}

.tp-avatar {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: #2a2438;
  border-radius: 9px;

  font-weight: 900;
}

.tp-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tp-avatar--small {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  font-size: 11px;
}

.tp-tier {
  padding: 2px 7px;

  color: var(--tier);
  background: color-mix(in srgb, var(--tier) 16%, transparent);
  border-radius: 6px;

  font-size: 11px;
  font-weight: 900;
}

/* Добавленные */
.tp-item {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 10px;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
}

.tp-item__head {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tp-item__head strong {
  flex: 1;
  min-width: 0;

  overflow: hidden;

  color: #fff;

  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tp-item__actions {
  display: flex;
  gap: 3px;
}

.tp-item__actions button {
  width: 26px;
  height: 26px;

  color: #c4c0d0;
  background: rgba(255, 255, 255, 0.06);
  border: 0;
  border-radius: 7px;

  cursor: pointer;
}

.tp-item__actions .tp-item__remove {
  color: #f87171;
}

.tp-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.tp-role {
  padding: 4px 9px;

  color: #c4c0d0;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;

  font-size: 11px;
  font-weight: 700;

  cursor: pointer;
}

.tp-role.is-on {
  color: #fff;
  background: color-mix(in srgb, var(--role) 30%, transparent);
  border-color: var(--role);
}
</style>
