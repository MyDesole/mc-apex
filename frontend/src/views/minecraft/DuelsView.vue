<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { duelsApi } from '@/services/minecraft/duels.js'
import ServerAddress from '@/components/core/ServerAddress.vue'
import ModeStatsPanel from '@/components/minecraft/ModeStatsPanel.vue'
import { siteApi } from '@/services/site/site.js'

const route = useRoute(), router = useRouter(), auth = useAuthStore()
const MODES = {
  duels: { title: 'Дуэли', command: '/d ник', text: 'Поединки один на один: Sumo, Boxing, BedFight, Classic, NoDebuff, Gapple, Cobweb, BuildUHC, BlockFight и Archer.', details: ['Пригласи соперника через /d ник или выбери дуэль в игровом меню.', 'Раскладки доступны через /layouts. Предметы, броня и количество вещей определяются набором.', 'Приватная дуэль через /private проходит без общего рейтинга и наград.'] },
  bedwars: { title: 'BedWars', command: 'Компас → BedWars', text: 'Карты на 2, 4 и 8 команд. Защищай кровать, собирай ресурсы и уничтожай базы соперников. Пока кровать цела, можно возродиться.', details: ['Выбери карту через компас в главном лобби. Размер команды указан в таблице арен ниже.', 'Пати: /party invite ник. После разрушения кровати следующая смерть становится финальной.', 'APEX и LEGEND могут создать /private. Друзья участвуют бесплатно; общие награды и статистика в такой игре отключены.'] },
  fastbridge: { title: 'FastBridge', command: '/fb', text: 'Тренировка моста на 16, 24, 32 и 64 блока или бесконечной дорожке. На конечной дистанции попытка завершается нажатием золотой плиты на финишном острове.', details: ['Таймер начинается с первого поставленного блока. Обычный вход на остров не завершает попытку.', 'Реплеи: /fb replays; вид от первого лица: /fb camera first. Пауза и скорость доступны всем.', 'Настройки: /fb settings; история: /fb history. Приватные тренировки не попадают в общий топ.'] },
}
const mode = computed(() => Object.hasOwn(MODES, route.query.mode) ? route.query.mode : 'duels')
const current = computed(() => MODES[mode.value])
const scope = ref(route.params.id ? 'player' : 'server'), kit = ref(''), page = ref(1)
const data = ref(null), loading = ref(false), error = ref(''), server = ref(null)
const serverAddress = computed(() => server.value?.address || 'apexpvp.pro')
const playerId = computed(() => route.params.id || auth.user?.id)
const displayedStats = computed(() => data.value?.server_stats || data.value?.stats)
const kitNames = ['Boxing','Sumo','BedFight','NoDebuff','Classic','Gapple','Cobweb','BuildUHC','BlockFight','Archer']
let requestId = 0
function pickMode(value) { router.replace({ name: 'duels', params: route.params.id ? {id:route.params.id} : {}, query: { ...route.query, mode: value } }) }
function pickScope(value) { scope.value = value; page.value = 1; kit.value = '' }
function duration(seconds = 0) { return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2,'0')}` }
function date(iso) { return iso ? new Date(iso).toLocaleString('ru-RU',{timeZone:'Europe/Moscow',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—' }
async function load() {
  const request = ++requestId; error.value = ''; data.value = null
  if (mode.value !== 'duels') { loading.value = false; return }
  if (scope.value === 'player' && !playerId.value) { loading.value = false; return }
  loading.value = true
  try {
    const params = { page: page.value, kit: kit.value }
    const fresh = scope.value === 'server' ? await duelsApi.recent(params) : await duelsApi.player(playerId.value,params)
    if (request === requestId) data.value = fresh
  } catch(e) { if (request === requestId) error.value = e.message || 'Не удалось загрузить историю.' }
  finally { if (request === requestId) loading.value = false }
}
watch([mode,scope,kit,page,playerId],load,{immediate:true})
watch(() => route.params.id, value => { if (value) scope.value = 'player'; page.value = 1 })
siteApi.serverStatus().then(value => { server.value = value }).catch(() => {})
onBeforeUnmount(() => { requestId++ })
</script>

<template>
  <main class="content-page modes-page">
    <header class="content-page__head"><h1>Игровые режимы</h1><p>История дуэлей, статистика BedWars и рекорды FastBridge.</p></header>
    <div class="modes-connection"><ServerAddress :address="serverAddress" /><span v-if="server?.online" class="mode-online">Сервер работает · {{ server.players }} онлайн</span><RouterLink to="/status">Как подключиться</RouterLink></div>
    <nav class="modes-tabs" aria-label="Игровой режим"><button v-for="(m,key) in MODES" :key="key" :class="{active:mode===key}" :aria-pressed="mode===key" @click="pickMode(key)">{{ m.title }}</button></nav>
    <section class="content-card mode-intro"><header class="mode-section-head"><h2>{{ current.title }}</h2><code>{{ current.command }}</code></header><p>{{ current.text }}</p><details><summary>Правила и команды</summary><ol><li v-for="text in current.details" :key="text">{{ text }}</li></ol></details></section>
    <ModeStatsPanel v-if="mode!=='duels'" :key="mode" :mode="mode" :user-id="route.params.id || null" />
    <section v-else class="content-card mode-history">
      <header class="mode-section-head"><h2>История дуэлей</h2><button class="mode-refresh" :disabled="loading" @click="load">Обновить</button></header>
      <div class="mode-history__controls"><div><button :class="{active:scope==='server'}" @click="pickScope('server')">Матчи сервера</button><button :class="{active:scope==='player'}" @click="pickScope('player')">{{ route.params.id && route.params.id != auth.user?.id ? 'История игрока' : 'Моя история' }}</button></div><label>Набор<select v-model="kit" @change="page=1"><option value="">Все наборы</option><option v-for="name in kitNames" :key="name" :value="name.toLowerCase()">{{ name }}</option></select></label></div>
      <p v-if="scope==='player' && !playerId" class="mode-state"><RouterLink :to="{name:'login',query:{redirect:'/duels'}}">Войди в аккаунт</RouterLink>, чтобы открыть свою историю. Матчи сервера доступны всем.</p>
      <p v-if="scope==='player' && data?.player" class="mode-history__player">Игрок: <strong>{{ data.player.minecraft_username || data.player.username }}</strong><RouterLink v-if="!data.player.minecraft_username && data.player.id===auth.user?.id" to="/profile">Привязать Minecraft →</RouterLink></p>
      <div v-if="displayedStats" class="mode-numbers"><div><strong>{{ displayedStats.total.games }}</strong><span>Матчей</span></div><div><strong>{{ displayedStats.total.wins }}</strong><span>Побед</span></div><div><strong>{{ displayedStats.total.losses }}</strong><span>Поражений</span></div><div><strong>{{ displayedStats.total.win_rate }}%</strong><span>Побед</span></div></div>
      <p v-if="data?.server_stats" class="mode-note">Статистика сервера: обычные матчи из очереди. В истории сайта сохранено {{ data.meta.total }} матчей{{ kit ? ' по этому набору' : '' }}. Старые итоги, для которых сохранились только общие счётчики, не содержат списка соперников и времени.</p>
      <p v-if="loading" class="mode-state" role="status">Загружаю матчи…</p><div v-else-if="error" class="mode-state mode-state--error" role="alert">{{ error }} <button @click="load">Попробовать ещё</button></div>
      <template v-else-if="data"><div v-if="data.duels.length" class="mode-table-wrap mode-match-list"><table class="mode-table"><thead><tr><th>Итог</th><th>{{ scope==='server'?'Игроки':'Соперник' }}</th><th>Набор</th><th>Время</th><th>Когда · МСК</th></tr></thead><tbody><tr v-for="d in data.duels" :key="d.id"><td><span :class="{'mode-win':scope==='player'&&d.won,'mode-loss':scope==='player'&&!d.won}">{{ scope==='player' ? (d.won?'Победа':'Поражение') : 'Победа' }}</span></td><td><template v-if="scope==='server'"><RouterLink v-if="d.winner_user_id" :to="`/duels/${d.winner_user_id}`">{{ d.winner }}</RouterLink><strong v-else>{{ d.winner }}</strong><span class="mode-versus"> — </span><RouterLink v-if="d.loser_user_id" :to="`/duels/${d.loser_user_id}`">{{ d.loser }}</RouterLink><span v-else>{{ d.loser }}</span></template><template v-else><RouterLink v-if="d.opponent_user_id" :to="`/duels/${d.opponent_user_id}`">{{ d.opponent }}</RouterLink><strong v-else>{{ d.opponent }}</strong></template></td><td><strong>{{ d.kit }}</strong><small class="mode-cell-note">{{ d.ranked?'Рейтинговый':'Обычный' }}</small></td><td>{{ duration(d.duration_seconds) }}</td><td class="mode-time">{{ date(d.played_at) }}</td></tr></tbody></table></div><p v-else class="mode-state">{{ data.server_stats?.total.games ? 'Подробности прошлых матчей не были переданы на сайт. Новые завершённые дуэли будут появляться здесь.' : 'Завершённых дуэлей по этому фильтру пока нет.' }}</p><nav v-if="data.meta.last_page>1" class="mode-pager" aria-label="Страницы истории"><button :disabled="page<=1" @click="page--">← Назад</button><span>{{ data.meta.current_page }} / {{ data.meta.last_page }} · {{ data.meta.total }} матчей</span><button :disabled="page>=data.meta.last_page" @click="page++">Дальше →</button></nav></template>
      <p class="mode-note mode-fairplay">Приватные тренировки не влияют на общую статистику. Донат добавляет оформление и удобство; предметы, урон и ресурсы обычного матча одинаковы для всех.</p>
    </section>
  </main>
</template>
<style src="./DuelsView.css"></style>
