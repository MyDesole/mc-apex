<script setup>
/**
 * Подарок монет собеседнику прямо из диалога.
 *
 * Правила те же, что у подарка из кошелька: только другу, мин/макс,
 * дневной лимит и комиссия — всё проверяет сервер. Здесь только выбор
 * суммы и подсказка, сколько дойдёт.
 */
import { computed, onMounted, ref } from 'vue'
import { giftApi } from '@/services/shop/gift.js'
import { chatApi } from '@/services/chat/chat.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const props = defineProps({
  conversationId: { type: Number, required: true },
  partner: { type: Object, default: null },
})

const emit = defineEmits(['sent', 'close'])

const limits = ref(null)
const balance = ref(null)
const giftedToday = ref(0)
const amount = ref(100)
const note = ref('')
const sending = ref(false)
const error = ref('')

const PRESETS = [50, 100, 500, 1000]

const presets = computed(() => {
  const l = limits.value

  return l ? PRESETS.filter((value) => value >= l.min && value <= l.max) : PRESETS
})

const leftToday = computed(() => limits.value ? Math.max(0, limits.value.daily_limit - giftedToday.value) : null)

const fee = computed(() => limits.value ? Math.floor((Number(amount.value) || 0) * limits.value.fee_percent / 100) : 0)

const received = computed(() => Math.max(0, (Number(amount.value) || 0) - fee.value))

const problem = computed(() => {
  const value = Number(amount.value) || 0
  const l = limits.value

  if (!l) return ''
  if (!l.enabled) return 'Подарки временно отключены.'
  if (value < l.min || value > l.max) return `Сумма — от ${l.min} до ${l.max.toLocaleString('ru-RU')}.`
  if (leftToday.value !== null && value > leftToday.value) return `Сегодня можно подарить ещё ${leftToday.value}.`
  if (balance.value !== null && value > balance.value) return 'Не хватает монет.'

  return ''
})

onMounted(async () => {
  try {
    const data = await giftApi.limits()

    limits.value = data.limits
    balance.value = data.balance
    giftedToday.value = data.gifted_today ?? 0
  } catch {
    // Лимиты не загрузились — проверит сервер
  }
})

async function send() {
  if (sending.value || problem.value) return

  sending.value = true
  error.value = ''

  try {
    const data = await chatApi.gift(props.conversationId, Number(amount.value), note.value.trim())

    emit('sent', data)
  } catch (e) {
    error.value = e.message || 'Не удалось подарить.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="gift-panel" @click.stop>
    <header class="gift-panel__head">
      <PixelSprite sprite="diamond" :size="18" anim="bounce" />
      <strong>Подарок {{ partner?.username ? `для ${partner.username}` : '' }}</strong>
      <button type="button" class="gift-panel__close" aria-label="Закрыть" @click="emit('close')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
      </button>
    </header>

    <div class="gift-panel__presets">
      <button
          v-for="value in presets"
          :key="value"
          type="button"
          :class="{ 'is-on': Number(amount) === value }"
          @click="amount = value"
      >{{ value }}</button>
    </div>

    <label class="gift-panel__field">
      <span>Сумма, ApexCoin</span>
      <input v-model.number="amount" type="number" inputmode="numeric" :min="limits?.min ?? 1" :max="limits?.max">
    </label>

    <label class="gift-panel__field">
      <span>Подпись</span>
      <input v-model="note" type="text" maxlength="200" placeholder="Например: на новый кейс">
    </label>

    <p class="gift-panel__hint">
      <template v-if="balance !== null">На счету: <b>{{ balance.toLocaleString('ru-RU') }}</b>.</template>
      Дойдёт <b>{{ received.toLocaleString('ru-RU') }}</b><template v-if="fee"> (комиссия {{ fee }})</template>.
    </p>

    <p v-if="problem || error" class="gift-panel__error">{{ error || problem }}</p>

    <button type="button" class="gift-panel__send" :disabled="sending || Boolean(problem)" @click="send">
      {{ sending ? 'Дарим…' : `Подарить ${Number(amount) || 0}` }}
    </button>
  </div>
</template>

<style scoped>
.gift-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: min(300px, calc(100vw - 32px));
  padding: 14px;

  background: #17111f;
  border: 1px solid rgba(251, 191, 36, 0.35);
  border-radius: 10px;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.55);
}

.gift-panel__head {
  display: flex;
  align-items: center;
  gap: 8px;

  font-size: 14px;
}

.gift-panel__head strong {
  flex: 1;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.gift-panel__close {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;

  color: inherit;

  background: transparent;
  border: 0;
  opacity: 0.6;

  cursor: pointer;
}

.gift-panel__presets {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.gift-panel__presets button {
  padding: 7px 0;

  font-weight: 700;
  color: inherit;

  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;

  cursor: pointer;
}

.gift-panel__presets button.is-on {
  color: #1a1206;

  background: #fbbf24;
  border-color: #fbbf24;
}

.gift-panel__field {
  display: flex;
  flex-direction: column;
  gap: 4px;

  font-size: 11px;
  opacity: 0.9;
}

.gift-panel__field input {
  padding: 8px 10px;

  font: inherit;
  font-size: 14px;
  color: inherit;

  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;
}

.gift-panel__hint {
  margin: 0;

  font-size: 12px;
  opacity: 0.75;
}

.gift-panel__error {
  margin: 0;

  font-size: 12px;
  color: #fca5a5;
}

.gift-panel__send {
  padding: 10px;

  font-weight: 800;
  color: #1a1206;

  background: #fbbf24;
  border: 0;
  border-radius: 6px;
  box-shadow: inset 0 -3px 0 rgba(0, 0, 0, 0.25);

  cursor: pointer;
}

.gift-panel__send:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
