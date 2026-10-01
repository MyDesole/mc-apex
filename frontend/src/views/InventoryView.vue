<script setup>
import { computed, onMounted, ref } from 'vue'
import { shopApi } from '@/services/shop.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'
import {
  TYPE_LABELS,
  RARITY_LABELS,
  RARITY_COLORS,
  itemSubtitle,
} from '@/data/economy.js'

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
/* =========================================================
   BASE
========================================================= */

.inventory-page {
  --purple: #7c3aed;
  --purple-light: #8b5cf6;
  --gold: #facc15;
  --cyan: #22d3ee;
  --green: #22c55e;
  --danger: #ef4444;

  max-width: 1180px;
  margin: 0 auto;
  padding: 34px 20px 72px;

  color: var(--text);
}

/* =========================================================
   HEADER
========================================================= */

.inventory-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;

  margin-bottom: 30px;
}

.inventory-eyebrow {
  display: flex;
  align-items: center;
  gap: 9px;

  margin-bottom: 10px;

  color: var(--accent);

  font-size: 0.66rem;
  font-weight: 850;
  letter-spacing: 0.17em;
  text-transform: uppercase;
}

.inventory-eyebrow__line {
  width: 23px;
  height: 2px;

  background: var(--accent);

  box-shadow:
      0 0 8px rgba(124, 58, 237, 0.8);
}

.inventory-header__row {
  display: flex;
  align-items: center;
  gap: 20px;
}

.inventory-title {
  margin: 0;

  font-size: clamp(2rem, 4vw, 2.7rem);
  font-weight: 850;
  letter-spacing: -0.05em;
  line-height: 1;
}

.inventory-subtitle {
  margin: 9px 0 0;

  color: var(--text-dim);

  font-size: 0.84rem;
}

.inventory-online {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 7px 10px;

  color: #86efac;

  background: rgba(34, 197, 94, 0.055);
  border: 1px solid rgba(34, 197, 94, 0.18);
  border-radius: 8px;

  font-size: 0.61rem;
  font-weight: 850;
  letter-spacing: 0.1em;
}

.inventory-online__dot {
  width: 6px;
  height: 6px;

  background: var(--green);
  border-radius: 50%;

  box-shadow:
      0 0 8px var(--green);
}

.inventory-shop-btn {
  display: inline-flex;
  min-height: 39px;

  align-items: center;
  justify-content: center;
  gap: 8px;

  padding: 9px 15px;

  color: var(--text);

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.015)
      );

  border: 1px solid var(--border);
  border-radius: 9px;

  font-size: 0.78rem;
  font-weight: 750;
  text-decoration: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.inventory-shop-btn:hover {
  border-color: var(--border-hover);

  background:
      rgba(255, 255, 255, 0.055);

  transform: translateY(-1px);
}

.inventory-shop-btn svg {
  color: var(--accent);

  filter:
      drop-shadow(0 0 5px currentColor);
}

/* =========================================================
   STATS
========================================================= */

.inventory-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;

  margin-bottom: 26px;
}

.inventory-stat {
  position: relative;

  display: flex;
  align-items: center;
  gap: 12px;

  min-height: 76px;

  padding: 13px 14px;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.025),
          transparent 55%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 11px;
}

.inventory-stat::after {
  position: absolute;
  right: -25px;
  bottom: -35px;

  width: 90px;
  height: 90px;

  content: '';

  border: 1px solid currentColor;
  border-radius: 14px;

  opacity: 0.035;
  transform: rotate(45deg);
}

.inventory-stat__icon {
  display: grid;
  width: 39px;
  height: 39px;
  flex: 0 0 39px;
  place-items: center;

  border-radius: 9px;
}

.inventory-stat__icon--purple {
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.18);
}

.inventory-stat__icon--gold {
  color: var(--gold);
  background: rgba(250, 204, 21, 0.08);
  border: 1px solid rgba(250, 204, 21, 0.17);
}

.inventory-stat__icon--cyan {
  color: var(--cyan);
  background: rgba(34, 211, 238, 0.08);
  border: 1px solid rgba(34, 211, 238, 0.17);
}

.inventory-stat__icon--green {
  color: #4ade80;
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.17);
}

.inventory-stat__icon svg {
  filter: drop-shadow(0 0 5px currentColor);
}

.inventory-stat__content {
  min-width: 0;
}

.inventory-stat__label {
  display: block;

  color: var(--text-muted);

  font-size: 0.57rem;
  font-weight: 800;
  letter-spacing: 0.1em;
}

.inventory-stat__value {
  margin-right: 5px;

  color: var(--text);

  font-size: 1.1rem;
  font-weight: 850;
}

.inventory-stat__hint {
  color: var(--text-muted);

  font-size: 0.66rem;
}

/* =========================================================
   ALERT
========================================================= */

.inventory-alert {
  display: flex;
  align-items: center;
  gap: 10px;

  margin-bottom: 16px;
  padding: 11px 13px;

  border-radius: 9px;

  font-size: 0.8rem;
}

.inventory-alert__icon {
  display: grid;
  width: 29px;
  height: 29px;
  flex: 0 0 29px;
  place-items: center;

  border-radius: 7px;
}

.inventory-alert__close {
  display: grid;
  width: 28px;
  height: 28px;
  margin-left: auto;

  place-items: center;

  color: inherit;
  background: transparent;
  border: 0;

  opacity: 0.65;

  cursor: pointer;
}

.inventory-alert__close:hover {
  opacity: 1;
}

.inventory-alert--error {
  color: #fca5a5;

  background: rgba(239, 68, 68, 0.07);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.inventory-alert--error .inventory-alert__icon {
  background: rgba(239, 68, 68, 0.1);
}

.inventory-alert--success {
  color: #86efac;

  background: rgba(34, 197, 94, 0.07);
  border: 1px solid rgba(34, 197, 94, 0.2);
}

.inventory-alert--success .inventory-alert__icon {
  background: rgba(34, 197, 94, 0.1);
}

/* =========================================================
   LOADING
========================================================= */

.inventory-loading {
  display: flex;
  min-height: 260px;

  align-items: center;
  justify-content: center;
  gap: 12px;

  color: var(--text-muted);

  font-size: 0.78rem;
}

.inventory-loading__spinner {
  position: relative;

  width: 22px;
  height: 22px;

  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;

  animation: inventory-spin 0.8s linear infinite;
}

@keyframes inventory-spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   EMPTY
========================================================= */

.inventory-empty {
  position: relative;

  display: flex;
  min-height: 390px;

  align-items: center;
  justify-content: center;
  flex-direction: column;

  overflow: hidden;

  padding: 40px;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(124, 58, 237, 0.09),
          transparent 32%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 15px;

  text-align: center;
}

.inventory-empty__visual {
  position: relative;

  display: grid;
  width: 90px;
  height: 90px;

  place-items: center;

  margin-bottom: 24px;
}

.inventory-empty__icon {
  display: grid;
  width: 64px;
  height: 64px;

  place-items: center;

  color: var(--accent);

  background:
      linear-gradient(
          145deg,
          rgba(124, 58, 237, 0.18),
          rgba(124, 58, 237, 0.03)
      );

  border: 1px solid rgba(124, 58, 237, 0.3);
  border-radius: 14px;

  box-shadow:
      0 0 30px rgba(124, 58, 237, 0.08);
}

.inventory-empty__icon svg {
  filter:
      drop-shadow(0 0 7px currentColor);
}

.inventory-empty__corner {
  position: absolute;

  width: 10px;
  height: 10px;

  border-color: var(--accent);
  opacity: 0.45;
}

.inventory-empty__corner--1 {
  top: 0;
  left: 0;

  border-top: 1px solid;
  border-left: 1px solid;
}

.inventory-empty__corner--2 {
  top: 0;
  right: 0;

  border-top: 1px solid;
  border-right: 1px solid;
}

.inventory-empty__corner--3 {
  right: 0;
  bottom: 0;

  border-right: 1px solid;
  border-bottom: 1px solid;
}

.inventory-empty__corner--4 {
  bottom: 0;
  left: 0;

  border-bottom: 1px solid;
  border-left: 1px solid;
}

.inventory-empty__eyebrow {
  color: var(--accent);

  font-size: 0.62rem;
  font-weight: 850;
  letter-spacing: 0.16em;
}

.inventory-empty__title {
  margin: 8px 0 6px;

  color: var(--text);

  font-size: 1.25rem;
  font-weight: 800;
}

.inventory-empty__text {
  max-width: 390px;

  margin: 0;

  color: var(--text-dim);

  font-size: 0.82rem;
  line-height: 1.55;
}

.inventory-empty__button {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  margin-top: 20px;
  padding: 10px 16px;

  color: #fff;

  background: linear-gradient(
      135deg,
      #7c3aed,
      #6d28d9
  );

  border: 1px solid rgba(139, 92, 246, 0.6);
  border-radius: 9px;

  font-size: 0.78rem;
  font-weight: 750;
  text-decoration: none;

  box-shadow:
      0 7px 25px rgba(124, 58, 237, 0.18);
}

/* =========================================================
   GROUP
========================================================= */

.inventory-group {
  margin-bottom: 38px;
}

.inventory-group__header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  margin-bottom: 13px;
}

.inventory-group__heading {
  display: flex;
  align-items: center;
  gap: 10px;
}

.inventory-group__icon {
  display: grid;
  width: 35px;
  height: 35px;

  place-items: center;

  color: var(--accent);

  background: rgba(124, 58, 237, 0.08);
  border: 1px solid rgba(124, 58, 237, 0.16);
  border-radius: 8px;
}

.inventory-group__icon svg {
  filter: drop-shadow(0 0 5px currentColor);
}

.inventory-group__eyebrow {
  display: block;

  margin-bottom: 2px;

  color: var(--text-muted);

  font-size: 0.55rem;
  font-weight: 800;
  letter-spacing: 0.12em;
}

.inventory-group__title {
  margin: 0;

  color: var(--text);

  font-size: 1.05rem;
  font-weight: 800;
}

.inventory-group__count {
  color: var(--text-muted);

  font-size: 0.6rem;
  font-weight: 850;
  letter-spacing: 0.12em;
}

.inventory-group__count span {
  margin-left: 3px;
}

/* =========================================================
   GRID
========================================================= */

.inventory-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 13px;
}

/* =========================================================
   CARD
========================================================= */

.inventory-card {
  --rarity: #71717a;

  position: relative;

  display: flex;
  min-height: 248px;
  flex-direction: column;

  overflow: hidden;

  padding: 16px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.025),
          transparent 45%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-top: 2px solid var(--rarity);
  border-radius: 13px;

  isolation: isolate;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.inventory-card:hover {
  transform: translateY(-3px);

  border-color: var(--border-hover);

  box-shadow:
      0 16px 38px rgba(0, 0, 0, 0.28),
      0 0 30px color-mix(
          in srgb,
          var(--rarity) 7%,
          transparent
      );
}

.inventory-card--equipped {
  border-color: color-mix(
      in srgb,
      var(--accent) 40%,
      var(--border)
  );

  box-shadow:
      inset 0 0 0 1px rgba(124, 58, 237, 0.07),
      0 0 24px rgba(124, 58, 237, 0.05);
}

.inventory-card--priority {
  border-top-color: var(--gold);
}

.inventory-card__glow {
  position: absolute;
  z-index: -1;

  top: -65px;
  left: -40px;

  width: 135px;
  height: 135px;

  background: var(--rarity);
  border-radius: 50%;

  opacity: 0.055;
  filter: blur(38px);

  pointer-events: none;
}

.inventory-card__top {
  display: flex;
  min-height: 47px;

  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.inventory-card__icon {
  position: relative;

  display: grid;
  width: 47px;
  height: 47px;

  place-items: center;

  color: var(--rarity);

  background:
      linear-gradient(
          145deg,
          color-mix(
              in srgb,
              var(--rarity) 15%,
              transparent
          ),
          transparent
      );

  border: 1px solid color-mix(
      in srgb,
      var(--rarity) 27%,
      transparent
  );

  border-radius: 11px;

  transition:
      transform 0.2s ease,
      box-shadow 0.2s ease;
}

.inventory-card__icon-bg {
  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.08),
          transparent 40%
      );

  border-radius: inherit;
  pointer-events: none;
}

.inventory-card__icon svg {
  position: relative;
  z-index: 1;

  filter:
      drop-shadow(0 0 5px currentColor)
      drop-shadow(0 0 10px currentColor);
}

.inventory-card:hover .inventory-card__icon {
  transform: translateY(-2px);

  box-shadow:
      0 0 20px color-mix(
          in srgb,
          var(--rarity) 10%,
          transparent
      );
}

.inventory-card__badges {
  display: flex;
  align-items: center;
  gap: 5px;
}

.inventory-card__status {
  display: inline-flex;

  align-items: center;
  gap: 5px;

  padding: 5px 7px;

  border-radius: 7px;

  font-size: 0.58rem;
  font-weight: 850;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.inventory-card__status--equipped {
  color: #86efac;

  background: rgba(34, 197, 94, 0.065);
  border: 1px solid rgba(34, 197, 94, 0.18);
}

.inventory-card__status--priority {
  color: var(--gold);

  background: rgba(250, 204, 21, 0.065);
  border: 1px solid rgba(250, 204, 21, 0.18);
}

.inventory-card__status-dot {
  width: 5px;
  height: 5px;

  background: var(--green);
  border-radius: 50%;

  box-shadow:
      0 0 7px var(--green);
}

/* =========================================================
   CARD CONTENT
========================================================= */

.inventory-card__content {
  flex: 1;

  padding-top: 18px;
}

.inventory-card__rarity {
  display: block;

  margin-bottom: 5px;

  color: var(--rarity);

  font-size: 0.59rem;
  font-weight: 850;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.inventory-card__name {
  margin: 0;

  color: var(--text);

  font-size: 1rem;
  font-weight: 800;
  line-height: 1.25;
}

.inventory-card__description {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;

  margin: 6px 0 0;

  color: var(--text-dim);

  font-size: 0.76rem;
  line-height: 1.45;
}

.inventory-card__description span {
  color: var(--text-muted);
}

/* =========================================================
   CARD FOOTER
========================================================= */

.inventory-card__footer {
  padding-top: 18px;
}

.inventory-action {
  display: flex;
  width: 100%;
  min-height: 38px;

  align-items: center;
  justify-content: center;
  gap: 7px;

  padding: 8px 12px;

  color: var(--text-dim);

  background: rgba(255, 255, 255, 0.018);
  border: 1px solid var(--border);
  border-radius: 9px;

  font-size: 0.76rem;
  font-weight: 750;

  cursor: pointer;

  transition:
      background 0.18s ease,
      border-color 0.18s ease,
      color 0.18s ease,
      transform 0.18s ease;
}

.inventory-action:not(:disabled):hover {
  color: var(--text);

  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border-hover);

  transform: translateY(-1px);
}

.inventory-action--accent {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          #7c3aed,
          #6d28d9
      );

  border-color: rgba(139, 92, 246, 0.55);

  box-shadow:
      0 5px 18px rgba(124, 58, 237, 0.13);
}

.inventory-action--accent:hover {
  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #7c3aed
      );
}

.inventory-action--equipped {
  color: var(--text-dim);
}

.inventory-action--priority {
  color: var(--gold);

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.1),
          rgba(250, 204, 21, 0.025)
      );

  border-color: rgba(250, 204, 21, 0.23);
}

.inventory-action--priority:not(:disabled):hover {
  color: #fde68a;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.15),
          rgba(250, 204, 21, 0.04)
      );

  border-color: rgba(250, 204, 21, 0.35);

  box-shadow:
      0 0 20px rgba(250, 204, 21, 0.07);
}

.inventory-action:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.inventory-card__locked {
  display: flex;
  min-height: 38px;

  align-items: center;
  justify-content: center;
  gap: 7px;

  color: var(--text-muted);

  background: rgba(255, 255, 255, 0.012);
  border: 1px solid var(--border);
  border-radius: 9px;

  font-size: 0.68rem;
}

.inventory-card__equipped-mark {
  position: absolute;
  right: 0;
  bottom: 0;

  display: grid;
  width: 22px;
  height: 22px;

  place-items: center;

  color: #86efac;

  background: rgba(34, 197, 94, 0.12);

  border-top: 1px solid rgba(34, 197, 94, 0.2);
  border-left: 1px solid rgba(34, 197, 94, 0.2);
  border-radius: 8px 0 0 0;
}

/* =========================================================
   PRIORITY MODAL
========================================================= */

.priority-modal {
  position: fixed;
  inset: 0;
  z-index: 100;

  display: flex;

  align-items: center;
  justify-content: center;

  padding: 20px;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(124, 58, 237, 0.1),
          transparent 34%
      ),
      rgba(2, 2, 6, 0.84);

  backdrop-filter: blur(9px);
}

.priority-modal__box {
  position: relative;

  width: 100%;
  max-width: 500px;
  max-height: 88vh;

  overflow-y: auto;

  padding: 24px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.035),
          transparent 38%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 15px;

  box-shadow:
      0 35px 90px rgba(0, 0, 0, 0.55),
      0 0 50px rgba(124, 58, 237, 0.08);
}

.priority-modal__scanline {
  position: absolute;
  top: 0;
  left: 25px;
  right: 25px;

  height: 2px;

  background:
      linear-gradient(
          90deg,
          transparent,
          #facc15,
          var(--accent),
          transparent
      );

  opacity: 0.8;
}

.priority-modal__header {
  display: flex;
  align-items: center;
  gap: 12px;

  margin-bottom: 17px;
}

.priority-modal__icon {
  display: grid;
  width: 44px;
  height: 44px;

  flex: 0 0 44px;
  place-items: center;

  color: var(--gold);

  background:
      linear-gradient(
          145deg,
          rgba(250, 204, 21, 0.14),
          rgba(250, 204, 21, 0.025)
      );

  border: 1px solid rgba(250, 204, 21, 0.25);
  border-radius: 10px;

  box-shadow:
      0 0 20px rgba(250, 204, 21, 0.06);
}

.priority-modal__icon svg {
  filter:
      drop-shadow(0 0 6px currentColor);
}

.priority-modal__eyebrow {
  display: block;

  margin-bottom: 3px;

  color: var(--gold);

  font-size: 0.58rem;
  font-weight: 850;
  letter-spacing: 0.13em;
}

.priority-modal__title {
  margin: 0;

  color: var(--text);

  font-size: 1.15rem;
  font-weight: 800;
}

.priority-modal__close {
  display: grid;
  width: 32px;
  height: 32px;

  margin-left: auto;

  place-items: center;

  color: var(--text-muted);

  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--border);
  border-radius: 8px;

  cursor: pointer;

  transition:
      color 0.15s ease,
      border-color 0.15s ease;
}

.priority-modal__close:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.priority-modal__intro {
  display: flex;

  align-items: center;
  justify-content: space-between;

  gap: 15px;

  margin-bottom: 17px;
  padding: 12px;

  background: rgba(255, 255, 255, 0.018);
  border: 1px solid var(--border);
  border-radius: 9px;
}

.priority-modal__intro p {
  margin: 0;

  color: var(--text-dim);

  font-size: 0.76rem;
  line-height: 1.5;
}

.priority-modal__charges {
  display: flex;

  flex: 0 0 auto;

  align-items: center;
  gap: 5px;

  color: var(--gold);

  font-size: 0.7rem;
  font-weight: 750;
}

.priority-modal__charges strong {
  font-size: 0.9rem;
}

.priority-tests__label {
  margin-bottom: 8px;

  color: var(--text-muted);

  font-size: 0.58rem;
  font-weight: 850;
  letter-spacing: 0.12em;
}

.priority-tests {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.priority-test {
  position: relative;

  display: grid;

  grid-template-columns: 18px 1fr auto;

  gap: 9px;
  align-items: center;

  padding: 11px 12px;

  background: rgba(255, 255, 255, 0.015);
  border: 1px solid var(--border);
  border-radius: 9px;

  cursor: pointer;

  transition:
      background 0.15s ease,
      border-color 0.15s ease;
}

.priority-test input {
  position: absolute;

  width: 1px;
  height: 1px;

  opacity: 0;
}

.priority-test:hover:not(.priority-test--disabled) {
  background: rgba(124, 58, 237, 0.055);
  border-color: var(--border-hover);
}

.priority-test--selected {
  background: rgba(124, 58, 237, 0.1);
  border-color: rgba(124, 58, 237, 0.45);
}

.priority-test__radio {
  display: grid;
  width: 16px;
  height: 16px;

  place-items: center;

  border: 1px solid var(--border-hover);
  border-radius: 50%;
}

.priority-test__radio span {
  width: 7px;
  height: 7px;

  background: var(--accent);
  border-radius: 50%;

  opacity: 0;
  transform: scale(0.5);

  transition:
      opacity 0.15s ease,
      transform 0.15s ease;
}

.priority-test--selected .priority-test__radio {
  border-color: var(--accent);
}

.priority-test--selected .priority-test__radio span {
  opacity: 1;
  transform: scale(1);
}

.priority-test__mode {
  display: flex;

  align-items: center;
  gap: 7px;

  color: var(--text);

  font-size: 0.78rem;
  font-weight: 750;
}

.priority-test__mode svg {
  color: var(--accent);
}

.priority-test__id {
  color: var(--text-muted);

  font-size: 0.7rem;
}

.priority-test__active {
  grid-column: 2 / -1;

  color: var(--gold);

  font-size: 0.62rem;
  font-weight: 700;
}

.priority-test--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.priority-modal__empty {
  display: flex;

  gap: 11px;
  align-items: flex-start;

  padding: 14px;

  color: #fca5a5;

  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 9px;
}

.priority-modal__empty svg {
  flex: 0 0 auto;
}

.priority-modal__empty strong {
  display: block;

  margin-bottom: 3px;

  font-size: 0.78rem;
}

.priority-modal__empty p {
  margin: 0;

  color: rgba(252, 165, 165, 0.7);

  font-size: 0.7rem;
}

.priority-modal__actions {
  display: flex;

  gap: 8px;

  justify-content: flex-end;

  margin-top: 19px;
}

.priority-modal__cancel,
.priority-modal__apply {
  display: inline-flex;
  min-height: 39px;

  align-items: center;
  justify-content: center;
  gap: 7px;

  padding: 8px 14px;

  border-radius: 9px;

  font-size: 0.75rem;
  font-weight: 750;

  cursor: pointer;

  transition:
      transform 0.15s ease,
      background 0.15s ease,
      border-color 0.15s ease;
}

.priority-modal__cancel {
  color: var(--text-dim);

  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border);
}

.priority-modal__cancel:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.priority-modal__apply {
  color: #17120a;

  background:
      linear-gradient(
          135deg,
          #fde047,
          #eab308
      );

  border: 1px solid rgba(250, 204, 21, 0.7);

  box-shadow:
      0 5px 20px rgba(250, 204, 21, 0.12);
}

.priority-modal__apply:hover:not(:disabled) {
  transform: translateY(-1px);

  box-shadow:
      0 7px 25px rgba(250, 204, 21, 0.18);
}

.priority-modal__apply:disabled,
.priority-modal__cancel:disabled,
.priority-modal__close:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* =========================================================
   TRANSITIONS
========================================================= */

.notice-enter-active,
.notice-leave-active {
  transition:
      opacity 0.2s ease,
      transform 0.2s ease;
}

.notice-enter-from,
.notice-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active .priority-modal__box,
.modal-leave-active .priority-modal__box {
  transition:
      opacity 0.2s ease,
      transform 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .priority-modal__box,
.modal-leave-to .priority-modal__box {
  opacity: 0;
  transform: translateY(10px) scale(0.98);
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {
  .inventory-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 700px) {
  .inventory-page {
    padding: 24px 14px 50px;
  }

  .inventory-header {
    align-items: stretch;
    flex-direction: column;

    margin-bottom: 25px;
  }

  .inventory-header__row {
    align-items: flex-start;
    flex-direction: column;
    gap: 12px;
  }

  .inventory-online {
    display: none;
  }

  .inventory-shop-btn {
    width: 100%;
  }

  .inventory-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }

  .inventory-stat {
    min-height: 68px;
    padding: 10px;
  }

  .inventory-stat__icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }

  .inventory-stat__label {
    font-size: 0.5rem;
  }

  .inventory-stat__value {
    font-size: 1rem;
  }

  .inventory-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
  }

  .inventory-card {
    min-height: 225px;
    padding: 13px;
  }

  .inventory-card__icon {
    width: 41px;
    height: 41px;
  }

  .inventory-card__status {
    padding: 4px 6px;

    font-size: 0.52rem;
  }

  .inventory-card__name {
    font-size: 0.9rem;
  }

  .inventory-card__description {
    font-size: 0.7rem;
  }

  .inventory-group {
    margin-bottom: 30px;
  }

  .priority-modal__box {
    padding: 19px;
  }

  .priority-modal__intro {
    align-items: flex-start;
    flex-direction: column;
  }

  .priority-modal__actions {
    flex-direction: column-reverse;
  }

  .priority-modal__cancel,
  .priority-modal__apply {
    width: 100%;
  }
}

@media (max-width: 450px) {
  .inventory-title {
    font-size: 1.8rem;
  }

  .inventory-subtitle {
    font-size: 0.76rem;
  }

  .inventory-stats {
    grid-template-columns: 1fr 1fr;
  }

  .inventory-stat__hint {
    display: none;
  }

  .inventory-grid {
    grid-template-columns: 1fr;
  }

  .inventory-card {
    min-height: 205px;
  }

  .inventory-empty {
    min-height: 340px;
    padding: 30px 20px;
  }

  .inventory-empty__text {
    font-size: 0.76rem;
  }
}
</style>