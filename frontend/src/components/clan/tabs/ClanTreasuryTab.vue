<script setup>
/**
 * Казна клана: участники скидываются ApexCoin, лидер оплачивает
 * из неё клановые предметы в магазине (подсветку и её оформление).
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { myClanApi } from '@/services/clan/myClan.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { formatCoins } from '@/data/wallet/economy.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  isLeader: { type: Boolean, default: false },
})

const auth = useAuthStore()

const data = ref(null)
const amount = ref(100)
const busy = ref(false)
const error = ref('')
const notice = ref('')

const myBalance = computed(() => auth.user?.apex_coins ?? 0)

async function load() {
  try {
    data.value = await myClanApi.treasury()
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить казну.'
  }
}

async function donate() {
  error.value = ''
  notice.value = ''
  busy.value = true

  try {
    const result = await myClanApi.donate(Number(amount.value))
    data.value = result
    notice.value = result.message
    await auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось сделать взнос.'
  } finally {
    busy.value = false
  }
}

function date(iso) {
  return iso ? new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : ''
}

onMounted(load)
</script>

<template>
  <div class="treasury">
    <section class="treasury__card treasury__balance">
      <span class="treasury__label">В казне клана</span>
      <strong class="treasury__sum">₳ {{ formatCoins(data?.balance ?? 0) }}</strong>
      <p class="treasury__hint">
        Скидывайтесь на нужды клана: лидер оплачивает из казны подсветку клана и её оформление в
        <RouterLink to="/shop?type=clan_highlight">магазине</RouterLink>.
        <template v-if="isLeader"> При покупке клановых предметов выберите «Казна клана».</template>
      </p>
    </section>

    <section class="treasury__card">
      <h3>Скинуться в казну</h3>
      <form class="treasury__form" @submit.prevent="donate">
        <input
            v-model.number="amount"
            class="treasury__input"
            type="number"
            min="1"
            :max="data?.max_donation ?? 100000"
            aria-label="Сумма взноса"
        >
        <button class="btn btn-primary" type="submit" :disabled="busy || !amount || amount < 1 || amount > myBalance">
          {{ busy ? 'Отправляем…' : 'Скинуть' }}
        </button>
      </form>
      <small class="treasury__muted">Ваш баланс: ₳ {{ formatCoins(myBalance) }}. Взнос нельзя вернуть.</small>

      <div v-if="error" class="alert alert--error" role="alert">{{ error }}</div>
      <div v-if="notice" class="alert alert--success" role="status">{{ notice }}</div>
    </section>

    <section class="treasury__card">
      <h3>Главные вкладчики</h3>
      <p v-if="!data?.donors?.length" class="treasury__muted">Пока никто не скидывался — будьте первым.</p>
      <ol class="treasury__list">
        <li v-for="d in data?.donors ?? []" :key="d.user?.id">
          <RouterLink v-if="d.user" :to="userLink(d.user)">{{ d.user.username }}</RouterLink>
          <b>₳ {{ formatCoins(d.total) }}</b>
        </li>
      </ol>
    </section>

    <section class="treasury__card">
      <h3>История</h3>
      <p v-if="!data?.history?.length" class="treasury__muted">Движений пока нет.</p>
      <ul class="treasury__list">
        <li v-for="h in data?.history ?? []" :key="h.id">
          <span>
            {{ h.user?.username ?? '—' }} · {{ h.type === 'donation' ? 'взнос' : h.description }}
            <small class="treasury__muted">{{ date(h.created_at) }}</small>
          </span>
          <b :class="h.amount > 0 ? 'treasury__plus' : 'treasury__minus'">
            {{ h.amount > 0 ? '+' : '−' }}₳ {{ formatCoins(Math.abs(h.amount)) }}
          </b>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.treasury {
  display: grid;
  gap: 14px;
}

.treasury__card {
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.02);
}

.treasury__card h3 {
  margin: 0 0 10px;
  font-size: 15px;
}

.treasury__label {
  display: block;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.7;
}

.treasury__sum {
  display: block;
  margin: 4px 0 8px;
  font-size: 32px;
  color: #facc15;
}

.treasury__hint,
.treasury__muted {
  margin: 0;
  font-size: 13px;
  opacity: 0.7;
}

.treasury__form {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
}

.treasury__input {
  flex: 1;
  min-width: 0;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.25);
  color: inherit;
}

.treasury__list {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.treasury__list li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
}

.treasury__plus { color: #86efac; }
.treasury__minus { color: #fca5a5; }

.alert {
  margin-top: 10px;
}
</style>
