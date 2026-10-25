<script setup>
/**
 * Выбор игрока для сравнения: поиск по нику с подсказками.
 */
import { onUnmounted, ref, watch } from 'vue'
import { searchApi } from '@/services/search/search.js'
import { tierColors } from '@/data/players/profileCustomization.js'

defineProps({
  placeholder: { type: String, default: 'Ник игрока' },
  exclude: { type: String, default: null },
})

const emit = defineEmits(['select'])

const query = ref('')
const players = ref([])
const open = ref(false)
const active = ref(0)

let timer = null
let requestId = 0

watch(query, (value) => {
  clearTimeout(timer)
  active.value = 0

  const q = value.trim()

  if (q.length < 2) {
    players.value = []
    return
  }

  timer = setTimeout(async () => {
    const id = ++requestId

    try {
      const data = await searchApi.query(q)

      if (id === requestId) {
        players.value = data.players ?? []
        open.value = true
      }
    } catch {
      if (id === requestId) players.value = []
    }
  }, 140)
})

function pick(player) {
  if (!player) return

  emit('select', player.username)
  query.value = ''
  players.value = []
  open.value = false
}

function onKeydown(event) {
  const total = players.value.length

  if (event.key === 'ArrowDown' && total) {
    event.preventDefault()
    active.value = (active.value + 1) % total
  } else if (event.key === 'ArrowUp' && total) {
    event.preventDefault()
    active.value = (active.value - 1 + total) % total
  } else if (event.key === 'Enter') {
    event.preventDefault()
    pick(players.value[active.value])
  } else if (event.key === 'Escape') {
    open.value = false
  }
}

onUnmounted(() => clearTimeout(timer))
</script>

<template>
  <div class="picker">
    <input
        v-model="query"
        class="picker__input"
        type="text"
        :placeholder="placeholder"
        autocomplete="off"
        spellcheck="false"
        @focus="open = true"
        @blur="open = false"
        @keydown="onKeydown"
    />

    <ul
        v-if="open && players.length"
        class="picker__list"
        role="listbox"
    >
      <li
          v-for="(player, index) in players"
          :key="player.id"
          class="picker__item"
          :class="{
            'picker__item--active': index === active,
            'picker__item--disabled': player.username === exclude,
          }"
          role="option"
          :aria-selected="index === active"
          @mousedown.prevent="player.username !== exclude && pick(player)"
          @mousemove="active = index"
      >
        <span
            class="tier-block picker__tier"
            :style="{ '--tier': tierColors[player.tier] || '#a78bfa' }"
        >{{ player.tier }}</span>

        <span class="picker__name">
          <span
              v-if="player.clan_tag"
              class="picker__tag"
              :style="player.clan_color ? { color: player.clan_color } : {}"
          >[{{ player.clan_tag }}]</span>
          {{ player.username }}
        </span>

        <span class="picker__score">{{ player.tier_score }}%</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.picker {
  position: relative;
  width: 100%;
}

.picker__input {
  width: 100%;
  min-height: 44px;

  padding: 10px 14px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);

  font-size: 15px;

  outline: none;
}

.picker__input:focus {
  border-color: var(--accent);
}

.picker__list {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 20;

  margin: 0;
  padding: 6px;

  list-style: none;

  background: var(--bg-card);
  border: 1px solid var(--border-hover);
  border-radius: var(--radius);

  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
}

.picker__item {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 7px 8px;

  border-radius: 4px;

  cursor: pointer;
}

.picker__item--active {
  background: var(--accent-soft);
}

.picker__item--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.picker__tier {
  width: 26px;
  height: 26px;
  font-size: 12px;
}

.picker__name {
  flex: 1;
  min-width: 0;

  overflow: hidden;

  color: var(--text);

  font-weight: 600;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.picker__tag {
  margin-right: 4px;
  color: var(--accent-light);
}

.picker__score {
  color: var(--text-muted);
  font-size: 13px;
}
</style>
