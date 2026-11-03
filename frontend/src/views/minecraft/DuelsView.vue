<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { duelsApi } from '@/services/minecraft/duels.js'

const auth = useAuthStore()
const route = useRoute()

const tab = ref('history')
const loading = ref(true)
const error = ref('')
const data = ref(null)
const clans = ref([])
const online = ref({ count: 0, players: [] })
const kit = ref('')
const page = ref(1)

const playerId = computed(() => route.params.id || auth.user?.id)

function duration(seconds) {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

function date(iso) {
  return iso ? new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : ''
}

async function loadPlayer() {
  loading.value = true
  error.value = ''

  try {
    const params = { page: page.value }
    if (kit.value) params.kit = kit.value
    data.value = (await duelsApi.player(playerId.value, params)).data
  } catch (e) {
    error.value = e.response?.data?.message || 'Не удалось загрузить дуэли.'
  } finally {
    loading.value = false
  }
}

async function loadClans() {
  clans.value = (await duelsApi.clans()).data.clans
}

async function loadOnline() {
  online.value = (await duelsApi.online()).data
}

function pickKit(value) {
  kit.value = kit.value === value ? '' : value
  page.value = 1
}

watch([kit, page, playerId], loadPlayer)
onMounted(() => {
  loadPlayer()
  loadClans().catch(() => {})
  loadOnline().catch(() => {})
})
</script>

<template>
  <main class="duels-page">
    <header class="duels-head">
      <p class="eyebrow">Minecraft</p>
      <h1>Дуэли<template v-if="data"> · {{ data.player.minecraft_username || data.player.username }}</template></h1>
      <p v-if="data?.player.online" class="duels-online">Сейчас в игре</p>
    </header>

    <nav class="duels-tabs">
      <button :class="{ active: tab === 'history' }" @click="tab = 'history'">История</button>
      <button :class="{ active: tab === 'clans' }" @click="tab = 'clans'">Кланы</button>
      <button :class="{ active: tab === 'online' }" @click="tab = 'online'">Онлайн ({{ online.count }})</button>
    </nav>

    <p v-if="error" class="duels-error">{{ error }}</p>

    <section v-if="tab === 'history' && data">
      <div class="duels-summary">
        <div><b>{{ data.stats.total.games }}</b><span>матчей</span></div>
        <div><b>{{ data.stats.total.wins }}</b><span>побед</span></div>
        <div><b>{{ data.stats.total.losses }}</b><span>поражений</span></div>
        <div><b>{{ data.stats.total.win_rate }}%</b><span>винрейт</span></div>
        <div>
          <b>{{ data.stats.total.streak > 0 ? '+' : '' }}{{ data.stats.total.streak }}</b>
          <span>серия</span>
        </div>
      </div>

      <div v-if="data.stats.kits.length" class="duels-kits">
        <button
          v-for="k in data.stats.kits"
          :key="k.kit"
          :class="{ active: kit === k.kit }"
          @click="pickKit(k.kit)"
        >
          <b>{{ k.kit }}</b>
          <span>{{ k.wins }}П / {{ k.losses }}Н · {{ k.win_rate }}%</span>
        </button>
      </div>

      <p v-if="!loading && !data.duels.length" class="duels-empty">Дуэлей пока нет.</p>

      <ul class="duels-list">
        <li v-for="d in data.duels" :key="d.id" :class="d.won ? 'won' : 'lost'">
          <span class="duels-result">{{ d.won ? 'Победа' : 'Поражение' }}</span>
          <span class="duels-kit">{{ d.kit }}</span>
          <span class="duels-opponent">
            vs
            <RouterLink v-if="d.opponent_user_id" :to="`/players/${d.opponent_user_id}`">{{ d.opponent }}</RouterLink>
            <template v-else>{{ d.opponent }}</template>
          </span>
          <span class="duels-time">{{ duration(d.duration_seconds) }}</span>
          <span class="duels-date">{{ date(d.played_at) }}</span>
        </li>
      </ul>

      <div v-if="data.meta.last_page > 1" class="duels-pager">
        <button :disabled="page <= 1" @click="page--">Назад</button>
        <span>{{ page }} / {{ data.meta.last_page }}</span>
        <button :disabled="page >= data.meta.last_page" @click="page++">Дальше</button>
      </div>
    </section>

    <section v-if="tab === 'clans'">
      <p v-if="!clans.length" class="duels-empty">Дуэли кланов ещё не сыграны.</p>
      <ol class="duels-clans">
        <li v-for="c in clans" :key="c.clan_id">
          <RouterLink :to="`/clans/${c.clan_id}`">[{{ c.tag }}] {{ c.name }}</RouterLink>
          <span>{{ c.wins }}П / {{ c.losses }}Н · {{ c.win_rate }}%</span>
        </li>
      </ol>
    </section>

    <section v-if="tab === 'online'">
      <p v-if="!online.players.length" class="duels-empty">Никого с сайта сейчас нет на сервере.</p>
      <ul class="duels-online-list">
        <li v-for="p in online.players" :key="p.id">
          <RouterLink :to="`/players/${p.id}`">{{ p.username }}</RouterLink>
          <span>{{ p.minecraft_username }}</span>
        </li>
      </ul>
    </section>
  </main>
</template>

<style src="./DuelsView.css"></style>
