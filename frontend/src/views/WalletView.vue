<script setup>
import { computed, onMounted, ref } from 'vue'
import { walletApi } from '@/services/wallet.js'
import { useAuthStore } from '@/stores/auth'
import { SOURCE_LABELS, formatCoins } from '@/data/economy.js'

const auth = useAuthStore()

const loading = ref(true)
const error = ref('')
const notice = ref('')

const balance = ref(0)
const spent = ref(0)
const earned = ref(0)
const bySource = ref({})
const giftedToday = ref(0)
const dailyAvailable = ref(false)
const dailyAmount = ref(0)
const giftLimits = ref({})

// Пригласительные ссылки
const referral = ref(null)
const copied = ref(false)

const transactions = ref([])
const page = ref(1)
const lastPage = ref(1)

const sources = computed(() =>
    Object.entries(bySource.value)
        .filter(([, total]) => Number(total) > 0)
        .sort((a, b) => Number(b[1]) - Number(a[1]))
)

const maxSourceValue = computed(() => {
  return Math.max(
      ...sources.value.map(([, total]) => Number(total)),
      1
  )
})

const balanceProgress = computed(() => {
  if (!giftLimits.value.daily_limit) return 0

  return Math.min(
      100,
      (Number(giftedToday.value) / Number(giftLimits.value.daily_limit)) * 100
  )
})

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await walletApi.summary()

    balance.value = data.balance ?? 0
    spent.value = data.spent ?? 0
    earned.value = data.earned ?? 0
    bySource.value = data.earned_by_source ?? {}
    giftedToday.value = data.gifted_today ?? 0
    dailyAvailable.value = Boolean(data.daily_bonus_available)
    dailyAmount.value = data.daily_bonus_amount ?? 0
    giftLimits.value = data.gift_limits ?? {}
    referral.value = data.referral ?? null

    await loadTransactions(1)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить кошелёк.'
  } finally {
    loading.value = false
  }
}

async function loadTransactions(nextPage = 1) {
  try {
    const data = await walletApi.transactions({
      page: nextPage,
      per_page: 20,
    })

    transactions.value = data.data ?? []
    page.value = data.current_page ?? 1
    lastPage.value = data.last_page ?? 1
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить историю операций.'
  }
}

async function copyInviteLink() {
  if (!referral.value?.link) return

  try {
    await navigator.clipboard.writeText('https://mc-apex.ru/register?ref=' + referral.value.code)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch (e) {
    error.value = 'Не удалось скопировать ссылку — скопируй вручную.'
  }
}

async function claimDaily() {
  error.value = ''
  notice.value = ''

  try {
    const result = await walletApi.claimDailyBonus()

    notice.value = result.message
    balance.value = result.balance
    dailyAvailable.value = false

    await load()
    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось получить бонус.'
  }
}

function sourceLabel(source) {
  return SOURCE_LABELS[source] || source
}

function signed(amount) {
  const value = Number(amount ?? 0)

  return `${value > 0 ? '+' : ''}${formatCoins(value)}`
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function sourcePercent(total) {
  return Math.min(
      100,
      (Number(total) / maxSourceValue.value) * 100
  )
}

function transactionIcon(row) {
  return Number(row.amount) >= 0 ? 'plus' : 'minus'
}

onMounted(load)
</script>

<template>
  <div class="wallet">

    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="wallet__head">
      <div class="wallet__heading">
        <div class="wallet__eyebrow">
          <span class="wallet__eyebrow-line" />
          ECONOMY SYSTEM
        </div>

        <h1 class="wallet__title">
          Кошелёк
        </h1>

        <p class="wallet__subtitle">
          Управление ApexCoin · баланс · начисления · операции
        </p>
      </div>

      <RouterLink
          class="wallet__shop-btn"
          :to="{ name: 'shop' }"
      >
        <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
          <path d="M6 8h12l1 13H5L6 8Z" />
          <path d="M9 8a3 3 0 0 1 6 0" />
        </svg>

        В магазин

        <svg
            class="wallet__shop-arrow"
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
          <path d="M5 12h14" />
          <path d="m13 6 6 6-6 6" />
        </svg>
      </RouterLink>
    </header>

    <!-- =====================================================
         ALERTS
         ===================================================== -->

    <Transition name="alert">
      <div
          v-if="error"
          class="alert alert--error"
      >
        <span class="alert__icon">
          !
        </span>

        <span>{{ error }}</span>

        <button
            type="button"
            class="alert__close"
            @click="error = ''"
        >
          ×
        </button>
      </div>
    </Transition>

    <Transition name="alert">
      <div
          v-if="notice"
          class="alert alert--ok"
      >
        <span class="alert__icon">
          ✓
        </span>

        <span>{{ notice }}</span>

        <button
            type="button"
            class="alert__close"
            @click="notice = ''"
        >
          ×
        </button>
      </div>
    </Transition>

    <!-- =====================================================
         LOADING
         ===================================================== -->

    <div
        v-if="loading"
        class="wallet-state"
    >
      <div class="wallet-state__spinner" />

      <span>
        Считаем монеты…
      </span>
    </div>

    <template v-else>

      <!-- ===================================================
           BALANCE OVERVIEW
           =================================================== -->

      <section class="balance-grid">

        <!-- MAIN BALANCE -->

        <article class="balance-card balance-card--main">
          <div class="balance-card__glow" />

          <div class="balance-card__top">
            <div class="balance-card__label">
              <span class="balance-card__dot" />
              ДОСТУПНЫЙ БАЛАНС
            </div>

            <div class="coin">
              ₳
            </div>
          </div>

          <div class="balance-card__amount">
            <span class="balance-card__currency">₳</span>
            {{ formatCoins(balance) }}
          </div>

          <div class="balance-card__footer">
            <span>
              ApexCoin
            </span>

            <span class="balance-card__status">
              <span />
              ACTIVE
            </span>
          </div>

          <div
              v-if="dailyAvailable"
              class="daily-bonus"
          >
            <div class="daily-bonus__icon">
              <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 3v18" />
                <path d="M17 7H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
              </svg>
            </div>

            <div class="daily-bonus__info">
              <strong>
                Ежедневный бонус
              </strong>

              <span>
                +{{ formatCoins(dailyAmount) }} за вход
              </span>
            </div>

            <button
                class="daily-bonus__button"
                type="button"
                @click="claimDaily"
            >
              Забрать

              <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path d="M5 12h14" />
                <path d="m13 6 6 6-6 6" />
              </svg>
            </button>
          </div>

          <div
              v-else
              class="daily-complete"
          >
            <span class="daily-complete__check">
              ✓
            </span>

            Ежедневный бонус уже получен
          </div>
        </article>

        <!-- EARNED -->

        <article class="metric-card">
          <div class="metric-card__top">
            <span class="metric-card__label">
              ВСЕГО ЗАРАБОТАНО
            </span>

            <span class="metric-card__icon metric-card__icon--green">
              <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M12 19V5" />
                <path d="m6 11 6-6 6 6" />
              </svg>
            </span>
          </div>

          <div class="metric-card__value">
            <span>₳</span>
            {{ formatCoins(earned) }}
          </div>

          <div class="metric-card__line metric-card__line--green">
            <span />
          </div>

          <div class="metric-card__hint">
            Суммарные начисления
          </div>
        </article>

        <!-- SPENT -->

        <article class="metric-card">
          <div class="metric-card__top">
            <span class="metric-card__label">
              ПОТРАЧЕНО
            </span>

            <span class="metric-card__icon metric-card__icon--red">
              <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M12 5v14" />
                <path d="m18 13-6 6-6-6" />
              </svg>
            </span>
          </div>

          <div class="metric-card__value metric-card__value--red">
            <span>₳</span>
            {{ formatCoins(spent) }}
          </div>

          <div class="metric-card__line metric-card__line--red">
            <span />
          </div>

          <div class="metric-card__hint">
            Покупки и расходы
          </div>
        </article>

        <!-- GIFTS -->

        <article class="metric-card">
          <div class="metric-card__top">
            <span class="metric-card__label">
              ПОДАРЕНО СЕГОДНЯ
            </span>

            <span class="metric-card__icon metric-card__icon--purple">
              <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M20 12v10H4V12" />
                <path d="M2 7h20v5H2z" />
                <path d="M12 22V7" />
                <path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Z" />
                <path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" />
              </svg>
            </span>
          </div>

          <div class="metric-card__value">
            <span>₳</span>
            {{ formatCoins(giftedToday) }}

            <small v-if="giftLimits.daily_limit">
              / {{ formatCoins(giftLimits.daily_limit) }}
            </small>
          </div>

          <div
              v-if="giftLimits.daily_limit"
              class="gift-progress"
          >
            <div class="gift-progress__track">
              <span
                  :style="{ width: `${balanceProgress}%` }"
              />
            </div>

            <div class="gift-progress__labels">
              <span>DAILY LIMIT</span>
              <span>{{ Math.round(balanceProgress) }}%</span>
            </div>
          </div>

          <div
              v-else
              class="metric-card__hint"
          >
            Подарки сегодня
          </div>
        </article>
      </section>

      <!-- ===================================================
           SOURCES
           =================================================== -->

      <section
          v-if="sources.length"
          class="panel sources"
      >
        <header class="panel__head">
          <div>
            <span class="panel__eyebrow">
              EARNINGS BREAKDOWN
            </span>

            <h2 class="panel__title">
              Откуда монеты
            </h2>
          </div>

          <span class="panel__count">
            {{ sources.length }}
          </span>
        </header>

        <div class="sources__list">
          <div
              v-for="[source, total] in sources"
              :key="source"
              class="source-row"
          >
            <div class="source-row__icon">
              <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 3v18" />
                <path d="M17 7H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
              </svg>
            </div>

            <div class="source-row__content">
              <div class="source-row__top">
                <span class="source-row__name">
                  {{ sourceLabel(source) }}
                </span>

                <span class="source-row__value">
                  ₳ {{ formatCoins(total) }}
                </span>
              </div>

              <div class="source-row__track">
                <span
                    :style="{
                    width: `${sourcePercent(total)}%`
                  }"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===================================================
           INVITES
           =================================================== -->

      <section
          v-if="referral"
          class="panel referral"
      >
        <header class="panel__head">
          <div>
            <span class="panel__eyebrow">
              INVITE FRIENDS
            </span>

            <h2 class="panel__title">
              Пригласительные ссылки
            </h2>
          </div>

          <span class="panel__count">
            {{ referral.invited_count }}
          </span>
        </header>

        <p class="referral__lead">
          Отправь ссылку другу. Когда он зарегистрируется — ты получишь
          <b>₳ {{ formatCoins(referral.reward_per_invite) }}</b>, а он стартовый бонус.
        </p>

        <div class="referral__row">
          <input
              class="referral__input"
              type="text"
              readonly
              :value="'https://mc-apex.ru/register?ref=' + referral.code "
              @focus="$event.target.select()"
          >

          <button
              class="btn btn-primary"
              type="button"
              @click="copyInviteLink"
          >
            {{ copied ? 'Скопировано' : 'Скопировать' }}
          </button>
        </div>

        <div class="referral__meta">
          <span>Твой код: <b>{{ referral.code }}</b></span>
          <span>Пришло по ссылке: <b>{{ referral.invited_count }}</b></span>
        </div>

        <div
            v-if="referral.invited_users?.length"
            class="referral__list"
        >
          <div
              v-for="u in referral.invited_users"
              :key="u.id"
              class="referral__user"
          >
            <span class="referral__user-name">{{ u.username }}</span>
            <span class="referral__user-date">
              {{ new Date(u.joined_at).toLocaleDateString('ru-RU') }}
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           HISTORY
           =================================================== -->

      <section class="panel history">

        <header class="panel__head">
          <div>
            <span class="panel__eyebrow">
              TRANSACTION LOG
            </span>

            <h2 class="panel__title">
              История операций
            </h2>
          </div>

          <span class="history__live">
            <span />
            LIVE
          </span>
        </header>

        <div
            v-if="!transactions.length"
            class="history-empty"
        >
          <div class="history-empty__icon">
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.6"
            >
              <path d="M12 3v18" />
              <path d="M17 7H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
            </svg>
          </div>

          <strong>
            Операций пока нет
          </strong>

          <span>
            Здесь появятся все изменения твоего баланса
          </span>
        </div>

        <div
            v-else
            class="transaction-list"
        >
          <!-- DESKTOP HEADER -->

          <div class="transaction-head">
            <span>ДАТА</span>
            <span>ОПЕРАЦИЯ</span>
            <span>СУММА</span>
            <span>БАЛАНС</span>
          </div>

          <!-- ROWS -->

          <div
              v-for="row in transactions"
              :key="row.id"
              class="transaction"
              :class="{
              'transaction--positive': Number(row.amount) > 0,
              'transaction--negative': Number(row.amount) < 0
            }"
          >
            <div class="transaction__date">
              <span class="transaction__date-main">
                {{ formatDate(row.created_at).split(',')[0] }}
              </span>

              <span class="transaction__date-time">
                {{ formatDate(row.created_at).split(',')[1] }}
              </span>
            </div>

            <div class="transaction__operation">
              <span
                  class="transaction__icon"
                  :class="{
                  'transaction__icon--plus': transactionIcon(row) === 'plus',
                  'transaction__icon--minus': transactionIcon(row) === 'minus'
                }"
              >
                <svg
                    v-if="transactionIcon(row) === 'plus'"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                  <path d="M12 5v14" />
                  <path d="M5 12h14" />
                </svg>

                <svg
                    v-else
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                >
                  <path d="M5 12h14" />
                </svg>
              </span>

              <span class="transaction__operation-info">
                <strong>
                  {{ sourceLabel(row.source) }}
                </strong>

                <small v-if="row.description">
                  {{ row.description }}
                </small>
              </span>
            </div>

            <div
                class="transaction__amount"
                :class="{
                'transaction__amount--plus': Number(row.amount) > 0,
                'transaction__amount--minus': Number(row.amount) < 0
              }"
            >
              {{ signed(row.amount) }}
            </div>

            <div class="transaction__balance">
              <span>
                ₳
              </span>

              {{ formatCoins(row.balance_after) }}
            </div>
          </div>
        </div>

        <!-- PAGINATION -->

        <div
            v-if="lastPage > 1"
            class="pager"
        >
          <button
              class="pager__button"
              type="button"
              :disabled="page <= 1"
              @click="loadTransactions(page - 1)"
          >
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path d="m15 18-6-6 6-6" />
            </svg>

            Назад
          </button>

          <div class="pager__current">
            <span>
              PAGE
            </span>

            {{ page }}

            <small>
              / {{ lastPage }}
            </small>
          </div>

          <button
              class="pager__button"
              type="button"
              :disabled="page >= lastPage"
              @click="loadTransactions(page + 1)"
          >
            Вперёд

            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path d="m9 18 6-6-6-6" />
            </svg>
          </button>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
/* ============================================================
   PAGE
   ============================================================ */

.wallet {
  position: relative;

  width: min(1080px, calc(100% - 40px));

  margin: 0 auto;
  padding: 32px 0 70px;

  color: var(--text);
}

/* subtle page glow */

.wallet::before {
  content: "";

  position: fixed;

  top: 80px;
  left: 50%;

  width: 700px;
  height: 400px;

  background:
      radial-gradient(
          ellipse,
          rgba(124, 58, 237, .045),
          transparent 70%
      );

  transform: translateX(-50%);

  pointer-events: none;
  z-index: -1;
}

/* ============================================================
   HEADER
   ============================================================ */

.wallet__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 24px;

  margin-bottom: 24px;
}

.wallet__heading {
  min-width: 0;
}

.wallet__eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-bottom: 7px;

  color: #64748b;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 2px;
}

.wallet__eyebrow-line {
  width: 22px;
  height: 1px;

  background: #facc15;

  box-shadow:
      0 0 8px rgba(250, 204, 21, .65);
}

.wallet__title {
  margin: 0;

  color: var(--text);

  font-size: 30px;
  font-weight: 950;

  letter-spacing: -.8px;
}

.wallet__subtitle {
  margin: 6px 0 0;

  color: var(--text-dim);

  font-size: 13px;
}

.wallet__shop-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  min-height: 38px;

  padding: 0 14px;

  color: var(--text-dim);

  background:
      linear-gradient(
          135deg,
          rgba(124, 58, 237, .1),
          rgba(10, 10, 18, .7)
      );

  border: 1px solid var(--border);
  border-radius: 10px;

  font-size: 12px;
  font-weight: 800;

  text-decoration: none;

  box-shadow:
      inset 0 1px rgba(255,255,255,.025);

  transition:
      color .2s ease,
      border-color .2s ease,
      transform .2s ease,
      background .2s ease;
}

.wallet__shop-btn:hover {
  color: #fff;

  border-color:
      rgba(124, 58, 237, .55);

  background:
      rgba(124, 58, 237, .12);

  transform: translateY(-1px);
}

.wallet__shop-arrow {
  color: #64748b;

  transition: transform .2s ease;
}

.wallet__shop-btn:hover .wallet__shop-arrow {
  color: #a78bfa;

  transform: translateX(2px);
}

/* ============================================================
   ALERTS
   ============================================================ */

.alert {
  display: flex;
  align-items: center;
  gap: 10px;

  min-height: 42px;

  margin-bottom: 14px;
  padding: 9px 12px;

  border-radius: 11px;

  font-size: 12px;
  font-weight: 650;
}

.alert__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;

  width: 23px;
  height: 23px;

  border-radius: 7px;

  font-size: 11px;
  font-weight: 950;
}

.alert__close {
  margin-left: auto;

  padding: 2px 5px;

  color: currentColor;
  background: transparent;

  border: 0;

  font-size: 17px;
  line-height: 1;

  opacity: .5;

  cursor: pointer;
}

.alert__close:hover {
  opacity: 1;
}

.alert--error {
  color: #fca5a5;

  background:
      linear-gradient(
          90deg,
          rgba(239, 68, 68, .1),
          rgba(239, 68, 68, .035)
      );

  border: 1px solid rgba(239, 68, 68, .25);
}

.alert--error .alert__icon {
  color: #fca5a5;
  background: rgba(239, 68, 68, .14);
}

.alert--ok {
  color: #86efac;

  background:
      linear-gradient(
          90deg,
          rgba(34, 197, 94, .1),
          rgba(34, 197, 94, .035)
      );

  border: 1px solid rgba(34, 197, 94, .25);
}

.alert--ok .alert__icon {
  color: #86efac;
  background: rgba(34, 197, 94, .14);
}

.alert-enter-active,
.alert-leave-active {
  transition:
      opacity .2s ease,
      transform .2s ease;
}

.alert-enter-from,
.alert-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

/* ============================================================
   LOADING
   ============================================================ */

.wallet-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  min-height: 360px;

  gap: 12px;

  color: var(--text-muted);

  font-size: 12px;
}

.wallet-state__spinner {
  width: 28px;
  height: 28px;

  border: 2px solid
  rgba(250, 204, 21, .15);

  border-top-color: #facc15;

  border-radius: 50%;

  animation: spin .8s linear infinite;

  box-shadow:
      0 0 15px rgba(250, 204, 21, .15);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================
   BALANCE GRID
   ============================================================ */

.balance-grid {
  display: grid;

  grid-template-columns:
    minmax(300px, 1.6fr)
    repeat(3, minmax(160px, 1fr));

  gap: 10px;

  margin-bottom: 24px;
}

/* ============================================================
   MAIN BALANCE
   ============================================================ */

.balance-card {
  position: relative;

  min-height: 220px;

  padding: 20px;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(250, 204, 21, .1),
          rgba(124, 58, 237, .035) 50%,
          var(--bg-card) 85%
      );

  border: 1px solid rgba(250, 204, 21, .25);
  border-radius: 16px;

  box-shadow:
      0 15px 40px rgba(0,0,0,.16),
      inset 0 1px rgba(255,255,255,.035);

  transition:
      border-color .2s ease,
      transform .2s ease;
}

.balance-card:hover {
  border-color: rgba(250, 204, 21, .4);

  transform: translateY(-1px);
}

.balance-card::before {
  content: "";

  position: absolute;

  top: -100px;
  right: -80px;

  width: 240px;
  height: 240px;

  background:
      radial-gradient(
          circle,
          rgba(250,204,21,.16),
          transparent 68%
      );

  pointer-events: none;
}

.balance-card__glow {
  position: absolute;

  right: 20px;
  bottom: 35px;

  width: 180px;
  height: 70px;

  background:
      radial-gradient(
          ellipse,
          rgba(250,204,21,.1),
          transparent 70%
      );

  filter: blur(18px);

  pointer-events: none;
}

.balance-card__top {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: space-between;
}

.balance-card__label {
  display: flex;
  align-items: center;
  gap: 7px;

  color: #64748b;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.6px;
}

.balance-card__dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #facc15;

  box-shadow:
      0 0 8px rgba(250,204,21,.8);
}

.coin {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 32px;
  height: 32px;

  color: #facc15;

  background:
      radial-gradient(
          circle at 35% 30%,
          rgba(255,255,255,.18),
          transparent 30%
      ),
      rgba(250,204,21,.08);

  border: 1px solid rgba(250,204,21,.28);
  border-radius: 50%;

  font-size: 14px;
  font-weight: 950;

  box-shadow:
      0 0 20px rgba(250,204,21,.12);
}

.balance-card__amount {
  position: relative;
  z-index: 1;

  margin-top: 22px;

  color: #facc15;

  font-size: clamp(32px, 4vw, 45px);
  font-weight: 950;

  line-height: 1;

  letter-spacing: -1.5px;

  font-variant-numeric: tabular-nums;

  text-shadow:
      0 0 25px rgba(250,204,21,.13);
}

.balance-card__currency {
  margin-right: 3px;

  color: rgba(250,204,21,.7);

  font-size: .7em;
}

.balance-card__footer {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-top: 12px;

  color: #475569;

  font-size: 9px;
  font-weight: 800;
  letter-spacing: .8px;
  text-transform: uppercase;
}

.balance-card__status {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  color: #64748b;

  font-size: 7px;
  letter-spacing: 1.3px;
}

.balance-card__status span {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow:
      0 0 7px rgba(34,197,94,.7);
}

/* ============================================================
   DAILY BONUS
   ============================================================ */

.daily-bonus {
  position: absolute;
  z-index: 3;

  right: 12px;
  bottom: 12px;
  left: 12px;

  display: flex;
  align-items: center;
  gap: 9px;

  padding: 9px 10px;

  background:
      linear-gradient(
          90deg,
          rgba(250,204,21,.1),
          rgba(250,204,21,.035)
      );

  border: 1px solid rgba(250,204,21,.2);
  border-radius: 10px;

  backdrop-filter: blur(8px);
}

.daily-bonus__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;

  width: 31px;
  height: 31px;

  color: #facc15;

  background: rgba(250,204,21,.09);

  border-radius: 8px;
}

.daily-bonus__info {
  display: flex;
  flex-direction: column;
  gap: 2px;

  min-width: 0;
}

.daily-bonus__info strong {
  color: var(--text);

  font-size: 10px;
  font-weight: 850;
}

.daily-bonus__info span {
  color: #64748b;

  font-size: 8px;
  font-weight: 700;
}

.daily-bonus__button {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  margin-left: auto;

  padding: 7px 9px;

  color: #17120a;

  background: #facc15;

  border: 0;
  border-radius: 7px;

  font: inherit;
  font-size: 9px;
  font-weight: 950;

  cursor: pointer;

  transition:
      transform .18s ease,
      box-shadow .18s ease;
}

.daily-bonus__button:hover {
  transform: translateY(-1px);

  box-shadow:
      0 5px 16px rgba(250,204,21,.22);
}

.daily-complete {
  position: absolute;
  right: 20px;
  bottom: 18px;
  left: 20px;

  display: flex;
  align-items: center;
  gap: 6px;

  color: #475569;

  font-size: 8px;
  font-weight: 800;
}

.daily-complete__check {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  width: 17px;
  height: 17px;

  color: #22c55e;

  background: rgba(34,197,94,.08);

  border: 1px solid rgba(34,197,94,.18);
  border-radius: 5px;

  font-size: 9px;
}

/* ============================================================
   METRIC CARDS
   ============================================================ */

.metric-card {
  position: relative;

  display: flex;
  flex-direction: column;

  min-height: 220px;

  padding: 17px;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(255,255,255,.018),
          var(--bg-card)
      );

  border: 1px solid var(--border);
  border-radius: 16px;

  box-shadow:
      0 10px 30px rgba(0,0,0,.1),
      inset 0 1px rgba(255,255,255,.025);

  transition:
      border-color .2s ease,
      transform .2s ease;
}

.metric-card:hover {
  border-color: var(--border-hover);

  transform: translateY(-1px);
}

.metric-card::after {
  content: "";

  position: absolute;

  right: -40px;
  bottom: -50px;

  width: 120px;
  height: 120px;

  background:
      radial-gradient(
          circle,
          rgba(124,58,237,.08),
          transparent 70%
      );

  pointer-events: none;
}

.metric-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.metric-card__label {
  color: #64748b;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.3px;
}

.metric-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 29px;
  height: 29px;

  border-radius: 8px;
}

.metric-card__icon--green {
  color: #22c55e;
  background: rgba(34,197,94,.08);
  border: 1px solid rgba(34,197,94,.13);
}

.metric-card__icon--red {
  color: #f87171;
  background: rgba(239,68,68,.08);
  border: 1px solid rgba(239,68,68,.13);
}

.metric-card__icon--purple {
  color: #a78bfa;
  background: rgba(124,58,237,.09);
  border: 1px solid rgba(124,58,237,.15);
}

.metric-card__value {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: baseline;
  gap: 4px;

  margin-top: auto;
  margin-bottom: 13px;

  color: var(--text);

  font-size: 24px;
  font-weight: 900;

  line-height: 1;

  letter-spacing: -.6px;

  font-variant-numeric: tabular-nums;
}

.metric-card__value > span {
  color: #facc15;

  font-size: .72em;
}

.metric-card__value--red {
  color: #fca5a5;
}

.metric-card__value small {
  color: #475569;

  font-size: 10px;
  font-weight: 700;
}

.metric-card__line {
  position: relative;

  height: 3px;

  margin-bottom: 9px;

  overflow: hidden;

  background: rgba(255,255,255,.035);

  border-radius: 999px;
}

.metric-card__line span {
  display: block;

  width: 45%;
  height: 100%;

  border-radius: inherit;
}

.metric-card__line--green span {
  background: #22c55e;
  box-shadow: 0 0 10px rgba(34,197,94,.45);
}

.metric-card__line--red span {
  background: #ef4444;
  box-shadow: 0 0 10px rgba(239,68,68,.4);
}

.metric-card__hint {
  color: #475569;

  font-size: 8px;
  font-weight: 700;
}

/* ============================================================
   GIFT PROGRESS
   ============================================================ */

.gift-progress {
  margin-top: auto;
}

.gift-progress__track {
  height: 4px;

  overflow: hidden;

  background: rgba(255,255,255,.035);

  border-radius: 999px;
}

.gift-progress__track span {
  display: block;

  height: 100%;

  background:
      linear-gradient(
          90deg,
          #7c3aed,
          #a78bfa
      );

  border-radius: inherit;

  box-shadow:
      0 0 10px rgba(124,58,237,.35);

  transition: width .5s ease;
}

.gift-progress__labels {
  display: flex;
  justify-content: space-between;

  margin-top: 7px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

.gift-progress__labels span:last-child {
  color: #8b5cf6;
}

/* ============================================================
   PANEL
   ============================================================ */

.panel {
  position: relative;

  margin-bottom: 18px;
  padding: 17px;

  background:
      linear-gradient(
          145deg,
          rgba(255,255,255,.012),
          var(--bg-card)
      );

  border: 1px solid var(--border);
  border-radius: 15px;

  box-shadow:
      0 10px 30px rgba(0,0,0,.08),
      inset 0 1px rgba(255,255,255,.02);
}

.panel__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 16px;

  padding-bottom: 13px;
  margin-bottom: 13px;

  border-bottom: 1px solid var(--border);
}

.panel__eyebrow {
  display: block;

  margin-bottom: 3px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.8px;
}

.panel__title {
  margin: 0;

  color: var(--text);

  font-size: 14px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: .7px;
}

.panel__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 24px;
  height: 21px;

  padding: 0 7px;

  color: #64748b;

  background: rgba(255,255,255,.025);

  border: 1px solid var(--border);
  border-radius: 999px;

  font-size: 9px;
  font-weight: 900;
}

/* ============================================================
   SOURCES
   ============================================================ */

.sources__list {
  display: flex;
  flex-direction: column;

  gap: 7px;
}

.source-row {
  display: flex;
  align-items: center;

  gap: 10px;

  padding: 8px;

  border: 1px solid transparent;
  border-radius: 10px;

  transition:
      background .18s ease,
      border-color .18s ease;
}

.source-row:hover {
  background: rgba(255,255,255,.018);

  border-color: var(--border);
}

.source-row__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;

  width: 30px;
  height: 30px;

  color: #a78bfa;

  background: rgba(124,58,237,.08);

  border: 1px solid rgba(124,58,237,.14);
  border-radius: 8px;
}

.source-row__content {
  flex: 1;
  min-width: 0;
}

.source-row__top {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 12px;

  margin-bottom: 6px;
}

.source-row__name {
  overflow: hidden;

  color: var(--text-dim);

  font-size: 10px;
  font-weight: 750;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.source-row__value {
  flex: 0 0 auto;

  color: #facc15;

  font-size: 10px;
  font-weight: 900;

  font-variant-numeric: tabular-nums;
}

.source-row__track {
  height: 4px;

  overflow: hidden;

  background: rgba(255,255,255,.035);

  border-radius: 999px;
}

.source-row__track span {
  display: block;

  height: 100%;

  min-width: 4px;

  background:
      linear-gradient(
          90deg,
          #7c3aed,
          #facc15
      );

  border-radius: inherit;

  box-shadow:
      0 0 10px rgba(124,58,237,.18);
}

/* ============================================================
   HISTORY
   ============================================================ */

.history {
  margin-bottom: 0;
}

.history__live {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.5px;
}

.history__live span {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow:
      0 0 8px rgba(34,197,94,.65);
}

/* ============================================================
   TRANSACTION LIST
   ============================================================ */

.transaction-list {
  overflow: hidden;

  border: 1px solid rgba(255,255,255,.025);
  border-radius: 10px;
}

.transaction-head {
  display: grid;

  grid-template-columns:
    125px
    minmax(220px, 1fr)
    100px
    110px;

  align-items: center;

  gap: 15px;

  min-height: 34px;

  padding: 0 11px;

  color: #475569;

  background: rgba(255,255,255,.012);

  border-bottom: 1px solid var(--border);

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1.2px;
}

.transaction {
  position: relative;

  display: grid;

  grid-template-columns:
    125px
    minmax(220px, 1fr)
    100px
    110px;

  align-items: center;

  gap: 15px;

  min-height: 59px;

  padding: 7px 11px;

  background: transparent;

  border-bottom: 1px solid rgba(255,255,255,.025);

  transition:
      background .18s ease,
      transform .18s ease;
}

.transaction:last-child {
  border-bottom: 0;
}

.transaction:hover {
  background:
      linear-gradient(
          90deg,
          rgba(124,58,237,.035),
          transparent 70%
      );
}

.transaction::before {
  content: "";

  position: absolute;

  top: 10px;
  bottom: 10px;
  left: 0;

  width: 2px;

  border-radius: 0 3px 3px 0;

  opacity: .65;
}

.transaction--positive::before {
  background: #22c55e;

  box-shadow:
      0 0 8px rgba(34,197,94,.35);
}

.transaction--negative::before {
  background: #ef4444;

  box-shadow:
      0 0 8px rgba(239,68,68,.25);
}

.transaction__date {
  display: flex;
  flex-direction: column;

  gap: 2px;

  color: #64748b;

  font-size: 9px;

  font-variant-numeric: tabular-nums;
}

.transaction__date-main {
  color: var(--text-dim);

  font-weight: 750;
}

.transaction__date-time {
  color: #475569;

  font-size: 8px;
}

.transaction__operation {
  display: flex;
  align-items: center;

  gap: 9px;

  min-width: 0;
}

.transaction__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;

  width: 30px;
  height: 30px;

  border-radius: 8px;
}

.transaction__icon--plus {
  color: #22c55e;

  background: rgba(34,197,94,.07);

  border: 1px solid rgba(34,197,94,.12);
}

.transaction__icon--minus {
  color: #f87171;

  background: rgba(239,68,68,.07);

  border: 1px solid rgba(239,68,68,.12);
}

.transaction__operation-info {
  display: flex;
  flex-direction: column;

  gap: 2px;

  min-width: 0;
}

.transaction__operation-info strong {
  overflow: hidden;

  color: var(--text);

  font-size: 10px;
  font-weight: 800;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.transaction__operation-info small {
  overflow: hidden;

  color: #475569;

  font-size: 8px;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.transaction__amount {
  font-size: 11px;
  font-weight: 950;

  text-align: right;

  font-variant-numeric: tabular-nums;
}

.transaction__amount--plus {
  color: #4ade80;
}

.transaction__amount--minus {
  color: #f87171;
}

.transaction__balance {
  color: var(--text-dim);

  font-size: 10px;
  font-weight: 800;

  text-align: right;

  font-variant-numeric: tabular-nums;
}

.transaction__balance span {
  margin-right: 2px;

  color: #facc15;

  font-size: 8px;
}

/* ============================================================
   EMPTY
   ============================================================ */

.history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;

  gap: 5px;

  padding: 55px 20px;

  color: #475569;

  text-align: center;
}

.history-empty__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 46px;
  height: 46px;

  margin-bottom: 7px;

  color: #64748b;

  background: rgba(255,255,255,.025);

  border: 1px solid var(--border);
  border-radius: 12px;
}

.history-empty strong {
  color: var(--text-dim);

  font-size: 12px;
}

.history-empty > span {
  font-size: 9px;
}

/* ============================================================
   PAGINATION
   ============================================================ */

.pager {
  display: flex;
  align-items: center;
  justify-content: center;

  gap: 12px;

  margin-top: 14px;
}

.pager__button {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  min-height: 31px;

  padding: 0 10px;

  color: var(--text-dim);

  background: rgba(255,255,255,.02);

  border: 1px solid var(--border);
  border-radius: 8px;

  font: inherit;
  font-size: 9px;
  font-weight: 800;

  cursor: pointer;

  transition:
      color .18s ease,
      border-color .18s ease,
      background .18s ease;
}

.pager__button:hover:not(:disabled) {
  color: var(--text);

  background: rgba(124,58,237,.08);

  border-color: rgba(124,58,237,.3);
}

.pager__button:disabled {
  opacity: .35;

  cursor: not-allowed;
}

.pager__current {
  display: flex;
  align-items: baseline;
  gap: 5px;

  color: var(--text);

  font-size: 10px;
  font-weight: 900;
}

.pager__current > span {
  color: #475569;

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

.pager__current small {
  color: #475569;

  font-size: 8px;
}

/* ============================================================
   RESPONSIVE — 900
   ============================================================ */

@media (max-width: 900px) {
  .wallet {
    width: calc(100% - 32px);

    padding-top: 24px;
  }

  .balance-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .balance-card {
    grid-column: 1 / -1;
  }

  .transaction-head,
  .transaction {
    grid-template-columns:
      105px
      minmax(180px, 1fr)
      85px
      95px;

    gap: 10px;
  }
}

/* ============================================================
   RESPONSIVE — 680
   ============================================================ */

@media (max-width: 680px) {
  .wallet {
    width: calc(100% - 24px);

    padding-top: 18px;
    padding-bottom: 45px;
  }

  .wallet__head {
    align-items: stretch;

    margin-bottom: 18px;
  }

  .wallet__title {
    font-size: 24px;
  }

  .wallet__subtitle {
    font-size: 11.5px;
  }

  .wallet__shop-btn {
    justify-content: center;

    width: 100%;
  }

  .balance-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 8px;
  }

  .balance-card {
    min-height: 205px;

    padding: 16px;
  }

  .balance-card__amount {
    font-size: 34px;
  }

  .metric-card {
    min-height: 145px;

    padding: 13px;
  }

  .metric-card__value {
    font-size: 20px;
  }

  .daily-bonus {
    right: 9px;
    bottom: 9px;
    left: 9px;
  }

  .panel {
    padding: 13px;
  }

  .transaction-head {
    display: none;
  }

  .transaction {
    grid-template-columns:
      1fr auto;

    gap: 8px;

    min-height: 67px;

    padding: 9px 8px;
  }

  .transaction__date {
    order: 3;

    grid-column: 1;

    flex-direction: row;

    gap: 5px;

    font-size: 8px;
  }

  .transaction__operation {
    grid-column: 1;

    grid-row: 1 / span 2;
  }

  .transaction__amount {
    grid-column: 2;
    grid-row: 1;

    align-self: center;
  }

  .transaction__balance {
    grid-column: 2;
    grid-row: 2;

    font-size: 8px;
  }

  .transaction__date {
    display: none;
  }

  .transaction__icon {
    width: 28px;
    height: 28px;
  }

  .transaction__operation-info strong {
    font-size: 9px;
  }

  .transaction__operation-info small {
    font-size: 7.5px;
  }

  .transaction__amount {
    font-size: 10px;
  }

  .transaction__balance {
    font-size: 9px;
  }
}

/* ============================================================
   RESPONSIVE — 460
   ============================================================ */

@media (max-width: 460px) {
  .wallet {
    width: calc(100% - 18px);
  }

  .wallet__title {
    font-size: 21px;
  }

  .wallet__eyebrow {
    font-size: 7px;
  }

  .balance-grid {
    grid-template-columns: 1fr;
  }

  .balance-card {
    grid-column: auto;

    min-height: 205px;
  }

  .metric-card {
    min-height: 125px;
  }

  .metric-card__value {
    font-size: 22px;
  }

  .source-row {
    padding: 6px 3px;
  }

  .source-row__icon {
    width: 27px;
    height: 27px;
  }

  .panel__title {
    font-size: 12px;
  }

  .panel__eyebrow {
    font-size: 6.5px;
  }

  .history__live {
    display: none;
  }

  .transaction {
    min-height: 62px;
  }

  .pager {
    gap: 7px;
  }

  .pager__button {
    padding: 0 8px;
  }
}

/* ============================================================
   REDUCED MOTION
   ============================================================ */

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: .01ms !important;
  }
}

/* Пригласительные ссылки */
.referral__lead { margin: 0 0 14px; color: var(--text-dim); font-size: 14px; line-height: 1.6; }
.referral__lead b { color: #facc15; }
.referral__row { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.referral__input {
  flex: 1 1 260px;
  min-width: 0;
  padding: 10px 14px;
  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 13px;
}
.referral__meta { display: flex; gap: 18px; flex-wrap: wrap; color: var(--text-dim); font-size: 13px; margin-bottom: 12px; }
.referral__meta b { color: var(--text); }
.referral__list { display: flex; flex-direction: column; gap: 4px; border-top: 1px solid var(--border); padding-top: 12px; }
.referral__user { display: flex; justify-content: space-between; font-size: 13px; }
.referral__user-name { color: var(--text); font-weight: 600; }
.referral__user-date { color: var(--text-muted); }
</style>