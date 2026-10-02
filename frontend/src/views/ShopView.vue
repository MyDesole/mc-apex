<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { shopApi } from '@/services/shop.js'
import { walletApi } from '@/services/wallet.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'
import {
  TYPE_LABELS,
  RARITY_LABELS,
  RARITY_COLORS,
  formatCoins,
  itemSubtitle,
} from '@/data/economy.js'

const auth = useAuthStore()
const route = useRoute()

const loading = ref(true)
const error = ref('')
const notice = ref('')

const items = ref([])
const balance = ref(0)
const tierRewards = ref({})
const dailyBonusAvailable = ref(false)
const dailyBonusAmount = ref(0)

const TABS = [
  { value: 'all', label: 'Всё' },
  // Подсветка клана: отдельная вкладка, иначе предмет теряется в общем списке
  { value: 'clan_highlight', label: 'Подсветка клана' },
  { value: 'tier_priority', label: 'Приоритет теста' },
  { value: 'avatar_frame', label: 'Рамки' },
  { value: 'profile_effect', label: 'Эффекты' },
  { value: 'badge', label: 'Бейджи' },
  { value: 'accent_color', label: 'Акценты' },
]

/**
 * Вкладка при открытии: из адреса (?type=clan_highlight), иначе «Всё».
 * Ссылки из других разделов ведут сразу на нужную категорию.
 */
const requestedType = String(route.query.type ?? '')

const activeType = ref(
    TABS.some((tab) => tab.value === requestedType) ? requestedType : 'all'
)

const filtered = computed(() => {
  if (activeType.value === 'all') return items.value

  return items.value.filter((item) => item.type === activeType.value)
})

// === Покупка ===
const purchaseItem = ref(null)
const purchaseQuantity = ref(1)
const purchaseBusy = ref(false)

const priorityCandidates = ref([])
const priorityTargetId = ref(null)
const priorityCharges = ref(0)
const priorityLoading = ref(false)

const isPriorityPurchase = computed(() => {
  return purchaseItem.value?.type === 'tier_priority'
})

const totalPrice = computed(() => {
  return (purchaseItem.value?.price ?? 0) * purchaseQuantity.value
})

const canPay = computed(() => {
  return totalPrice.value <= balance.value
})

function rarityStyle(item) {
  return {
    '--rarity': RARITY_COLORS[item.rarity] || RARITY_COLORS.common,
  }
}

function modeLabel(mode) {
  return mode === 'pvp' ? 'PvP' : 'BedWars'
}

function formatBalance(value) {
  return formatCoins(value ?? 0)
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await shopApi.catalog()

    items.value = data.items ?? []
    balance.value = data.balance ?? auth.user?.apex_coins ?? 0
    tierRewards.value = data.tier_rewards ?? {}

    if (auth.isAuthenticated) {
      const wallet = await walletApi.summary()

      dailyBonusAvailable.value = Boolean(wallet.daily_bonus_available)
      dailyBonusAmount.value = wallet.daily_bonus_amount ?? 0
      balance.value = wallet.balance ?? 0
    }
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить магазин.'
  } finally {
    loading.value = false
  }
}

async function claimDaily() {
  error.value = ''
  notice.value = ''

  try {
    const result = await walletApi.claimDailyBonus()

    notice.value = result.message
    balance.value = result.balance
    dailyBonusAvailable.value = false

    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось получить бонус.'
  }
}

function openPurchase(item) {
  if (!auth.isAuthenticated) {
    error.value = 'Войдите, чтобы покупать предметы.'
    return
  }

  error.value = ''
  notice.value = ''

  purchaseItem.value = item
  purchaseQuantity.value = 1
  priorityTargetId.value = null
  priorityCandidates.value = []

  if (item.type === 'tier_priority') {
    loadPriorityCandidates()
  }
}

function closePurchase() {
  purchaseItem.value = null
  priorityCandidates.value = []
  priorityTargetId.value = null
}

async function loadPriorityCandidates() {
  priorityLoading.value = true

  try {
    const data = await shopApi.priorityCandidates()

    priorityCandidates.value = data.tier_tests ?? []
    priorityCharges.value = data.charges ?? 0
    priorityTargetId.value = null
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить заявки.'
  } finally {
    priorityLoading.value = false
  }
}

async function confirmPurchase() {
  if (!purchaseItem.value) return

  purchaseBusy.value = true
  error.value = ''

  try {
    const payload = {}

    if (purchaseQuantity.value > 1) {
      payload.quantity = purchaseQuantity.value
    }

    if (isPriorityPurchase.value && priorityTargetId.value) {
      payload.tier_test_id = priorityTargetId.value
    }

    const result = await shopApi.purchase(
        purchaseItem.value.id,
        payload,
    )

    notice.value = result.message
    balance.value = result.balance

    const owned = items.value.find(
        (item) => item.id === purchaseItem.value.id,
    )

    if (owned) {
      owned.owned = true
      owned.quantity =
          (owned.quantity ?? 0) +
          (purchaseQuantity.value > 1
              ? purchaseQuantity.value
              : 1)

      owned.can_afford = owned.price <= balance.value
    }

    await auth.fetchMe()
    closePurchase()
  } catch (e) {
    error.value = e.message || 'Не удалось купить предмет.'
  } finally {
    purchaseBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shop">
    <!-- HEADER -->
    <header class="shop__head">
      <div class="shop__heading">
        <span class="eyebrow">APEX ECONOMY</span>

        <h1 class="shop__title">
          Магазин
        </h1>

        <p class="shop__subtitle">
          Косметика и приоритет тир-тестов за ApexCoin
        </p>
      </div>

      <div v-if="auth.isAuthenticated" class="shop__actions">
        <div class="balance">
          <span class="balance__coin">₳</span>

          <div class="balance__content">
            <span class="balance__label">BALANCE</span>
            <strong class="balance__value">
              {{ formatBalance(balance) }}
            </strong>
          </div>
        </div>

        <button
            v-if="dailyBonusAvailable"
            class="btn btn--gold"
            type="button"
            @click="claimDaily"
        >
          <AppIcon icon="gift" :size="17" />
          Забрать +{{ dailyBonusAmount }}
        </button>

        <RouterLink
            class="btn btn--ghost"
            :to="{ name: 'wallet' }"
        >
          Кошелёк
        </RouterLink>
      </div>

      <RouterLink
          v-else
          class="btn btn--gold"
          :to="{ name: 'login' }"
      >
        Войти, чтобы покупать
      </RouterLink>
    </header>

    <!-- ALERTS -->
    <div v-if="error" class="alert alert--error">
      <AppIcon icon="alert-circle" :size="18" />
      <span>{{ error }}</span>
    </div>

    <div v-if="notice" class="alert alert--ok">
      <AppIcon icon="check-circle" :size="18" />
      <span>{{ notice }}</span>
    </div>

    <!-- EARN -->
    <section class="earn">
      <div class="section-head">
        <div>
          <span class="eyebrow">APEXCOIN</span>
          <h2 class="section-title">
            Как заработать монеты
          </h2>
        </div>

        <span class="section-badge">
          4 источника
        </span>
      </div>

      <div class="earn__grid">
        <!-- TIER TEST -->
        <article class="earn-card earn-card--primary">
          <div class="earn-card__icon">
            <AppIcon icon="target" :size="22" />
          </div>

          <div class="earn-card__body">
            <div class="earn-card__top">
              <h3>Тир-тест</h3>
              <span class="earn-card__tag">CORE</span>
            </div>

            <p>
              Основной источник монет за каждый пройденный тест.
            </p>

            <ul
                v-if="Object.keys(tierRewards).length"
                class="earn-card__tiers"
            >
              <li
                  v-for="(amount, tier) in tierRewards"
                  :key="tier"
              >
                <span>{{ tier }}</span>
                <strong>+{{ formatCoins(amount) }}</strong>
              </li>
            </ul>
          </div>
        </article>

        <!-- ACHIEVEMENTS -->
        <article class="earn-card">
          <div class="earn-card__icon">
            <AppIcon icon="medal" :size="22" />
          </div>

          <div class="earn-card__body">
            <h3>Ачивки</h3>

            <p>
              Каждая открытая ачивка приносит монеты.
              Чем она реже — тем больше награда.
            </p>
          </div>
        </article>

        <!-- DAILY -->
        <article class="earn-card">
          <div class="earn-card__icon">
            <AppIcon icon="gift" :size="22" />
          </div>

          <div class="earn-card__body">
            <div class="earn-card__top">
              <h3>Ежедневный вход</h3>

              <span
                  v-if="dailyBonusAvailable"
                  class="earn-card__live"
              >
                ДОСТУПНО
              </span>
            </div>

            <p>
              Заходи каждый день и забирай бонус
              в кошельке.
            </p>
          </div>
        </article>

        <!-- PRIORITY -->
        <article class="earn-card">
          <div class="earn-card__icon">
            <AppIcon icon="rocket" :size="22" />
          </div>

          <div class="earn-card__body">
            <h3>Приоритет</h3>

            <p>
              Купи буст — заявка уйдёт в начало
              очереди тестеров.
            </p>
          </div>
        </article>
      </div>
    </section>

    <!-- FILTERS -->
    <div class="catalog-head">
      <div>
        <span class="eyebrow">CATALOG</span>
        <h2 class="section-title">
          Предметы
        </h2>
      </div>

      <nav class="tabs">
        <button
            v-for="tab in TABS"
            :key="tab.value"
            class="tabs__tab"
            :class="{
            'tabs__tab--active': activeType === tab.value,
          }"
            type="button"
            @click="activeType = tab.value"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="state">
      <div class="state__loader"></div>
      <span>Загружаем каталог…</span>
    </div>

    <!-- EMPTY -->
    <div v-else-if="!filtered.length" class="empty">
      <div class="empty__icon">
        <AppIcon icon="box" :size="28" />
      </div>

      <h3>Здесь пока пусто</h3>

      <p>
        В этой категории пока нет доступных предметов.
      </p>
    </div>

    <!-- CATALOG -->
    <div v-else class="grid">
      <article
          v-for="item in filtered"
          :key="item.id"
          class="card"
          :style="rarityStyle(item)"
      >
        <div class="card__glow"></div>

        <div class="card__top">
          <div class="card__icon">
            <AppIcon
                :icon="item.icon"
                :size="25"
            />
          </div>

          <span class="card__rarity">
            {{ RARITY_LABELS[item.rarity] || item.rarity }}
          </span>
        </div>

        <div class="card__content">
          <h3 class="card__name">
            {{ item.name }}
          </h3>

          <p class="card__type">
            {{ itemSubtitle(item) }}
          </p>

          <p
              v-if="item.description"
              class="card__desc"
          >
            {{ item.description }}
          </p>
        </div>

        <div class="card__foot">
          <div class="price">
            <span class="price__coin">₳</span>
            <span>{{ formatCoins(item.price) }}</span>
          </div>

          <span
              v-if="item.owned && !item.is_repeatable"
              class="owned"
          >
            <AppIcon icon="check" :size="15" />
            Куплено
          </span>

          <button
              v-else
              class="btn btn--buy"
              type="button"
              :disabled="
              auth.isAuthenticated &&
              !item.can_afford
            "
              @click="openPurchase(item)"
          >
            {{
              item.can_afford === false
                  ? 'Не хватает'
                  : 'Купить'
            }}
          </button>
        </div>
      </article>
    </div>

    <!-- PURCHASE MODAL -->
    <div
        v-if="purchaseItem"
        class="modal"
        @click.self="closePurchase"
    >
      <div class="modal__box">
        <div class="modal__header">
          <div>
            <span class="eyebrow">
              PURCHASE
            </span>

            <h3 class="modal__title">
              {{ purchaseItem.name }}
            </h3>

            <p class="modal__type">
              {{
                TYPE_LABELS[purchaseItem.type] ||
                purchaseItem.type
              }}
            </p>
          </div>

          <button
              class="modal__close"
              type="button"
              aria-label="Закрыть"
              @click="closePurchase"
          >
            ×
          </button>
        </div>

        <!-- PRIORITY -->
        <template v-if="isPriorityPurchase">
          <div class="modal-info">
            <AppIcon icon="rocket" :size="20" />

            <p>
              Приоритет поднимает заявку в начало
              очереди тестеров и сгорает после её
              завершения.
            </p>
          </div>

          <p class="modal__hint">
            Можно применить сразу или купить впрок —
            заряд будет ждать в инвентаре.
          </p>

          <div
              v-if="priorityLoading"
              class="state state--sm"
          >
            <div class="state__loader"></div>
            <span>Загружаем заявки…</span>
          </div>

          <template
              v-else-if="priorityCandidates.length"
          >
            <div class="modal-section-title">
              <span>Применить сейчас</span>

              <span class="charges">
                {{ priorityCharges }} зарядов
              </span>
            </div>

            <div class="tests">
              <label class="tests__row">
                <input
                    v-model="priorityTargetId"
                    type="radio"
                    :value="null"
                >

                <span class="tests__radio"></span>

                <span class="tests__content">
                  <strong>
                    Не сейчас
                  </strong>

                  <small>
                    Оставить в инвентаре
                  </small>
                </span>
              </label>

              <label
                  v-for="test in priorityCandidates"
                  :key="test.id"
                  class="tests__row"
                  :class="{
                  'tests__row--disabled':
                    test.is_priority,
                }"
              >
                <input
                    v-model="priorityTargetId"
                    type="radio"
                    :value="test.id"
                    :disabled="test.is_priority"
                >

                <span class="tests__radio"></span>

                <span class="tests__content">
                  <strong>
                    {{ modeLabel(test.mode) }}
                  </strong>

                  <small>
                    Тест #{{ test.id }}
                  </small>
                </span>

                <span
                    v-if="test.is_priority"
                    class="tests__flag"
                >
                  PRIORITY
                </span>
              </label>
            </div>
          </template>

          <div
              v-else
              class="modal-info modal-info--blue"
          >
            <AppIcon icon="info" :size="19" />

            <p>
              Активных заявок нет — приоритет
              сохранится в инвентаре.
            </p>
          </div>
        </template>

        <!-- QUANTITY -->
        <label
            v-if="
            purchaseItem.is_repeatable &&
            !isPriorityPurchase
          "
            class="qty"
        >
          <span>Количество</span>

          <input
              v-model.number="purchaseQuantity"
              type="number"
              min="1"
              max="10"
          >
        </label>

        <!-- TOTAL -->
        <div class="modal-total">
          <div>
            <span class="modal-total__label">
              Итого
            </span>

            <span class="modal-total__balance">
              Баланс: ₳ {{ formatCoins(balance) }}
            </span>
          </div>

          <strong>
            ₳ {{ formatCoins(totalPrice) }}
          </strong>
        </div>

        <div
            v-if="!canPay"
            class="alert alert--error"
        >
          <AppIcon icon="alert-circle" :size="17" />

          <span>
            Не хватает
            {{ formatCoins(totalPrice - balance) }}
            ApexCoin.
          </span>
        </div>

        <!-- ACTIONS -->
        <div class="modal__actions">
          <button
              class="btn btn--ghost"
              type="button"
              @click="closePurchase"
          >
            Отмена
          </button>

          <button
              class="btn btn--gold"
              type="button"
              :disabled="
              purchaseBusy || !canPay
            "
              @click="confirmPurchase"
          >
            {{
              purchaseBusy
                  ? 'Покупаем…'
                  : 'Подтвердить покупку'
            }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.shop {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
  padding: 34px 0 72px;
  color: var(--text);
}

/* =========================================================
   SHARED
========================================================= */

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: var(--text-muted);
  font-size: 0.68rem;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.eyebrow::before {
  width: 5px;
  height: 5px;
  content: '';
  background: var(--accent);
  border-radius: 50%;
  box-shadow: 0 0 10px rgba(124, 58, 237, 0.8);
}

.section-title {
  margin: 5px 0 0;
  color: var(--text);
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

/* =========================================================
   HEADER
========================================================= */

.shop__head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 30px;
}

.shop__title {
  margin: 7px 0 0;
  color: var(--text);
  font-size: clamp(2rem, 4vw, 2.7rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.045em;
}

.shop__subtitle {
  max-width: 620px;
  margin: 9px 0 0;
  color: var(--text-dim);
  font-size: 0.92rem;
}

.shop__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 9px;
  flex-wrap: wrap;
}

/* =========================================================
   BALANCE
========================================================= */

.balance {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 46px;
  padding: 7px 13px 7px 9px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 11px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.14);
}

.balance__coin {
  display: grid;
  width: 31px;
  height: 31px;
  place-items: center;
  color: #1b1704;
  background: #facc15;
  border-radius: 50%;
  box-shadow:
      0 0 18px rgba(250, 204, 21, 0.22),
      inset 0 -2px 0 rgba(0, 0, 0, 0.12);
  font-size: 0.9rem;
  font-weight: 900;
}

.balance__content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.balance__label {
  color: var(--text-muted);
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.1em;
}

.balance__value {
  color: var(--text);
  font-size: 0.9rem;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

/* =========================================================
   BUTTONS
========================================================= */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 40px;
  padding: 9px 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 800;
  line-height: 1;
  text-decoration: none;
  cursor: pointer;
  transition:
      transform 0.16s ease,
      border-color 0.16s ease,
      background 0.16s ease,
      box-shadow 0.16s ease,
      opacity 0.16s ease;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.btn:active:not(:disabled) {
  transform: translateY(0);
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.btn--gold {
  color: #181506;
  background: #facc15;
  border-color: #facc15;
  box-shadow:
      0 5px 18px rgba(250, 204, 21, 0.12),
      inset 0 1px 0 rgba(255, 255, 255, 0.22);
}

.btn--gold:hover:not(:disabled) {
  background: #fde047;
  border-color: #fde047;
  box-shadow:
      0 8px 24px rgba(250, 204, 21, 0.18);
}

.btn--ghost {
  color: var(--text-dim);
  background: var(--bg-card);
  border-color: var(--border);
}

.btn--ghost:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover);
  background: rgba(255, 255, 255, 0.025);
}

.btn--buy {
  min-height: 34px;
  padding: 8px 13px;
  color: var(--text);
  background: rgba(124, 58, 237, 0.13);
  border-color: rgba(124, 58, 237, 0.35);
}

.btn--buy:hover:not(:disabled) {
  background: rgba(124, 58, 237, 0.23);
  border-color: var(--accent);
  box-shadow: 0 5px 18px rgba(124, 58, 237, 0.15);
}

/* =========================================================
   ALERTS
========================================================= */

.alert {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 16px;
  padding: 11px 14px;
  border-radius: 10px;
  font-size: 0.84rem;
  line-height: 1.45;
}

.alert--error {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.28);
}

.alert--ok {
  color: #86efac;
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.28);
}

/* =========================================================
   EARN
========================================================= */

.earn {
  position: relative;
  overflow: hidden;
  margin-bottom: 34px;
  padding: 20px;
  background:
      radial-gradient(
          circle at 100% 0%,
          rgba(124, 58, 237, 0.13),
          transparent 38%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 15px;
}

.earn::after {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 180px;
  height: 1px;
  content: '';
  background: linear-gradient(
      90deg,
      transparent,
      var(--accent)
  );
  opacity: 0.7;
}

.section-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 16px;
}

.section-badge {
  padding: 5px 8px;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.earn__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.earn-card {
  position: relative;
  display: flex;
  gap: 12px;
  min-width: 0;
  padding: 14px;
  background: rgba(255, 255, 255, 0.018);
  border: 1px solid var(--border);
  border-radius: 11px;
  transition:
      transform 0.16s ease,
      border-color 0.16s ease,
      background 0.16s ease;
}

.earn-card:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.025);
  border-color: var(--border-hover);
}

.earn-card--primary {
  background:
      linear-gradient(
          145deg,
          rgba(124, 58, 237, 0.1),
          rgba(255, 255, 255, 0.015)
      );
}

.earn-card__icon {
  display: grid;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  place-items: center;
  color: var(--accent);
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.18);
  border-radius: 9px;
}

.earn-card__body {
  min-width: 0;
}

.earn-card h3 {
  margin: 0;
  color: var(--text);
  font-size: 0.88rem;
  font-weight: 800;
}

.earn-card p {
  margin: 5px 0 0;
  color: var(--text-dim);
  font-size: 0.75rem;
  line-height: 1.45;
}

.earn-card__top {
  display: flex;
  align-items: center;
  gap: 7px;
}

.earn-card__tag,
.earn-card__live {
  padding: 3px 5px;
  color: var(--accent);
  background: rgba(124, 58, 237, 0.1);
  border-radius: 4px;
  font-size: 0.52rem;
  font-weight: 900;
  letter-spacing: 0.08em;
}

.earn-card__live {
  color: #86efac;
  background: rgba(34, 197, 94, 0.08);
}

.earn-card__tiers {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
  margin: 9px 0 0;
  padding: 0;
  list-style: none;
}

.earn-card__tiers li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 5px;
  padding: 4px 6px;
  background: rgba(0, 0, 0, 0.12);
  border-radius: 5px;
  font-size: 0.65rem;
}

.earn-card__tiers span {
  color: var(--text-muted);
  font-weight: 700;
}

.earn-card__tiers strong {
  color: #facc15;
  font-size: 0.63rem;
}

/* =========================================================
   CATALOG HEADER / TABS
========================================================= */

.catalog-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 17px;
}

.tabs {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}

.tabs__tab {
  min-height: 32px;
  padding: 7px 11px;
  color: var(--text-muted);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.71rem;
  font-weight: 700;
  cursor: pointer;
  transition:
      color 0.15s ease,
      background 0.15s ease,
      border-color 0.15s ease;
}

.tabs__tab:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.tabs__tab--active {
  color: var(--text);
  background: rgba(124, 58, 237, 0.16);
  border-color: var(--accent);
  box-shadow: 0 0 18px rgba(124, 58, 237, 0.07);
}

/* =========================================================
   GRID
========================================================= */

.grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

/* =========================================================
   ITEM CARD
========================================================= */

.card {
  position: relative;
  display: flex;
  min-height: 235px;
  flex-direction: column;
  overflow: hidden;
  padding: 16px;
  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.018),
          transparent 48%
      ),
      var(--bg-card);
  border: 1px solid var(--border);
  border-top: 2px solid var(--rarity);
  border-radius: 12px;
  isolation: isolate;
  transition:
      transform 0.18s ease,
      border-color 0.18s ease,
      box-shadow 0.18s ease;
}

.card:hover {
  transform: translateY(-3px);
  border-color: var(--border-hover);
  border-top-color: var(--rarity);
  box-shadow:
      0 12px 30px rgba(0, 0, 0, 0.18),
      0 0 22px color-mix(
          in srgb,
          var(--rarity) 8%,
          transparent
      );
}

.card__glow {
  position: absolute;
  top: -70px;
  right: -60px;
  z-index: -1;
  width: 150px;
  height: 150px;
  background: var(--rarity);
  border-radius: 50%;
  filter: blur(65px);
  opacity: 0.07;
  pointer-events: none;
}

.card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  color: var(--rarity);
  background: color-mix(
      in srgb,
      var(--rarity) 9%,
      transparent
  );
  border: 1px solid color-mix(
      in srgb,
      var(--rarity) 22%,
      transparent
  );
  border-radius: 10px;
}

.card__rarity {
  padding-top: 3px;
  color: var(--rarity);
  font-size: 0.58rem;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.card__content {
  flex: 1;
  padding-top: 15px;
}

.card__name {
  margin: 0;
  color: var(--text);
  font-size: 0.98rem;
  font-weight: 850;
  letter-spacing: -0.015em;
}

.card__type {
  margin: 4px 0 0;
  color: var(--text-muted);
  font-size: 0.69rem;
}

.card__desc {
  margin: 10px 0 0;
  color: var(--text-dim);
  font-size: 0.75rem;
  line-height: 1.5;
}

.card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding-top: 15px;
}

.price {
  display: flex;
  align-items: center;
  gap: 5px;
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
}

.price__coin {
  color: #facc15;
  font-size: 0.9rem;
  font-weight: 900;
}

.owned {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #86efac;
  font-size: 0.68rem;
  font-weight: 800;
}

/* =========================================================
   STATES
========================================================= */

.state {
  display: flex;
  min-height: 220px;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--text-muted);
  font-size: 0.82rem;
}

.state--sm {
  min-height: 90px;
}

.state__loader {
  width: 16px;
  height: 16px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty {
  display: flex;
  min-height: 230px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 13px;
  text-align: center;
}

.empty__icon {
  display: grid;
  width: 52px;
  height: 52px;
  margin-bottom: 12px;
  place-items: center;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.empty h3 {
  margin: 0;
  color: var(--text);
  font-size: 0.95rem;
}

.empty p {
  margin: 6px 0 0;
  color: var(--text-muted);
  font-size: 0.78rem;
}

/* =========================================================
   MODAL
========================================================= */

.modal {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(4, 4, 8, 0.78);
  backdrop-filter: blur(8px);
}

.modal__box {
  position: relative;
  width: min(500px, 100%);
  max-height: 88vh;
  overflow-y: auto;
  padding: 22px;
  background:
      radial-gradient(
          circle at 100% 0%,
          rgba(124, 58, 237, 0.1),
          transparent 35%
      ),
      var(--bg-card);
  border: 1px solid var(--border-hover);
  border-radius: 15px;
  box-shadow:
      0 25px 80px rgba(0, 0, 0, 0.45),
      0 0 45px rgba(124, 58, 237, 0.07);
}

.modal__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 17px;
}

.modal__title {
  margin: 6px 0 0;
  color: var(--text);
  font-size: 1.25rem;
  font-weight: 850;
}

.modal__type {
  margin: 4px 0 0;
  color: var(--text-muted);
  font-size: 0.72rem;
}

.modal__close {
  display: grid;
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  place-items: center;
  padding: 0;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 1.25rem;
  line-height: 1;
  cursor: pointer;
}

.modal__close:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.modal-info {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
  padding: 12px;
  color: #c4b5fd;
  background: rgba(124, 58, 237, 0.08);
  border: 1px solid rgba(124, 58, 237, 0.18);
  border-radius: 9px;
}

.modal-info p {
  margin: 0;
  font-size: 0.76rem;
  line-height: 1.5;
}

.modal-info--blue {
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.07);
  border-color: rgba(99, 102, 241, 0.17);
}

.modal__hint {
  margin: 0 0 15px;
  color: var(--text-dim);
  font-size: 0.76rem;
  line-height: 1.5;
}

.modal-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
  color: var(--text-dim);
  font-size: 0.7rem;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.charges {
  color: #facc15;
}

/* =========================================================
   TESTS
========================================================= */

.tests {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.tests__row {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 50px;
  padding: 9px 11px;
  background: rgba(255, 255, 255, 0.018);
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  transition:
      border-color 0.15s ease,
      background 0.15s ease;
}

.tests__row:hover {
  background: rgba(255, 255, 255, 0.025);
  border-color: var(--border-hover);
}

.tests__row input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.tests__radio {
  width: 15px;
  height: 15px;
  flex: 0 0 15px;
  border: 1px solid var(--border-hover);
  border-radius: 50%;
}

.tests__row:has(input:checked) {
  background: rgba(124, 58, 237, 0.08);
  border-color: var(--accent);
}

.tests__row:has(input:checked) .tests__radio {
  border: 4px solid var(--accent);
}

.tests__row--disabled {
  opacity: 0.48;
  cursor: not-allowed;
}

.tests__content {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.tests__content strong {
  color: var(--text);
  font-size: 0.76rem;
}

.tests__content small {
  color: var(--text-muted);
  font-size: 0.65rem;
}

.tests__flag {
  margin-left: auto;
  padding: 4px 6px;
  color: #facc15;
  background: rgba(250, 204, 21, 0.07);
  border-radius: 5px;
  font-size: 0.52rem;
  font-weight: 900;
  letter-spacing: 0.06em;
}

/* =========================================================
   QUANTITY / TOTAL
========================================================= */

.qty {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin: 5px 0 15px;
  color: var(--text-dim);
  font-size: 0.78rem;
  font-weight: 700;
}

.qty input {
  width: 82px;
  padding: 8px 10px;
  color: var(--text);
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border);
  border-radius: 8px;
  outline: none;
  font-size: 0.8rem;
}

.qty input:focus {
  border-color: var(--accent);
}

.modal-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  margin-top: 14px;
  padding: 14px 0;
  border-top: 1px solid var(--border);
}

.modal-total > div {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.modal-total__label {
  color: var(--text-dim);
  font-size: 0.76rem;
  font-weight: 700;
}

.modal-total__balance {
  color: var(--text-muted);
  font-size: 0.62rem;
}

.modal-total > strong {
  color: #facc15;
  font-size: 1.15rem;
  font-variant-numeric: tabular-nums;
}

.modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 7px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {
  .earn__grid,
  .grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .shop {
    width: min(100% - 24px, 680px);
    padding-top: 24px;
  }

  .shop__head {
    align-items: flex-start;
    flex-direction: column;
  }

  .shop__actions {
    width: 100%;
    justify-content: flex-start;
  }

  .balance {
    flex: 1;
  }

  .earn {
    padding: 15px;
  }

  .earn__grid,
  .grid {
    grid-template-columns: 1fr;
  }

  .catalog-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .tabs {
    width: 100%;
    justify-content: flex-start;
  }

  .tabs__tab {
    flex: 1;
  }
}

@media (max-width: 520px) {
  .shop {
    width: min(100% - 18px, 680px);
    padding-bottom: 40px;
  }

  .shop__title {
    font-size: 2rem;
  }

  .shop__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .balance {
    grid-column: 1 / -1;
  }

  .shop__actions .btn {
    width: 100%;
  }

  .section-head {
    align-items: flex-start;
  }

  .section-badge {
    display: none;
  }

  .earn-card {
    padding: 13px;
  }

  .card {
    min-height: 220px;
  }

  .modal {
    align-items: flex-end;
    padding: 8px;
  }

  .modal__box {
    max-height: 92vh;
    padding: 18px;
    border-radius: 14px;
  }

  .modal__actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .modal__actions .btn {
    width: 100%;
  }
}
</style>