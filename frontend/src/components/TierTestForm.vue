<script setup>
import { ref, watch } from 'vue'
import { tierTestsApi } from '@/services/tierTests.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'created'])

const loading = ref(false)
const error = ref('')
const success = ref(false)

const form = ref({
  mode: 'pvp',
  contact_type: 'discord',
  contact_value: '',
  preferred_time: '',
  notes: '',
})

// сброс формы при каждом открытии
watch(() => props.modelValue, (open) => {
  if (open) {
    success.value = false
    error.value = ''
    form.value = {
      mode: 'pvp',
      contact_type: 'discord',
      contact_value: '',
      preferred_time: '',
      notes: '',
    }
  }
})

function close() {
  emit('update:modelValue', false)
}

async function submit() {
  loading.value = true
  error.value = ''

  try {
    await tierTestsApi.create(form.value)
    success.value = true
    emit('created')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
  <!-- ===== МОДАЛКА ЗАПИСИ ===== -->
  <div v-if="modelValue && !success" class="modal-bg" @click.self="close">
    <div class="modal">
      <header class="modal-head">
        <h2>Запись на тир-тест</h2>
        <button class="close" @click="close" aria-label="Закрыть">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        </button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <div class="field">
          <label>Режим</label>
          <select v-model="form.mode">
            <option value="pvp">PvP (p-ранг)</option>
            <option value="bedwars">BedWars (b-ранг)</option>
          </select>
        </div>

        <div class="field">
          <label>Способ связи</label>
          <div class="contact-picker">
            <button
                type="button"
                class="contact-btn"
                :class="{ active: form.contact_type === 'discord' }"
                @click="form.contact_type = 'discord'"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="#5865f2">
                <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
              </svg>
              Discord
            </button>

            <button
                type="button"
                class="contact-btn"
                :class="{ active: form.contact_type === 'telegram' }"
                @click="form.contact_type = 'telegram'"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="#229ed9">
                <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z" />
              </svg>
              Telegram
            </button>
          </div>
        </div>

        <div class="field">
          <label>
            {{ form.contact_type === 'discord' ? 'Discord тег' : 'Telegram' }}
          </label>
          <input
              v-model="form.contact_value"
              type="text"
              :placeholder="form.contact_type === 'discord' ? 'username#0000 или @username' : '@username'"
              maxlength="128"
          />
          <small class="hint">Тестер свяжется с тобой по этому контакту</small>
        </div>

        <div class="field">
          <label>Удобное время</label>
          <input
              v-model="form.preferred_time"
              type="text"
              maxlength="128"
              placeholder="Например: сегодня 20:00–22:00, выходные днём"
          />
          <small class="hint">Когда тебе удобно пройти тест</small>
        </div>

        <div class="field">
          <label>Комментарий (опционально)</label>
          <textarea v-model="form.notes" rows="3" maxlength="1000" placeholder="Что хочешь показать?" />
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="close">Отмена</button>
        <button
            class="btn-submit"
            :disabled="loading || !form.contact_value || !form.preferred_time"
            @click="submit"
        >
          {{ loading ? 'Отправка...' : 'Записаться' }}
        </button>
      </footer>
    </div>
  </div>

  <!-- ===== SPLASH: УСПЕШНО ЗАПИСАН ===== -->
  <div v-if="modelValue && success" class="modal-bg" @click.self="close">
    <div class="splash">
      <div class="splash__icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="12" cy="12" r="10" stroke="var(--accent)" />
          <path d="M8 12l3 3 5-6" stroke="var(--accent)" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <h2 class="splash__title">Вы записаны!</h2>

      <p class="splash__text">
        Заявка на тир-тест принята.
        Ожидайте — тестер свяжется с вами по указанному контакту.
      </p>

      <div class="splash__info">
        <div class="info-row">
          <span class="info-label">Связь:</span>
          <span class="info-value">{{ form.contact_value }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Время:</span>
          <span class="info-value">{{ form.preferred_time }}</span>
        </div>
      </div>

      <button class="btn-ok" @click="close">Понятно</button>
    </div>
  </div>
  </Teleport>
</template>


<style scoped>
/* ============================================
   КНОПКА ОТКРЫТИЯ
   ============================================ */

.btn-open {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 20px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
  transition: all 0.2s;
}

.btn-open:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.35);
}

/* ============================================
   МОДАЛКА
   ============================================ */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 520px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--text);
}

.close {
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.15s;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

/* ============================================
   BODY / ФОРМА
   ============================================ */

.body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  font-size: 13px;
  outline: none;
  resize: vertical;
  transition: border-color 0.15s;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.hint {
  color: var(--text-muted);
  font-size: 11px;
}

/* ============================================
   СПОСОБ СВЯЗИ
   ============================================ */

.contact-picker {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.contact-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.contact-btn:hover {
  border-color: var(--border-hover);
  color: var(--text);
}

.contact-btn.active {
  border-color: var(--accent);
  background: rgba(124, 58, 237, 0.08);
  color: var(--text);
  box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.15);
}

/* ============================================
   ФУТЕР
   ============================================ */

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-submit {
  min-height: 42px;
  padding: 0 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.15s;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-cancel:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.btn-submit {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.btn-submit:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ============================================
   SPLASH: УСПЕШНО ЗАПИСАН
   ============================================ */

.splash {
  width: 100%;
  max-width: 440px;
  padding: 40px 32px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 20px;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
  text-align: center;
  animation: splashIn 0.3s ease;
}

@keyframes splashIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.splash__icon {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
  filter: drop-shadow(0 0 20px rgba(124, 58, 237, 0.4));
}

.splash__title {
  margin: 0 0 12px;
  font-size: 26px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}

.splash__text {
  margin: 0 0 24px;
  color: var(--text-dim);
  font-size: 14px;
  line-height: 1.6;
}

.splash__info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 24px;
  text-align: left;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  gap: 12px;
}

.info-label {
  color: var(--text-muted);
  font-weight: 700;
  flex-shrink: 0;
}

.info-value {
  color: var(--accent-light);
  font-weight: 700;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 60%;
}

.btn-ok {
  width: 100%;
  min-height: 46px;
  padding: 0 20px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-ok:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 8px 25px rgba(124, 58, 237, 0.4);
}

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 500px) {
  .modal {
    max-height: 96vh;
  }

  .modal-head,
  .body,
  .modal-foot {
    padding-left: 16px;
    padding-right: 16px;
  }

  .splash {
    padding: 32px 22px;
  }

  .splash__title {
    font-size: 22px;
  }

  .contact-picker {
    grid-template-columns: 1fr;
  }

  .modal-foot {
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-submit {
    width: 100%;
  }
}
</style>