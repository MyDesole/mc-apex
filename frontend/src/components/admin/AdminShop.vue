<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { adminShopApi } from '@/services/adminShop.js'
import { RARITY_COLORS, TYPE_LABELS, formatCoins } from '@/data/economy.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
const isAdmin = computed(() => auth.user?.role === 'admin')

const tab = ref('catalog')

const loading = ref(true)
const error = ref('')
const notice = ref('')

// === Каталог ===
const items = ref([])
const types = ref([])
const rarities = ref([])
const editing = ref(null)
const saving = ref(false)

const blankItem = () => ({
  id: null,
  name: '',
  slug: '',
  description: '',
  type: 'avatar_frame',
  rarity: 'common',
  effect_value: '',
  price: 100,
  is_active: true,
  sort_order: 0,
  is_consumable: false,
  is_repeatable: false,
  max_quantity: null,
  metadata: {},
})

// === Награды ===
const rewards = ref(null)
const tierTable = ref({})
const sources = ref({})

// === Начисления ===
const grantUserId = ref('')
const grantAmount = ref(100)
const grantReason = ref('')

// === Леджер ===
const ledger = ref([])
const ledgerPage = ref(1)
const ledgerLastPage = ref(1)
const ledgerSource = ref('')

async function loadCatalog() {
  const data = await adminShopApi.items()
  items.value = data.items ?? []
  types.value = data.types ?? []
  rarities.value = data.rarities ?? []
}

async function loadRewards() {
  const data = await adminShopApi.rewards()
  rewards.value = data
  tierTable.value = { ...(data.tier_table ?? {}) }
  sources.value = { ...(data.sources ?? {}) }
}

async function loadLedger(nextPage = 1) {
  const params = { page: nextPage }

  if (ledgerSource.value) params.source = ledgerSource.value

  const data = await adminShopApi.transactions(params)
  ledger.value = data.data ?? []
  ledgerPage.value = data.current_page ?? 1
  ledgerLastPage.value = data.last_page ?? 1
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    await Promise.all([loadCatalog(), loadRewards()])

    if (isAdmin.value) {
      await loadLedger(1)
    }
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить данные магазина.'
  } finally {
    loading.value = false
  }
}

function startCreate() {
  editing.value = blankItem()
}

function startEdit(item) {
  editing.value = {
    ...item,
    metadata: item.metadata ?? {},
  }
}

async function saveItem() {
  if (!editing.value) return

  saving.value = true
  error.value = ''
  notice.value = ''

  try {
    const payload = { ...editing.value }

    delete payload.id
    delete payload.created_at
    delete payload.updated_at
    delete payload.icon
    delete payload.color

    if (!payload.slug) delete payload.slug
    if (payload.max_quantity === '' || payload.max_quantity === null) {
      payload.max_quantity = null
    }

    if (editing.value.id) {
      await adminShopApi.updateItem(editing.value.id, payload)
      notice.value = 'Предмет обновлён.'
    } else {
      await adminShopApi.createItem(payload)
      notice.value = 'Предмет добавлен в магазин.'
    }

    editing.value = null
    await loadCatalog()
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить предмет.'
  } finally {
    saving.value = false
  }
}

async function toggleItem(item) {
  error.value = ''

  try {
    const result = await adminShopApi.toggleItem(item.id)
    item.is_active = result.item.is_active
  } catch (e) {
    error.value = e.message || 'Не удалось переключить предмет.'
  }
}

async function removeItem(item) {
  if (!await confirmDialog(`Удалить «${item.name}» из магазина? У купивших игроков предмет останется в инвентаре.`)) {
    return
  }

  error.value = ''

  try {
    await adminShopApi.deleteItem(item.id)
    notice.value = 'Предмет удалён.'
    await loadCatalog()
  } catch (e) {
    error.value = e.message || 'Не удалось удалить предмет.'
  }
}

async function saveRewards() {
  saving.value = true
  error.value = ''
  notice.value = ''

  const perTier = {}

  for (const [tier, value] of Object.entries(tierTable.value)) {
    perTier[tier] = Number(value) || 0
  }

  try {
    await adminShopApi.updateRewards({
      'tier_test.per_tier': perTier,
      'tier_test.first_test_bonus': Number(rewards.value.first_test_bonus) || 0,
      'achievement.base': Number(rewards.value.achievement_base) || 0,
      'achievement.per_point': Number(rewards.value.achievement_per_point) || 0,
      'daily_bonus.amount': Number(rewards.value.daily_bonus) || 0,
      'daily_bonus.enabled': Boolean(rewards.value.daily_bonus_enabled),
      'gift.fee_percent': Number(rewards.value.gift_fee_percent) || 0,
      'gift.daily_limit': Number(rewards.value.gift_daily_limit) || 0,
      sources: sources.value,
    })

    notice.value = 'Настройки экономики сохранены.'
    await loadRewards()
  } catch (e) {
    error.value = e.message || 'Не удалось сохранить настройки.'
  } finally {
    saving.value = false
  }
}

async function grantCoins() {
  error.value = ''
  notice.value = ''

  const userId = Number(grantUserId.value)

  if (!userId) {
    error.value = 'Укажите ID игрока.'
    return
  }

  try {
    const result = await adminShopApi.grantCoins(userId, {
      amount: Number(grantAmount.value),
      reason: grantReason.value || null,
    })

    notice.value = `${result.message} Новый баланс: ₳ ${formatCoins(result.balance)}`
    grantReason.value = ''
    await loadLedger(1)
  } catch (e) {
    error.value = e.message || 'Не удалось начислить монеты.'
  }
}

function sourceLabel(source) {
  return {
    tier_test: 'Тир-тест',
    achievement: 'Ачивка',
    daily_bonus: 'Ежедневный бонус',
    gift_in: 'Подарок получен',
    gift_out: 'Подарок отправлен',
    purchase: 'Покупка',
    admin: 'Админ',
    other: 'Прочее',
  }[source] || source
}

onMounted(load)
</script>

<template>
  <div class="ashop">
    <p v-if="error" class="alert alert--error">{{ error }}</p>
    <p v-if="notice" class="alert alert--ok">{{ notice }}</p>

    <nav class="subnav">
      <button :class="{ active: tab === 'catalog' }" @click="tab = 'catalog'">Каталог</button>
      <button :class="{ active: tab === 'rewards' }" @click="tab = 'rewards'">Награды</button>
      <button v-if="isAdmin" :class="{ active: tab === 'grant' }" @click="tab = 'grant'">Начисления</button>
      <button v-if="isAdmin" :class="{ active: tab === 'ledger' }" @click="tab = 'ledger'">Леджер</button>
    </nav>

    <p v-if="loading" class="state">Загружаем…</p>

    <!-- ===================== КАТАЛОГ ===================== -->
    <section v-else-if="tab === 'catalog'">
      <div class="toolbar">
        <h2>Предметы магазина ({{ items.length }})</h2>
        <button class="btn btn--accent" @click="startCreate">+ Добавить предмет</button>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Тип</th>
              <th>Редкость</th>
              <th class="num">Цена</th>
              <th>Флаги</th>
              <th>Статус</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <AppIcon class="cell-icon" :icon="item.icon" :size="18" />
                <span class="cell-name">{{ item.name }}</span>
                <div v-if="item.slug" class="cell-slug">{{ item.slug }}</div>
              </td>
              <td>{{ TYPE_LABELS[item.type] || item.type }}</td>
              <td>
                <span class="dot" :style="{ background: RARITY_COLORS[item.rarity] }" />
                {{ item.rarity }}
              </td>
              <td class="num">₳ {{ formatCoins(item.price) }}</td>
              <td class="flags">
                <span v-if="item.is_consumable" class="tag">расходник</span>
                <span v-if="item.is_repeatable" class="tag">повторно</span>
                <span v-if="item.effect_value" class="tag tag--dim">{{ item.effect_value }}</span>
              </td>
              <td>
                <button
                  class="pill"
                  :class="item.is_active ? 'pill--on' : 'pill--off'"
                  @click="toggleItem(item)"
                >
                  {{ item.is_active ? 'в продаже' : 'скрыт' }}
                </button>
              </td>
              <td class="actions">
                <button class="btn btn--ghost btn--sm" @click="startEdit(item)">Изменить</button>
                <button class="btn btn--danger btn--sm" @click="removeItem(item)">Удалить</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ===================== НАГРАДЫ ===================== -->
    <section v-else-if="tab === 'rewards' && rewards">
      <h2>Сколько ApexCoin давать</h2>

      <div class="panels">
        <article class="panel">
          <h3>За тир-тест</h3>
          <p class="panel__hint">Начисляется за каждый завершённый тест по итоговому тиру.</p>

          <label v-for="(_, tier) in tierTable" :key="tier" class="field">
            <span class="field__label">Тир {{ tier }}</span>
            <input v-model.number="tierTable[tier]" type="number" min="0">
          </label>

          <label class="field">
            <span class="field__label">Бонус за первый тест</span>
            <input v-model.number="rewards.first_test_bonus" type="number" min="0">
          </label>
        </article>

        <article class="panel">
          <h3>За ачивки</h3>
          <p class="panel__hint">Награда = база + очки ачивки × множитель.</p>

          <label class="field">
            <span class="field__label">База</span>
            <input v-model.number="rewards.achievement_base" type="number" min="0">
          </label>

          <label class="field">
            <span class="field__label">За очко ачивки</span>
            <input v-model.number="rewards.achievement_per_point" type="number" min="0">
          </label>
        </article>

        <article class="panel">
          <h3>Бонусы и подарки</h3>

          <label class="field">
            <span class="field__label">Ежедневный бонус</span>
            <input v-model.number="rewards.daily_bonus" type="number" min="0">
          </label>

          <label class="field field--check">
            <input v-model="rewards.daily_bonus_enabled" type="checkbox">
            <span>Ежедневный бонус включён</span>
          </label>

          <label class="field">
            <span class="field__label">Комиссия подарка, %</span>
            <input v-model.number="rewards.gift_fee_percent" type="number" min="0" max="50">
          </label>

          <label class="field">
            <span class="field__label">Лимит подарков в день</span>
            <input v-model.number="rewards.gift_daily_limit" type="number" min="0">
          </label>
        </article>

        <article class="panel">
          <h3>Способы начисления</h3>
          <p class="panel__hint">Выключенный источник перестаёт начислять монеты.</p>

          <label v-for="(enabled, source) in sources" :key="source" class="field field--check">
            <input v-model="sources[source]" type="checkbox">
            <span>{{ sourceLabel(source) }}</span>
          </label>
        </article>
      </div>

      <button class="btn btn--accent" :disabled="saving" @click="saveRewards">
        {{ saving ? 'Сохраняем…' : 'Сохранить настройки' }}
      </button>
    </section>

    <!-- ===================== НАЧИСЛЕНИЯ ===================== -->
    <section v-else-if="tab === 'grant'">
      <h2>Ручное начисление или списание</h2>
      <p class="panel__hint">
        «Свои способы»: начислите монеты за ивент, конкурс, компенсацию или списание при злоупотреблении.
      </p>

      <div class="grant">
        <label class="field">
          <span class="field__label">ID игрока</span>
          <input v-model="grantUserId" type="number" min="1" placeholder="например 20">
        </label>

        <label class="field">
          <span class="field__label">Сумма (можно отрицательную)</span>
          <input v-model.number="grantAmount" type="number">
        </label>

        <label class="field field--wide">
          <span class="field__label">Причина</span>
          <input v-model="grantReason" type="text" placeholder="Награда за ивент «Осень 2026»">
        </label>

        <button class="btn btn--accent" @click="grantCoins">Применить</button>
      </div>
    </section>

    <!-- ===================== ЛЕДЖЕР ===================== -->
    <section v-else-if="tab === 'ledger' && isAdmin">
      <div class="toolbar">
        <h2>Все операции с ApexCoin</h2>

        <select v-model="ledgerSource" class="select" @change="loadLedger(1)">
          <option value="">Все источники</option>
          <option value="tier_test">Тир-тесты</option>
          <option value="achievement">Ачивки</option>
          <option value="daily_bonus">Ежедневный бонус</option>
          <option value="gift_out">Подарки</option>
          <option value="purchase">Покупки</option>
          <option value="admin">Админ</option>
        </select>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Дата</th>
              <th>Игрок</th>
              <th>Источник</th>
              <th>Описание</th>
              <th class="num">Сумма</th>
              <th class="num">Баланс</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in ledger" :key="row.id">
              <td class="dim">{{ new Date(row.created_at).toLocaleString('ru-RU') }}</td>
              <td>{{ row.user?.username || row.user_id }}</td>
              <td>{{ sourceLabel(row.source) }}</td>
              <td class="dim">{{ row.description }}</td>
              <td class="num" :class="row.amount > 0 ? 'plus' : 'minus'">
                {{ row.amount > 0 ? '+' : '' }}{{ formatCoins(row.amount) }}
              </td>
              <td class="num">₳ {{ formatCoins(row.balance_after) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="ledgerLastPage > 1" class="pager">
        <button class="btn btn--ghost btn--sm" :disabled="ledgerPage <= 1" @click="loadLedger(ledgerPage - 1)">
          Назад
        </button>
        <span class="dim">{{ ledgerPage }} / {{ ledgerLastPage }}</span>
        <button
          class="btn btn--ghost btn--sm"
          :disabled="ledgerPage >= ledgerLastPage"
          @click="loadLedger(ledgerPage + 1)"
        >
          Вперёд
        </button>
      </div>
    </section>

    <!-- ===================== ФОРМА ПРЕДМЕТА ===================== -->
    <div v-if="editing" class="modal" @click.self="editing = null">
      <div class="modal__box">
        <h3>{{ editing.id ? 'Изменить предмет' : 'Новый предмет' }}</h3>

        <label class="field">
          <span class="field__label">Название</span>
          <input v-model="editing.name" type="text">
        </label>

        <label class="field">
          <span class="field__label">Slug (необязательно)</span>
          <input v-model="editing.slug" type="text" placeholder="frame-neon">
        </label>

        <label class="field">
          <span class="field__label">Описание</span>
          <textarea v-model="editing.description" rows="2" />
        </label>

        <div class="row">
          <label class="field">
            <span class="field__label">Тип</span>
            <select v-model="editing.type" class="select">
              <option v-for="type in types" :key="type" :value="type">
                {{ TYPE_LABELS[type] || type }}
              </option>
            </select>
          </label>

          <label class="field">
            <span class="field__label">Редкость</span>
            <select v-model="editing.rarity" class="select">
              <option v-for="rarity in rarities" :key="rarity" :value="rarity">{{ rarity }}</option>
            </select>
          </label>
        </div>

        <div class="row">
          <label class="field">
            <span class="field__label">Значение эффекта</span>
            <input
              v-model="editing.effect_value"
              type="text"
              placeholder="id рамки, hex-цвет или /путь/к/картинке"
            >
          </label>

          <label class="field">
            <span class="field__label">Цена, ApexCoin</span>
            <input v-model.number="editing.price" type="number" min="0">
          </label>
        </div>

        <div class="row">
          <label class="field">
            <span class="field__label">Иконка (эмодзи)</span>
            <input v-model="editing.metadata.icon" type="text" placeholder="rocket">
          </label>

          <label class="field">
            <span class="field__label">Цвет</span>
            <input v-model="editing.metadata.color" type="text" placeholder="#f97316">
          </label>
        </div>

        <div class="row">
          <label class="field">
            <span class="field__label">Порядок</span>
            <input v-model.number="editing.sort_order" type="number" min="0">
          </label>

          <label class="field">
            <span class="field__label">Максимум в инвентаре</span>
            <input v-model.number="editing.max_quantity" type="number" min="1" placeholder="без лимита">
          </label>
        </div>

        <label class="field field--check">
          <input v-model="editing.is_active" type="checkbox">
          <span>В продаже</span>
        </label>

        <label class="field field--check">
          <input v-model="editing.is_consumable" type="checkbox">
          <span>Расходник (сгорает при применении)</span>
        </label>

        <label class="field field--check">
          <input v-model="editing.is_repeatable" type="checkbox">
          <span>Можно покупать повторно</span>
        </label>

        <div class="modal__actions">
          <button class="btn btn--ghost" @click="editing = null">Отмена</button>
          <button class="btn btn--accent" :disabled="saving" @click="saveItem">
            {{ saving ? 'Сохраняем…' : 'Сохранить' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ashop {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.subnav {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.subnav button {
  padding: 8px 14px;
  color: var(--text-dim, #9a9aab);
  background: #12121a;
  border: 1px solid #22222e;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.subnav button.active {
  color: #fff;
  background: rgba(124, 58, 237, 0.18);
  border-color: #7c3aed;
}

h2 {
  margin: 0 0 6px;
  font-size: 17px;
}

h3 {
  margin: 0 0 8px;
  font-size: 15px;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid #22222e;
  border-radius: 12px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.table th {
  padding: 10px 12px;
  color: #6b6b7d;
  font-weight: 600;
  text-align: left;
  background: #0f0f16;
}

.table td {
  padding: 10px 12px;
  border-top: 1px solid #1a1a26;
  vertical-align: middle;
}

.num {
  text-align: right;
  white-space: nowrap;
}

.dim {
  color: #6b6b7d;
}

.plus {
  color: #22c55e;
  font-weight: 700;
}

.minus {
  color: #f87171;
  font-weight: 700;
}

.cell-icon {
  margin-right: 6px;
}

.cell-name {
  font-weight: 600;
}

.cell-slug {
  color: #4b4b5a;
  font-size: 11px;
}

.dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  margin-right: 6px;
  border-radius: 50%;
}

.flags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.tag {
  padding: 2px 7px;
  color: #c4b5fd;
  background: rgba(124, 58, 237, 0.16);
  border-radius: 6px;
  font-size: 11px;
}

.tag--dim {
  color: #6b6b7d;
  background: #1a1a26;
}

.pill {
  padding: 4px 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.pill--on {
  color: #86efac;
  background: rgba(34, 197, 94, 0.14);
  border-color: rgba(34, 197, 94, 0.4);
}

.pill--off {
  color: #9a9aab;
  background: #1a1a26;
  border-color: #22222e;
}

.actions {
  display: flex;
  gap: 6px;
  white-space: nowrap;
}

.panels {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 14px;
  margin: 14px 0 18px;
}

.panel {
  padding: 16px;
  background: #12121a;
  border: 1px solid #22222e;
  border-radius: 12px;
}

.panel__hint {
  margin: 0 0 12px;
  color: #6b6b7d;
  font-size: 12px;
  line-height: 1.5;
}

.field {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
  font-size: 13px;
}

.field--check {
  justify-content: flex-start;
}

.field--wide {
  flex: 1 1 260px;
}

.field__label {
  color: #9a9aab;
}

.field input[type='number'],
.field input[type='text'],
.field textarea,
.select {
  width: 130px;
  padding: 8px 10px;
  color: #fff;
  background: #0f0f16;
  border: 1px solid #22222e;
  border-radius: 8px;
  font-size: 13px;
}

.field--wide input,
.field textarea {
  width: 100%;
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.row .field {
  flex: 1 1 160px;
}

.grant {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: flex-end;
  padding: 18px;
  background: #12121a;
  border: 1px solid #22222e;
  border-radius: 12px;
}

.btn {
  padding: 9px 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
}

.btn--sm {
  padding: 6px 10px;
  font-size: 12px;
}

.btn--accent {
  color: #fff;
  background: #7c3aed;
}

.btn--ghost {
  color: #9a9aab;
  background: transparent;
  border-color: #22222e;
}

.btn--danger {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.35);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.alert {
  margin: 0;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
}

.alert--error {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.35);
}

.alert--ok {
  color: #86efac;
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.35);
}

.state {
  padding: 36px 0;
  color: #6b6b7d;
  text-align: center;
}

.pager {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: center;
  margin-top: 14px;
}

.modal {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.7);
}

.modal__box {
  width: 100%;
  max-width: 560px;
  max-height: 88vh;
  overflow-y: auto;
  padding: 22px;
  background: #12121a;
  border: 1px solid #22222e;
  border-radius: 14px;
}

.modal__actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 18px;
}

/* Адаптация под общий стиль админки */
.ashop { color: var(--text); }
.ashop h2, .ashop h3 { color: var(--text); }
.subnav button {
  color: var(--text-dim);
  background: var(--bg-card);
  border: 1px solid var(--border);
}
.subnav button.active {
  color: var(--text);
  background: rgba(124, 58, 237, 0.18);
  border-color: var(--accent);
}
.table, .table th, .table td, .panel, .grant, .modal__box { background: var(--bg-card); border-color: var(--border); }
.table th { color: var(--text-dim); }
.panel__hint, .dim, .cell-slug { color: var(--text-muted); }
.field__label { color: var(--text-dim); }
.field input, .field select, .select, textarea {
  background: var(--bg-card);
  border: 1px solid var(--border);
  color: var(--text);
}
.btn--accent { background: var(--accent); color: #fff; }
.btn--ghost { background: transparent; color: var(--text-dim); border-color: var(--border); }
.btn--danger { background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35); color: #fca5a5; }
</style>
