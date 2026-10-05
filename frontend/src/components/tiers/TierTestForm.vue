<script setup>
import { ref, watch } from 'vue'
import { tierTestsApi } from '@/services/tiers/tierTests.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  /*
   * Активная заявка игрока, если она есть. Пока заявка в очереди или в
   * работе, вторую создавать нельзя: сервер такую попытку отклонит.
   */
  activeTest: { type: Object, default: null },
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

      <div v-if="!activeTest" class="body">
        <!--
          Заявка уже есть: вместо полей показываем её состояние, чтобы
          человек не заполнял форму зря.
        -->
        <div
            v-if="activeTest"
            class="active-notice"
        >
          <div class="active-notice__title">
            {{
              activeTest.status === 'in_progress'
                ? 'Тест уже идёт'
                : 'Заявка уже отправлена'
            }}
          </div>

          <div class="active-notice__text">
            {{
              activeTest.status === 'in_progress'
                ? 'Тестер взял твою заявку в работу. Дождись результата.'
                : 'Заявка ждёт тестера. Вторую создавать не нужно — дождись этой.'
            }}
          </div>

          <div class="active-notice__meta">
            <span>{{ activeTest.mode === 'pvp' ? 'PvP' : 'BedWars' }}</span>

            <span class="sep">·</span>

            <span>
              {{ new Date(activeTest.created_at).toLocaleDateString('ru-RU') }}
            </span>
          </div>
        </div>

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
        <button class="btn-cancel" @click="close">
          {{ activeTest ? 'Понятно' : 'Отмена' }}
        </button>

        <button
            v-if="!activeTest"
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
@import "@/components/tiers/TierTestForm.css";
</style>
