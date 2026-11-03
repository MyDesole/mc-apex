<script setup>
/**
 * Рейд «Разлом»: два окна в день (18:00 и 22:00). Собираешь группу,
 * выбираешь отряд из трёх питомцев и бьёшься с боссом: раунд 30 секунд,
 * приёмы выбирают все разом. Броня босса пробивается только связкой
 * стихий от разных игроков. Сервер считает всё, здесь опрос раз в 2,5 с.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import PetFigure from '@/components/pets/PetFigure.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import { ELEMENTS } from '@/data/players/petArena.js'
import { PET_RARITY } from '@/data/players/petTraits.js'
import { useNow } from '@/composables/core/useNow.js'

const auth = useAuthStore()
const now = useNow()

const state = ref(null)
const pets = ref([])
const picked = ref([])
const choice = ref({})
const error = ref('')
const busy = ref(false)
let timer = null

const MOVES = [['attack', 'Атака', '#a83232'], ['skill', 'Умение', '#7a2fb8'], ['defend', 'Защита', '#2f5fa8']]

const raid = computed(() => state.value?.mine ?? null)
const inFight = computed(() => raid.value?.status === 'active')
const inLobby = computed(() => raid.value?.status === 'lobby')
const finished = computed(() => raid.value && ['won', 'lost'].includes(raid.value.status))
const me = computed(() => raid.value?.members.find((m) => m.me) ?? null)
const free = computed(() => pets.value.filter((p) => !p.busy && p.level >= (state.value?.min_level ?? 30)))
const bossPct = computed(() => (raid.value ? Math.round((raid.value.boss.hp / Math.max(1, raid.value.boss.max)) * 100) : 0))
const secondsLeft = computed(() => (raid.value?.round_ends_at ? Math.max(0, Math.ceil((new Date(raid.value.round_ends_at).getTime() - now.value) / 1000)) : 0))
const timerPct = computed(() => Math.min(100, (secondsLeft.value / (state.value?.rules?.round_seconds ?? 30)) * 100))
const submitted = computed(() => !!raid.value?.my_moves || me.value?.ready)
const aliveMine = computed(() => (me.value?.units ?? []).filter((u) => u.hp > 0))
const pct = (u) => Math.max(0, Math.round((u.hp / Math.max(1, u.max)) * 100))

async function load() {
  try {
    state.value = await api.get('/raids')
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не удалось связаться с Разломом.'
  }
}

async function loadPets() {
  try {
    pets.value = (await api.get('/pets')).pets ?? []
  } catch {
    pets.value = []
  }
}

async function act(call, ok) {
  busy.value = true
  error.value = ''
  try {
    const result = await call()
    if (ok) ok(result)
    await load()
    await loadPets()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
    await load()
  } finally {
    busy.value = false
  }
}

function toggle(id) {
  picked.value = picked.value.includes(id) ? picked.value.filter((x) => x !== id) : [...picked.value.slice(-2), id]
}

const create = () => act(() => api.post('/raids', { squad: picked.value }), () => { picked.value = [] })
const join = (lobby) => act(() => api.post(`/raids/${lobby.id}/join`, { squad: picked.value }), () => { picked.value = [] })
const start = () => act(() => api.post(`/raids/${raid.value.id}/start`))
const leave = () => act(() => api.post(`/raids/${raid.value.id}/leave`))
const send = () => act(() => api.post(`/raids/${raid.value.id}/moves`, { moves: Object.fromEntries(aliveMine.value.map((u) => [u.id, choice.value[u.id] ?? 'attack'])) }))

// Новый раунд — приёмы по умолчанию «атака»
watch(() => raid.value?.round, () => { choice.value = {} })

onMounted(() => {
  load()
  loadPets()
  timer = setInterval(load, 2500)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="g-scene rd">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>

    <!-- Разлом спит -->
    <section v-if="state && !state.slot && !raid" class="rd-sleep g-dark">
      <PixelSprite sprite="boss-bottom" :size="120" anim="glow" glow="rgba(167,139,250,.8)" />
      <h2 class="g-title">Разлом спит</h2>
      <p class="g-hint">Откроется в {{ state.slots.join(' и ') }}. Ближайшее окно — через <b><CountdownText :to="state.next_slot" /></b>.</p>
      <ul class="rd-rules">
        <li>Группа 2–3 игрока, у каждого отряд из 3 питомцев (от {{ state.min_level }} ур.).</li>
        <li>Раунд {{ state.rules.round_seconds }} секунд: все выбирают приёмы одновременно.</li>
        <li>Броня босса держит 85% урона, пока в раунде не ударят разные стихии от разных игроков.</li>
        <li>Награда: ApexCoin, легендарная вещь и Осколки Разлома для Лавки.</li>
        <li>Выход из боя или молчание два раунда: бан рейдов на {{ state.rules.ban_hours }} ч, питомцы измотаны, босс сильнее.</li>
      </ul>
      <span class="g-chip g-chip--gold">Осколков Разлома: {{ state.shards }}</span>
    </section>

    <p v-if="state?.banned_until" class="g-msg g-msg--err">Рейды закрыты до <CountdownText :to="state.banned_until" done="сейчас" />: ты бросил группу.</p>

    <!-- Окно открыто, группы нет -->
    <section v-if="state?.slot && !raid" class="rd-gather">
      <div class="rd-gather__top g-wood">
        <h2 class="g-title">Разлом открыт</h2>
        <span class="g-chip">закроется через <b><CountdownText :to="state.slot_ends_at" done="сейчас" /></b></span>
      </div>

      <h3 class="g-hint">Выбери отряд: три питомца ({{ picked.length }}/3)</h3>
      <div class="g-hotbar">
        <button v-for="p in free" :key="p.inventory_id" type="button" class="g-slot" :class="{ 'is-on': picked.includes(p.inventory_id) }"
                :style="{ '--rar': PET_RARITY[p.traits?.rarity]?.color }" :title="`${p.name} · ${ELEMENTS[p.element]?.name} · ур. ${p.level}`" @click="toggle(p.inventory_id)">
          <PetFigure :species="p.species" :traits="p.traits" :size="34" :backdrop="false" /><i class="g-slot__tag">{{ p.level }}</i>
        </button>
        <span v-if="!free.length" class="g-hint">Нет свободных питомцев от {{ state.min_level }} уровня.</span>
      </div>

      <div class="rd-gather__act">
        <button type="button" class="g-btn g-btn--go" :disabled="busy || picked.length !== 3" @click="create">Создать группу</button>
      </div>

      <h3 class="g-hint">Открытые группы</h3>
      <p v-if="!state.lobbies.length" class="g-hint">Пока никого. Создай группу — остальные подключатся.</p>
      <div v-for="l in state.lobbies" :key="l.id" class="rd-lobby g-stone">
        <span><b>{{ l.host }}</b><small>{{ l.members }} из 3</small></span>
        <button type="button" class="g-btn g-btn--sm g-btn--gold" :disabled="busy || picked.length !== 3" @click="join(l)">Присоединиться</button>
      </div>
    </section>

    <!-- Набор группы -->
    <section v-if="inLobby" class="rd-lobbyroom">
      <h2 class="g-title">Группа собирается</h2>
      <div class="rd-members">
        <article v-for="m in raid.members" :key="m.user.id" class="rd-member g-stone">
          <b>{{ m.user.username }}<template v-if="m.me"> (ты)</template></b>
          <span class="rd-squad"><PetFigure v-for="u in m.units" :key="u.id" :species="u.species" :traits="u.traits" :size="40" :backdrop="false" /></span>
        </article>
      </div>
      <div class="rd-gather__act">
        <button v-if="raid.host" type="button" class="g-btn g-btn--go" :disabled="busy || raid.members.length < 2" @click="start">Начать бой ({{ raid.members.length }} из 3)</button>
        <p v-else class="g-hint">Ждём, пока хозяин начнёт бой.</p>
        <button type="button" class="g-btn g-btn--red" :disabled="busy" @click="leave">Выйти из группы</button>
      </div>
    </section>

    <!-- Бой -->
    <section v-if="inFight || finished" class="rd-battle">
      <div class="rd-boss">
        <PixelSprite sprite="boss-bottom" :size="finished && raid.status === 'won' ? 90 : 130" :anim="finished ? null : 'glow'" glow="rgba(167,139,250,.8)" />
        <div class="rd-boss__bar">
          <b>{{ raid.boss.name }}</b>
          <span class="g-bar" :style="{ '--v': bossPct + '%', '--c': '#a855f7' }"><i /></span>
          <small>{{ raid.boss.hp.toLocaleString('ru-RU') }} / {{ raid.boss.max.toLocaleString('ru-RU') }}</small>
        </div>
        <p v-if="raid.deserters" class="rd-warn">Босс усилен ливерами: +{{ raid.deserters * 15 }}%</p>
      </div>

      <div v-if="inFight" class="rd-clock">
        <span>Раунд {{ raid.round }}</span>
        <div class="g-bar" :style="{ '--v': timerPct + '%', '--c': secondsLeft < 8 ? '#ef4444' : '#4ade80' }"><i /></div>
        <b>{{ secondsLeft }} с</b>
      </div>

      <p v-if="raid.last_round" class="rd-last" :class="raid.last_round.synergy ? 'is-sync' : 'is-armor'">
        Раунд {{ raid.last_round.round }}: {{ raid.last_round.synergy ? 'броня пробита связкой' : 'броня держится — нужны разные стихии от разных игроков' }} · урон {{ raid.last_round.dealt.toLocaleString('ru-RU') }}
      </p>

      <!-- Отряды -->
      <div class="rd-teams">
        <article v-for="m in raid.members" :key="m.user.id" class="rd-team g-stone" :class="{ 'is-left': m.left, 'is-me': m.me }">
          <header><b>{{ m.user.username }}</b><i v-if="m.left">сбежал</i><i v-else-if="m.ready && inFight">готов</i></header>
          <div class="rd-units">
            <div v-for="u in m.units" :key="u.id" class="rd-unit" :class="{ 'is-down': u.hp <= 0 }" :style="{ '--el': ELEMENTS[u.element]?.color }">
              <PetFigure :species="u.species" :traits="u.traits" :size="46" :backdrop="false" />
              <span class="g-bar" :style="{ '--v': pct(u) + '%', '--c': pct(u) > 40 ? '#4ade80' : '#f87171' }"><i /></span>
              <small>{{ ELEMENTS[u.element]?.name }}</small>
              <div v-if="m.me && inFight && u.hp > 0 && !submitted" class="rd-moves">
                <button v-for="[key, label, color] in MOVES" :key="key" type="button" class="rd-move" :class="{ 'is-on': (choice[u.id] ?? 'attack') === key }" :style="{ '--c': color }"
                        :disabled="key === 'skill' && u.cd > 0" :title="key === 'skill' && u.cd > 0 ? `перезарядка ${u.cd}` : label" @click="choice[u.id] = key">{{ label }}</button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <div v-if="inFight" class="rd-send">
        <button v-if="!submitted" type="button" class="g-btn g-btn--go" :disabled="busy || !aliveMine.length" @click="send">Подтвердить ход</button>
        <span v-else class="g-chip">Ход принят — ждём остальных или конец таймера</span>
        <button type="button" class="g-btn g-btn--red g-btn--sm" :disabled="busy" title="Выход из боя — бан рейдов на 6 часов" @click="leave">Покинуть бой</button>
      </div>

      <div v-if="finished" class="rd-result g-wood">
        <h3 class="g-title">{{ raid.status === 'won' ? 'Разлом закрыт!' : 'Отряд пал' }}</h3>
        <p v-if="raid.won" class="g-hint">Награда: ₳ {{ raid.won.coins.toLocaleString('ru-RU') }}, {{ raid.won.shards }} Осколков Разлома и легендарная вещь (смотри в Складе).</p>
        <p v-else class="g-hint">Босс устоял. Следующее окно — через <CountdownText :to="state.next_slot" />.</p>
      </div>

      <ul class="rd-log"><li v-for="(line, i) in raid.log" :key="i">{{ line }}</li></ul>
    </section>
  </div>
</template>

<style scoped>
.rd-sleep { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 22px; text-align: center; }
.rd-rules { max-width: 560px; margin: 0; padding-left: 20px; text-align: left; font-size: 13px; line-height: 1.6; color: #e8dcc0; }
.rd-gather__top { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; margin-bottom: 8px; }
.rd-gather__act { display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0; }
.rd-lobby { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; padding: 8px 12px; }
.rd-lobby small { display: block; font-size: 11px; color: #c9b9e8; }
.rd-members { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; margin: 8px 0; }
.rd-member { padding: 8px 10px; }
.rd-squad { display: flex; gap: 4px; margin-top: 6px; }
.rd-boss { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 10px; background: radial-gradient(50% 70% at 50% 40%, rgba(124, 58, 237, 0.35), transparent 70%), #130d22; border: 4px solid #1b0f06; }
.rd-boss__bar { display: grid; width: min(420px, 100%); gap: 4px; text-align: center; font-size: 13px; }
.rd-boss__bar .g-bar { height: 16px; }
.rd-warn { margin: 0; font-size: 12px; font-weight: 800; color: #fca5a5; }
.rd-clock { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; margin: 10px 0; font-size: 13px; }
.rd-last { margin: 8px 0; padding: 6px 10px; font-size: 12px; font-weight: 800; background: rgba(0, 0, 0, 0.4); border: 3px solid #1b0f06; }
.rd-last.is-sync { color: #86efac; border-color: #2f6b2f; }
.rd-last.is-armor { color: #fca5a5; }
.rd-teams { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 8px; }
.rd-team { padding: 8px 10px; }
.rd-team.is-me { box-shadow: inset 0 0 0 3px #4a4058, 0 0 0 3px #ffd45c; }
.rd-team.is-left { opacity: 0.5; }
.rd-team header { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
.rd-team header i { font-style: normal; font-size: 11px; font-weight: 800; color: #86efac; }
.rd-team.is-left header i { color: #fca5a5; }
.rd-units { display: flex; gap: 6px; justify-content: space-around; }
.rd-unit { display: flex; flex-direction: column; align-items: center; gap: 3px; width: 66px; }
.rd-unit.is-down { filter: grayscale(1) brightness(0.5); }
.rd-unit .g-bar { width: 100%; height: 7px; }
.rd-unit small { font-size: 10px; color: var(--el); }
.rd-moves { display: flex; flex-direction: column; gap: 2px; width: 100%; }
.rd-move { padding: 2px 0; font: inherit; font-size: 10px; font-weight: 800; color: #fff; cursor: pointer; background: #20150c; border: 2px solid var(--c); }
.rd-move.is-on { background: var(--c); }
.rd-move:disabled { opacity: 0.4; cursor: not-allowed; }
.rd-send { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin: 10px 0; }
.rd-result { margin-top: 10px; padding: 12px; text-align: center; }
.rd-log { margin: 10px 0 0; padding: 8px 12px; max-height: 160px; overflow-y: auto; list-style: none; font-size: 11px; line-height: 1.6; color: #c9b9e8; background: #130d22; border: 3px solid #1b0f06; }
</style>
