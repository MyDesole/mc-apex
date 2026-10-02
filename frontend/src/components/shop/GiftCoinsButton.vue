<script setup>
import { computed, ref } from 'vue'

import { giftApi } from '@/services/shop/gift.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import { formatCoins } from '@/data/wallet/economy.js'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
})

const auth = useAuthStore()

const open = ref(false)
const amount = ref(100)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const limits = ref(null)

const balance = computed(() => auth.user?.apex_coins ?? 0)

const numericAmount = computed(() => {
  const value = Number(amount.value)
  return Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0
})

const feePercent = computed(() => {
  return Number(limits.value?.fee_percent ?? 0)
})

const fee = computed(() => {
  return Math.floor(
      numericAmount.value * feePercent.value / 100
  )
})

const received = computed(() => {
  return Math.max(
      0,
      numericAmount.value - fee.value
  )
})

const minAmount = computed(() => {
  return Number(limits.value?.min ?? 1)
})

const maxAmount = computed(() => {
  return Number(limits.value?.max ?? Infinity)
})

const canSend = computed(() => {
  const value = numericAmount.value

  return (
      value > 0 &&
      value >= minAmount.value &&
      value <= maxAmount.value &&
      value <= balance.value
  )
})

const amountTooLow = computed(() => {
  return (
      numericAmount.value > 0 &&
      limits.value &&
      numericAmount.value < minAmount.value
  )
})

const amountTooHigh = computed(() => {
  return (
      limits.value &&
      numericAmount.value > maxAmount.value
  )
})

const insufficientBalance = computed(() => {
  return (
      numericAmount.value > 0 &&
      numericAmount.value > balance.value
  )
})

const QUICK = [50, 100, 250, 500, 1000]

async function openModal() {
  error.value = ''
  notice.value = ''
  open.value = true

  try {
    limits.value = await giftApi.limits()
  } catch (e) {
    error.value = e.message || 'Не удалось получить лимиты подарков.'
  }
}

function closeModal() {
  if (busy.value) return

  open.value = false
  error.value = ''
  notice.value = ''
}

function selectAmount(value) {
  amount.value = value
  error.value = ''
  notice.value = ''
}

async function send() {
  if (!canSend.value || busy.value) return

  busy.value = true
  error.value = ''
  notice.value = ''

  try {
    const result = await giftApi.send(
        props.user.id,
        numericAmount.value
    )

    notice.value = result.message

    limits.value = {
      ...(limits.value ?? {}),
      balance: result.balance,
    }

    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось отправить подарок.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <!-- =====================================================
       КНОПКА
  ====================================================== -->

  <button
      class="gift-btn"
      type="button"
      @click="openModal"
  >
    <span class="gift-btn__icon">
      <AppIcon
          icon="gift"
          :size="16"
          color="currentColor"
      />
    </span>

    <span class="gift-btn__text">
      Подарить ApexCoin
    </span>

    <span class="gift-btn__arrow">
      <AppIcon
          icon="send"
          :size="12"
          color="currentColor"
      />
    </span>
  </button>

  <!-- =====================================================
       MODAL
  ====================================================== -->

  <Teleport to="body">
    <Transition name="gift-modal">
      <div
          v-if="open"
          class="modal"
          @click.self="closeModal"
      >
        <div class="modal__backdrop-glow"></div>

        <div class="modal__box">

          <!-- HEADER -->

          <div class="modal__header">

            <div class="modal__title-wrap">
              <div class="modal__eyebrow">
                APEX / GIFT
              </div>

              <h3>
                Подарок игроку
              </h3>

              <p>
                Отправьте ApexCoin другому игроку.
              </p>
            </div>

            <button
                class="modal__close"
                type="button"
                aria-label="Закрыть"
                :disabled="busy"
                @click="closeModal"
            >
              <AppIcon
                  icon="close"
                  :size="17"
                  color="currentColor"
              />
            </button>

          </div>

          <!-- RECIPIENT -->

          <div class="recipient">

            <div class="recipient__avatar">
              <img
                  v-if="user.avatar_url"
                  :src="user.avatar_url"
                  :alt="user.username"
              />

              <template v-else>
                {{ (user.username || '?').charAt(0).toUpperCase() }}
              </template>
            </div>

            <div class="recipient__info">
              <span class="recipient__label">
                Получатель
              </span>

              <strong>
                {{ user.username }}
              </strong>
            </div>

            <div class="recipient__icon">
              <AppIcon
                  icon="gift"
                  :size="17"
                  color="currentColor"
              />
            </div>

          </div>

          <!-- BALANCE -->

          <div class="balance-card">

            <div class="balance-card__icon">
              <AppIcon
                  icon="coin"
                  :size="18"
                  color="currentColor"
              />
            </div>

            <div class="balance-card__info">
              <span>
                Ваш баланс
              </span>

              <strong>
                ₳ {{ formatCoins(balance) }}
              </strong>
            </div>

            <span class="balance-card__status">
              Доступно
            </span>

          </div>

          <!-- ALERT -->

          <div
              v-if="error"
              class="alert alert--error"
          >
            <span class="alert__icon">
              <AppIcon
                  icon="bolt"
                  :size="15"
                  color="currentColor"
              />
            </span>

            <span>{{ error }}</span>
          </div>

          <div
              v-if="notice"
              class="alert alert--ok"
          >
            <span class="alert__icon">
              <AppIcon
                  icon="check"
                  :size="15"
                  color="currentColor"
              />
            </span>

            <span>{{ notice }}</span>
          </div>

          <!-- AMOUNT -->

          <div class="amount-section">

            <div class="section-label">
              <span>Сумма подарка</span>

              <span
                  v-if="limits?.min && limits?.max"
                  class="section-label__hint"
              >
                {{ formatCoins(limits.min) }}
                —
                {{ formatCoins(limits.max) }}
              </span>
            </div>

            <div class="amount-input">

              <span class="amount-input__coin">
                ₳
              </span>

              <input
                  v-model.number="amount"
                  type="number"
                  min="1"
                  step="10"
                  inputmode="numeric"
                  @focus="$event.target.select()"
              />

              <span class="amount-input__currency">
                APEX
              </span>

            </div>

          </div>

          <!-- QUICK -->

          <div class="quick">

            <button
                v-for="value in QUICK"
                :key="value"
                type="button"
                class="quick__btn"
                :class="{ active: numericAmount === value }"
                :disabled="busy"
                @click="selectAmount(value)"
            >
              <span>₳</span>
              {{ formatCoins(value) }}
            </button>

          </div>

          <!-- VALIDATION -->

          <div
              v-if="amountTooLow"
              class="validation validation--warning"
          >
            <AppIcon
                icon="bolt"
                :size="14"
                color="currentColor"
            />

            Минимальная сумма —
            <strong>₳ {{ formatCoins(minAmount) }}</strong>
          </div>

          <div
              v-else-if="amountTooHigh"
              class="validation validation--error"
          >
            <AppIcon
                icon="bolt"
                :size="14"
                color="currentColor"
            />

            Максимальная сумма —
            <strong>₳ {{ formatCoins(maxAmount) }}</strong>
          </div>

          <div
              v-else-if="insufficientBalance"
              class="validation validation--error"
          >
            <AppIcon
                icon="coin"
                :size="14"
                color="currentColor"
            />

            Недостаточно ApexCoin на балансе
          </div>

          <!-- CALCULATION -->

          <div class="calculation">

            <div class="calculation__row">
              <span>Вы отправляете</span>

              <strong>
                ₳ {{ formatCoins(numericAmount) }}
              </strong>
            </div>

            <div
                v-if="fee > 0"
                class="calculation__row calculation__row--fee"
            >
              <span>
                Комиссия
                <small>{{ feePercent }}%</small>
              </span>

              <strong>
                − ₳ {{ formatCoins(fee) }}
              </strong>
            </div>

            <div class="calculation__divider"></div>

            <div class="calculation__row calculation__row--received">
              <span>
                Получит {{ user.username }}
              </span>

              <strong>
                ₳ {{ formatCoins(received) }}
              </strong>
            </div>

          </div>

          <!-- LIMITS -->

          <div
              v-if="limits?.daily_limit"
              class="limits"
          >
            <div class="limits__icon">
              <AppIcon
                  icon="chart"
                  :size="14"
                  color="currentColor"
              />
            </div>

            <div>
              <span>Дневной лимит</span>

              <strong>
                ₳ {{ formatCoins(limits.daily_limit) }}
              </strong>
            </div>
          </div>

          <!-- ACTIONS -->

          <div class="modal__actions">

            <button
                class="btn btn--ghost"
                type="button"
                :disabled="busy"
                @click="closeModal"
            >
              Закрыть
            </button>

            <button
                class="btn btn--gift"
                type="button"
                :disabled="busy || !canSend"
                @click="send"
            >
              <AppIcon
                  v-if="!busy"
                  icon="gift"
                  :size="16"
                  color="currentColor"
              />

              <span
                  v-if="busy"
                  class="btn__spinner"
              ></span>

              <span>
                {{ busy ? 'Отправляем…' : 'Подарить ApexCoin' }}
              </span>
            </button>

          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@import "@/components/shop/GiftCoinsButton.css";
</style>
