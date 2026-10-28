<script setup>
/**
 * Открытый лот: питомец крупно, черты с шансами, уровень, ставка,
 * история ставок и живой чат лота. Ставки и сообщения приходят сразу
 * через Reverb; если сокет лежит — опрос раз в несколько секунд.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { PET_RARITY, chanceLabel } from '@/data/players/petTraits.js'
import { useLiveChannel } from '@/composables/shop/useLiveChannel.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  lot: { type: Object, required: true },
  now: { type: Number, required: true },
})

const emit = defineEmits(['close', 'changed'])

const auth = useAuthStore()

const bids = ref([])
const messages = ref([])
const draft = ref('')
const amount = ref(props.lot.min_bid)
const busy = ref(false)
const sending = ref(false)
const error = ref('')
const chatError = ref('')
const feed = ref(null)

const traits = computed(() => props.lot.pet?.pet ?? null)
const rarity = computed(() => PET_RARITY[traits.value?.rarity] ?? { label: 'Питомец', color: '#a8a3b8' })
const mine = computed(() => auth.user?.id && props.lot.seller?.id === auth.user.id)
const leading = computed(() => auth.user?.id && props.lot.leader?.id === auth.user.id)
const active = computed(() => props.lot.status === 'active')
const canModerate = computed(() => ['admin', 'moderator'].includes(auth.user?.role))

const msLeft = computed(() => new Date(props.lot.ends_at).getTime() - props.now)
const timer = computed(() => {
  const ms = msLeft.value
  if (ms <= 0) return 'завершается'
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин ${s} с` : `${m}:${String(s).padStart(2, '0')}`
})

const TRAIT_ROWS = [['model', 'Окрас'], ['backdrop', 'Фон'], ['accessory', 'Аксессуар']]

function coins(value) {
  return Number(value || 0).toLocaleString('ru-RU')
}

function time(iso) {
  return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

async function loadLot() {
  try {
    const data = await api.get(`/auctions/${props.lot.id}`)
    bids.value = data.bids ?? []
    emit('changed', data)
  } catch {
    /* лот могли закрыть — список обновится сам */
  }
}

async function loadMessages() {
  try {
    const data = await api.get(`/auctions/${props.lot.id}/messages`)
    const stick = nearBottom()
    messages.value = data.messages ?? []
    if (stick) scrollDown()
  } catch {
    /* не критично */
  }
}

function nearBottom() {
  const el = feed.value
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80
}

function scrollDown() {
  nextTick(() => {
    if (feed.value) feed.value.scrollTop = feed.value.scrollHeight
  })
}

function addMessage(message) {
  if (!message || messages.value.some((m) => m.id === message.id)) return
  const stick = nearBottom() || message.user?.id === auth.user?.id
  messages.value = [...messages.value, message].slice(-100)
  if (stick) scrollDown()
}

const { live, subscribe } = useLiveChannel({
  'auction.lot': (payload) => {
    if (payload?.lot) emit('changed', payload.lot)
    loadLot()
  },
  'auction.message': (payload) => addMessage(payload?.message),
  'auction.message_deleted': (payload) => {
    messages.value = messages.value.filter((m) => m.id !== payload?.id)
  },
})

async function send() {
  const body = draft.value.trim()
  if (!body || sending.value) return

  sending.value = true
  chatError.value = ''
  try {
    const message = await api.post(`/shop/auctions/${props.lot.id}/messages`, { body })
    draft.value = ''
    addMessage(message)
  } catch (e) {
    chatError.value = e.message || 'Не удалось отправить.'
  } finally {
    sending.value = false
  }
}

async function removeMessage(message) {
  if (!await confirmDialog('Удалить сообщение?')) return
  try {
    await api.delete(`/shop/auctions/${props.lot.id}/messages/${message.id}`)
    messages.value = messages.value.filter((m) => m.id !== message.id)
  } catch (e) {
    chatError.value = e.message || 'Не удалось удалить.'
  }
}

async function bid() {
  const value = Number(amount.value)
  if (!await confirmDialog(`Поставить ${coins(value)} ApexCoin? Монеты заморозятся до конца торгов, если перебьют — вернутся.`)) return

  busy.value = true
  error.value = ''
  try {
    const fresh = await api.post(`/shop/auctions/${props.lot.id}/bid`, { amount: value })
    bids.value = fresh.bids ?? bids.value
    emit('changed', fresh)
    auth.fetchMe()
  } catch (e) {
    error.value = e.message || 'Не удалось сделать ставку.'
    loadLot()
  } finally {
    busy.value = false
  }
}

async function withdraw() {
  const penalty = props.lot.cancel_penalty
  const text = penalty
      ? `Забрать питомца с торгов? Лидеру вернётся его ставка, а с тебя спишется неустойка ${coins(penalty)} ApexCoin (10% от ставки).`
      : 'Забрать питомца с торгов? Ставок нет — это бесплатно.'
  if (!await confirmDialog(text)) return

  busy.value = true
  error.value = ''
  try {
    await api.post(`/shop/auctions/${props.lot.id}/cancel`)
    emit('changed', { ...props.lot, status: 'cancelled' })
    auth.fetchMe()
    emit('close')
  } catch (e) {
    error.value = e.message || 'Не удалось снять лот.'
  } finally {
    busy.value = false
  }
}

// Минимальная ставка выросла — подтягиваем поле
watch(() => props.lot.min_bid, (min) => {
  if (!amount.value || amount.value < min) amount.value = min
})

let poll = null

function onKey(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  loadLot()
  loadMessages().then(scrollDown)
  subscribe(`auction.${props.lot.id}`)
  // Сокет лежит — опрашиваем чаще, жив — изредка для надёжности
  poll = setInterval(() => {
    if (!live.value) {
      loadMessages()
      loadLot()
    }
  }, 6000)
  window.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
  clearInterval(poll)
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div class="lp" @click.self="emit('close')">
      <div class="lp__box" :style="{ '--rar': rarity.color }" role="dialog" aria-modal="true">
        <button type="button" class="lp__close" title="Закрыть" @click="emit('close')">
          <AppIcon icon="close" :size="16" />
        </button>

        <!-- Лот -->
        <section class="lp__lot">
          <div class="lp__stage">
            <ShopItemPreview v-if="lot.pet" :item="lot.pet" class="lp__preview" />
            <span class="lp__level"><small>Уровень</small>{{ lot.level ?? traits?.level ?? 1 }}</span>
          </div>

          <header class="lp__head">
            <span class="lp__rarity">{{ rarity.label }} · лот #{{ lot.id }}</span>
            <h2>{{ traits?.name || lot.pet?.name }} <em v-if="traits?.serial">#{{ traits.serial }}</em></h2>
            <p>
              {{ lot.pet?.name }} · продаёт
              <RouterLink v-if="lot.seller" :to="userLink(lot.seller)" @click="emit('close')">{{ lot.seller.username }}</RouterLink>
            </p>
          </header>

          <dl v-if="traits" class="lp__traits">
            <div v-for="[key, label] in TRAIT_ROWS" :key="key">
              <dt>{{ label }}</dt>
              <dd>{{ traits[key]?.name }}</dd>
              <small>{{ chanceLabel(traits[key]?.chance) }}</small>
            </div>
          </dl>

          <div v-if="lot.arena" class="lp__arena">
            <span><AppIcon icon="sword" :size="13" /> Рейтинг арены <b>{{ lot.arena.rating }}</b></span>
            <span>Победы <b>{{ lot.arena.wins }}</b> · поражения <b>{{ lot.arena.losses }}</b></span>
          </div>

          <div class="lp__price">
            <div>
              <span>{{ lot.current_price ? 'Текущая ставка' : 'Стартовая цена' }}</span>
              <b>₳ {{ coins(lot.current_price || lot.start_price) }}</b>
              <small v-if="lot.leader">{{ leading ? 'Ты лидируешь' : `лидирует ${lot.leader.username}` }}</small>
            </div>
            <div class="lp__timer" :class="{ 'is-soon': msLeft < 10 * 60000 }">
              <span>До конца</span>
              <b><AppIcon icon="clock" :size="14" /> {{ active ? timer : 'торги закрыты' }}</b>
              <small>ставок: {{ lot.bids_count }}</small>
            </div>
          </div>

          <p v-if="error" class="lp__error">{{ error }}</p>

          <template v-if="active">
            <div v-if="mine" class="lp__own">
              <p>
                {{ lot.bids_count ? `Снять лот можно, но лидеру вернётся ставка, а с тебя — неустойка ${coins(lot.cancel_penalty)} ApexCoin.` : 'Ставок пока нет — забрать питомца можно бесплатно.' }}
              </p>
              <button type="button" class="lp-btn" :disabled="busy" @click="withdraw">
                Забрать питомца{{ lot.cancel_penalty ? ` · ₳ ${coins(lot.cancel_penalty)}` : '' }}
              </button>
            </div>

            <form v-else-if="auth.isAuthenticated && !leading" class="lp__bid" @submit.prevent="bid">
              <label>
                <span>₳</span>
                <input v-model.number="amount" type="number" :min="lot.min_bid" step="1" />
              </label>
              <button type="submit" class="lp-btn lp-btn--primary" :disabled="busy || amount < lot.min_bid">Поставить</button>
              <div class="lp__quick">
                <button v-for="add in [0, 50, 200, 1000]" :key="add" type="button" @click="amount = lot.min_bid + add">
                  {{ add ? `+${coins(add)}` : 'Мин.' }}
                </button>
              </div>
            </form>

            <p v-else-if="leading" class="lp__note lp__note--ok">Твоя ставка лидирует. Если перебьют — монеты вернутся.</p>
            <p v-else class="lp__note"><RouterLink to="/login" @click="emit('close')">Войди</RouterLink>, чтобы делать ставки.</p>
          </template>

          <div v-if="bids.length" class="lp__bids">
            <h3>История ставок</h3>
            <ol>
              <li v-for="(b, i) in bids" :key="`${b.user.id}-${b.amount}`" :class="{ 'is-top': i === 0 }">
                <RouterLink :to="userLink(b.user)" @click="emit('close')">{{ b.user.username }}</RouterLink>
                <b>₳ {{ coins(b.amount) }}</b>
                <time>{{ time(b.created_at) }}</time>
              </li>
            </ol>
          </div>
        </section>

        <!-- Чат лота -->
        <section class="lp__chat">
          <header>
            <h3><AppIcon icon="chat" :size="15" /> Чат лота</h3>
            <span class="lp__live" :class="{ 'is-on': live }"><i></i>{{ live ? 'в эфире' : 'обновляется' }}</span>
          </header>

          <div ref="feed" class="lp__feed">
            <p v-if="!messages.length" class="lp__empty">Пока тихо. Спроси продавца про питомца или поторгуйся.</p>
            <div
                v-for="m in messages"
                :key="m.id"
                class="lp-msg"
                :class="{ 'is-me': m.user?.id === auth.user?.id }"
            >
              <span class="lp-msg__avatar">
                <img v-if="m.user?.avatar_url" :src="m.user.avatar_url" alt="" />
                <template v-else>{{ (m.user?.username || '?').charAt(0).toUpperCase() }}</template>
              </span>
              <div class="lp-msg__body">
                <div class="lp-msg__meta">
                  <RouterLink v-if="m.user" :to="userLink(m.user)" @click="emit('close')">{{ m.user.username }}</RouterLink>
                  <span v-if="m.is_seller" class="lp-tag lp-tag--seller">продавец</span>
                  <span v-else-if="m.is_leader" class="lp-tag lp-tag--leader">лидер</span>
                  <time>{{ time(m.created_at) }}</time>
                  <button
                      v-if="canModerate || m.user?.id === auth.user?.id"
                      type="button"
                      class="lp-msg__del"
                      title="Удалить"
                      @click="removeMessage(m)"
                  ><AppIcon icon="trash" :size="12" /></button>
                </div>
                <p>{{ m.body }}</p>
              </div>
            </div>
          </div>

          <p v-if="chatError" class="lp__error lp__error--chat">{{ chatError }}</p>

          <form v-if="auth.isAuthenticated && active" class="lp__send" @submit.prevent="send">
            <input v-model="draft" maxlength="300" placeholder="Написать в чат лота" />
            <button type="submit" class="lp-btn lp-btn--primary" :disabled="sending || !draft.trim()" title="Отправить">
              <AppIcon icon="send" :size="14" />
            </button>
          </form>
          <p v-else-if="!active" class="lp__note lp__note--chat">Торги закончились — чат закрыт.</p>
          <p v-else class="lp__note lp__note--chat"><RouterLink to="/login" @click="emit('close')">Войди</RouterLink>, чтобы писать в чат.</p>
        </section>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.lp {
  position: fixed;
  inset: 0;
  z-index: 1000;

  display: grid;
  place-items: center;

  padding: 20px;
  overflow-y: auto;

  background: rgba(6, 5, 10, 0.78);
  backdrop-filter: blur(6px);
}

.lp__box {
  position: relative;

  width: min(1040px, 100%);

  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);

  overflow: hidden;

  background: var(--bg-card, #15131c);
  border: 1px solid color-mix(in srgb, var(--rar) 40%, rgba(255, 255, 255, 0.08));
  border-radius: 22px;
  box-shadow: 0 30px 80px -30px color-mix(in srgb, var(--rar) 60%, #000);
}

.lp__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;

  width: 34px;
  height: 34px;

  display: grid;
  place-items: center;

  color: #d6d2e2;
  background: rgba(10, 8, 16, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;

  cursor: pointer;
}

.lp__close:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }

/* ---------- Лот ---------- */

.lp__lot {
  display: flex;
  flex-direction: column;
  gap: 14px;

  padding: 18px;

  border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.lp__stage {
  position: relative;
  height: 230px;

  overflow: hidden;

  background: radial-gradient(circle at 50% 70%, color-mix(in srgb, var(--rar) 26%, transparent), transparent 70%);
  border-radius: 16px;
}

.lp__preview { height: 100%; }

.lp__level {
  position: absolute;
  left: 12px;
  bottom: 12px;

  display: flex;
  flex-direction: column;
  align-items: center;

  padding: 6px 12px;

  color: #fff;
  background: rgba(10, 8, 16, 0.75);
  border: 1px solid color-mix(in srgb, var(--rar) 60%, transparent);
  border-radius: 12px;

  font-size: 24px;
  font-weight: 900;
  line-height: 1;
}

.lp__level small {
  margin-bottom: 3px;

  color: var(--rar);

  font-size: 9px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.lp__rarity {
  color: var(--rar);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.lp__head h2 { margin: 4px 0 2px; color: #fff; font-size: 24px; line-height: 1.1; }
.lp__head h2 em { color: #a8a3b8; font-size: 15px; font-style: normal; }
.lp__head p { margin: 0; color: #8a8598; font-size: 13px; }
.lp__head a { color: #c4b5fd; }

.lp__traits {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;

  margin: 0;
}

.lp__traits div {
  padding: 8px 10px;

  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
}

.lp__traits dt { color: #8a8598; font-size: 9.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lp__traits dd { margin: 2px 0 0; overflow: hidden; color: #fff; font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.lp__traits small { color: var(--rar); font-size: 11px; font-weight: 700; }

.lp__arena {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;

  padding: 8px 12px;

  color: #a8a3b8;
  background: rgba(244, 63, 94, 0.07);
  border: 1px solid rgba(244, 63, 94, 0.22);
  border-radius: 10px;

  font-size: 12.5px;
}

.lp__arena span { display: inline-flex; align-items: center; gap: 6px; }
.lp__arena b { color: #fda4af; }

.lp__price {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  border-radius: 14px;
}

.lp__price > div {
  display: flex;
  flex-direction: column;
  gap: 2px;

  padding: 12px 14px;

  background: #100e16;
}

.lp__price span { color: #8a8598; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lp__price b { display: inline-flex; align-items: center; gap: 6px; color: #fde68a; font-size: 22px; font-weight: 900; font-variant-numeric: tabular-nums; }
.lp__price small { color: #a8a3b8; font-size: 12px; }
.lp__timer b { color: #fff; font-size: 17px; }
.lp__timer.is-soon b { color: #fca5a5; }

.lp__bid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 6px;
}

.lp__bid label {
  display: flex;
  align-items: center;
  gap: 6px;

  padding: 0 12px;

  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 11px;
}

.lp__bid label:focus-within { border-color: #f59e0b; }
.lp__bid label span { color: #fbbf24; font-weight: 900; }

.lp__bid input {
  flex: 1;
  min-width: 0;
  height: 42px;

  color: #fff;
  background: none;
  border: 0;
  outline: none;

  font: inherit;
  font-size: 16px;
  font-weight: 800;
}

.lp__quick { grid-column: 1 / -1; display: flex; gap: 6px; }

.lp__quick button {
  flex: 1;
  min-height: 30px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;

  font: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;
}

.lp__quick button:hover { color: #fff; border-color: #f59e0b; }

.lp__own {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 12px;

  background: rgba(255, 255, 255, 0.03);
  border: 1px dashed rgba(255, 255, 255, 0.14);
  border-radius: 12px;
}

.lp__own p { margin: 0; color: #a8a3b8; font-size: 12.5px; line-height: 1.45; }

.lp-btn {
  min-height: 42px;
  padding: 0 16px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  color: #e9e5f5;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 11px;

  font: inherit;
  font-size: 13.5px;
  font-weight: 800;

  cursor: pointer;
}

.lp-btn:hover:not(:disabled) { background: rgba(255, 255, 255, 0.12); }
.lp-btn--primary { color: #1a1206; background: linear-gradient(135deg, #fde047, #f59e0b); border-color: transparent; }
.lp-btn--primary:hover:not(:disabled) { background: linear-gradient(135deg, #fef08a, #fbbf24); }
.lp-btn:disabled { opacity: 0.5; cursor: default; }

.lp__note { margin: 0; color: #a8a3b8; font-size: 13px; }
.lp__note a { color: #c4b5fd; }
.lp__note--ok { color: #86efac; font-weight: 700; }

.lp__error {
  margin: 0;
  padding: 8px 12px;

  color: #fecaca;
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 10px;

  font-size: 12.5px;
}

.lp__bids h3 { margin: 0 0 6px; color: #8a8598; font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lp__bids ol { display: flex; flex-direction: column; gap: 2px; max-height: 150px; margin: 0; padding: 0; overflow-y: auto; list-style: none; }

.lp__bids li {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 10px;

  padding: 6px 10px;

  border-radius: 8px;

  font-size: 13px;
}

.lp__bids li.is-top { background: rgba(251, 191, 36, 0.1); }
.lp__bids li a { overflow: hidden; color: #d6d2e2; text-overflow: ellipsis; white-space: nowrap; }
.lp__bids li b { color: #fde68a; }
.lp__bids li time { color: #6f6a80; font-size: 12px; }

/* ---------- Чат ---------- */

.lp__chat {
  min-height: 0;

  display: flex;
  flex-direction: column;

  background: #100e16;
}

.lp__chat > header {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 16px 56px 12px 16px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.lp__chat h3 { display: inline-flex; align-items: center; gap: 7px; margin: 0; color: #fff; font-size: 15px; }

.lp__live {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  color: #8a8598;

  font-size: 11px;
  font-weight: 700;
}

.lp__live i { width: 7px; height: 7px; background: #6f6a80; border-radius: 50%; }
.lp__live.is-on { color: #86efac; }
.lp__live.is-on i { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25); animation: lp-pulse 1.6s ease-in-out infinite; }

@keyframes lp-pulse { 50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.08); } }

.lp__feed {
  flex: 1;
  min-height: 0;
  height: 0;

  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 14px 16px;
  overflow-y: auto;
}

.lp__empty { margin: auto; max-width: 240px; color: #6f6a80; font-size: 13px; text-align: center; }

.lp-msg { display: flex; gap: 9px; }

.lp-msg__avatar {
  width: 30px;
  height: 30px;

  display: grid;
  place-items: center;
  flex-shrink: 0;
  overflow: hidden;

  color: #ddd6fe;
  background: #2a2438;
  border-radius: 9px;

  font-size: 13px;
  font-weight: 800;
}

.lp-msg__avatar img { width: 100%; height: 100%; object-fit: cover; }

.lp-msg__body { min-width: 0; flex: 1; }

.lp-msg__meta { display: flex; align-items: center; gap: 6px; }
.lp-msg__meta a { color: #c4b5fd; font-size: 12.5px; font-weight: 800; text-decoration: none; }
.lp-msg.is-me .lp-msg__meta a { color: #fde68a; }
.lp-msg__meta time { color: #6f6a80; font-size: 11px; }

.lp-msg__del {
  display: grid;
  place-items: center;

  margin-left: auto;
  padding: 2px;

  color: #6f6a80;
  background: none;
  border: 0;

  cursor: pointer;
  opacity: 0;
}

.lp-msg:hover .lp-msg__del { opacity: 1; }
.lp-msg__del:hover { color: #fca5a5; }

.lp-msg p {
  margin: 2px 0 0;

  color: #e9e5f5;

  font-size: 13.5px;
  line-height: 1.45;
  overflow-wrap: anywhere;
}

.lp-tag { padding: 1px 6px; border-radius: 5px; font-size: 10px; font-weight: 800; }
.lp-tag--seller { color: #1a1206; background: #fbbf24; }
.lp-tag--leader { color: #052e16; background: #4ade80; }

.lp__error--chat { margin: 0 16px 8px; }
.lp__note--chat { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.06); }

.lp__send {
  display: flex;
  gap: 6px;

  padding: 12px;

  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.lp__send input {
  flex: 1;
  min-width: 0;
  height: 42px;
  padding: 0 12px;

  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 11px;
  outline: none;

  font: inherit;
  font-size: 14px;
}

.lp__send input:focus { border-color: #8b5cf6; }
.lp__send .lp-btn { width: 46px; padding: 0; }

@media (max-width: 820px) {
  .lp { place-items: start center; padding: 10px; }
  .lp__box { grid-template-columns: minmax(0, 1fr); }
  .lp__lot { border-right: 0; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
  .lp__stage { height: 190px; }
  .lp__feed { height: 320px; flex: none; }
  .lp-msg__del { opacity: 1; }
}
</style>
