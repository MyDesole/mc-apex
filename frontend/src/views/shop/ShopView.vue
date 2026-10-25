<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { shopApi } from '@/services/shop/shop.js'
import { walletApi } from '@/services/wallet/wallet.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
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
  { value: 'tier_priority', label: 'Приоритет теста' },
  { value: 'avatar_frame', label: 'Рамки' },
  { value: 'skin_backdrop', label: 'Фоны скина' },
  { value: 'card_theme', label: 'Карточки' },
  { value: 'profile_effect', label: 'Эффекты' },
  { value: 'badge', label: 'Бейджи' },
  { value: 'accent_color', label: 'Акценты' },
  // Подсветка клана: отдельные вкладки для срока и оформления
  { value: 'clan_highlight', label: 'Подсветка клана' },
  { value: 'clan_highlight_style', label: 'Оформление подсветки' },
]

/** Вкладки только с товарами: пустые категории не показываем. */
const visibleTabs = computed(() =>
    TABS.filter((tab) => tab.value === 'all' || items.value.some((item) => item.type === tab.value))
)

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

// Лидеру приходит казна клана: клановые предметы можно оплатить из неё
const clan = ref(null)
const payFrom = ref('balance')
const purchaseError = ref('')

const isClanPurchase = computed(() =>
    ['clan_highlight', 'clan_highlight_style'].includes(purchaseItem.value?.type),
)

const payBalance = computed(() =>
    isClanPurchase.value && payFrom.value === 'treasury' ? (clan.value?.treasury ?? 0) : balance.value,
)

const isPriorityPurchase = computed(() => {
  return purchaseItem.value?.type === 'tier_priority'
})

const totalPrice = computed(() => {
  return (purchaseItem.value?.price ?? 0) * purchaseQuantity.value
})

const canPay = computed(() => {
  return totalPrice.value <= payBalance.value
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
    clan.value = data.clan ?? null

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
  purchaseError.value = ''
  // Если на балансе не хватает, а в казне хватает — сразу предлагаем казну
  payFrom.value = clan.value && item.price > balance.value && item.price <= clan.value.treasury
      ? 'treasury'
      : 'balance'
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
  purchaseError.value = ''

  try {
    const payload = {}

    if (purchaseQuantity.value > 1) {
      payload.quantity = purchaseQuantity.value
    }

    if (isPriorityPurchase.value && priorityTargetId.value) {
      payload.tier_test_id = priorityTargetId.value
    }

    if (isClanPurchase.value && clan.value) {
      payload.pay_from = payFrom.value
    }

    const result = await shopApi.purchase(
        purchaseItem.value.id,
        payload,
    )

    notice.value = result.message
    balance.value = result.balance

    if (clan.value && result.clan_highlight) {
      clan.value.treasury = result.clan_highlight.treasury ?? clan.value.treasury
    }

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
    // Показываем причину внутри окна покупки: на телефоне окно закрывает
    // весь экран, и сообщение над витриной было не видно
    purchaseError.value = e.message || 'Не удалось купить предмет.'
  } finally {
    purchaseBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="shop">
    <!-- ===================== Шапка ===================== -->
    <header class="shop__head">
      <div>
        <h1 class="shop__title">Магазин</h1>
        <p class="shop__subtitle">Косметика для профиля и приоритет тир-тестов за ApexCoin</p>
      </div>

      <div v-if="auth.isAuthenticated" class="shop__actions">
        <div class="balance">
          <span class="balance__coin" aria-hidden="true">₳</span>
          <div class="balance__content">
            <span class="balance__label">Баланс</span>
            <strong class="balance__value">{{ formatBalance(balance) }}</strong>
          </div>
        </div>

        <button
            v-if="dailyBonusAvailable"
            class="btn btn-primary"
            type="button"
            @click="claimDaily"
        >
          <AppIcon icon="gift" :size="17" />
          Забрать +{{ dailyBonusAmount }}
        </button>

        <RouterLink class="btn btn-secondary" :to="{ name: 'wallet' }">Кошелёк</RouterLink>
        <RouterLink class="btn btn-secondary" :to="{ name: 'inventory' }">Инвентарь</RouterLink>
      </div>

      <RouterLink v-else class="btn btn-primary" :to="{ name: 'login' }">
        Войти, чтобы покупать
      </RouterLink>
    </header>

    <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>
    <div v-if="notice" class="alert alert--ok" role="status">{{ notice }}</div>

    <!-- ===================== Как заработать ===================== -->
    <section class="earn">
      <h2 class="section-title">Как заработать монеты</h2>

      <div class="earn__grid">
        <article class="earn-card earn-card--primary">
          <AppIcon icon="target" :size="20" />
          <div>
            <h3>Тир-тест</h3>
            <p>Главный источник монет — за каждый пройденный тест.</p>

            <ul v-if="Object.keys(tierRewards).length" class="earn-card__tiers">
              <li v-for="(amount, tier) in tierRewards" :key="tier">
                <span>{{ tier }}</span>
                <strong>+{{ formatCoins(amount) }}</strong>
              </li>
            </ul>
          </div>
        </article>

        <article class="earn-card">
          <AppIcon icon="medal" :size="20" />
          <div>
            <h3>Ачивки</h3>
            <p>Каждая ачивка приносит монеты. Чем реже — тем больше.</p>
            <RouterLink to="/achievements" class="earn-card__link">Все ачивки →</RouterLink>
          </div>
        </article>

        <article class="earn-card">
          <AppIcon icon="gift" :size="20" />
          <div>
            <h3>
              Ежедневный вход
              <span v-if="dailyBonusAvailable" class="earn-card__live">доступно</span>
            </h3>
            <p>Заходите каждый день и забирайте бонус в кошельке.</p>
          </div>
        </article>
      </div>
    </section>

    <!-- ===================== Каталог ===================== -->
    <div class="catalog-head">
      <h2 class="section-title">Предметы</h2>

      <nav class="tabs" aria-label="Категории">
        <button
            v-for="tab in visibleTabs"
            :key="tab.value"
            class="tabs__tab"
            :class="{ 'tabs__tab--active': activeType === tab.value }"
            type="button"
            @click="activeType = tab.value"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <div v-if="loading" class="state">Загружаем каталог…</div>

    <div v-else-if="!filtered.length" class="state">
      В этой категории пока нет предметов.
    </div>

    <div v-else class="grid">
      <article
          v-for="item in filtered"
          :key="item.id"
          class="card"
          :class="`card--${item.rarity || 'common'}`"
          :style="rarityStyle(item)"
      >
        <ShopItemPreview :item="item" />

        <div class="card__content">
          <div class="card__top">
            <h3 class="card__name">{{ item.name }}</h3>
            <span class="card__rarity">{{ RARITY_LABELS[item.rarity] || item.rarity }}</span>
          </div>

          <p class="card__type">{{ itemSubtitle(item) }}</p>
          <p v-if="item.description" class="card__desc">{{ item.description }}</p>
        </div>

        <div class="card__foot">
          <span class="price">
            <span class="price__coin" aria-hidden="true">₳</span>
            {{ formatCoins(item.price) }}
          </span>

          <span v-if="item.owned && !item.is_repeatable" class="owned">
            <AppIcon icon="check" :size="15" />
            Куплено
          </span>

          <button
              v-else
              class="btn btn-primary card__buy"
              type="button"
              :disabled="auth.isAuthenticated && !item.can_afford"
              @click="openPurchase(item)"
          >
            {{ item.can_afford === false ? 'Не хватает' : 'Купить' }}
          </button>
        </div>
      </article>
    </div>

    <!-- ===================== Покупка ===================== -->
    <div
        v-if="purchaseItem"
        class="modal"
        @click.self="closePurchase"
    >
      <div class="modal__box" role="dialog" aria-modal="true" :aria-label="`Покупка: ${purchaseItem.name}`">
        <ShopItemPreview :item="purchaseItem" class="modal__preview" />

        <div class="modal__header">
          <div>
            <h3 class="modal__title">{{ purchaseItem.name }}</h3>
            <p class="modal__type">{{ TYPE_LABELS[purchaseItem.type] || purchaseItem.type }}</p>
          </div>

          <button class="modal__close" type="button" aria-label="Закрыть" @click="closePurchase">×</button>
        </div>

        <!-- Приоритет тир-теста -->
        <template v-if="isPriorityPurchase">
          <p class="modal__hint">
            Приоритет поднимает заявку в начало очереди тестеров и сгорает после её завершения.
            Можно применить сразу или купить впрок — заряд подождёт в инвентаре.
          </p>

          <div v-if="priorityLoading" class="state state--sm">Загружаем заявки…</div>

          <template v-else-if="priorityCandidates.length">
            <div class="modal-section-title">
              <span>Применить сейчас</span>
              <span class="charges">{{ priorityCharges }} зарядов</span>
            </div>

            <div class="tests">
              <label class="tests__row">
                <input v-model="priorityTargetId" type="radio" :value="null">
                <span class="tests__content">
                  <strong>Не сейчас</strong>
                  <small>Оставить в инвентаре</small>
                </span>
              </label>

              <label
                  v-for="test in priorityCandidates"
                  :key="test.id"
                  class="tests__row"
                  :class="{ 'tests__row--disabled': test.is_priority }"
              >
                <input v-model="priorityTargetId" type="radio" :value="test.id" :disabled="test.is_priority">
                <span class="tests__content">
                  <strong>{{ modeLabel(test.mode) }}</strong>
                  <small>Тест #{{ test.id }}</small>
                </span>
                <span v-if="test.is_priority" class="tests__flag">Уже в приоритете</span>
              </label>
            </div>
          </template>

          <p v-else class="modal__hint">Активных заявок нет — приоритет сохранится в инвентаре.</p>
        </template>

        <label v-if="purchaseItem.is_repeatable && !isPriorityPurchase" class="qty">
          <span>Количество</span>
          <input v-model.number="purchaseQuantity" type="number" min="1" max="10">
        </label>

        <div v-if="isClanPurchase && clan" class="pay-from" role="radiogroup" aria-label="Чем платить">
          <label class="pay-from__option">
            <input v-model="payFrom" type="radio" value="balance">
            <span>Свой баланс · ₳ {{ formatCoins(balance) }}</span>
          </label>
          <label class="pay-from__option">
            <input v-model="payFrom" type="radio" value="treasury">
            <span>Казна клана [{{ clan.tag }}] · ₳ {{ formatCoins(clan.treasury) }}</span>
          </label>
        </div>

        <div class="modal-total">
          <div>
            <span class="modal-total__label">Итого</span>
            <span class="modal-total__balance">
              {{ isClanPurchase && payFrom === 'treasury' ? 'Казна' : 'Баланс' }}: ₳ {{ formatCoins(payBalance) }}
            </span>
          </div>
          <strong>₳ {{ formatCoins(totalPrice) }}</strong>
        </div>

        <div v-if="!canPay" class="alert alert--error">
          Не хватает {{ formatCoins(totalPrice - payBalance) }} ApexCoin{{ isClanPurchase && payFrom === 'treasury' ? ' в казне клана' : '' }}.
        </div>

        <div v-if="purchaseError" class="alert alert--error" role="alert">{{ purchaseError }}</div>

        <div class="modal__actions">
          <button class="btn btn-secondary" type="button" @click="closePurchase">Отмена</button>
          <button
              class="btn btn-primary"
              type="button"
              :disabled="purchaseBusy || !canPay"
              @click="confirmPurchase"
          >
            {{ purchaseBusy ? 'Покупаем…' : 'Подтвердить покупку' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/views/shop/ShopView.css";
</style>
