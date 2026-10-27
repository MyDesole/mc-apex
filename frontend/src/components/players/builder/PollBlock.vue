<script setup>
/**
 * Опрос в профиле: гости голосуют, результаты видны сразу полосками.
 * Голосовать можно и после — голос переезжает на новый вариант.
 */
import { computed, onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  owner: { type: Object, required: true },
  blockId: { type: String, required: true },
  question: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  preview: { type: Boolean, default: false },
})

const auth = useAuthStore()

const votes = ref([])
const total = ref(0)
const mine = ref(null)
const busy = ref(false)
const error = ref('')

const options = computed(() => props.options.filter((o) => o !== null && o !== undefined))

// Пока не голосовал — варианты кнопками, после — результаты
const showResults = computed(() => mine.value !== null || props.preview || !auth.isAuthenticated)

function percent(i) {
  return total.value ? Math.round(((votes.value[i] || 0) / total.value) * 100) : 0
}

async function load() {
  if (props.preview) return
  try {
    apply(await api.get(`/players/${props.owner.id}/polls/${props.blockId}`))
  } catch {
    // Опрос только что добавили и ещё не сохранили — голосов нет
  }
}

function apply(data) {
  votes.value = data.votes ?? []
  total.value = data.total ?? 0
  mine.value = data.mine ?? null
}

async function vote(i) {
  if (busy.value || props.preview) return
  if (!auth.isAuthenticated) {
    error.value = 'Войди, чтобы голосовать.'
    return
  }

  busy.value = true
  error.value = ''
  try {
    apply(await api.post(`/players/${props.owner.id}/polls/${props.blockId}`, { option: i }))
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="poll">
    <p v-if="question" class="poll__question">{{ question }}</p>

    <div class="poll__options">
      <button
          v-for="(option, i) in options"
          :key="i"
          type="button"
          class="poll__option"
          :class="{ 'is-mine': mine === i, 'is-results': showResults }"
          :disabled="busy || preview"
          @click="vote(i)"
      >
        <span v-if="showResults" class="poll__fill" :style="{ width: `${percent(i)}%` }"></span>
        <span class="poll__label">{{ option || `Вариант ${i + 1}` }}</span>
        <span v-if="showResults" class="poll__pct">{{ percent(i) }}%</span>
      </button>
    </div>

    <p class="poll__meta">
      {{ total }} {{ total % 10 === 1 && total % 100 !== 11 ? 'голос' : (total % 10 >= 2 && total % 10 <= 4 && (total % 100 < 12 || total % 100 > 14)) ? 'голоса' : 'голосов' }}
      <template v-if="mine !== null"> · можно переголосовать</template>
    </p>
    <p v-if="error" class="poll__error">{{ error }}</p>
  </div>
</template>

<style scoped>
.poll {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.poll__question {
  margin: 0;

  color: var(--text, #fff);

  font-size: 15px;
  font-weight: 800;
}

.poll__options {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.poll__option {
  position: relative;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;

  padding: 10px 12px;

  color: var(--text, #fff);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;

  font: inherit;
  font-size: 13px;
  font-weight: 700;
  text-align: left;

  cursor: pointer;
  transition: border-color 0.15s ease, transform 0.15s ease;
}

.poll__option:hover:not(:disabled) {
  border-color: var(--accent-color, #8b5cf6);
  transform: translateX(2px);
}

.poll__option:disabled {
  cursor: default;
}

.poll__option.is-mine {
  border-color: var(--accent-color, #8b5cf6);
  box-shadow: 0 0 0 1px var(--accent-color, #8b5cf6);
}

.poll__fill {
  position: absolute;
  inset: 0 auto 0 0;
  z-index: 0;

  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 28%, transparent);
  border-radius: inherit;

  transition: width 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.poll__label,
.poll__pct {
  position: relative;
  z-index: 1;
}

.poll__pct {
  color: var(--accent-color, #c4b5fd);
  font-weight: 900;
}

.poll__meta {
  margin: 0;
  color: var(--text-muted, #8a8598);
  font-size: 11px;
}

.poll__error {
  margin: 0;
  color: #fca5a5;
  font-size: 12px;
}
</style>
