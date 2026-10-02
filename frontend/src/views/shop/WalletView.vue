<script setup>
import { computed, onMounted, ref } from 'vue'
import { walletApi } from '@/services/wallet/wallet.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { SOURCE_LABELS, formatCoins } from '@/data/wallet/economy.js'

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
@import "@/views/shop/WalletView.css";
</style>
