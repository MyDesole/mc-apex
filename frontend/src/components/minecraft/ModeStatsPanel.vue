<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({ mode: { type: String, required: true }, userId: { type: [Number, String], default: null } })
const auth = useAuthStore(), data = ref(null), loading = ref(false), error = ref('')
const distance = ref('32'), table = ref('current'), metric = ref('wins'), page = ref(1)
const target = computed(() => props.userId || auth.user?.id)
const own = computed(() => !!auth.user?.id && (!props.userId || String(props.userId) === String(auth.user.id)))
const fb = computed(() => props.mode === 'fastbridge')
const n = value => Number(value ?? 0).toLocaleString('ru-RU')
const result = value => value == null || value < 0 ? '—' : distance.value === 'inf' ? `${n(value)} бл.` : `${(value / 1000).toFixed(3)} с`
const cps = value => value == null ? '—' : Number(value).toFixed(1)
const when = value => value ? new Date(value).toLocaleString('ru-RU', { timeZone: 'Europe/Moscow', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'
const states = { waiting: 'Ожидание', starting: 'Начинается', playing: 'Идёт игра', restarting: 'Перезапуск' }
const numbers = computed(() => fb.value ? [ ['Игроков',data.value?.summary.players], ['Рекордов',data.value?.summary.records], ['Попыток за 24 ч',data.value?.summary.attempts24], ['Финишей за 24 ч',data.value?.summary.finishes24] ] : [ ['Игроков',data.value?.summary.players], ['Кроватей разрушено',data.value?.summary.beds], ['Финальных убийств',data.value?.summary.final_kills], ['Арен',data.value?.summary.arenas] ])
let version = 0
async function load() {
  const request = ++version; loading.value = true; error.value = ''
  try {
    const fresh = await api.get(`/minecraft/modes/${props.mode}`, { params: { distance: distance.value, table: table.value, metric: metric.value, page: page.value, user: target.value } })
    if (request === version) data.value = fresh
  } catch(e) { if (request === version) error.value = e.message || 'Не удалось загрузить статистику.' }
  finally { if (request === version) loading.value = false }
}
watch([() => props.mode,distance,table,metric,target],() => { data.value=null;page.value=1;load() },{immediate:true})
watch(page,() => { data.value=null;load() })
let refresh = 0
const timer = setInterval(() => { if (!data.value?.configured || ++refresh%6===0) load() },5000)
onBeforeUnmount(() => { version++; clearInterval(timer) })
</script>

<template>
  <section class="content-card mode-data">
    <header class="mode-section-head"><h2>{{ fb ? 'Рекорды FastBridge' : 'Статистика BedWars' }}</h2><button class="mode-refresh" :disabled="loading" @click="load">Обновить</button></header>
    <div class="mode-filters">
      <template v-if="fb"><label>Дистанция<select v-model="distance"><option value="16">16 блоков</option><option value="24">24 блока</option><option value="32">32 блока</option><option value="64">64 блока</option><option value="inf">Бесконечно</option></select></label><label>Таблица<select v-model="table"><option v-for="entry in data?.tables?.length ? data.tables : [{key:'current',label:'Новая карта · золотые плиты'}]" :key="entry.key" :value="entry.key">{{ entry.label }}{{ entry.records != null ? ` · ${n(entry.records)} рек.` : '' }}</option></select></label></template>
      <label v-else>Рейтинг по<select v-model="metric"><option value="wins">Победам</option><option value="final_kills">Финальным убийствам</option><option value="beds">Разрушенным кроватям</option></select></label>
    </div>
    <p v-if="error" class="mode-state mode-state--error" role="alert">{{ error }} <button @click="load">Попробовать ещё</button></p>
    <p v-else-if="loading && !data" class="mode-state" role="status">Загружаю данные сервера…</p>
    <p v-else-if="data && !data.configured" class="mode-state" role="status">Статистика синхронизируется с сервером. Обновление появится автоматически.</p>
    <template v-else-if="data?.configured">
      <div class="mode-numbers"><div v-for="[label,value] in numbers" :key="label"><strong>{{ n(value) }}</strong><span>{{ label }}</span></div></div>
      <p v-if="fb && table!=='current'" class="mode-note">Архив предыдущей версии карты. Его результаты отделены от рекордов с финишем по золотым плитам.</p>
      <p v-else-if="fb" class="mode-note">{{ distance==='inf' ? 'Чем больше блоков до падения, тем выше результат.' : 'Чем меньше время до нажатия золотой плиты, тем выше результат.' }} Старые рекорды доступны в списке «Таблица».</p>
      <div v-if="data.personal" class="mode-personal"><strong>{{ own ? 'Твой результат' : data.personal.name }}</strong><template v-if="fb"><span>Рекорд: <b>{{ result(data.personal.best) }}</b></span><span>{{ n(data.personal.attempts24) }} попыток за сутки</span></template><template v-else><span>Побед: <b>{{ n(data.personal.wins) }}</b></span><span>Игр: <b>{{ n(data.personal.games) }}</b></span><span>Кроватей: <b>{{ n(data.personal.beds) }}</b></span></template></div>
      <div v-if="data.players.length" class="mode-table-wrap"><table class="mode-table"><thead><tr><th>#</th><th>Игрок</th><template v-if="fb"><th>Рекорд</th><th>Попытки за 24 ч</th><th>Финиши за 24 ч</th></template><template v-else><th>Победы</th><th>Финал. убийства</th><th>Кровати</th><th>Игры</th></template></tr></thead><tbody><tr v-for="p in data.players" :key="p.place"><td class="mode-place">{{ p.place }}</td><td><RouterLink v-if="p.user_id" :to="{name:'duels',params:{id:p.user_id},query:{mode}}">{{ p.name }}</RouterLink><strong v-else>{{ p.name }}</strong></td><template v-if="fb"><td class="mode-result">{{ result(p.best) }}</td><td>{{ n(p.attempts24) }}</td><td>{{ n(p.finishes24) }}</td></template><template v-else><td class="mode-result">{{ n(p.wins) }}</td><td>{{ n(p.final_kills) }}</td><td>{{ n(p.beds) }}</td><td>{{ n(p.games) }}</td></template></tr></tbody></table></div>
      <p v-else class="mode-state">{{ fb ? 'На этой дистанции ещё нет завершённых рекордов. Выбери другую дистанцию или открой архив.' : 'Сыгранных матчей BedWars в статистике пока нет.' }}</p>
      <nav v-if="data.meta.last_page>1" class="mode-pager" aria-label="Страницы рейтинга"><button :disabled="page<=1" @click="page--">← Назад</button><span>{{ data.meta.current_page }} / {{ data.meta.last_page }}</span><button :disabled="page>=data.meta.last_page" @click="page++">Дальше →</button></nav>
      <p class="mode-updated" :class="{'mode-updated--stale':data.stale}">{{ data.stale ? 'Последние доступные данные' : 'Обновлено' }} {{ when(data.updated_at) }} МСК · приватные матчи вне рейтинга</p>
    </template>
  </section>
  <section v-if="!fb && data?.configured" class="content-card mode-data"><header class="mode-section-head"><h2>Карты и текущие игры</h2><span class="mode-note">{{ n(data.summary.online) }} игроков в открытых аренах</span></header><div v-if="data.arenas.length" class="mode-table-wrap"><table class="mode-table"><thead><tr><th>Карта</th><th>Команды</th><th>Игроки</th><th>Состояние</th></tr></thead><tbody><tr v-for="arena in data.arenas" :key="arena.id"><td><strong>{{ arena.name }}</strong></td><td>{{ arena.teams }} × {{ arena.team_size }}</td><td>{{ arena.players }} / {{ arena.max_players }}</td><td><span class="mode-arena-state" :class="{'mode-arena-state--playing':arena.state==='playing'}">{{ states[arena.state] }}</span></td></tr></tbody></table></div><p v-else class="mode-state">Сейчас нет открытых арен. Приватные игры в этом списке не показываются.</p></section>
  <section v-if="fb" class="content-card mode-data"><h2>{{ props.userId && !own ? 'История попыток' : 'Мои последние попытки' }}</h2><p v-if="!auth.user" class="mode-note"><RouterLink :to="{name:'login',query:{redirect:'/duels?mode=fastbridge'}}">Войди в аккаунт</RouterLink>, чтобы увидеть свои попытки, время, блоки и клики.</p><p v-else-if="props.userId && !own" class="mode-note">Подробная история доступна самому игроку. Публичные рекорды показаны выше.</p><template v-else-if="data?.history?.length"><div class="mode-table-wrap"><table class="mode-table"><thead><tr><th>Когда</th><th>Итог</th><th>Время</th><th>Блоки</th><th>ПКМ / ЛКМ</th></tr></thead><tbody><tr v-for="(h,i) in data.history" :key="`${h.at}-${i}`"><td>{{ when(h.at) }}</td><td>{{ h.outcome }}</td><td>{{ (h.elapsed/1000).toFixed(3) }} с</td><td>{{ h.blocks ?? '—' }}</td><td>{{ cps(h.right_cps) }} / {{ cps(h.left_cps) }}</td></tr></tbody></table></div><p class="mode-note">Последние сохранённые публичные попытки этой дистанции, включая прежние версии карты. Приватные записи остаются в игре.</p></template><p v-else class="mode-note">Сохранённых публичных попыток на этой дистанции пока нет. Полная игровая история — <code>/fb history</code>, реплеи — <code>/fb replays</code>.</p></section>
</template>
