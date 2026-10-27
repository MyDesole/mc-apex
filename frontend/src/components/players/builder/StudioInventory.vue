<script setup>
/**
 * Инвентарь и примерочная в студии профиля.
 *
 * «Моё» — купленное для профиля по слотам: нажал — надел (тот же API,
 * что у страницы инвентаря, надеть некупленное нельзя).
 * «Примерка» — витрина магазина: предмет надевается на холст понарошку,
 * только в этой вкладке и только в памяти. Понравилось — «Купить»,
 * нет — «Снять». При выходе из студии всё примеренное снимается.
 */
import { computed, onMounted, ref } from 'vue'
import { shopApi } from '@/services/shop/shop.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { RARITY_COLORS } from '@/data/wallet/economy.js'
import { useTryOn } from '@/composables/players/useTryOn.js'

const auth = useAuthStore()

// Слоты, которые видно на профиле; field — куда предмет пишется у игрока
const SLOTS = [
  ['avatar_frame', 'Рамка', 'avatar_frame'],
  ['profile_effect', 'Эффект', 'profile_effect'],
  ['profile_layout', 'Макет', 'profile_layout'],
  ['card_theme', 'Карточка', 'card_theme'],
  ['accent_color', 'Акцент', 'accent_color'],
  ['name_effect', 'Ник', 'name_effect'],
  ['name_icon', 'Значок', 'name_icon'],
  ['profile_title', 'Титул', 'profile_title'],
  ['skin_backdrop', 'Фон скина', 'skin_backdrop'],
  ['profile_entrance', 'Появление', 'profile_entrance'],
  ['cursor_trail', 'След', 'cursor_trail'],
  ['badge', 'Бейджи', null],
  ['profile_pet', 'Питомцы', null],
]

const FIELD = Object.fromEntries(SLOTS.map(([type, , field]) => [type, field]))

const mode = ref('mine') // mine | shop
const counts = ref({})
const equipped = ref([])
const slot = ref(null)
const items = ref([])
const loading = ref(true)
const busy = ref(null)
const message = ref('')

const slots = computed(() => SLOTS
    .map(([type, label]) => ({
      type,
      label,
      count: counts.value[type] ?? 0,
      on: equipped.value.find((i) => i.type === type)?.name ?? null,
    }))
    // В примерочной — все слоты, которые можно надеть на холст
    .filter((s) => (mode.value === 'shop' ? FIELD[s.type] : s.count > 0)))

async function load(type = slot.value) {
  loading.value = true
  message.value = ''
  try {
    if (mode.value === 'shop') {
      const data = await shopApi.catalogPage({ type: type || 'avatar_frame', limit: 60 })
      items.value = data.items ?? []
      return
    }

    const data = await shopApi.inventoryPage({ tab: type || 'all', limit: 60 })
    counts.value = data.counts ?? counts.value
    equipped.value = data.equipped ?? equipped.value
    items.value = data.items ?? []
    if (!type) {
      // Первый вход: открываем первый слот, где что-то есть
      slot.value = slots.value[0]?.type ?? null
      if (slot.value) return load(slot.value)
    }
  } catch (e) {
    message.value = e.message || 'Не удалось загрузить предметы.'
  } finally {
    loading.value = false
  }
}

function setMode(next) {
  mode.value = next
  if (next === 'shop' && !FIELD[slot.value]) slot.value = 'avatar_frame'
  load(slot.value)
}

function openSlot(type) {
  slot.value = type
  load(type)
}

/* ---------- Моё: надеть / снять ---------- */

async function toggle(item) {
  busy.value = item.inventory_id ?? item.id
  message.value = ''
  try {
    const isPet = item.type === 'profile_pet' && Number.isInteger(item.inventory_id)
    const result = isPet
      ? await shopApi.togglePet(item.inventory_id, !item.equipped)
      : item.equipped ? await shopApi.unequip(item.id) : await shopApi.equip(item.id)
    message.value = result?.message || ''
    await Promise.all([load(slot.value), auth.fetchMe()])
  } catch (e) {
    message.value = e.message || 'Не удалось надеть предмет.'
  } finally {
    busy.value = null
  }
}

/* ---------- Примерка ---------- */

const { trying, tryOn: wear, restoreAll, forget } = useTryOn()

function tryOn(item) {
  wear(item, FIELD[item.type], item.effect_value || item.slug)
}

async function buy(item) {
  if (!await confirmDialog(`Купить «${item.name}» за ${item.price} ApexCoin и сразу надеть?`)) return
  busy.value = item.id
  message.value = ''
  try {
    await shopApi.purchase(item.id)
    await shopApi.equip(item.id)
    // Купленное надето по-настоящему — примерка этого поля больше не нужна
    forget(FIELD[item.type])
    restoreAll()
    await auth.fetchMe()
    message.value = `«${item.name}» куплено и надето.`
    await load(slot.value)
  } catch (e) {
    message.value = e.message || 'Не удалось купить.'
  } finally {
    busy.value = null
  }
}

function onShopClick(item) {
  if (item.owned) {
    message.value = 'Этот предмет уже твой — надень его во вкладке «Моё».'
    return
  }
  tryOn(item)
}

onMounted(() => load())
</script>

<template>
  <div class="studio-inv">
    <div class="studio-seg">
      <button type="button" :class="{ 'is-on': mode === 'mine' }" @click="setMode('mine')">Моё</button>
      <button type="button" :class="{ 'is-on': mode === 'shop' }" @click="setMode('shop')">Примерка</button>
    </div>

    <p v-if="mode === 'shop'" class="studio-note">
      Нажми на предмет — он появится на холсте, как будто надет. Платить, чтобы посмотреть, не нужно.
    </p>

    <!-- Что сейчас примеряем -->
    <div v-if="trying" class="studio-try">
      <div>
        <small>Примеряешь</small>
        <strong>{{ trying.name }}</strong>
      </div>
      <button type="button" class="studio-btn studio-btn--primary" :disabled="busy !== null" @click="buy(trying)">
        Купить · {{ trying.price }}
      </button>
      <button type="button" class="studio-btn studio-btn--ghost" @click="restoreAll">Снять</button>
    </div>

    <div v-if="slots.length" class="studio-inv__slots">
      <button
          v-for="s in slots"
          :key="s.type"
          type="button"
          :class="{ 'is-on': slot === s.type }"
          @click="openSlot(s.type)"
      >
        <strong>{{ s.label }}</strong>
        <small v-if="mode === 'mine'">{{ s.on || 'не надето' }}</small>
      </button>
    </div>

    <p v-if="message" class="studio-note">{{ message }}</p>

    <div v-if="loading && !items.length" class="studio-empty">Загружаем…</div>

    <div v-else-if="items.length" class="studio-inv__grid">
      <button
          v-for="item in items"
          :key="item.inventory_id ?? item.id"
          type="button"
          class="studio-inv__item"
          :class="{
            'is-on': mode === 'mine' ? item.equipped : trying?.id === item.id,
            'is-busy': busy === (item.inventory_id ?? item.id),
          }"
          :style="{ '--rarity': RARITY_COLORS[item.rarity] || RARITY_COLORS.common }"
          :disabled="busy !== null"
          @click="mode === 'mine' ? toggle(item) : onShopClick(item)"
      >
        <ShopItemPreview :item="item" class="studio-inv__preview" />
        <span class="studio-inv__name">{{ item.name }}</span>
        <span class="studio-inv__state">
          <template v-if="mode === 'mine'">{{ item.equipped ? 'Надето · снять' : 'Надеть' }}</template>
          <template v-else-if="item.owned">Уже твоё</template>
          <template v-else>{{ trying?.id === item.id ? 'На холсте' : `Примерить · ${item.price}` }}</template>
        </span>
      </button>
    </div>

    <p v-else-if="!loading" class="studio-empty">
      {{ mode === 'mine' ? 'В инвентаре пока нет предметов для профиля — загляни в «Примерку».' : 'В этом разделе магазина пусто.' }}
    </p>
  </div>
</template>

<style>
.studio-inv { display: flex; flex-direction: column; gap: 14px; }

.studio-inv__slots {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;
}

.studio-inv__slots button {
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
  padding: 8px 10px;
  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid var(--studio-line, rgba(255, 255, 255, 0.07));
  border-radius: 10px;
  text-align: left;
  cursor: pointer;
}

.studio-inv__slots button.is-on {
  color: #fff;
  background: color-mix(in srgb, var(--studio-accent, #8b5cf6) 14%, transparent);
  border-color: var(--studio-accent, #8b5cf6);
}

.studio-inv__slots strong { font-size: 12.5px; }

.studio-inv__slots small {
  max-width: 100%;
  overflow: hidden;
  color: var(--studio-dim, #8d88a0);
  font-size: 10.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.studio-inv__grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.studio-inv__item {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 6px 6px 8px;
  color: #e6e3ee;
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid color-mix(in srgb, var(--rarity) 30%, transparent);
  border-radius: 12px;
  text-align: left;
  cursor: pointer;
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.studio-inv__item:hover:not(:disabled) { transform: translateY(-2px); border-color: var(--rarity); }

.studio-inv__item.is-on {
  border-color: var(--studio-accent, #8b5cf6);
  box-shadow: 0 0 0 1px var(--studio-accent, #8b5cf6);
}

.studio-inv__item.is-busy { opacity: 0.5; }
.studio-inv__item:disabled { cursor: progress; }

.studio-inv__preview {
  height: 84px;
  overflow: hidden;
  border-radius: 8px;
}

.studio-inv__name {
  overflow: hidden;
  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.studio-inv__state {
  color: var(--studio-dim, #8d88a0);
  font-size: 10.5px;
  font-weight: 700;
}

.studio-inv__item.is-on .studio-inv__state { color: #c4b5fd; }

.studio-try {
  position: sticky;
  top: -16px;
  z-index: 2;

  display: flex;
  align-items: center;
  gap: 8px;

  padding: 10px;

  background: color-mix(in srgb, var(--studio-accent, #8b5cf6) 18%, #111019);
  border: 1px solid var(--studio-accent, #8b5cf6);
  border-radius: 12px;
}

.studio-try > div { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.studio-try small { color: var(--studio-dim, #8d88a0); font-size: 10.5px; }

.studio-try strong {
  overflow: hidden;
  font-size: 12.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.studio-try .studio-btn { min-height: 32px; padding: 0 10px; font-size: 12px; }
</style>
