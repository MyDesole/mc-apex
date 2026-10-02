<script setup>
import { alert as alertDialog, confirm as confirmDialog, prompt as promptDialog } from '@/utils/dialog.js'
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { testerApi } from '@/services/tiers/tester.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  tierTest: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const auth = useAuthStore()

// Читаем проп напрямую: ref(props.tierTest) запоминал первый открытый
// тест, и при переключении на другой режим список аспектов не менялся
const test = computed(() => props.tierTest)
const error = ref('')
const processing = ref(false)

const isMine = computed(() => test.value.claimed_by === auth.user.id)
const isCompleted = computed(() => test.value.status === 'completed')
const isPending = computed(() => test.value.status === 'pending')
const isInProgress = computed(() => test.value.status === 'in_progress')

const ASPECT_LABELS = {
  pvp: {
    block_placing: 'БП',
    rotka: 'Ротка',
    movement: 'Мувмент',
    aim: 'Аим',
    game_sense: 'Понимание боя',
  },
  bedwars: {
    pvp: 'PvP',
    game_sense: 'Понимание игры',
    bed_play: 'Игра на кровати',
    teamplay: 'Командная игра',
    building: 'Строительство',
  },
}

// Список полей строго по режиму заявки. Неизвестный режим считаем PvP.
const labels = computed(() => ASPECT_LABELS[test.value?.mode] ?? ASPECT_LABELS.pvp)

// Заголовок блока оценок: помогает тестеру не перепутать режимы
const modeTitle = computed(() => (test.value?.mode === 'bedwars' ? 'BedWars' : 'PvP'))

// Пустая форма: все поля обоих режимов, используем нужные по labels
function emptyForm() {
  return {
    block_placing: 0,
    rotka: 0,
    movement: 0,
    aim: 0,
    game_sense: 0,
    pvp: 0,
    bed_play: 0,
    teamplay: 0,
    building: 0,
    notes: '',
  }
}

/**
 * Собирает форму оценок под текущую заявку.
 *
 * Аспекты у режимов разные: в PvP это aim и game_sense, в BedWars —
 * pvp, bed_play, teamplay и building. Поэтому форму пересобираем при
 * каждом переключении заявки, иначе в модалке остаются поля
 * предыдущего режима.
 */
function formForCurrentTest() {
  const base = emptyForm()

  if (isCompleted.value && test.value.aspects) {
    return { ...base, ...test.value.aspects, notes: test.value.notes ?? '' }
  }

  return base
}

const form = ref(formForCurrentTest())

// Переключают заявку или меняют режим — форма пересобирается
watch(
    () => [test.value?.id, test.value?.mode],
    () => { form.value = formForCurrentTest() },
    { immediate: true }
)

// Сумма баллов по активным полям (макс. 100)
const sum = computed(() => {
  const l = labels.value
  return Object.keys(l).reduce((acc, key) => acc + (Number(form.value[key]) || 0), 0)
})

// Процент = сумма (шкала 0–100 без умножения)
const percent = computed(() => sum.value)

// Тир по новой сетке: A — 71+, S здесь НЕ выдаётся (только за турниры)
const tier = computed(() => {
  const p = percent.value
  if (p >= 71) return 'A'
  if (p >= 56) return 'B'
  if (p >= 41) return 'C'
  if (p >= 21) return 'D'
  return 'E'
})

const tierColors = {
  'S+': '#fbbf24',
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

async function claim() {
  processing.value = true
  error.value = ''
  try {
    await testerApi.claim(test.value.id)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function unclaim() {
  if (!await confirmDialog('Отказаться от заявки?')) return
  processing.value = true
  try {
    await testerApi.unclaim(test.value.id)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function complete() {
  if (!await confirmDialog(`Провести тест? Итог: тир ${tier.value} (${percent.value}%)`)) return

  processing.value = true
  error.value = ''

  try {
    // отправляем только нужные поля под текущий режим
    const payload = { notes: form.value.notes }
    for (const key of Object.keys(labels.value)) {
      payload[key] = form.value[key]
    }

    await testerApi.complete(test.value.id, payload)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function cancel() {
  const reason = await promptDialog('Причина отмены (опционально):')
  if (reason === null) return

  processing.value = true
  try {
    await testerApi.cancel(test.value.id, reason)
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    processing.value = false
  }
}

async function copyContact(value) {
  navigator.clipboard.writeText(value)
  await alertDialog('Скопировано: ' + value)
}

function avatarLetter(username) {
  return (username || 'И').charAt(0).toUpperCase()
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <div>
          <h2>Тир-тест · {{ test.mode === 'pvp' ? 'PvP' : 'BedWars' }}</h2>
          <span class="sub">{{ new Date(test.created_at).toLocaleString('ru-RU') }}</span>
        </div>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Игрок -->
        <RouterLink :to="userLink(test.user)" class="player-card">
          <div class="avatar">
            <img v-if="test.user.avatar_url" :src="test.user.avatar_url" class="avatar-img" />
            <template v-else>{{ avatarLetter(test.user.username) }}</template>
          </div>
          <div class="player-info">
            <div class="player-name">
              <span v-if="test.user.clan_member?.clan" class="clan-tag">
                [{{ test.user.clan_member.clan.tag }}]
              </span>
              {{ test.user.username }}
            </div>
            <div class="player-meta">
              Текущий тир: <b>{{ test.user.tier }}</b> · {{ test.user.tier_score }}%
            </div>
          </div>
        </RouterLink>

        <!-- Заметка игрока -->
        <div v-if="test.notes && !isInProgress && !isCompleted" class="note">
          <span class="note__label">Заметка игрока:</span>
          <span class="note__text">{{ test.notes }}</span>
        </div>

        <!-- Контакт -->
        <div v-if="test.contact_value" class="contact-block">
          <div class="contact-block__title">Контакт для связи</div>

          <div class="contact-row">
            <div class="contact-type">
              <span v-if="test.contact_type === 'discord'" class="type-badge discord">Discord</span>
              <span v-else class="type-badge telegram">Telegram</span>
            </div>

            <div class="contact-value">
              <code>{{ test.contact_value }}</code>
              <button class="btn-copy" @click="copyContact(test.contact_value)" title="Скопировать">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="9" width="13" height="13" rx="2" />
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                </svg>
              </button>
            </div>
          </div>

          <div v-if="test.preferred_time" class="contact-row">
            <div class="contact-type">
              <span class="type-badge time">Удобное время</span>
            </div>
            <div class="contact-value">{{ test.preferred_time }}</div>
          </div>
        </div>

        <!-- PENDING -->
        <template v-if="isPending">
          <div class="hint">
            Эта заявка свободна. Нажми «Взять в работу», чтобы начать тест.
          </div>
          <button class="btn-primary" :disabled="processing" @click="claim">
            {{ processing ? '...' : 'Взять в работу' }}
          </button>
        </template>

        <!-- IN_PROGRESS + MINE -->
        <template v-else-if="isInProgress && isMine">
          <div class="mode-note" :class="`mode-note--${test.mode}`">
            <span class="mode-note__title">Режим: {{ modeTitle }}</span>
            <span class="mode-note__hint">
              {{ test.mode === 'bedwars'
                  ? 'Оцениваются PvP, понимание игры, игра на кровати, командная игра и строительство'
                  : 'Оцениваются БП, ротка, мувмент, аим и понимание боя' }}
            </span>
          </div>

          <div class="form">
            <div
                v-for="(label, key) in labels"
                :key="key"
                class="aspect-row"
            >
              <span class="aspect-label">{{ label }}</span>
              <input
                  v-model.number="form[key]"
                  type="range"
                  min="0"
                  max="20"
                  class="slider"
              />
              <input
                  v-model.number="form[key]"
                  type="number"
                  min="0"
                  max="20"
                  class="value"
              />
            </div>
          </div>

          <textarea
              v-model="form.notes"
              rows="3"
              placeholder="Заметки тестера (опционально)"
              class="notes"
          />

          <div class="result" :style="{ '--tier-color': tierColors[tier] }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 100</span>
            </div>
            <div class="result__block">
              <span class="result__label">Процент</span>
              <span class="result__value accent">{{ percent }}%</span>
            </div>
            <div class="result__block">
              <span class="result__label">Итоговый тир</span>
              <span class="result__value tier">{{ tier }}</span>
            </div>
          </div>

          <div class="actions">
            <button class="btn-cancel" :disabled="processing" @click="unclaim">
              Вернуть в очередь
            </button>
            <button class="btn-danger" :disabled="processing" @click="cancel">
              Отменить
            </button>
            <button class="btn-primary flex-1" :disabled="processing" @click="complete">
              {{ processing ? '...' : 'Завершить тест' }}
            </button>
          </div>
        </template>

        <!-- IN_PROGRESS, но у другого -->
        <template v-else-if="isInProgress && !isMine">
          <div class="hint hint--warn">
            Эту заявку уже взял {{ test.claimer?.username ?? test.tester?.username }}.
          </div>
        </template>

        <!-- COMPLETED -->
        <template v-else-if="isCompleted">
          <div class="result result--final" :style="{ '--tier-color': tierColors[test.result_tier] || '#6b7280' }">
            <div class="result__block">
              <span class="result__label">Балл</span>
              <span class="result__value">{{ sum }} / 100</span>
            </div>
            <div class="result__block">
              <span class="result__label">Процент</span>
              <span class="result__value accent">{{ test.result_score }}%</span>
            </div>
            <div class="result__block">
              <span class="result__label">Тир</span>
              <span class="result__value tier">{{ test.result_tier }}</span>
            </div>
          </div>

          <div v-if="test.notes" class="notes-view">
            <span class="notes-view__label">Заметки:</span>
            <p>{{ test.notes }}</p>
          </div>
        </template>

        <!-- CANCELLED -->
        <template v-else-if="test.status === 'cancelled'">
          <div class="hint hint--warn">
            Заявка отменена.
            <span v-if="test.notes">Причина: {{ test.notes }}</span>
          </div>
        </template>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Закрыть</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/tiers/tester/TesterTierTestModal.css";
</style>
