<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { shopApi } from '@/services/shop/shop.js'
import { useCursorList } from '@/composables/core/useCursorList.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { friendsApi } from '@/services/friends/friends.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import {
  TYPE_LABELS,
  RARITY_LABELS,
  RARITY_COLORS,
  itemSubtitle,
} from '@/data/wallet/economy.js'

const auth = useAuthStore()

const loading = ref(true)
const error = ref('')
const notice = ref('')
const busyId = ref(null)

const maxBadges = ref(3)
const priorityCharges = ref(0)
const priorityCandidates = ref([])

const applyItem = ref(null)
const applyTargetId = ref(null)
const applyBusy = ref(false)

/* ---------- Поиск, вкладки, сортировка ---------- */

// Порядок вкладок: сначала то, что меняет вид профиля
const TYPE_ORDER = [
  'profile_layout', 'profile_pet', 'profile_title', 'name_icon', 'chat_bubble', 'sticker_pack',
  'name_effect', 'profile_entrance', 'cursor_trail',
  'avatar_frame', 'profile_effect', 'skin_backdrop',
  'card_theme', 'accent_color', 'card_background', 'badge', 'tier_priority',
]


const search = ref('')
const activeTab = ref('all')
const sortBy = ref('rarity')

function readView() {
  try {
    const saved = JSON.parse(localStorage.getItem('apex:inventory-view') || '{}')
    if (saved.sortBy) sortBy.value = saved.sortBy
  } catch {
    // нет доступа к хранилищу — сортировка по умолчанию
  }
}

watch(sortBy, (value) => {
  try {
    localStorage.setItem('apex:inventory-view', JSON.stringify({ sortBy: value }))
  } catch {
    // не запомним — не страшно
  }
})

/*
 * Инвентарь по курсору: вкладка, поиск и сортировка на сервере, клиент
 * рисует по 24 предмета и догружает при прокрутке — сотни анимированных
 * превью разом тормозили страницу. С первой страницей приходят счётчики
 * вкладок и всё надетое (для «Сейчас надето»).
 */
const counts = ref({})
const equippedItems = ref([])

const list = useCursorList(
    ({ cursor }) => shopApi.inventoryPage({
      tab: activeTab.value,
      q: search.value.trim(),
      sort: sortBy.value,
      cursor,
    }),
    {
      onFirstPage(data) {
        counts.value = data.counts ?? {}
        equippedItems.value = data.equipped ?? []
        if (data.bundles) bundles.value = data.bundles
        if (data.max_equipped_badges) maxBadges.value = data.max_equipped_badges
        priorityCharges.value = data.priority_charges ?? priorityCharges.value
        priorityCandidates.value = data.priority_candidates ?? priorityCandidates.value
      },
    },
)

const visibleItems = list.items

const tabs = computed(() => {
  const types = Object.keys(counts.value)
      .filter((key) => !['all', 'equipped'].includes(key))
      .sort((a, b) => {
        const ia = TYPE_ORDER.indexOf(a)
        const ib = TYPE_ORDER.indexOf(b)
        return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib)
      })

  return [
    { key: 'all', label: 'Все', count: counts.value.all ?? 0 },
    { key: 'equipped', label: 'Надето', count: counts.value.equipped ?? 0 },
    ...types.map((type) => ({ key: type, label: TYPE_LABELS[type] || type, count: counts.value[type] })),
  ]
})

// Вкладка и сортировка — сразу, поиск — после паузы в наборе
watch([activeTab, sortBy], () => list.reload())

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => list.reload(), 300)
})

/* ---------- «Сейчас надето»: слоты образа ---------- */

const SLOTS = [
  { type: 'profile_layout', label: 'Макет', icon: 'frame' },
  { type: 'profile_pet', label: 'Питомец', icon: 'heart' },
  { type: 'profile_title', label: 'Титул', icon: 'crown' },
  { type: 'name_icon', label: 'Значок', icon: 'star' },
  { type: 'chat_bubble', label: 'Сообщения', icon: 'sparkles' },
  { type: 'name_effect', label: 'Ник', icon: 'sparkles' },
  { type: 'profile_entrance', label: 'Появление', icon: 'bolt' },
  { type: 'cursor_trail', label: 'След', icon: 'star' },
  { type: 'avatar_frame', label: 'Рамка', icon: 'frame' },
  { type: 'profile_effect', label: 'Эффект', icon: 'sparkles' },
  { type: 'skin_backdrop', label: 'Фон скина', icon: 'globe' },
  { type: 'card_theme', label: 'Карточка', icon: 'doc' },
  { type: 'accent_color', label: 'Акцент', icon: 'palette' },
]

const loadout = computed(() =>
    SLOTS.map((slot) => ({
      ...slot,
      item: equippedItems.value.find((i) => i.type === slot.type) ?? null,
      owned: counts.value[slot.type] ?? 0,
    })).filter((slot) => slot.owned > 0),
)

const equippedBadges = computed(() => equippedItems.value.filter((i) => i.type === 'badge'))

function openTab(key) {
  activeTab.value = key
  search.value = ''
}

const equippedCount = computed(() => counts.value.equipped ?? 0)

const hasPriorityItems = computed(() => (counts.value.tier_priority ?? 0) > 0)

// Всего предметов (для шапки и пустого состояния)
const totalOwned = computed(() => counts.value.all ?? 0)

async function load() {
  loading.value = true
  error.value = ''

  await list.reload()
  if (list.error.value) error.value = list.error.value || 'Не удалось загрузить инвентарь.'

  loading.value = false
}

function openApply(item) {
  applyItem.value = item

  applyTargetId.value =
      priorityCandidates.value.find((test) => !test.is_priority)?.id ?? null

  error.value = ''
  notice.value = ''
}

function closeApply() {
  if (applyBusy.value) return

  applyItem.value = null
  applyTargetId.value = null
}

async function confirmApply() {
  if (!applyTargetId.value) {
    error.value = 'Выберите заявку.'
    return
  }

  applyBusy.value = true
  error.value = ''

  try {
    const result = await shopApi.applyPriority(applyTargetId.value)

    notice.value = result.message
    priorityCharges.value = result.charges ?? 0

    closeApply()
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось применить приоритет.'
  } finally {
    applyBusy.value = false
  }
}

function modeLabel(mode) {
  return mode === 'pvp' ? 'PvP' : 'BedWars'
}

// Наборы: надеваются одной кнопкой — всё, что из набора есть
const bundles = ref([])
const bundleBusy = ref(null)

async function equipBundle(bundle) {
  bundleBusy.value = bundle.id
  error.value = ''
  notice.value = ''

  try {
    const result = await shopApi.equipBundle(bundle.id)

    bundles.value = result.bundles ?? bundles.value
    notice.value = result.message
    await list.reload()

    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось надеть набор.'
  } finally {
    bundleBusy.value = null
  }
}

async function toggleEquip(item) {
  busyId.value = item.id
  error.value = ''
  notice.value = ''

  try {
    // Питомец — конкретный экземпляр: их бывает несколько, на профиле до трёх
    const isPetInstance = item.type === 'profile_pet' && Number.isInteger(item.inventory_id)

    const result = isPetInstance
        ? await shopApi.togglePet(item.inventory_id, !item.equipped)
        : item.equipped
            ? await shopApi.unequip(item.id)
            : await shopApi.equip(item.id)

    notice.value = result.message
    // Надетое могло смениться в любом слоте — перечитываем первую страницу
    await list.reload()

    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось изменить экипировку.'
  } finally {
    busyId.value = null
  }
}

/* ---------- Продать и подарить ---------- */

async function sell(item) {
  if (!await confirmDialog(`Продать «${item.name}» магазину за ${item.sell_value} ApexCoin?`)) return

  busyId.value = item.id
  error.value = ''
  notice.value = ''
  try {
    const result = await shopApi.sell(item.inventory_id)
    notice.value = `«${item.name}» продан за ${result.coins} ApexCoin.`
    if (auth.user) auth.user.apex_coins = result.balance
    await list.reload()
  } catch (e) {
    error.value = e.message || 'Не удалось продать.'
  } finally {
    busyId.value = null
  }
}

const giftItem = ref(null)
const giftFriends = ref([])
const giftQuery = ref('')
const giftBusy = ref(false)

async function openGift(item) {
  giftItem.value = item
  giftQuery.value = ''
  if (!giftFriends.value.length) {
    try {
      giftFriends.value = (await friendsApi.list()).friends || []
    } catch {
      giftFriends.value = []
    }
  }
}

const giftList = computed(() => {
  const q = giftQuery.value.trim().toLowerCase()
  return q ? giftFriends.value.filter((f) => f.username?.toLowerCase().includes(q)) : giftFriends.value
})

async function sendGift(friend) {
  if (giftBusy.value) return
  giftBusy.value = true
  error.value = ''
  try {
    await shopApi.gift(giftItem.value.inventory_id, friend.id)
    notice.value = `«${giftItem.value.name}» подарен ${friend.username}.`
    giftItem.value = null
    await Promise.all([list.reload(), auth.fetchMe()])
  } catch (e) {
    error.value = e.message || 'Не удалось подарить.'
    giftItem.value = null
  } finally {
    giftBusy.value = false
  }
}

function rarityStyle(item) {
  return {
    '--rarity': RARITY_COLORS[item.rarity] || RARITY_COLORS.common,
  }
}

onMounted(() => {
  readView()
  load()
})
</script>

<template>
  <div class="inventory-page">
    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="inventory-header">
      <div class="inventory-header__main">

        <div class="inventory-header__row">
          <div>
            <h1 class="inventory-title">
              Инвентарь
            </h1>

            <p class="inventory-subtitle">
              Управляй своими предметами и экипировкой
            </p>
          </div>

        </div>
      </div>

      <RouterLink
          class="inventory-shop-btn"
          :to="{ name: 'shop' }"
      >
        <AppIcon icon="gem" :size="16" />
        <span>В магазин</span>
      </RouterLink>
    </header>

    <!-- =====================================================
         STATS
    ====================================================== -->

    <section class="inventory-stats">
      <div class="inventory-stat">
        <div class="inventory-stat__icon inventory-stat__icon--purple">
          <AppIcon icon="shield" :size="19" />
        </div>

        <div class="inventory-stat__content">
          <span class="inventory-stat__label">
            ЭКИПИРОВАНО
          </span>

          <strong class="inventory-stat__value">
            {{ equippedCount }}
          </strong>

          <span class="inventory-stat__hint">
            предметов
          </span>
        </div>
      </div>

      <div class="inventory-stat">
        <div class="inventory-stat__icon inventory-stat__icon--gold">
          <AppIcon icon="medal" :size="19" />
        </div>

        <div class="inventory-stat__content">
          <span class="inventory-stat__label">
            СЛОТОВ БЕЙДЖЕЙ
          </span>

          <strong class="inventory-stat__value">
            {{ equippedBadges.length }}/{{ maxBadges }}
          </strong>

          <span class="inventory-stat__hint">
            доступно
          </span>
        </div>
      </div>

      <div class="inventory-stat">
        <div class="inventory-stat__icon inventory-stat__icon--cyan">
          <AppIcon icon="bolt" :size="19" />
        </div>

        <div class="inventory-stat__content">
          <span class="inventory-stat__label">
            ПРИОРИТЕТЫ
          </span>

          <strong class="inventory-stat__value">
            {{ priorityCharges }}
          </strong>

          <span class="inventory-stat__hint">
            зарядов
          </span>
        </div>
      </div>

      <div class="inventory-stat">
        <div class="inventory-stat__icon inventory-stat__icon--green">
          <AppIcon icon="box" :size="19" />
        </div>

        <div class="inventory-stat__content">
          <span class="inventory-stat__label">
            ПРЕДМЕТЫ
          </span>

          <strong class="inventory-stat__value">
            {{ totalOwned }}
          </strong>

          <span class="inventory-stat__hint">
            в коллекции
          </span>
        </div>
      </div>
    </section>

    <!-- =====================================================
         NOTICES
    ====================================================== -->

    <transition name="notice">
      <div
          v-if="error"
          class="inventory-alert inventory-alert--error"
      >
        <div class="inventory-alert__icon">
          <AppIcon icon="alert" :size="17" />
        </div>

        <span>{{ error }}</span>

        <button
            type="button"
            class="inventory-alert__close"
            @click="error = ''"
        >
          <AppIcon icon="close" :size="15" />
        </button>
      </div>
    </transition>

    <transition name="notice">
      <div
          v-if="notice"
          class="inventory-alert inventory-alert--success"
      >
        <div class="inventory-alert__icon">
          <AppIcon icon="check" :size="17" />
        </div>

        <span>{{ notice }}</span>

        <button
            type="button"
            class="inventory-alert__close"
            @click="notice = ''"
        >
          <AppIcon icon="close" :size="15" />
        </button>
      </div>
    </transition>

    <!-- =====================================================
         LOADING
    ====================================================== -->

    <div
        v-if="loading"
        class="inventory-loading"
    >
      <div class="inventory-loading__spinner">
        <span></span>
      </div>

      <span>Синхронизация инвентаря…</span>
    </div>

    <!-- =====================================================
         EMPTY
    ====================================================== -->

    <section
        v-else-if="!totalOwned"
        class="inventory-empty"
    >
      <div class="inventory-empty__visual">
        <div class="inventory-empty__icon">
          <AppIcon icon="box" :size="34" />
        </div>

        <span class="inventory-empty__corner inventory-empty__corner--1"></span>
        <span class="inventory-empty__corner inventory-empty__corner--2"></span>
        <span class="inventory-empty__corner inventory-empty__corner--3"></span>
        <span class="inventory-empty__corner inventory-empty__corner--4"></span>
      </div>

      <span class="inventory-empty__eyebrow">
        Инвентарь пуст
      </span>

      <h2 class="inventory-empty__title">
        Здесь пока ничего нет
      </h2>

      <p class="inventory-empty__text">
        Загляни в магазин и собери свою коллекцию
        косметики и бонусов.
      </p>

      <RouterLink
          class="inventory-empty__button"
          :to="{ name: 'shop' }"
      >
        <AppIcon icon="gem" :size="17" />
        Открыть магазин
      </RouterLink>
    </section>

    <!-- =====================================================
         INVENTORY
    ====================================================== -->

    <template v-else>
      <!-- ===== Сейчас надето: слоты образа ===== -->
      <section class="loadout">
        <h2 class="loadout__title">Сейчас надето</h2>

        <div class="loadout__slots">
          <button
              v-for="slot in loadout"
              :key="slot.type"
              type="button"
              class="loadout__slot"
              :class="{ 'is-empty': !slot.item, 'is-active': activeTab === slot.type }"
              :style="slot.item ? rarityStyle(slot.item) : null"
              @click="openTab(slot.type)"
          >
            <span class="loadout__icon"><AppIcon :icon="slot.icon" :size="16" /></span>
            <span class="loadout__text">
              <small>{{ slot.label }}</small>
              <b>{{ slot.item?.name ?? 'Не надето' }}</b>
            </span>
            <span class="loadout__count">{{ slot.owned }}</span>
          </button>

          <button
              v-if="(counts.badge ?? 0) > 0"
              type="button"
              class="loadout__slot"
              :class="{ 'is-empty': !equippedBadges.length, 'is-active': activeTab === 'badge' }"
              @click="openTab('badge')"
          >
            <span class="loadout__icon"><AppIcon icon="medal" :size="16" /></span>
            <span class="loadout__text">
              <small>Бейджи {{ equippedBadges.length }}/{{ maxBadges }}</small>
              <b>{{ equippedBadges.map((b) => b.name).join(', ') || 'Не надето' }}</b>
            </span>
          </button>
        </div>
      </section>

      <!-- ===== Наборы: надеть целиком ===== -->
      <section v-if="bundles.length" class="inv-bundles">
        <h2 class="loadout__title">Наборы</h2>

        <div class="inv-bundles__list">
          <article
              v-for="bundle in bundles"
              :key="bundle.id"
              class="inv-bundle"
              :class="{ 'inv-bundle--on': bundle.equipped_all }"
          >
            <ShopItemPreview :item="bundle" class="inv-bundle__preview" />

            <div class="inv-bundle__info">
              <strong>{{ bundle.name.replace(/^Набор\s+/, '').replace(/[«»]/g, '') }}</strong>
              <span>{{ bundle.owned_count }} из {{ bundle.total_count }} предметов</span>
            </div>

            <span v-if="bundle.equipped_all" class="inv-bundle__state">
              <AppIcon icon="check" :size="14" />
              Надет
            </span>
            <button
                v-else
                type="button"
                class="inv-bundle__btn"
                :disabled="bundleBusy === bundle.id"
                @click="equipBundle(bundle)"
            >
              {{ bundleBusy === bundle.id ? 'Надеваем…' : 'Надеть набор' }}
            </button>
          </article>
        </div>
      </section>

      <!-- ===== Поиск, вкладки, сортировка ===== -->
      <section class="inventory-toolbar">
        <div class="inventory-toolbar__row">
          <label class="inventory-search">
            <AppIcon icon="target" :size="15" />
            <input
                v-model="search"
                type="search"
                placeholder="Найти предмет по названию"
                aria-label="Поиск по инвентарю"
            >
          </label>

          <select v-model="sortBy" class="inventory-sort" aria-label="Сортировка">
            <option value="rarity">Сначала редкие</option>
            <option value="new">Сначала новые</option>
            <option value="name">По названию</option>
          </select>
        </div>

        <div class="inventory-tabs" role="tablist">
          <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              role="tab"
              class="inventory-tab"
              :class="{ 'is-active': activeTab === tab.key }"
              :aria-selected="activeTab === tab.key"
              @click="openTab(tab.key)"
          >
            {{ tab.label }}
            <span>{{ tab.count }}</span>
          </button>
        </div>
      </section>

      <p v-if="!visibleItems.length && !list.loading.value" class="inventory-nothing">
        Ничего не нашлось<template v-if="search.trim()"> по «{{ search.trim() }}»</template>.
      </p>

      <div class="inventory-grid">
          <article
              v-for="item in visibleItems"
              :key="item.inventory_id"
              class="inventory-card"
              :class="{
              'inventory-card--equipped': item.equipped,
              'inventory-card--priority': item.type === 'tier_priority',
            }"
              :style="rarityStyle(item)"
          >
            <!-- Rarity glow -->
            <div class="inventory-card__glow"></div>

            <!-- Top -->
            <div class="inventory-card__top">
              <span class="inventory-card__type">{{ TYPE_LABELS[item.type] || item.type }}</span>

              <div class="inventory-card__badges">
                <span
                    v-if="item.equipped"
                    class="inventory-card__status inventory-card__status--equipped"
                >
                  <span class="inventory-card__status-dot"></span>
                  Надето
                </span>

                <span
                    v-else-if="item.type === 'tier_priority'"
                    class="inventory-card__status inventory-card__status--priority"
                >
                  <AppIcon icon="bolt" :size="11" />
                  {{ priorityCharges }}
                </span>
              </div>
            </div>

            <!-- Живое превью: видно, что это за предмет, без чтения названий -->
            <ShopItemPreview :item="item" class="inventory-card__preview" />

            <!-- Info -->
            <div class="inventory-card__content">
              <span class="inventory-card__rarity">
                {{ RARITY_LABELS[item.rarity] || item.rarity }}
              </span>

              <h3 class="inventory-card__name">
                {{ item.name }}
              </h3>

              <!-- Уникальный питомец: номер и черты этого экземпляра -->
              <p v-if="item.pet" class="inventory-card__pet">
                <b>#{{ item.pet.serial }}</b>
                {{ item.pet.model.name }} · {{ item.pet.backdrop.name }} · {{ item.pet.accessory.name }}
              </p>

              <p class="inventory-card__description">
                {{ itemSubtitle(item) }}

                <template v-if="item.quantity > 1">
                  <span>·</span>
                  ×{{ item.quantity }}
                </template>
              </p>
            </div>

            <!-- Bottom -->
            <div class="inventory-card__footer">
              <button
                  v-if="item.type === 'tier_priority'"
                  class="inventory-action inventory-action--priority"
                  type="button"
                  :disabled="!priorityCharges"
                  @click="openApply(item)"
              >
                <AppIcon icon="bolt" :size="15" />
                <span>Применить приоритет</span>
              </button>

              <button
                  v-else-if="item.is_equippable"
                  class="inventory-action"
                  :class="{
                  'inventory-action--equipped': item.equipped,
                  'inventory-action--accent': !item.equipped,
                }"
                  type="button"
                  :disabled="busyId === item.id"
                  @click="toggleEquip(item)"
              >
                <AppIcon
                    :icon="item.equipped ? 'close' : 'shield'"
                    :size="15"
                />

                <span>
                  {{
                    busyId === item.id
                        ? 'Обновление…'
                        : item.equipped
                            ? 'Снять предмет'
                            : 'Надеть предмет'
                  }}
                </span>
              </button>

              <div
                  v-else
                  class="inventory-card__locked"
              >
                <AppIcon icon="shield" :size="14" />
                <span>Коллекционный предмет</span>
              </div>
            </div>

            <!-- Продать магазину или подарить другу -->
            <div v-if="item.tradeable && Number.isInteger(item.inventory_id)" class="inventory-card__trade">
              <button type="button" :disabled="busyId === item.id" @click="sell(item)">
                <AppIcon icon="coin" :size="13" /> Продать · {{ item.sell_value }}
              </button>
              <button type="button" :disabled="busyId === item.id" @click="openGift(item)">
                <AppIcon icon="gift" :size="13" /> Подарить
              </button>
            </div>

            <!-- Equipped corner -->
            <div
                v-if="item.equipped"
                class="inventory-card__equipped-mark"
            >
              <AppIcon icon="check" :size="12" />
            </div>
          </article>
      </div>

      <!-- Выбор друга для подарка -->
      <Teleport to="body">
        <div v-if="giftItem" class="gift-modal" @click.self="giftItem = null">
          <div class="gift-modal__card">
            <header>
              <div>
                <small>Подарить</small>
                <strong>{{ giftItem.name }}</strong>
              </div>
              <button type="button" aria-label="Закрыть" @click="giftItem = null"><AppIcon icon="close" :size="16" /></button>
            </header>
            <input v-model="giftQuery" class="gift-modal__search" placeholder="Найти друга по нику" />
            <div class="gift-modal__list">
              <button
                  v-for="friend in giftList"
                  :key="friend.id"
                  type="button"
                  class="gift-modal__friend"
                  :disabled="giftBusy"
                  @click="sendGift(friend)"
              >
                <span class="gift-modal__avatar">
                  <img v-if="friend.avatar_url" :src="friend.avatar_url" :alt="friend.username" />
                  <template v-else>{{ (friend.username || '?').charAt(0).toUpperCase() }}</template>
                </span>
                <span>{{ friend.username }}</span>
                <AppIcon icon="gift" :size="15" />
              </button>
              <p v-if="!giftList.length" class="gift-modal__empty">
                {{ giftFriends.length ? 'Никого с таким ником.' : 'Дарить можно только друзьям — сначала добавь кого-нибудь в друзья.' }}
              </p>
            </div>
            <p class="gift-modal__note">У друга подарок продаётся магазину дешевле — как выпавший из сундука.</p>
          </div>
        </div>
      </Teleport>

      <!-- Маячок догрузки следующей страницы -->
      <div
          v-if="!list.done.value"
          :ref="(el) => (list.sentinel.value = el)"
          class="inventory-more"
      >
        <template v-if="list.loadingMore.value">Загружаем ещё…</template>
        <button v-else type="button" class="inventory-more__btn" @click="list.loadMore()">Показать ещё</button>
      </div>
    </template>
    <!-- =====================================================
         PRIORITY MODAL
    ====================================================== -->

    <Teleport to="body">
      <transition name="modal">
        <div
            v-if="applyItem"
            class="priority-modal"
            @click.self="closeApply"
        >
          <div class="priority-modal__box">
            <div class="priority-modal__scanline"></div>

            <!-- Header -->
            <header class="priority-modal__header">
              <div class="priority-modal__icon">
                <AppIcon icon="bolt" :size="24" />
              </div>

              <div>

                <h3 class="priority-modal__title">
                  Применить приоритет
                </h3>
              </div>

              <button
                  type="button"
                  class="priority-modal__close"
                  :disabled="applyBusy"
                  @click="closeApply"
              >
                <AppIcon icon="close" :size="17" />
              </button>
            </header>

            <!-- Description -->
            <div class="priority-modal__intro">
              <p>
                Выбери активную заявку, которую нужно
                поднять в начало очереди тестеров.
              </p>

              <div class="priority-modal__charges">
                <AppIcon icon="bolt" :size="15" />

                <span>Зарядов</span>

                <strong>{{ priorityCharges }}</strong>
              </div>
            </div>

            <!-- No candidates -->
            <div
                v-if="!priorityCandidates.length"
                class="priority-modal__empty"
            >
              <AppIcon icon="alert" :size="22" />

              <div>
                <strong>Нет активных заявок</strong>

                <p>
                  Сначала оставь заявку на тир-тест.
                </p>
              </div>
            </div>

            <!-- Candidates -->
            <div
                v-else
                class="priority-tests"
            >
              <div class="priority-tests__label">
                ВЫБЕРИ ЗАЯВКУ
              </div>

              <label
                  v-for="test in priorityCandidates"
                  :key="test.id"
                  class="priority-test"
                  :class="{
                  'priority-test--disabled': test.is_priority,
                  'priority-test--selected':
                    applyTargetId === test.id && !test.is_priority,
                }"
              >
                <input
                    v-model="applyTargetId"
                    type="radio"
                    :value="test.id"
                    :disabled="test.is_priority"
                >

                <span class="priority-test__radio">
                  <span></span>
                </span>

                <span class="priority-test__mode">
                  <AppIcon
                      :icon="test.mode === 'pvp' ? 'sword' : 'target'"
                      :size="16"
                  />

                  {{ modeLabel(test.mode) }}
                </span>

                <span class="priority-test__id">
                  #{{ test.id }}
                </span>

                <span
                    v-if="test.is_priority"
                    class="priority-test__active"
                >
                  Уже активно
                </span>
              </label>
            </div>

            <!-- Actions -->
            <footer class="priority-modal__actions">
              <button
                  class="priority-modal__cancel"
                  type="button"
                  :disabled="applyBusy"
                  @click="closeApply"
              >
                Отмена
              </button>

              <button
                  class="priority-modal__apply"
                  type="button"
                  :disabled="
                  applyBusy ||
                  !priorityCandidates.length ||
                  !applyTargetId
                "
                  @click="confirmApply"
              >
                <AppIcon
                    :icon="applyBusy ? 'sparkles' : 'bolt'"
                    :size="16"
                />

                {{
                  applyBusy
                      ? 'Применяем…'
                      : 'Активировать приоритет'
                }}
              </button>
            </footer>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
@import "@/views/shop/InventoryView.css";
</style>
