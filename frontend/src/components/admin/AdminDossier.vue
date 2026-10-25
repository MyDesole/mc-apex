<script setup>
/**
 * Досье игрока — панель справа поверх любого раздела админки.
 *
 * Всё о человеке в одном месте: статус, рейтинг, тесты, жалобы на него,
 * история модерации и (для админа) почта и движение ApexCoin.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { alert, confirm, confirmWithReason } from '@/utils/dialog.js'

const props = defineProps({
  userId: { type: Number, required: true },
})

const emit = defineEmits(['close', 'changed'])

const auth = useAuthStore()
const isAdmin = computed(() => auth.user?.role === 'admin')

const data = ref(null)
const loading = ref(true)
const busy = ref(false)

const ROLE_LABELS = {
  user: 'Игрок', media: 'Медийка', tester: 'Тестер', moderator: 'Модератор',
  admin: 'Администратор', bridge_tester: 'Бридж-тестер', bridge_curator: 'Бридж-куратор',
}

const TEST_STATUS = { pending: 'ждёт', in_progress: 'в работе', completed: 'завершён', cancelled: 'отменён' }

const BAN_DAYS = [
  { value: 1, label: '1 день' },
  { value: 7, label: '7 дней' },
  { value: 30, label: '30 дней' },
  { value: null, label: 'Навсегда' },
]

const banDays = ref(7)

const user = computed(() => data.value?.user)

async function load() {
  loading.value = true
  try {
    data.value = await adminApi.dossier(props.userId)
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

async function ban() {
  const answer = await confirmWithReason(`Забанить ${user.value.username}?`, {
    danger: true,
    confirmText: 'Забанить',
    reasonLabel: 'Причина (увидит игрок)',
    reasonPlaceholder: 'Например: читы, подтверждено записью',
  })

  if (!answer) return

  if (!answer.reason.trim()) {
    await alert('Укажите причину бана', { title: 'Бан' })
    return
  }

  busy.value = true
  try {
    const until = banDays.value
        ? new Date(Date.now() + banDays.value * 86400000).toISOString()
        : null

    await adminApi.ban(user.value.id, { reason: answer.reason.trim(), until })
    await load()
    emit('changed')
  } catch (e) {
    await alert(e.message || 'Не удалось забанить', { title: 'Бан' })
  } finally {
    busy.value = false
  }
}

async function unban() {
  if (!(await confirm(`Разбанить ${user.value.username}?`, { confirmText: 'Разбанить' }))) return

  busy.value = true
  try {
    await adminApi.unban(user.value.id)
    await load()
    emit('changed')
  } finally {
    busy.value = false
  }
}

function date(iso, withTime = false) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: 'numeric', month: 'short', year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

function onKey(event) {
  if (event.key === 'Escape') emit('close')
}

watch(() => props.userId, load)

onMounted(() => {
  load()
  window.addEventListener('keydown', onKey)
})

onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div class="dossier-backdrop" @click.self="emit('close')">
      <aside class="dossier" role="dialog" aria-label="Досье игрока">
        <button type="button" class="dossier__close" aria-label="Закрыть" @click="emit('close')">×</button>

        <div v-if="loading" class="kit-empty">Загрузка…</div>
        <div v-else-if="!data" class="kit-empty">Не удалось загрузить досье</div>

        <template v-else>
          <!-- Шапка -->
          <header class="dossier__head">
            <span class="dossier__avatar">
              <img v-if="user.avatar_url" :src="user.avatar_url" alt="" />
              <template v-else>{{ user.username.charAt(0).toUpperCase() }}</template>
            </span>
            <div>
              <h2>{{ user.username }}</h2>
              <p class="dossier__tags">
                <span class="kit-tag kit-tag--blue">{{ ROLE_LABELS[user.role] ?? user.role }}</span>
                <span v-if="user.is_banned" class="kit-tag kit-tag--red">
                  Бан {{ user.banned_until ? `до ${date(user.banned_until)}` : 'навсегда' }}
                </span>
                <span v-if="user.is_verified" class="kit-tag kit-tag--green">Верифицирован</span>
                <span v-if="user.clan" class="kit-tag">[{{ user.clan.tag }}] {{ user.clan.name }}</span>
              </p>
            </div>
          </header>

          <p v-if="user.is_banned && user.ban_reason" class="dossier__ban">Причина бана: {{ user.ban_reason }}</p>

          <!-- Действия -->
          <div class="dossier__actions">
            <RouterLink :to="`/user/${encodeURIComponent(user.username)}`" class="kit-btn" @click="emit('close')">
              Открыть профиль
            </RouterLink>

            <template v-if="isAdmin && user.role !== 'admin'">
              <button v-if="user.is_banned" type="button" class="kit-btn" :disabled="busy" @click="unban">Разбанить</button>
              <span v-else class="dossier__ban-controls">
                <select v-model="banDays" class="kit-select" aria-label="Срок бана">
                  <option v-for="o in BAN_DAYS" :key="o.label" :value="o.value">{{ o.label }}</option>
                </select>
                <button type="button" class="kit-btn kit-btn--danger" :disabled="busy" @click="ban">Бан</button>
              </span>
            </template>
          </div>

          <!-- Факты -->
          <dl class="dossier__facts">
            <div v-if="user.email"><dt>Почта</dt><dd>{{ user.email }}</dd></div>
            <div><dt>Minecraft</dt><dd>{{ user.minecraft_username || 'не привязан' }}</dd></div>
            <div><dt>Регистрация</dt><dd>{{ date(user.created_at) }}</dd></div>
            <div><dt>Последний визит</dt><dd>{{ date(user.last_seen_at, true) }}</dd></div>
            <div><dt>Тир</dt><dd>{{ user.tier || '—' }} · {{ Math.round(user.tier_score) }}%</dd></div>
            <div>
              <dt>Рейтинг</dt>
              <dd>{{ user.rating_points }} очк.<template v-if="data.rank.position"> · #{{ data.rank.position }} из {{ data.rank.total }}</template></dd>
            </div>
            <div v-if="user.apex_coins !== null"><dt>ApexCoin</dt><dd>{{ user.apex_coins.toLocaleString('ru-RU') }}</dd></div>
            <div><dt>Ачивок</dt><dd>{{ user.achievements_count }}</dd></div>
            <div v-if="data.tests_conducted"><dt>Провёл тестов</dt><dd>{{ data.tests_conducted }}</dd></div>
            <div>
              <dt>Жалоб на него / от него</dt>
              <dd :class="{ red: data.reports_against_total > 2 }">{{ data.reports_against_total }} / {{ data.reports_filed }}</dd>
            </div>
          </dl>

          <!-- Разбивка рейтинга -->
          <section v-if="data.rating" class="dossier__section">
            <h3>Очки рейтинга</h3>
            <p class="kit-muted">
              Тиры {{ data.rating.tiers_points }} (PvP {{ data.rating.pvp }}%, BW {{ data.rating.bedwars }}%, бридж {{ data.rating.bridge }}%)
              · матчи {{ data.rating.match_points }} · турниры {{ data.rating.tournament_points }}
              · войны {{ data.rating.war_points + data.rating.war_win_points }}
            </p>
          </section>

          <!-- Жалобы на игрока -->
          <section class="dossier__section">
            <h3>Жалобы на игрока</h3>
            <p v-if="!data.reports_against.length" class="kit-muted">Жалоб нет</p>
            <ul class="dossier__list">
              <li v-for="r in data.reports_against" :key="r.id">
                <span class="kit-tag kit-tag--red">{{ r.reason_label }}</span>
                <span>{{ r.reporter?.username }}<template v-if="r.comment">: «{{ r.comment }}»</template></span>
                <small class="kit-muted">{{ r.status === 'open' ? 'открыта' : r.resolution }} · {{ date(r.created_at) }}</small>
              </li>
            </ul>
          </section>

          <!-- История модерации -->
          <section class="dossier__section">
            <h3>История модерации</h3>
            <p v-if="!data.moderation.length" class="kit-muted">Пусто</p>
            <ul class="dossier__list">
              <li v-for="m in data.moderation" :key="m.id">
                <span class="kit-tag">{{ m.label }}</span>
                <span>{{ m.actor?.username ?? 'система' }}<template v-if="m.details?.reason || m.details?.note"> — {{ m.details.reason || m.details.note }}</template></span>
                <small class="kit-muted">{{ date(m.created_at, true) }}</small>
              </li>
            </ul>
          </section>

          <!-- Тир-тесты -->
          <section class="dossier__section">
            <h3>Тир-тесты</h3>
            <p v-if="!data.tier_tests.length" class="kit-muted">Не проходил</p>
            <ul class="dossier__list">
              <li v-for="t in data.tier_tests" :key="t.id">
                <span class="kit-tag">{{ t.mode === 'pvp' ? 'PvP' : 'BedWars' }}</span>
                <span>
                  {{ TEST_STATUS[t.status] ?? t.status }}
                  <template v-if="t.result_tier"> · {{ t.result_tier }} ({{ Math.round(t.result_score) }}%)</template>
                  <template v-if="t.tester"> · тестер {{ t.tester.username }}</template>
                </span>
                <small class="kit-muted">{{ date(t.completed_at || t.created_at) }}</small>
              </li>
            </ul>
          </section>

          <!-- ApexCoin -->
          <section v-if="data.transactions" class="dossier__section">
            <h3>Последние операции ApexCoin</h3>
            <p v-if="!data.transactions.length" class="kit-muted">Операций нет</p>
            <ul class="dossier__list">
              <li v-for="tx in data.transactions" :key="tx.id">
                <b :class="tx.amount > 0 ? 'green' : 'red'">{{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }}</b>
                <span>{{ tx.description || tx.source }}</span>
                <small class="kit-muted">{{ date(tx.created_at, true) }}</small>
              </li>
            </ul>
          </section>
        </template>
      </aside>
    </div>
  </Teleport>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.dossier-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  justify-content: flex-end;

  background: rgba(0, 0, 0, 0.5);
}

.dossier {
  position: relative;

  width: min(520px, 100%);
  height: 100%;
  overflow-y: auto;

  display: flex;
  flex-direction: column;
  gap: 16px;

  padding: 24px;

  background: var(--bg);
  border-left: 1px solid var(--border);

  animation: dossier-in 0.18s ease-out;
}

@keyframes dossier-in {
  from { transform: translateX(30px); opacity: 0; }
}

.dossier__close {
  position: absolute;
  top: 12px;
  right: 14px;

  width: 32px;
  height: 32px;

  color: var(--text-muted);
  background: none;
  border: 0;

  font-size: 26px;
  line-height: 1;

  cursor: pointer;
}

.dossier__head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding-right: 30px;
}

.dossier__head h2 {
  margin: 0 0 6px;
  font-family: var(--font-display);
  font-size: 22px;
  word-break: break-all;
}

.dossier__avatar {
  flex-shrink: 0;

  width: 64px;
  height: 64px;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #fff;
  background: var(--accent);
  border-radius: 6px;

  font-family: var(--font-display);
  font-size: 26px;
  font-weight: 700;
}

.dossier__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.dossier__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
}

.dossier__ban {
  margin: 0;
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border-radius: 6px;
  font-size: 14px;
}

.dossier__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dossier__actions .kit-btn {
  text-decoration: none;
}

.dossier__ban-controls {
  display: flex;
  gap: 6px;
  margin-left: auto;
}

.dossier__facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 16px;

  margin: 0;
  padding: 14px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.dossier__facts dt {
  color: var(--text-muted);
  font-size: 12px;
}

.dossier__facts dd {
  margin: 2px 0 0;
  font-weight: 600;
  font-size: 14px;
  word-break: break-word;
}

.dossier__section h3 {
  margin: 0 0 8px;
  font-size: 15px;
}

.dossier__section p {
  margin: 0;
}

.dossier__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dossier__list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  font-size: 14px;
}

.green { color: #4ade80; }
.red { color: #f87171; }

@media (max-width: 520px) {
  .dossier {
    padding: 18px 14px;
  }

  .dossier__facts {
    grid-template-columns: 1fr;
  }

  .dossier__list li {
    grid-template-columns: 1fr;
    gap: 2px;
  }
}
</style>
