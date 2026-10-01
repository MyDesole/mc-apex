<script setup>
import { computed, ref } from 'vue'

import { giftApi } from '@/services/gift.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'
import { formatCoins } from '@/data/economy.js'

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
/* =========================================================
   GIFT BUTTON
========================================================= */

.gift-btn {
  position: relative;

  min-height: 38px;

  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 0 10px 0 5px;

  overflow: hidden;

  color: #f7d774;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.12),
          rgba(250, 204, 21, 0.035)
      );

  border: 1px solid rgba(250, 204, 21, 0.24);
  border-radius: 10px;

  font-size: 12px;
  font-weight: 800;

  cursor: pointer;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, 0.05),
      0 5px 18px rgba(250, 204, 21, 0.04);

  transition:
      color 0.2s ease,
      background 0.2s ease,
      border-color 0.2s ease,
      transform 0.2s ease,
      box-shadow 0.2s ease;
}

.gift-btn:hover {
  color: #ffe7a0;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.18),
          rgba(250, 204, 21, 0.055)
      );

  border-color: rgba(250, 204, 21, 0.42);

  transform: translateY(-1px);

  box-shadow:
      inset 0 1px rgba(255, 255, 255, 0.07),
      0 8px 25px rgba(250, 204, 21, 0.10);
}

.gift-btn__icon {
  width: 28px;
  height: 28px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #facc15;

  background: rgba(250, 204, 21, 0.12);

  border: 1px solid rgba(250, 204, 21, 0.18);
  border-radius: 7px;

  transition:
      transform 0.2s ease,
      background 0.2s ease;
}

.gift-btn:hover .gift-btn__icon {
  transform: rotate(-4deg) scale(1.04);

  background: rgba(250, 204, 21, 0.19);
}

.gift-btn__text {
  white-space: nowrap;
}

.gift-btn__arrow {
  width: 15px;
  height: 15px;

  margin-left: 2px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: rgba(250, 204, 21, 0.42);

  transform: rotate(-45deg);

  transition:
      color 0.2s ease,
      transform 0.2s ease;
}

.gift-btn:hover .gift-btn__arrow {
  color: #facc15;
  transform: rotate(-45deg) translate(2px, -2px);
}

/* =========================================================
   MODAL
========================================================= */

.modal {
  position: fixed;
  inset: 0;

  z-index: 9999;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 24px;

  overflow-y: auto;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(124, 58, 237, 0.08),
          transparent 38%
      ),
      rgba(4, 3, 8, 0.78);

  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.modal__backdrop-glow {
  position: fixed;

  top: 50%;
  left: 50%;

  width: 460px;
  height: 460px;

  transform: translate(-50%, -50%);

  background: radial-gradient(
      circle,
      rgba(250, 204, 21, 0.055),
      transparent 68%
  );

  pointer-events: none;
}

/* =========================================================
   MODAL BOX
========================================================= */

.modal__box {
  position: relative;
  z-index: 1;

  width: 100%;
  max-width: 470px;

  padding: 22px;

  background:
      radial-gradient(
          circle at 100% 0%,
          rgba(124, 58, 237, 0.075),
          transparent 32%
      ),
      linear-gradient(
          145deg,
          rgba(26, 24, 36, 0.99),
          rgba(13, 12, 19, 0.99)
      );

  border: 1px solid rgba(255, 255, 255, 0.085);
  border-radius: 16px;

  box-shadow:
      0 35px 100px rgba(0, 0, 0, 0.62),
      0 0 0 1px rgba(124, 58, 237, 0.025),
      0 0 55px rgba(250, 204, 21, 0.035);

  overflow: hidden;
}

.modal__box::before {
  content: '';

  position: absolute;

  top: 0;
  left: 24px;
  right: 24px;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(250, 204, 21, 0.42),
          rgba(139, 92, 246, 0.22),
          transparent
      );
}

/* =========================================================
   HEADER
========================================================= */

.modal__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 18px;

  margin-bottom: 18px;
}

.modal__title-wrap {
  min-width: 0;
}

.modal__eyebrow {
  margin-bottom: 6px;

  color: rgba(250, 204, 21, 0.62);

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1.7px;
  text-transform: uppercase;
}

.modal__header h3 {
  margin: 0;

  color: #fff;

  font-size: 20px;
  line-height: 1.15;

  font-weight: 900;
  letter-spacing: -0.45px;
}

.modal__header p {
  margin: 6px 0 0;

  color: #716d7e;

  font-size: 11px;
  line-height: 1.45;
}

.modal__close {
  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #777284;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 8px;

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease;
}

.modal__close:hover {
  color: #fff;

  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.11);
}

.modal__close:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* =========================================================
   RECIPIENT
========================================================= */

.recipient {
  display: flex;
  align-items: center;
  gap: 11px;

  padding: 10px;

  margin-bottom: 10px;

  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, 0.08),
          rgba(255, 255, 255, 0.018)
      );

  border: 1px solid rgba(139, 92, 246, 0.11);
  border-radius: 11px;
}

.recipient__avatar {
  width: 40px;
  height: 40px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: hidden;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #4c1d95
      );

  border: 1px solid rgba(196, 181, 253, 0.18);
  border-radius: 9px;

  font-size: 13px;
  font-weight: 900;

  box-shadow:
      0 5px 16px rgba(124, 58, 237, 0.17);
}

.recipient__avatar img {
  width: 100%;
  height: 100%;

  object-fit: cover;
}

.recipient__info {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 2px;
}

.recipient__label {
  color: #656071;

  font-size: 8px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: 1px;
}

.recipient__info strong {
  overflow: hidden;

  color: #f2eff8;

  font-size: 13px;
  font-weight: 800;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.recipient__icon {
  width: 30px;
  height: 30px;

  margin-left: auto;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #facc15;

  background: rgba(250, 204, 21, 0.08);

  border: 1px solid rgba(250, 204, 21, 0.12);
  border-radius: 8px;
}

/* =========================================================
   BALANCE
========================================================= */

.balance-card {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 11px 12px;

  margin-bottom: 18px;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.075),
          rgba(250, 204, 21, 0.025)
      );

  border: 1px solid rgba(250, 204, 21, 0.13);
  border-radius: 10px;
}

.balance-card__icon {
  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #facc15;

  background: rgba(250, 204, 21, 0.10);

  border: 1px solid rgba(250, 204, 21, 0.14);
  border-radius: 8px;
}

.balance-card__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.balance-card__info span {
  color: rgba(250, 204, 21, 0.48);

  font-size: 8px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: 1px;
}

.balance-card__info strong {
  color: #f5dfa0;

  font-size: 13px;
  font-weight: 850;

  font-variant-numeric: tabular-nums;
}

.balance-card__status {
  margin-left: auto;

  padding: 4px 7px;

  color: #86efac;

  background: rgba(34, 197, 94, 0.07);

  border: 1px solid rgba(34, 197, 94, 0.12);
  border-radius: 6px;

  font-size: 7px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: 0.8px;
}

/* =========================================================
   ALERTS
========================================================= */

.alert {
  display: flex;
  align-items: flex-start;
  gap: 9px;

  margin-bottom: 12px;

  padding: 10px 12px;

  border-radius: 9px;

  font-size: 11px;
  line-height: 1.45;
}

.alert__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;
}

.alert--error {
  color: #fca5a5;

  background: rgba(239, 68, 68, 0.075);

  border: 1px solid rgba(239, 68, 68, 0.18);
}

.alert--ok {
  color: #86efac;

  background: rgba(34, 197, 94, 0.075);

  border: 1px solid rgba(34, 197, 94, 0.18);
}

/* =========================================================
   AMOUNT
========================================================= */

.amount-section {
  margin-top: 2px;
}

.section-label {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 7px;

  color: #a8a3b3;

  font-size: 10px;
  font-weight: 750;
}

.section-label__hint {
  color: #5f5a6b;

  font-size: 8px;
  font-weight: 650;

  font-variant-numeric: tabular-nums;
}

.amount-input {
  height: 52px;

  display: flex;
  align-items: center;

  padding: 0 13px;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.035),
          rgba(255, 255, 255, 0.012)
      );

  border: 1px solid rgba(255, 255, 255, 0.085);
  border-radius: 11px;

  transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.amount-input:focus-within {
  border-color: rgba(139, 92, 246, 0.42);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, 0.075);
}

.amount-input__coin {
  color: #facc15;

  font-size: 18px;
  font-weight: 900;
}

.amount-input input {
  min-width: 0;
  flex: 1;

  padding: 0 9px;

  color: #fff;

  background: transparent;

  border: 0;
  outline: none;

  font-size: 20px;
  font-weight: 850;

  font-variant-numeric: tabular-nums;
}

.amount-input input::-webkit-inner-spin-button,
.amount-input input::-webkit-outer-spin-button {
  margin: 0;
  -webkit-appearance: none;
}

.amount-input input[type='number'] {
  -moz-appearance: textfield;
}

.amount-input__currency {
  color: #595566;

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 1px;
}

/* =========================================================
   QUICK AMOUNTS
========================================================= */

.quick {
  display: grid;
  grid-template-columns: repeat(5, 1fr);

  gap: 6px;

  margin-top: 8px;
}

.quick__btn {
  min-height: 31px;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;

  color: #777284;

  background: rgba(255, 255, 255, 0.018);

  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 7px;

  font-size: 9px;
  font-weight: 750;

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease,
      transform 0.18s ease;
}

.quick__btn span {
  color: #8f8260;
  font-size: 8px;
}

.quick__btn:hover {
  color: #ddd8e8;

  background: rgba(255, 255, 255, 0.045);

  border-color: rgba(255, 255, 255, 0.10);

  transform: translateY(-1px);
}

.quick__btn.active {
  color: #f7d774;

  background: rgba(250, 204, 21, 0.085);

  border-color: rgba(250, 204, 21, 0.24);

  box-shadow:
      inset 0 1px rgba(255, 255, 255, 0.04);
}

.quick__btn.active span {
  color: #facc15;
}

.quick__btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
}

/* =========================================================
   VALIDATION
========================================================= */

.validation {
  display: flex;
  align-items: center;
  gap: 7px;

  margin-top: 9px;
  padding: 8px 10px;

  border-radius: 8px;

  font-size: 9px;
  line-height: 1.4;
}

.validation strong {
  font-weight: 850;
}

.validation--warning {
  color: #fcd34d;

  background: rgba(250, 204, 21, 0.055);

  border: 1px solid rgba(250, 204, 21, 0.12);
}

.validation--error {
  color: #fca5a5;

  background: rgba(239, 68, 68, 0.055);

  border: 1px solid rgba(239, 68, 68, 0.13);
}

/* =========================================================
   CALCULATION
========================================================= */

.calculation {
  margin-top: 14px;

  padding: 12px;

  background: rgba(0, 0, 0, 0.13);

  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 10px;
}

.calculation__row {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 12px;

  color: #777283;

  font-size: 9px;
}

.calculation__row strong {
  color: #b8b2c3;

  font-size: 10px;
  font-weight: 800;

  font-variant-numeric: tabular-nums;
}

.calculation__row--fee {
  margin-top: 7px;

  color: #625d6b;
}

.calculation__row--fee strong {
  color: #827b8b;
}

.calculation__row--fee small {
  margin-left: 3px;

  color: #56515f;

  font-size: 8px;
}

.calculation__divider {
  height: 1px;

  margin: 10px 0;

  background: rgba(255, 255, 255, 0.055);
}

.calculation__row--received {
  color: #aaa4b4;
}

.calculation__row--received strong {
  color: #f5d978;

  font-size: 13px;

  text-shadow:
      0 0 15px rgba(250, 204, 21, 0.08);
}

/* =========================================================
   LIMITS
========================================================= */

.limits {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-top: 8px;

  color: #625e6d;

  font-size: 8px;
}

.limits__icon {
  width: 24px;
  height: 24px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #706a7b;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 6px;
}

.limits > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.limits strong {
  color: #817b8c;

  font-size: 9px;
  font-weight: 800;

  font-variant-numeric: tabular-nums;
}

/* =========================================================
   ACTIONS
========================================================= */

.modal__actions {
  display: flex;
  gap: 8px;

  margin-top: 18px;
}

.btn {
  min-height: 40px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  padding: 0 14px;

  border-radius: 9px;

  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  transition:
      transform 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease,
      box-shadow 0.18s ease,
      color 0.18s ease;
}

.btn--ghost {
  color: #858090;

  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.065);
}

.btn--ghost:hover {
  color: #d8d3e2;

  background: rgba(255, 255, 255, 0.05);

  border-color: rgba(255, 255, 255, 0.10);
}

.btn--gift {
  flex: 1;

  color: #1b1503;

  background:
      linear-gradient(
          135deg,
          #facc15,
          #eab308
      );

  border: 1px solid rgba(253, 224, 71, 0.45);

  box-shadow:
      0 7px 22px rgba(250, 204, 21, 0.12),
      inset 0 1px rgba(255, 255, 255, 0.28);
}

.btn--gift:hover:not(:disabled) {
  transform: translateY(-1px);

  background:
      linear-gradient(
          135deg,
          #fde047,
          #facc15
      );

  box-shadow:
      0 10px 28px rgba(250, 204, 21, 0.19),
      inset 0 1px rgba(255, 255, 255, 0.32);
}

.btn:disabled {
  opacity: 0.42;
  cursor: not-allowed;
  transform: none;
}

.btn__spinner {
  width: 13px;
  height: 13px;

  border: 2px solid rgba(27, 21, 3, 0.22);
  border-top-color: #1b1503;

  border-radius: 50%;

  animation: spin 0.65s linear infinite;
}

/* =========================================================
   MODAL TRANSITION
========================================================= */

.gift-modal-enter-active,
.gift-modal-leave-active {
  transition:
      opacity 0.22s ease;
}

.gift-modal-enter-active .modal__box,
.gift-modal-leave-active .modal__box {
  transition:
      opacity 0.22s ease,
      transform 0.22s ease;
}

.gift-modal-enter-from,
.gift-modal-leave-to {
  opacity: 0;
}

.gift-modal-enter-from .modal__box,
.gift-modal-leave-to .modal__box {
  opacity: 0;
  transform: translateY(12px) scale(0.97);
}

/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 560px) {
  .gift-btn {
    width: 100%;
    justify-content: center;
  }

  .modal {
    align-items: flex-start;

    padding: 14px;

    padding-top: max(14px, env(safe-area-inset-top));
    padding-bottom: max(14px, env(safe-area-inset-bottom));
  }

  .modal__box {
    max-width: 100%;

    margin: auto 0;

    padding: 18px;

    border-radius: 14px;
  }

  .modal__header h3 {
    font-size: 18px;
  }

  .quick {
    grid-template-columns: repeat(3, 1fr);
  }

  .modal__actions {
    flex-direction: column-reverse;
  }

  .btn {
    width: 100%;
  }
}

@media (max-width: 380px) {
  .modal {
    padding: 9px;
  }

  .modal__box {
    padding: 15px;
  }

  .recipient {
    padding: 8px;
  }

  .balance-card {
    padding: 9px;
  }

  .quick__btn {
    min-height: 29px;
  }
}
</style>