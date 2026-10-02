<script setup>
import { computed, onMounted, ref } from 'vue'
import { shopApi } from '@/services/shop/shop.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
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

const items = ref([])
const maxBadges = ref(3)
const priorityCharges = ref(0)
const priorityCandidates = ref([])

const applyItem = ref(null)
const applyTargetId = ref(null)
const applyBusy = ref(false)

const groups = computed(() => {
  const byType = {}

  for (const item of items.value) {
    if (!byType[item.type]) {
      byType[item.type] = []
    }

    byType[item.type].push(item)
  }

  return Object.entries(byType).map(([type, list]) => ({
    type,
    label: TYPE_LABELS[type] || type,
    items: list,
  }))
})

const equippedCount = computed(() =>
    items.value.filter((item) => item.equipped).length,
)

const equippedItems = computed(() =>
    items.value.filter((item) => item.equipped),
)

const hasPriorityItems = computed(() =>
    items.value.some((item) => item.type === 'tier_priority'),
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await shopApi.inventory()

    items.value = data.items ?? []
    maxBadges.value = data.max_equipped_badges ?? 3
    priorityCharges.value = data.priority_charges ?? 0
    priorityCandidates.value = data.priority_candidates ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить инвентарь.'
  } finally {
    loading.value = false
  }
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

async function toggleEquip(item) {
  busyId.value = item.id
  error.value = ''
  notice.value = ''

  try {
    const result = item.equipped
        ? await shopApi.unequip(item.id)
        : await shopApi.equip(item.id)

    items.value = result.inventory ?? []
    notice.value = result.message

    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось изменить экипировку.'
  } finally {
    busyId.value = null
  }
}

function rarityStyle(item) {
  return {
    '--rarity': RARITY_COLORS[item.rarity] || RARITY_COLORS.common,
  }
}

function iconForItem(item) {
  if (item.type === 'tier_priority') {
    return 'bolt'
  }

  return item.icon
}

function groupIcon(type) {
  if (type === 'tier_priority') return 'bolt'
  if (type.includes('badge')) return 'medal'
  if (type.includes('frame')) return 'frame'
  if (type.includes('color')) return 'gem'
  if (type.includes('effect')) return 'sparkles'

  return 'box'
}

onMounted(load)
</script>

<template>
  <div class="inventory-page">
    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="inventory-header">
      <div class="inventory-header__main">
        <div class="inventory-eyebrow">
          <span class="inventory-eyebrow__line"></span>
          APEX LOADOUT
        </div>

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
            {{ equippedCount }}/{{ maxBadges }}
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
            {{ items.length }}
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
        v-else-if="!items.length"
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
        INVENTORY EMPTY
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
      <section
          v-for="group in groups"
          :key="group.type"
          class="inventory-group"
      >
        <div class="inventory-group__header">
          <div class="inventory-group__heading">
            <div class="inventory-group__icon">
              <AppIcon
                  :icon="groupIcon(group.type)"
                  :size="17"
              />
            </div>

            <div>
              <span class="inventory-group__eyebrow">
                INVENTORY CLASS
              </span>

              <h2 class="inventory-group__title">
                {{ group.label }}
              </h2>
            </div>
          </div>

          <div class="inventory-group__count">
            {{ String(group.items.length).padStart(2, '0') }}
            <span>ITEMS</span>
          </div>
        </div>

        <div class="inventory-grid">
          <article
              v-for="item in group.items"
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
              <div class="inventory-card__icon">
                <div class="inventory-card__icon-bg"></div>

                <AppIcon
                    :icon="iconForItem(item)"
                    :size="26"
                />
              </div>

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

            <!-- Info -->
            <div class="inventory-card__content">
              <span class="inventory-card__rarity">
                {{ RARITY_LABELS[item.rarity] || item.rarity }}
              </span>

              <h3 class="inventory-card__name">
                {{ item.name }}
              </h3>

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

            <!-- Equipped corner -->
            <div
                v-if="item.equipped"
                class="inventory-card__equipped-mark"
            >
              <AppIcon icon="check" :size="12" />
            </div>
          </article>
        </div>
      </section>
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
                <span class="priority-modal__eyebrow">
                  PRIORITY BOOST
                </span>

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
