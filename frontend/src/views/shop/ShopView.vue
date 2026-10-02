<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { shopApi } from '@/services/shop/shop.js'
import { walletApi } from '@/services/wallet/wallet.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import {
  TYPE_LABELS,
  RARITY_LABELS,
  RARITY_COLORS,
  formatCoins,
  itemSubtitle,
} from '@/data/wallet/economy.js'

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
  // Подсветка клана: отдельные вкладки для срока и оформления
  { value: 'clan_highlight', label: 'Подсветка клана' },
  { value: 'clan_highlight_style', label: 'Оформление подсветки' },
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
@import "@/views/shop/ShopView.css";
</style>
