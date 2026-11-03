<script setup>
/**
 * Выставить материал или снаряжение на рынок игроков. Один диалог на
 * все сцены: кузницу, склад и сам рынок.
 */
import { computed, ref } from 'vue'
import { api } from '@/services/core/api.js'

const props = defineProps({
  kind: { type: String, required: true }, // material | gear
  name: { type: String, required: true },
  material: { type: String, default: null },
  item: { type: Number, default: null },
  maxQty: { type: Number, default: 1 },
})
const emit = defineEmits(['close', 'done'])

const FEE = 0.05

const qty = ref(1)
const price = ref(100)
const error = ref('')
const busy = ref(false)

const net = computed(() => Math.max(0, Math.floor(Number(price.value || 0) * (1 - FEE))))

async function submit() {
  busy.value = true
  error.value = ''
  try {
    if (props.kind === 'material') {
      await api.post('/market/sell-material', { material: props.material, qty: Number(qty.value), price: Number(price.value) })
    } else {
      await api.post('/market/sell-gear', { item: props.item, price: Number(price.value) })
    }
    emit('done', `«${props.name}» выставлен на рынок.`)
    emit('close')
  } catch (e) {
    error.value = e.message || 'Не удалось выставить.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="g-modal" @click.self="emit('close')">
    <form class="g-modal__box g-wood sd" @submit.prevent="submit">
      <h3>На рынок: {{ name }}</h3>
      <label v-if="kind === 'material'" class="sd-row">
        <span>Количество (у тебя {{ maxQty }})</span>
        <input v-model.number="qty" type="number" min="1" :max="maxQty">
      </label>
      <label class="sd-row">
        <span>Цена за всё, ApexCoin</span>
        <input v-model.number="price" type="number" min="1" max="1000000">
      </label>
      <p class="g-hint">Комиссия рынка 5%: ты получишь ₳ {{ net }}. Лот висит 72 часа, потом товар вернётся.</p>
      <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
      <div class="sd-act">
        <button type="submit" class="g-btn g-btn--go" :disabled="busy">Выставить</button>
        <button type="button" class="g-btn" @click="emit('close')">Отмена</button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.sd { text-align: left; }
.sd h3 { text-align: center; }
.sd-row { display: flex; flex-direction: column; gap: 4px; margin: 10px 0; font-size: 12px; font-weight: 800; color: #ffe9a8; }
.sd-row input { padding: 7px 10px; font: inherit; font-size: 14px; color: #fff; background: #20150c; border: 3px solid #4a2a10; }
.sd-act { display: flex; gap: 8px; justify-content: center; margin-top: 10px; }
</style>
