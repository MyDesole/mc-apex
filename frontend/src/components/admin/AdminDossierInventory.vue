<script setup>
/**
 * Досье: инвентарь игрока. Админ видит всё, что у игрока лежит, и может
 * надеть или снять предмет, поменять количество, забрать предмет или
 * перебросить черты питомца. Всё пишется в историю модерации.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import { confirm, prompt } from '@/utils/dialog.js'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { RARITY_LABELS, TYPE_LABELS } from '@/data/wallet/economy.js'

const props = defineProps({
  userId: { type: Number, required: true },
  username: { type: String, default: '' },
  // Счётчик снаружи: после подарка предмета список перечитывается
  refreshKey: { type: Number, default: 0 },
})

const items = ref([])
const loading = ref(true)
const busyId = ref(null)
const error = ref('')
const tab = ref('all')
const search = ref('')
const ownsAll = ref(false)

const STACKABLE = ['tier_priority', 'coin_bundle']

const tabs = computed(() => {
  const counts = {}
  for (const item of items.value) counts[item.type] = (counts[item.type] || 0) + 1

  return [
    { key: 'all', label: 'Все', count: items.value.length },
    { key: 'equipped', label: 'Надето', count: items.value.filter((i) => i.equipped).length },
    ...Object.entries(counts).map(([type, count]) => ({ key: type, label: TYPE_LABELS[type] || type, count })),
  ]
})

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()

  return items.value
      .filter((i) => tab.value === 'all' || (tab.value === 'equipped' ? i.equipped : i.type === tab.value))
      .filter((i) => !q || i.name.toLowerCase().includes(q))
      .sort((a, b) => Number(b.equipped) - Number(a.equipped))
})

async function load() {
  loading.value = true
  error.value = ''

  try {
    apply(await adminApi.userInventory(props.userId))
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить инвентарь.'
  } finally {
    loading.value = false
  }
}

function apply(data) {
  items.value = data.items ?? []
  ownsAll.value = Boolean(data.owns_all_cosmetics)
}

async function run(item, fn) {
  busyId.value = item.inventory_id
  error.value = ''

  try {
    apply(await fn())
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busyId.value = null
  }
}

function toggle(item) {
  run(item, () => adminApi.userInventoryEquip(props.userId, item.shop_item_id, !item.equipped))
}

async function setQuantity(item) {
  const value = await prompt(`Сколько «${item.name}» оставить у игрока?`, {
    title: 'Количество', placeholder: String(item.quantity), confirmText: 'Сохранить',
  })
  const quantity = Number.parseInt(value ?? '', 10)
  if (!Number.isFinite(quantity) || quantity < 1) return

  run(item, () => adminApi.userInventoryQuantity(props.userId, item.inventory_id, quantity))
}

async function remove(item) {
  if (!(await confirm(`Забрать «${item.name}» у ${props.username}? Это нельзя отменить.`, { confirmText: 'Забрать' }))) return
  run(item, () => adminApi.userInventoryRemove(props.userId, item.inventory_id))
}

async function reroll(item) {
  if (!(await confirm(`Перебросить черты питомца #${item.pet.serial}? Окрас, фон и аксессуар выпадут заново.`, { confirmText: 'Перебросить' }))) return
  run(item, () => adminApi.userInventoryRerollPet(props.userId, item.inventory_id))
}

watch(() => props.refreshKey, load)
onMounted(load)
</script>

<template>
  <section class="dossier__section adm-inv">
    <h3>
      Инвентарь
      <span class="kit-muted">{{ items.length }} предметов<template v-if="ownsAll"> · админу вся косметика доступна и без покупки</template></span>
    </h3>

    <div class="adm-inv__bar">
      <div class="adm-inv__tabs">
        <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            class="adm-inv__tab"
            :class="{ 'is-on': tab === t.key }"
            @click="tab = t.key"
        >{{ t.label }} <b>{{ t.count }}</b></button>
      </div>
      <input v-model="search" class="kit-input adm-inv__search" placeholder="Поиск по названию" />
    </div>

    <p v-if="error" class="adm-inv__error">{{ error }}</p>
    <p v-if="loading" class="kit-muted">Загружаем…</p>
    <p v-else-if="!visible.length" class="kit-muted">Ничего нет.</p>

    <div v-else class="adm-inv__grid">
      <article
          v-for="item in visible"
          :key="item.inventory_id"
          class="adm-inv__card"
          :class="{ 'is-equipped': item.equipped, 'is-busy': busyId === item.inventory_id }"
      >
        <ShopItemPreview :item="item" class="adm-inv__preview" />

        <div class="adm-inv__info">
          <strong>{{ item.name }}</strong>
          <small>{{ TYPE_LABELS[item.type] || item.type }} · {{ RARITY_LABELS[item.rarity] || item.rarity }}</small>
          <small v-if="item.pet" class="adm-inv__pet">
            #{{ item.pet.serial }} · {{ item.pet.model.name }} · {{ item.pet.backdrop.name }} · {{ item.pet.accessory.name }}
          </small>
          <small v-if="item.quantity > 1">× {{ item.quantity }}</small>
        </div>

        <div class="adm-inv__actions">
          <button
              v-if="item.equippable !== false && !STACKABLE.includes(item.type)"
              type="button"
              class="kit-btn"
              :disabled="busyId !== null"
              @click="toggle(item)"
          >{{ item.equipped ? 'Снять' : 'Надеть' }}</button>
          <button
              v-if="STACKABLE.includes(item.type)"
              type="button"
              class="kit-btn"
              :disabled="busyId !== null"
              @click="setQuantity(item)"
          >Количество</button>
          <button
              v-if="item.pet"
              type="button"
              class="kit-btn"
              :disabled="busyId !== null"
              title="Новые случайные окрас, фон и аксессуар"
              @click="reroll(item)"
          >🎲 Черты</button>
          <button
              type="button"
              class="kit-btn kit-btn--danger"
              :disabled="busyId !== null"
              @click="remove(item)"
          >Забрать</button>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.adm-inv__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;

  margin-bottom: 12px;
}

.adm-inv__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.adm-inv__tab {
  padding: 5px 10px;

  color: #a8a3b8;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;

  font: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.adm-inv__tab b {
  margin-left: 3px;
  color: #fff;
}

.adm-inv__tab.is-on {
  color: #fff;
  background: rgba(139, 92, 246, 0.3);
  border-color: rgba(139, 92, 246, 0.6);
}

.adm-inv__search {
  max-width: 220px;
}

.adm-inv__error {
  color: #fca5a5;
}

.adm-inv__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}

.adm-inv__card {
  overflow: hidden;

  display: flex;
  flex-direction: column;

  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;

  transition: opacity 0.15s ease;
}

.adm-inv__card.is-equipped {
  border-color: rgba(74, 222, 128, 0.55);
  box-shadow: inset 0 0 0 1px rgba(74, 222, 128, 0.2);
}

.adm-inv__card.is-busy {
  opacity: 0.55;
}

.adm-inv__preview {
  height: 96px !important;
}

.adm-inv__info {
  display: flex;
  flex-direction: column;
  gap: 2px;

  padding: 8px 10px 4px;
}

.adm-inv__info strong {
  color: #fff;
  font-size: 13px;
}

.adm-inv__info small {
  color: #8a8598;
  font-size: 11px;
}

.adm-inv__pet {
  color: #c4b5fd !important;
}

.adm-inv__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;

  margin-top: auto;
  padding: 8px 10px 10px;
}

.adm-inv__actions .kit-btn {
  padding: 4px 9px;
  font-size: 12px;
}
</style>
