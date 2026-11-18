<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { duelsApi } from '@/services/minecraft/duels.js'
import ServerAddress from '@/components/core/ServerAddress.vue'

const route = useRoute(), router = useRouter(), auth = useAuthStore()
const MODES = {
  duels: { title: 'Дуэли', label: 'Один на один', text: 'Выбери свой набор и проверь технику в честном поединке. От Sumo и Boxing до BedFight и BuildUHC.', command: '/d ник', details: ['Пригласи соперника через /d ник или выбери дуэль в игровом меню.', 'Предметы и правила определяет набор. Донат не меняет урон, отбрасывание и состав экипировки.', 'Приватная дуэль — тренировка без рейтинга и наград.'], features: [['10 наборов', 'Sumo, Boxing, BedFight, Classic, NoDebuff, Gapple, Cobweb, BuildUHC, BlockFight и Archer.'], ['Твоя раскладка', 'Бесплатное редактирование и дополнительные сохранённые варианты для донатеров.'], ['История матчей', 'Результат, соперник, набор и длительность завершённых обычных дуэлей.']] },
  bedwars: { title: 'BedWars', label: 'Командная игра', text: 'Защищай кровать, строй мосты и атакуй вместе с командой. Пока кровать цела, у тебя есть шанс вернуться в бой.', command: '/hub', details: ['Открой компас в главном лобби и выбери BedWars, затем карту на 2, 4 или 8 команд.', 'Собирай ресурсы, покупай экипировку и улучшения. После разрушения кровати следующая смерть становится финальной.', 'Пати собирается через /party invite ник. Общий состав сохраняется между режимами.'], features: [['2 / 4 / 8 команд', 'Размер команды зависит от выбранной карты.'], ['Играй с друзьями', 'Пати, командный чат и совместный вход на подходящую арену.'], ['Приватный матч', 'Хозяин от APEX приглашает друзей; гостям донат не нужен. Матч не даёт общую статистику и награды.']] },
  fastbridge: { title: 'FastBridge', label: 'Личная тренировка', text: 'От первого блока до золотой плиты. Оттачивай скорость строительства на островах Akina и разбирай свои попытки.', command: '/fb', details: ['Выбери 16, 24, 32 или 64 блока. Таймер запускается с первого поставленного блока.', 'На конечной дистанции наступи на золотую плиту своего финишного острова. Вход на остров сам по себе не завершает попытку.', 'В бесконечном режиме считается дальность. Приватная тренировка не попадает в общий рейтинг.'], features: [['Пять дистанций', '16 / 24 / 32 / 64 блока и бесконечная дорожка.'], ['Реплеи от первого лица', 'Компас или /fb camera first. Пауза, скорость и повтор доступны без доната.'], ['Личные настройки', 'Пресеты, история, избранные записи и приватные дорожки для старших рангов.']] },
}
const mode = computed(() => Object.hasOwn(MODES, route.query.mode) ? route.query.mode : 'duels')
const current = computed(() => MODES[mode.value])
const scope = ref(route.params.id ? 'player' : 'server'), kit = ref(''), page = ref(1)
const data = ref(null), loading = ref(false), error = ref(''), online = ref(null)
const playerId = computed(() => route.params.id || auth.user?.id)
const kitNames = ['Boxing','Sumo','BedFight','NoDebuff','Classic','Gapple','Cobweb','BuildUHC','BlockFight','Archer']
let requestId = 0
function pickMode(value) { router.replace({ name: 'duels', params: route.params.id ? {id:route.params.id} : {}, query: { ...route.query, mode: value } }) }
function pickScope(value) { scope.value = value; page.value = 1; kit.value = '' }
function duration(seconds = 0) { return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2,'0')}` }
function date(iso) { return iso ? new Date(iso).toLocaleString('ru-RU',{timeZone:'Europe/Moscow',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—' }
async function load() {
  const request = ++requestId; error.value = ''; data.value = null
  if (scope.value === 'player' && !playerId.value) { loading.value = false; return }
  loading.value = true
  try {
    const params = { page: page.value, kit: kit.value }
    const fresh = scope.value === 'server' ? await duelsApi.recent(params) : await duelsApi.player(playerId.value,params)
    if (request === requestId) data.value = fresh
  } catch(e) { if (request === requestId) error.value = e.message || 'Не удалось загрузить историю.' }
  finally { if (request === requestId) loading.value = false }
}
watch([scope,kit,page,playerId],load,{immediate:true})
watch(() => route.params.id, value => { if (value) scope.value = 'player'; page.value = 1 })
duelsApi.online().then(value => { online.value = value }).catch(() => {})
onBeforeUnmount(() => { requestId++ })
</script>

<template>
  <main class="modes-page">
    <header class="modes-hero"><div><p class="modes-eyebrow">APEX · Minecraft</p><h1>Выбирай свою игру.</h1><p class="modes-hero__lead">Поединки, командные победы и личные рекорды — на одном сервере.</p><div class="modes-hero__actions"><ServerAddress /><span v-if="online">{{ online.count }} онлайн на сервере</span></div></div><div class="modes-hero__mark" aria-hidden="true"><svg viewBox="0 0 120 120"><path d="M25 18 100 93l-8 8L17 26V17h9zm70 0L20 93l8 8 75-75V17h-9z"/><path d="m17 80 23 23M80 17l23 23"/></svg></div></header>
    <nav class="modes-tabs" aria-label="Игровой режим"><button v-for="(m,key) in MODES" :key="key" :class="{active:mode===key}" :aria-pressed="mode===key" @click="pickMode(key)"><span>{{ m.title }}</span><small>{{ m.label }}</small></button></nav>
    <section class="mode-overview"><div><p class="modes-eyebrow">{{ current.label }}</p><h2>{{ current.title }}</h2><p>{{ current.text }}</p></div><div class="mode-command"><small>Начать в игре</small><code>{{ current.command }}</code><span v-if="mode==='bedwars'">Компас → BedWars</span></div></section>
    <div class="mode-features"><article v-for="([title,text],index) in current.features" :key="title"><span class="mode-feature__number">0{{ index+1 }}</span><h3>{{ title }}</h3><p>{{ text }}</p></article></div>
    <section class="mode-rules"><div><p class="modes-eyebrow">Быстрый старт</p><h2>Как играть</h2></div><ol><li v-for="text in current.details" :key="text">{{ text }}</li></ol></section>
    <section v-if="mode==='duels'" class="mode-history">
      <header><div><p class="modes-eyebrow">После финального раунда</p><h2>История дуэлей</h2></div><button class="mode-refresh" :disabled="loading" @click="load">Обновить</button></header>
      <div class="mode-history__controls"><div><button :class="{active:scope==='server'}" @click="pickScope('server')">Матчи сервера</button><button :class="{active:scope==='player'}" @click="pickScope('player')">{{ route.params.id && route.params.id != auth.user?.id ? 'История игрока' : 'Моя история' }}</button></div><label>Набор<select v-model="kit" @change="page=1"><option value="">Все наборы</option><option v-for="name in kitNames" :key="name" :value="name.toLowerCase()">{{ name }}</option></select></label></div>
      <p v-if="scope==='player' && !playerId" class="mode-state"><RouterLink :to="{name:'login',query:{redirect:'/duels'}}">Войди в аккаунт</RouterLink>, чтобы открыть свою историю. Матчи сервера доступны всем.</p>
      <p v-if="scope==='player' && data?.player" class="mode-history__player">Игрок: <b>{{ data.player.minecraft_username || data.player.username }}</b><RouterLink v-if="!data.player.minecraft_username && data.player.id===auth.user?.id" to="/profile">Привязать Minecraft →</RouterLink></p>
      <div v-if="data?.stats" class="mode-statistics"><div><b>{{ data.stats.total.games }}</b><span>Матчей</span></div><div><b>{{ data.stats.total.wins }}</b><span>Побед</span></div><div><b>{{ data.stats.total.losses }}</b><span>Поражений</span></div><div><b>{{ data.stats.total.win_rate }}%</b><span>Побед</span></div><div><b>{{ data.stats.total.streak > 0 ? '+' : '' }}{{ data.stats.total.streak }}</b><span>Серия</span></div></div>
      <p v-if="loading" class="mode-state" role="status">Загружаю матчи…</p><div v-else-if="error" class="mode-state mode-state--error" role="alert">{{ error }} <button @click="load">Попробовать ещё</button></div>
      <template v-else-if="data"><div v-if="data.duels.length" class="mode-match-list"><article v-for="d in data.duels" :key="d.id" class="mode-match"><span class="mode-match__badge" :class="{won:scope==='player'&&d.won,lost:scope==='player'&&!d.won}">{{ scope==='player' ? (d.won?'Победа':'Поражение') : 'Матч' }}</span><div class="mode-match__players"><template v-if="scope==='server'"><RouterLink v-if="d.winner_user_id" :to="`/players/${d.winner_user_id}`">{{ d.winner }}</RouterLink><b v-else>{{ d.winner }}</b><small>победил</small><RouterLink v-if="d.loser_user_id" :to="`/players/${d.loser_user_id}`">{{ d.loser }}</RouterLink><span v-else>{{ d.loser }}</span></template><template v-else><small>Против</small><RouterLink v-if="d.opponent_user_id" :to="`/players/${d.opponent_user_id}`">{{ d.opponent }}</RouterLink><b v-else>{{ d.opponent }}</b></template></div><div class="mode-match__kit"><strong>{{ d.kit }}</strong><small>{{ d.ranked?'Рейтинговый':'Обычный' }}</small></div><span class="mode-match__duration">{{ duration(d.duration_seconds) }}</span><time>{{ date(d.played_at) }} МСК</time></article></div><p v-else class="mode-state">Завершённых дуэлей по этому фильтру пока нет. Приватные тренировки в общую историю не попадают.</p><nav v-if="data.meta.last_page>1" class="mode-pager" aria-label="Страницы истории"><button :disabled="page<=1" @click="page--">← Назад</button><span>{{ data.meta.current_page }} / {{ data.meta.last_page }} · {{ data.meta.total }} матчей</span><button :disabled="page>=data.meta.last_page" @click="page++">Дальше →</button></nav></template>
    </section>
    <section v-else class="mode-extra"><h2>{{ mode==='fastbridge'?'Разбери свою попытку':'Собери команду' }}</h2><p>{{ mode==='fastbridge'?'Открой /fb replays в игре: запись покажет движения, блоки, клики и направление взгляда. /fb history хранит сводку личных попыток.':'Создай пати командой /party invite ник. Для собственной приватной игры используй /private; её хозяину нужен APEX или LEGEND.' }}</p><p class="mode-extra__note">История дуэлей находится в разделе «Дуэли». История FastBridge доступна в игре; веб-статистика BW и FB здесь пока не публикуется.</p></section>
    <aside class="mode-fairplay"><strong>Игра решается в матче.</strong><p>Донат добавляет оформление и удобство. Урон, ресурсы, экипировка и правила обычных матчей одинаковы для всех.</p></aside>
  </main>
</template>
<style src="./DuelsView.css"></style>
