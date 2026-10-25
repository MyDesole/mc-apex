<script setup>
/**
 * «Пульт» администратора: объявление на весь сайт, переключатели
 * функций, событие xN ApexCoin, раздачи и рассылки, промокоды и
 * кто онлайн прямо сейчас.
 */
import { computed, inject, onMounted, reactive, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import { clansApi } from '@/services/clan/clans.js'
import { alert, confirm } from '@/utils/dialog.js'

const openDossier = inject('openDossier', () => {})

const state = ref(null)
const loading = ref(true)
const saving = ref(false)

const TONES = [
  { value: 'info', label: 'Инфо' },
  { value: 'success', label: 'Праздник' },
  { value: 'warning', label: 'Внимание' },
  { value: 'danger', label: 'Важно' },
]

const AUDIENCES = [
  { value: 'online', label: 'Кто онлайн' },
  { value: 'active_week', label: 'Активные за неделю' },
  { value: 'all', label: 'Все игроки' },
  { value: 'clan', label: 'Клан' },
  { value: 'tier', label: 'Тир' },
]

const announcement = reactive({ text: '', tone: 'info', link: '', link_label: '', until: '' })
const features = reactive({})
const event = reactive({ multiplier: 1, until: '' })

const giveaway = reactive({ audience: 'online', clan_id: null, tier: 'A', coins: 100, message: '' })
const giving = ref(false)
const clans = ref([])

const promos = ref([])
const promo = reactive({ code: '', coins: 500, shop_item_id: null, max_uses: 100, expires_at: '', note: '' })

const online = ref([])

/** ISO → значение для <input type="datetime-local"> и обратно. */
function toLocal(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

function fromLocal(value) {
  return value ? new Date(value).toISOString() : null
}

function hydrate(data) {
  state.value = data
  Object.assign(announcement, { text: '', tone: 'info', link: '', link_label: '', ...(data.announcement || {}) })
  announcement.until = toLocal(data.announcement?.until)
  Object.assign(features, data.features)
  event.multiplier = data.multiplier || 1
  event.until = toLocal(data.multiplier_until)
}

async function save(payload, message) {
  saving.value = true
  try {
    hydrate(await adminApi.updateControl(payload))
    if (message) await alert(message, { title: 'Пульт' })
  } catch (e) {
    await alert(e.message || 'Не удалось сохранить', { title: 'Пульт' })
  } finally {
    saving.value = false
  }
}

function saveAnnouncement() {
  save({
    announcement: {
      text: announcement.text.trim(),
      tone: announcement.tone,
      link: announcement.link.trim() || null,
      link_label: announcement.link_label.trim() || null,
      until: fromLocal(announcement.until),
    },
  }, announcement.text.trim() ? 'Объявление опубликовано' : 'Объявление снято')
}

function removeAnnouncement() {
  announcement.text = ''
  save({ announcement: null }, 'Объявление снято')
}

function toggleFeature(key) {
  save({ features: { [key]: !features[key] } })
}

function saveEvent(multiplier = event.multiplier) {
  save({ multiplier, multiplier_until: fromLocal(event.until) },
      multiplier > 1 ? `Событие x${multiplier} запущено` : 'Событие остановлено')
}

const eventActive = computed(() => {
  if ((state.value?.multiplier ?? 1) <= 1) return false
  const until = state.value?.multiplier_until
  return !until || new Date(until) > new Date()
})

/* ---------- Раздача ---------- */

async function runGiveaway() {
  const payload = {
    audience: giveaway.audience,
    clan_id: giveaway.audience === 'clan' ? giveaway.clan_id : null,
    tier: giveaway.audience === 'tier' ? giveaway.tier : null,
    coins: Number(giveaway.coins) || 0,
    message: giveaway.message.trim() || null,
  }

  if (!payload.coins && !payload.message) return alert('Укажите монеты или текст сообщения', { title: 'Раздача' })

  try {
    const { count } = await adminApi.giveaway({ ...payload, dry_run: true })
    if (!count) return alert('В этой аудитории никого нет', { title: 'Раздача' })

    const what = [payload.coins ? `${payload.coins} ApexCoin` : null, payload.message ? 'уведомление' : null].filter(Boolean).join(' и ')
    if (!(await confirm(`Отправить ${what} — получателей: ${count}?`, { confirmText: 'Отправить' }))) return

    giving.value = true
    const result = await adminApi.giveaway(payload)
    await alert(`Готово: получили ${result.count}`, { title: 'Раздача' })
  } catch (e) {
    await alert(e.message || 'Не удалось выполнить раздачу', { title: 'Раздача' })
  } finally {
    giving.value = false
  }
}

/* ---------- Промокоды ---------- */

async function loadPromos() {
  promos.value = (await adminApi.promoCodes()).promo_codes ?? []
}

async function createPromo() {
  try {
    await adminApi.createPromo({
      code: promo.code.trim() || null,
      coins: Number(promo.coins) || 0,
      shop_item_id: promo.shop_item_id || null,
      max_uses: Number(promo.max_uses) || null,
      expires_at: fromLocal(promo.expires_at),
      note: promo.note.trim() || null,
    })
    Object.assign(promo, { code: '', note: '' })
    await loadPromos()
  } catch (e) {
    await alert(e.message || 'Не удалось создать промокод', { title: 'Промокод' })
  }
}

async function togglePromo(p) {
  await adminApi.togglePromo(p.id)
  await loadPromos()
}

async function deletePromo(p) {
  if (!(await confirm(`Удалить промокод ${p.code}?`, { danger: true, confirmText: 'Удалить' }))) return
  await adminApi.deletePromo(p.id)
  await loadPromos()
}

function copy(text) {
  navigator.clipboard?.writeText(text)
}

/* ---------- Онлайн ---------- */

async function loadOnline() {
  online.value = (await adminApi.onlineNow()).users ?? []
}

function date(iso) {
  return iso ? new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
}

onMounted(async () => {
  try {
    hydrate(await adminApi.control())
    await Promise.all([loadPromos(), loadOnline()])
    clans.value = (await clansApi.list({ sort: 'name' })).data ?? []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="kit-empty">Загрузка…</div>

  <div v-else class="control">
    <!-- ============ ОБЪЯВЛЕНИЕ ============ -->
    <section class="kit-card">
      <h3>Объявление на весь сайт</h3>

      <div class="announce-preview" :class="`announce-preview--${announcement.tone}`">
        {{ announcement.text || 'Так будет выглядеть баннер над каждой страницей' }}
        <b v-if="announcement.link">{{ announcement.link_label || 'Подробнее' }} →</b>
      </div>

      <textarea v-model="announcement.text" class="kit-input" rows="2" maxlength="300" placeholder="Текст объявления" />

      <div class="row">
        <div class="kit-chips">
          <button v-for="t in TONES" :key="t.value" type="button" :class="{ active: announcement.tone === t.value }" @click="announcement.tone = t.value">
            {{ t.label }}
          </button>
        </div>
      </div>

      <div class="row">
        <input v-model="announcement.link" class="kit-input" placeholder="Ссылка: /tournaments или https://…" />
        <input v-model="announcement.link_label" class="kit-input" placeholder="Текст кнопки" maxlength="40" />
        <label class="field-inline">до <input v-model="announcement.until" class="kit-input" type="datetime-local" /></label>
      </div>

      <div class="row row--end">
        <button v-if="state.announcement" type="button" class="kit-btn" :disabled="saving" @click="removeAnnouncement">Снять</button>
        <button type="button" class="kit-btn kit-btn--primary" :disabled="saving || !announcement.text.trim()" @click="saveAnnouncement">
          Опубликовать
        </button>
      </div>
    </section>

    <div class="grid">
      <!-- ============ ПЕРЕКЛЮЧАТЕЛИ ============ -->
      <section class="kit-card">
        <h3>Рубильники</h3>
        <p class="kit-muted">Выключенное недоступно игрокам — увидят «временно отключено». Админам всё работает.</p>
        <ul class="switches">
          <li v-for="(label, key) in state.feature_labels" :key="key">
            <span>{{ label }}</span>
            <button
                type="button"
                class="switch"
                :class="{ on: features[key] }"
                role="switch"
                :aria-checked="features[key]"
                :disabled="saving"
                @click="toggleFeature(key)"
            >
              <span />
            </button>
          </li>
        </ul>
      </section>

      <!-- ============ СОБЫТИЕ ============ -->
      <section class="kit-card">
        <h3>
          Событие ApexCoin
          <span v-if="eventActive" class="kit-tag kit-tag--green">идёт x{{ state.multiplier }}</span>
        </h3>
        <p class="kit-muted">Умножает заработок: тир-тесты, ачивки, ежедневный бонус и рефералы. Покупки и подарки — нет.</p>

        <div class="kit-chips multipliers">
          <button
              v-for="m in state.max_multiplier"
              :key="m"
              type="button"
              :class="{ active: event.multiplier === m }"
              @click="event.multiplier = m"
          >
            x{{ m }}
          </button>
        </div>

        <label class="field-inline">до <input v-model="event.until" class="kit-input" type="datetime-local" /></label>

        <div class="row row--end">
          <button v-if="eventActive" type="button" class="kit-btn" :disabled="saving" @click="saveEvent(1)">Остановить</button>
          <button type="button" class="kit-btn kit-btn--primary" :disabled="saving || event.multiplier < 2" @click="saveEvent()">Запустить</button>
        </div>
      </section>
    </div>

    <!-- ============ РАЗДАЧА ============ -->
    <section class="kit-card">
      <h3>Раздача и рассылка</h3>
      <p class="kit-muted">Монеты и/или уведомление выбранной аудитории. Забаненным не уходит.</p>

      <div class="kit-chips">
        <button v-for="a in AUDIENCES" :key="a.value" type="button" :class="{ active: giveaway.audience === a.value }" @click="giveaway.audience = a.value">
          {{ a.label }}
        </button>
      </div>

      <div class="row">
        <select v-if="giveaway.audience === 'clan'" v-model="giveaway.clan_id" class="kit-select">
          <option :value="null" disabled>Выберите клан</option>
          <option v-for="c in clans" :key="c.id" :value="c.id">[{{ c.tag }}] {{ c.name }}</option>
        </select>
        <select v-if="giveaway.audience === 'tier'" v-model="giveaway.tier" class="kit-select">
          <option v-for="t in ['S', 'A', 'B', 'C', 'D', 'E']" :key="t" :value="t">Тир {{ t }}</option>
        </select>
        <label class="field-inline">ApexCoin <input v-model.number="giveaway.coins" class="kit-input num" type="number" min="0" /></label>
      </div>

      <input v-model="giveaway.message" class="kit-input" maxlength="300" placeholder="Сообщение в уведомлениях (необязательно)" />

      <div class="row row--end">
        <button type="button" class="kit-btn kit-btn--primary" :disabled="giving" @click="runGiveaway">
          {{ giving ? 'Отправляем…' : 'Отправить' }}
        </button>
      </div>
    </section>

    <!-- ============ ПРОМОКОДЫ ============ -->
    <section class="kit-card">
      <h3>Промокоды</h3>
      <p class="kit-muted">Игроки вводят код в кошельке. Один код — одна активация на игрока.</p>

      <div class="row">
        <input v-model="promo.code" class="kit-input" placeholder="Код (пусто — сгенерируем)" maxlength="32" />
        <label class="field-inline">ApexCoin <input v-model.number="promo.coins" class="kit-input num" type="number" min="0" /></label>
        <select v-model="promo.shop_item_id" class="kit-select">
          <option :value="null">Без предмета</option>
          <option v-for="i in state.items" :key="i.id" :value="i.id">{{ i.name }}</option>
        </select>
      </div>
      <div class="row">
        <label class="field-inline">Активаций <input v-model.number="promo.max_uses" class="kit-input num" type="number" min="1" placeholder="∞" /></label>
        <label class="field-inline">до <input v-model="promo.expires_at" class="kit-input" type="datetime-local" /></label>
        <input v-model="promo.note" class="kit-input" placeholder="Для чего (видно только админам)" maxlength="255" />
        <button type="button" class="kit-btn kit-btn--primary" @click="createPromo">Создать</button>
      </div>

      <table v-if="promos.length" class="promos">
        <thead>
          <tr><th>Код</th><th>Даёт</th><th>Активаций</th><th>До</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="p in promos" :key="p.id" :class="{ off: !p.is_active }">
            <td>
              <button type="button" class="promo-code" title="Скопировать" @click="copy(p.code)">{{ p.code }}</button>
              <small v-if="p.note" class="kit-muted"> · {{ p.note }}</small>
            </td>
            <td>{{ [p.coins ? `${p.coins} ApexCoin` : null, p.item?.name].filter(Boolean).join(' + ') }}</td>
            <td>{{ p.uses }} / {{ p.max_uses ?? '∞' }}</td>
            <td>{{ p.expires_at ? date(p.expires_at) : '—' }}</td>
            <td class="promo-actions">
              <button type="button" class="kit-btn" @click="togglePromo(p)">{{ p.is_active ? 'Выключить' : 'Включить' }}</button>
              <button type="button" class="kit-btn kit-btn--danger" @click="deletePromo(p)">×</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- ============ ОНЛАЙН ============ -->
    <section class="kit-card">
      <h3>
        Онлайн сейчас <span class="kit-muted">{{ online.length }}</span>
        <button type="button" class="kit-btn" @click="loadOnline">Обновить</button>
      </h3>
      <p v-if="!online.length" class="kit-muted">Никого — тишина</p>
      <div class="online">
        <button v-for="u in online" :key="u.id" type="button" class="online__user" @click="openDossier(u.id)">
          <span class="kit-avatar">
            <img v-if="u.avatar_url" :src="u.avatar_url" alt="" />
            <template v-else>{{ u.username.charAt(0).toUpperCase() }}</template>
          </span>
          <span>{{ u.username }}</span>
          <small class="kit-muted">{{ u.tier || '—' }}</small>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.control {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.control .kit-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.control .kit-card h3 {
  margin-bottom: 0;
}

.control .kit-card p {
  margin: 0;
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.row .kit-input:not(.num) {
  flex: 1;
  min-width: 160px;
}

.row--end {
  justify-content: flex-end;
}

.field-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
  font-size: 13px;
}

.kit-input.num {
  width: 100px;
}

textarea.kit-input {
  resize: vertical;
}

.announce-preview {
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 14px;
  color: #fff;
  background: #2563eb;
}

.announce-preview b {
  margin-left: 8px;
}

.announce-preview--success { background: #16a34a; }
.announce-preview--warning { background: #b45309; }
.announce-preview--danger { background: #dc2626; }

.switches {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.switches li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.switch {
  position: relative;
  flex-shrink: 0;

  width: 42px;
  height: 24px;

  background: #3f3b4a;
  border: 0;
  border-radius: 12px;

  cursor: pointer;
  transition: background 0.15s ease;
}

.switch span {
  position: absolute;
  top: 3px;
  left: 3px;

  width: 18px;
  height: 18px;

  background: #fff;
  border-radius: 50%;

  transition: transform 0.15s ease;
}

.switch.on {
  background: #22c55e;
}

.switch.on span {
  transform: translateX(18px);
}

.multipliers button {
  min-width: 48px;
  font-family: var(--font-display);
}

.promos {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.promos th {
  padding: 4px 6px 8px;
  color: var(--text-muted);
  font-size: 12px;
  text-align: left;
}

.promos td {
  padding: 8px 6px;
  border-top: 1px solid var(--border);
}

.promos tr.off td {
  opacity: 0.5;
}

.promo-code {
  padding: 2px 8px;
  color: var(--accent-light);
  background: var(--accent-soft);
  border: 0;
  border-radius: 4px;
  font-family: monospace;
  font-size: 13px;
  font-weight: 700;
  cursor: copy;
}

.promo-actions {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.online {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.online__user {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 6px 10px 6px 6px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 14px;

  cursor: pointer;
}

.online__user:hover {
  border-color: var(--accent);
}

@media (max-width: 800px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .promos thead {
    display: none;
  }

  .promos tr {
    display: grid;
    grid-template-columns: 1fr auto;
    border-top: 1px solid var(--border);
  }

  .promos td {
    border: 0;
  }
}
</style>
