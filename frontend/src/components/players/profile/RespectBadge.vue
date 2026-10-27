<script setup>
/**
 * Респекты в шапке профиля.
 *
 * Гость: огненная кнопка «Респект» (раз в день) — вспышка пламени и +1.
 * Хозяин: счётчик и место в топе недели; по клику — карточка с теми, кто
 * дал респект, и гостями профиля за неделю.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import UserName from '@/components/players/UserName.vue'
import AppIcon from '@/components/core/AppIcon.vue'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  user: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})

const auth = useAuthStore()

const data = ref(null)
const busy = ref(false)
const error = ref('')
const flames = ref([])
const popped = ref(false)

const panelOpen = ref(false)
const panelPos = ref({ left: 0, top: 0 })
const guests = ref(null)
const btnEl = ref(null)

let flameId = 0

async function load() {
  try {
    data.value = await api.get(`/players/${props.user.id}/respects`)
  } catch {
    data.value = null
  }
}

function burst() {
  const pack = Array.from({ length: 12 }, () => ({
    id: ++flameId,
    dx: `${Math.round(Math.random() * 90 - 45)}px`,
    dy: `${Math.round(-40 - Math.random() * 50)}px`,
    rot: `${Math.round(Math.random() * 60 - 30)}deg`,
    icon: ['flame', 'flame', 'sparkles', 'bolt'][Math.floor(Math.random() * 4)],
  }))
  flames.value.push(...pack)
  popped.value = true
  setTimeout(() => {
    flames.value = flames.value.filter((f) => !pack.includes(f))
    popped.value = false
  }, 1100)
}

async function give() {
  if (busy.value || data.value?.given_today) return

  if (!auth.isAuthenticated) {
    error.value = 'Войди, чтобы дать респект'
    setTimeout(() => { error.value = '' }, 2500)
    return
  }

  busy.value = true
  try {
    data.value = await api.post(`/players/${props.user.id}/respects`)
    burst()
  } catch (e) {
    error.value = e.message || 'Не получилось'
    setTimeout(() => { error.value = '' }, 2500)
  } finally {
    busy.value = false
  }
}

async function openPanel() {
  const rect = btnEl.value?.getBoundingClientRect()
  if (rect) {
    panelPos.value = {
      left: Math.min(window.innerWidth - 170, Math.max(170, rect.left + rect.width / 2)),
      top: rect.bottom + 10,
    }
  }
  panelOpen.value = !panelOpen.value

  if (panelOpen.value && props.editable && !guests.value) {
    try {
      guests.value = await api.get('/players/me/guests')
    } catch {
      guests.value = { guests: [], week_unique: 0 }
    }
  }
}

function ago(iso) {
  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (minutes < 1) return 'только что'
  if (minutes < 60) return `${minutes} мин назад`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} ч назад`
  return `${Math.round(hours / 24)} дн назад`
}

function onKey(event) {
  if (event.key === 'Escape') panelOpen.value = false
}

onMounted(async () => {
  await load()

  // Гость зашёл в чужой профиль — хозяин увидит его среди гостей
  if (auth.isAuthenticated && auth.user?.id !== props.user.id) {
    api.post(`/players/${props.user.id}/visit`).catch(() => {})
  }

  window.addEventListener('keydown', onKey)
})

onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div v-if="data" class="respect">
    <button
        ref="btnEl"
        type="button"
        class="respect__btn"
        :class="{
          'is-given': data.given_today,
          'is-owner': !data.can_give,
          'is-pop': popped,
        }"
        :disabled="busy"
        :title="data.can_give ? (data.given_today ? 'Респект на сегодня дан — приходи завтра' : 'Дать респект') : 'Твои респекты и гости профиля'"
        @click="data.can_give ? (data.given_today ? openPanel() : give()) : openPanel()"
    >
      <span class="respect__fire"><AppIcon icon="flame" :size="16" /></span>
      <b>{{ data.total }}</b>
      <span v-if="data.can_give && !data.given_today" class="respect__cta">Респект</span>
      <span v-else-if="data.can_give" class="respect__done"><AppIcon icon="check" :size="14" /></span>
      <span v-if="data.rank && data.rank <= 10" class="respect__rank">#{{ data.rank }} недели</span>

      <span
          v-for="f in flames"
          :key="f.id"
          class="respect__flame"
          :style="{ '--dx': f.dx, '--dy': f.dy, '--rot': f.rot }"
      ><AppIcon :icon="f.icon" :size="16" /></span>
      <span v-if="popped" class="respect__plus">+1</span>
    </button>

    <span v-if="error" class="respect__error">{{ error }}</span>

    <!-- Карточка: кто дал респект и гости профиля -->
    <Teleport to="body">
      <div v-if="panelOpen" class="respect-backdrop" @click="panelOpen = false"></div>
      <div
          v-if="panelOpen"
          class="respect-panel"
          :style="{ left: `${panelPos.left}px`, top: `${panelPos.top}px` }"
      >
        <header class="respect-panel__head">
          <span class="respect-panel__fire"><AppIcon icon="flame" :size="26" /></span>
          <div>
            <strong>{{ data.total }} респектов</strong>
            <small>{{ data.week }} за неделю<template v-if="data.rank"> · #{{ data.rank }} в топе недели</template></small>
          </div>
        </header>

        <h4>Дали респект</h4>
        <p v-if="!data.recent.length" class="respect-panel__empty">Пока никто — поделись профилем с друзьями.</p>
        <RouterLink
            v-for="p in data.recent"
            :key="`r-${p.id}`"
            :to="userLink(p)"
            class="respect-panel__person"
            @click="panelOpen = false"
        >
          <span class="respect-panel__avatar">
            <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
            <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
          </span>
          <UserName :user="p" />
        </RouterLink>

        <template v-if="editable">
          <h4>Гости за неделю <b v-if="guests">{{ guests.week_unique }}</b></h4>
          <p v-if="!guests" class="respect-panel__empty">Загружаем…</p>
          <p v-else-if="!guests.guests.length" class="respect-panel__empty">Пока никто не заходил.</p>
          <RouterLink
              v-for="p in guests?.guests || []"
              :key="`g-${p.id}`"
              :to="userLink(p)"
              class="respect-panel__person"
              @click="panelOpen = false"
          >
            <span class="respect-panel__avatar">
              <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
              <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
            </span>
            <UserName :user="p" />
            <small>{{ ago(p.last_at) }}</small>
          </RouterLink>
        </template>

        <RouterLink to="/respects" class="respect-panel__top" @click="panelOpen = false"><AppIcon icon="trophy" :size="15" /> Топ уважаемых недели →</RouterLink>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.respect {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.respect__btn {
  position: relative;

  display: inline-flex;
  align-items: center;
  gap: 6px;

  min-height: 34px;
  padding: 0 14px 0 10px;

  color: #fff;
  background: linear-gradient(135deg, rgba(249, 115, 22, 0.35), rgba(239, 68, 68, 0.25));
  border: 1px solid rgba(251, 146, 60, 0.6);
  border-radius: 999px;
  box-shadow: 0 0 18px rgba(249, 115, 22, 0.25);
  backdrop-filter: blur(10px);

  font: inherit;
  font-size: 13px;
  font-weight: 800;

  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.respect__btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 0 26px rgba(249, 115, 22, 0.45);
}

.respect__btn.is-given,
.respect__btn.is-owner {
  background: rgba(15, 12, 24, 0.55);
  border-color: rgba(251, 146, 60, 0.4);
  box-shadow: none;
}

.respect__btn.is-pop {
  animation: respect-pop 0.5s cubic-bezier(0.3, 1.6, 0.5, 1);
}

@keyframes respect-pop {
  40% { transform: scale(1.2); }
}

.respect__fire {
  display: inline-block;
  animation: respect-flicker 1.6s ease-in-out infinite;
}

@keyframes respect-flicker {
  50% { transform: scale(1.12) rotate(-6deg); }
}

.respect__cta {
  padding-left: 6px;
  border-left: 1px solid rgba(255, 255, 255, 0.25);
}

.respect__done {
  color: #4ade80;
}

.respect__rank {
  padding: 1px 7px;

  color: #1c1917;
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border-radius: 999px;

  font-size: 10px;
  font-weight: 900;
}

.respect__flame {
  position: absolute;
  left: 18px;
  top: 4px;

  font-size: 16px;
  pointer-events: none;

  animation: respect-flame 1s ease-out forwards;
}

@keyframes respect-flame {
  0% { opacity: 1; transform: translate(0, 0) scale(0.6) rotate(0); }
  100% { opacity: 0; transform: translate(var(--dx), var(--dy)) scale(1.3) rotate(var(--rot)); }
}

.respect__plus {
  position: absolute;
  right: 6px;
  top: -6px;

  color: #fde047;

  font-family: var(--font-display);
  font-size: 18px;
  font-weight: 900;
  text-shadow: 0 0 10px rgba(253, 224, 71, 0.8);
  pointer-events: none;

  animation: respect-plus 1s ease-out forwards;
}

@keyframes respect-plus {
  from { opacity: 1; transform: translateY(0); }
  to { opacity: 0; transform: translateY(-30px); }
}

.respect__error {
  color: #fca5a5;
  font-size: 12px;
  font-weight: 700;
}

@media (prefers-reduced-motion: reduce) {
  .respect *,
  .respect__btn {
    animation: none !important;
  }
}
</style>

<style>
.respect-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1190;
}

.respect-panel {
  position: fixed;
  z-index: 1200;

  width: 320px;
  max-height: min(70vh, 520px);
  overflow-y: auto;

  padding: 14px;

  color: #e6e3ee;
  background: rgba(20, 17, 30, 0.97);
  border: 1px solid rgba(251, 146, 60, 0.35);
  border-radius: 18px;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(14px);

  transform: translateX(-50%);
}

.respect-panel__head {
  display: flex;
  align-items: center;
  gap: 10px;

  padding-bottom: 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.respect-panel__fire {
  font-size: 30px;
}

.respect-panel__head div {
  display: flex;
  flex-direction: column;
}

.respect-panel__head strong {
  color: #fff;
  font-size: 16px;
}

.respect-panel__head small {
  color: #fdba74;
  font-size: 12px;
}

.respect-panel h4 {
  margin: 12px 0 6px;

  color: #8a8598;

  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.respect-panel h4 b {
  margin-left: 4px;
  color: #fff;
}

.respect-panel__empty {
  margin: 0;
  color: #8a8598;
  font-size: 12px;
}

.respect-panel__person {
  display: flex;
  align-items: center;
  gap: 8px;

  padding: 5px 6px;

  color: #fff;
  border-radius: 10px;
  text-decoration: none;

  font-size: 13px;
  font-weight: 700;
}

.respect-panel__person:hover {
  background: rgba(255, 255, 255, 0.05);
}

.respect-panel__person small {
  margin-left: auto;
  color: #8a8598;
  font-size: 11px;
  font-weight: 600;
}

.respect-panel__avatar {
  width: 26px;
  height: 26px;
  flex-shrink: 0;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  background: linear-gradient(135deg, #7c3aed, #4c1d95);
  border-radius: 8px;

  font-size: 11px;
  font-weight: 900;
}

.respect-panel__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.respect-panel__top {
  display: block;

  margin-top: 12px;
  padding: 9px 12px;

  color: #1c1917;
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border-radius: 12px;

  font-size: 13px;
  font-weight: 900;
  text-align: center;
  text-decoration: none;
}
</style>

<style>
/* Векторные иконки респектов: огонь — тёплым градиентом */
.respect__fire svg,
.respect-panel__fire svg,
.respect__flame svg {
  color: #fb923c;
  filter: drop-shadow(0 0 6px rgba(249, 115, 22, 0.6));
}

.respect__flame {
  color: #fdba74;
}

.respect__done {
  display: inline-flex;
}

.respect-panel__fire {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 44px;
  height: 44px;

  background: radial-gradient(circle, rgba(249, 115, 22, 0.3), rgba(249, 115, 22, 0.05));
  border-radius: 14px;
}

.respect-panel__top {
  display: flex !important;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
</style>
