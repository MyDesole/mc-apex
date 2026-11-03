<script setup>
/**
 * Лавка: за Рубли — привилегии Minecraft-сервера, за Осколки Разлома —
 * уникальные вещи игры. Рубли нельзя вывести или передать другому игроку.
 */
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const data = ref(null)
const pets = ref([])
const petId = ref(null)
const error = ref('')
const notice = ref('')
const busy = ref('')

const STATUS = { pending: 'ждёт выдачи на сервере', done: 'выдана', failed: 'не выдана — напиши в поддержку' }

async function load() {
  try {
    data.value = await api.get('/lavka')
    pets.value = (await api.get('/pets')).pets ?? []
    petId.value ??= pets.value[0]?.inventory_id ?? null
  } catch (e) {
    error.value = e.message || 'Не удалось открыть лавку.'
  }
}

async function buy(path, body, name, price, unit) {
  const ok = await confirmDialog(`${name} за ${price} ${unit}?`, { title: 'Покупка', confirmText: 'Купить' })
  if (!ok) return
  busy.value = path
  error.value = ''
  try {
    data.value = await api.post(path, body)
    notice.value = `Куплено: ${name}.`
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="g-scene lv">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>
    <p v-if="notice" class="g-msg">{{ notice }}</p>

    <template v-if="data">
      <div class="lv-wallet">
        <span class="g-chip g-chip--gold">₽ Рубли: <b>{{ data.rubles }}</b></span>
        <span class="g-chip">Осколки Разлома: <b>{{ data.shards }}</b></span>
      </div>

      <section class="lv-shelf g-wood">
        <h3 class="g-title">Привилегии сервера <small>за Рубли</small></h3>
        <p v-if="!data.linked" class="g-hint">Привяжи ник Minecraft в профиле: привилегии выдаются на сервере по нику.</p>
        <p v-else class="g-hint">Выдадим на ник <b>{{ data.nick }}</b>: плагин сервера заберёт заказ сам.</p>
        <div class="lv-grid">
          <article v-for="p in data.privileges" :key="p.key" class="lv-item g-stone">
            <b>{{ p.name }}</b>
            <small>{{ p.about }}</small>
            <button type="button" class="g-btn g-btn--gold g-btn--sm" :disabled="!data.linked || p.cooldown || data.rubles < p.price || busy" @click="buy(`/lavka/privileges/${p.key}`, {}, p.name, p.price, '₽')">
              {{ p.cooldown ? 'куплено сегодня' : `₽ ${p.price}` }}
            </button>
          </article>
        </div>
        <ul v-if="data.orders.length" class="lv-orders">
          <li v-for="(o, i) in data.orders" :key="i"><b>{{ o.name }}</b> · ₽ {{ o.price }} · {{ STATUS[o.status] ?? o.status }}</li>
        </ul>
      </section>

      <section class="lv-shelf g-dark">
        <h3 class="g-title">Лавка Разлома <small>за Осколки</small></h3>
        <p class="g-hint">Осколки дают за победу в рейде «Разлом».</p>
        <div class="lv-grid">
          <article v-for="s in data.shard_items" :key="s.key" class="lv-item g-stone">
            <b>{{ s.name }}</b>
            <small>{{ s.about }}</small>
            <select v-if="s.needs_pet" v-model.number="petId" class="lv-select">
              <option v-for="p in pets" :key="p.inventory_id" :value="p.inventory_id">{{ p.name }} · ур. {{ p.level }}</option>
            </select>
            <button type="button" class="g-btn g-btn--go g-btn--sm" :disabled="data.shards < s.price || busy" @click="buy(`/lavka/shards/${s.key}`, s.needs_pet ? { pet: petId } : {}, s.name, s.price, 'Осколков')">{{ s.price }} Осколков</button>
          </article>
        </div>
      </section>

      <p class="g-hint">Рубли привязаны к аккаунту: передать их или вывести нельзя. Заработать: <RouterLink to="/world?at=dungeon">подземелья</RouterLink>, <RouterLink to="/world?at=raid">рейд «Разлом»</RouterLink>, фермы.</p>
    </template>
  </div>
</template>

<style scoped>
.lv-wallet { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.lv-shelf { margin-bottom: 12px; padding: 12px 14px; }
.lv-shelf h3 small { font-size: 12px; color: #ffe9a8; }
.lv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; margin-top: 8px; }
.lv-item { display: flex; flex-direction: column; gap: 6px; padding: 10px 12px; font-size: 13px; }
.lv-item small { font-size: 11px; line-height: 1.4; color: #c9b9e8; }
.lv-select { padding: 4px 8px; font: inherit; font-size: 12px; color: #fff; background: #20150c; border: 3px solid #4a2a10; }
.lv-orders { margin: 10px 0 0; padding: 8px 12px; list-style: none; font-size: 11px; line-height: 1.7; background: rgba(0, 0, 0, 0.3); border: 3px solid #4a2a10; }
.g-hint a { color: #ffd45c; }
</style>
