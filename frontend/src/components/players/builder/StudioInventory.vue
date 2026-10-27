<script setup>
/**
 * Инвентарь в студии профиля: всё купленное для профиля по слотам
 * (рамка, эффект, макет, акцент, ник, питомцы…). Нажал — надел, и
 * холст сразу показывает результат. Надевает тот же API, что страница
 * инвентаря, так что надеть некупленное нельзя.
 */
import { computed, onMounted, ref } from 'vue'
import { shopApi } from '@/services/shop/shop.js'
import { useAuthStore } from '@/stores/core/auth.js'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { RARITY_COLORS } from '@/data/wallet/economy.js'

const auth = useAuthStore()

// Слоты, которые видно на профиле
const SLOTS = [
  ['avatar_frame', 'Рамка'],
  ['profile_effect', 'Эффект'],
  ['profile_layout', 'Макет'],
  ['card_theme', 'Карточка'],
  ['accent_color', 'Акцент'],
  ['name_effect', 'Ник'],
  ['name_icon', 'Значок'],
  ['profile_title', 'Титул'],
  ['badge', 'Бейджи'],
  ['skin_backdrop', 'Фон скина'],
  ['profile_entrance', 'Появление'],
  ['cursor_trail', 'След'],
  ['profile_pet', 'Питомцы'],
]

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
    .filter((s) => s.count > 0))

async function load(type = slot.value) {
  loading.value = true
  try {
    const data = await shopApi.inventoryPage({ tab: type || 'all', limit: 60 })
    counts.value = data.counts ?? counts.value
    equipped.value = data.equipped ?? equipped.value
    items.value = data.items ?? []
    if (!slot.value && !type) {
      // Первый вход: открываем первый слот, где что-то есть
      slot.value = slots.value[0]?.type ?? null
      if (slot.value) return load(slot.value)
    }
  } catch (e) {
    message.value = e.message || 'Не удалось загрузить инвентарь.'
  } finally {
    loading.value = false
  }
}

function openSlot(type) {
  slot.value = type
  load(type)
}

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

onMounted(() => load())
</script>

<template>
  <div class="studio-inv">
    <div v-if="slots.length" class="studio-inv__slots">
      <button
          v-for="s in slots"
          :key="s.type"
          type="button"
          :class="{ 'is-on': slot === s.type }"
          @click="openSlot(s.type)"
      >
        <strong>{{ s.label }}</strong>
        <small>{{ s.on || 'не надето' }}</small>
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
          :class="{ 'is-on': item.equipped, 'is-busy': busy === (item.inventory_id ?? item.id) }"
          :style="{ '--rarity': RARITY_COLORS[item.rarity] || RARITY_COLORS.common }"
          :disabled="busy !== null"
          @click="toggle(item)"
      >
        <ShopItemPreview :item="item" class="studio-inv__preview" />
        <span class="studio-inv__name">{{ item.name }}</span>
        <span class="studio-inv__state">{{ item.equipped ? 'Надето · снять' : 'Надеть' }}</span>
      </button>
    </div>

    <p v-else-if="!slots.length && !loading" class="studio-empty">
      В инвентаре пока нет предметов для профиля. Загляни в магазин.
    </p>

    <RouterLink to="/shop" class="studio-wide-btn">Открыть магазин</RouterLink>
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

.studio-inv .studio-wide-btn { text-decoration: none; }
</style>
