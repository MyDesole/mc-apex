<script setup>
/**
 * Окно «Пожаловаться»: причина и комментарий. Жалоба уходит модераторам
 * в админку, о решении игрок получит уведомление.
 */
import { computed, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import { closeReport, reportState } from '@/composables/core/useReport.js'

const REASONS = [
  { key: 'cheating', label: 'Читы' },
  { key: 'insult', label: 'Оскорбления' },
  { key: 'spam', label: 'Спам или реклама' },
  { key: 'scam', label: 'Мошенничество' },
  { key: 'nickname', label: 'Ник или аватар' },
  { key: 'other', label: 'Другое' },
]

const TITLES = {
  user: 'Пожаловаться на игрока',
  message: 'Пожаловаться на сообщение',
  forum_topic: 'Пожаловаться на тему',
  forum_reply: 'Пожаловаться на ответ',
}

const reason = ref('')
const comment = ref('')
const sending = ref(false)
const error = ref('')
const sent = ref(false)

const title = computed(() => TITLES[reportState.type] ?? 'Жалоба')

watch(() => reportState.open, (open) => {
  if (open) {
    reason.value = ''
    comment.value = ''
    error.value = ''
    sent.value = false
  }
})

async function submit() {
  if (!reason.value || sending.value) return

  sending.value = true
  error.value = ''

  try {
    await api.post('/reports', {
      type: reportState.type,
      id: reportState.id,
      reason: reason.value,
      comment: comment.value.trim() || null,
    })
    sent.value = true
  } catch (e) {
    error.value = e.message || 'Не удалось отправить жалобу'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="reportState.open" class="report-backdrop" @click.self="closeReport">
      <div class="report-dialog" role="dialog" :aria-label="title">
        <template v-if="!sent">
          <h3>{{ title }}</h3>
          <p v-if="reportState.name" class="report-dialog__name">{{ reportState.name }}</p>

          <div class="report-dialog__reasons" role="radiogroup" aria-label="Причина">
            <button
                v-for="r in REASONS"
                :key="r.key"
                type="button"
                role="radio"
                :aria-checked="reason === r.key"
                :class="{ active: reason === r.key }"
                @click="reason = r.key"
            >
              {{ r.label }}
            </button>
          </div>

          <textarea
              v-model="comment"
              rows="3"
              maxlength="1000"
              placeholder="Подробности: что произошло, где и когда (необязательно)"
          />

          <p v-if="error" class="report-dialog__error">{{ error }}</p>

          <div class="report-dialog__actions">
            <button type="button" class="btn btn-secondary" @click="closeReport">Отмена</button>
            <button type="button" class="btn btn-primary" :disabled="!reason || sending" @click="submit">
              {{ sending ? 'Отправка…' : 'Отправить' }}
            </button>
          </div>
        </template>

        <template v-else>
          <h3>Жалоба отправлена</h3>
          <p class="report-dialog__name">
            Модераторы рассмотрят её в ближайшее время — о решении придёт уведомление.
          </p>
          <div class="report-dialog__actions">
            <button type="button" class="btn btn-primary" @click="closeReport">Понятно</button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.report-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 16px;

  background: rgba(0, 0, 0, 0.6);
}

.report-dialog {
  display: flex;
  flex-direction: column;
  gap: 12px;

  width: min(440px, 100%);
  padding: 20px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.report-dialog h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 18px;
}

.report-dialog__name {
  margin: 0;
  color: var(--text-muted);
  font-size: 14px;
}

.report-dialog__reasons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.report-dialog__reasons button {
  padding: 7px 12px;

  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 13px;

  cursor: pointer;
}

.report-dialog__reasons button.active {
  color: var(--text);
  background: rgba(239, 68, 68, 0.12);
  border-color: #ef4444;
}

.report-dialog textarea {
  padding: 10px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 14px;

  resize: vertical;
}

.report-dialog__error {
  margin: 0;
  color: #fca5a5;
  font-size: 13px;
}

.report-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
