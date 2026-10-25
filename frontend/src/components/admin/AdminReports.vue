<script setup>
/**
 * Жалобы игроков. Решение по одной жалобе закрывает все открытые
 * жалобы на тот же объект (это делает сервер).
 */
import { inject, onMounted, reactive, ref, watch } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import { alert, confirm } from '@/utils/dialog.js'

const openDossier = inject('openDossier', () => {})
const refreshCounters = inject('refreshCounters', () => {})

const STATUSES = [
  { key: 'open', label: 'Открытые' },
  { key: 'resolved', label: 'Меры приняты' },
  { key: 'dismissed', label: 'Отклонённые' },
  { key: 'all', label: 'Все' },
]

const SUBJECTS = {
  user: 'Профиль',
  message: 'Сообщение в чате',
  forum_topic: 'Тема форума',
  forum_reply: 'Ответ на форуме',
}

const RESOLUTIONS = {
  dismiss: 'Отклонена',
  warn: 'Предупреждение',
  ban: 'Бан',
  remove_content: 'Контент удалён',
}

const BAN_OPTIONS = [
  { value: 1, label: '1 день' },
  { value: 3, label: '3 дня' },
  { value: 7, label: '7 дней' },
  { value: 30, label: '30 дней' },
  { value: null, label: 'Навсегда' },
]

const status = ref('open')
const reports = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const busyId = ref(null)

// Черновик решения по каждой жалобе: заметка и срок бана
const drafts = reactive({})

function draft(id) {
  drafts[id] ??= { note: '', banDays: 7 }
  return drafts[id]
}

async function load(reset = true) {
  if (reset) {
    loading.value = true
    page.value = 1
  }

  try {
    const data = await adminApi.reports({ status: status.value, page: page.value })
    reports.value = reset ? data.data ?? [] : [...reports.value, ...(data.data ?? [])]
    lastPage.value = data.last_page ?? 1
  } catch {
    if (reset) reports.value = []
  } finally {
    loading.value = false
  }
}

function loadMore() {
  page.value += 1
  load(false)
}

async function resolve(report, action) {
  const d = draft(report.id)

  const question = {
    ban: `Забанить ${report.target?.username} ${d.banDays ? `на ${BAN_OPTIONS.find((o) => o.value === d.banDays)?.label}` : 'навсегда'}?`,
    remove_content: 'Удалить этот контент?',
  }[action]

  if (question && !(await confirm(question, { danger: true, confirmText: 'Да' }))) return

  busyId.value = report.id

  try {
    await adminApi.resolveReport(report.id, {
      action,
      note: d.note.trim() || null,
      ban_days: action === 'ban' ? d.banDays : null,
    })

    await load()
    refreshCounters()
  } catch (e) {
    await alert(e.message || 'Не удалось сохранить решение', { title: 'Жалоба' })
  } finally {
    busyId.value = null
  }
}

function time(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

watch(status, () => load())
onMounted(load)
</script>

<template>
  <div class="reports">
    <div class="kit-chips">
      <button
          v-for="s in STATUSES"
          :key="s.key"
          type="button"
          :class="{ active: status === s.key }"
          @click="status = s.key"
      >
        {{ s.label }}
      </button>
    </div>

    <div v-if="loading" class="kit-empty">Загрузка…</div>
    <div v-else-if="!reports.length" class="kit-empty">
      {{ status === 'open' ? 'Открытых жалоб нет — всё спокойно' : 'Пусто' }}
    </div>

    <article v-for="r in reports" v-else :key="r.id" class="kit-card report">
      <header class="report__head">
        <span class="kit-tag kit-tag--red">{{ r.reason_label }}</span>
        <span class="kit-tag">{{ SUBJECTS[r.subject_type] }}</span>
        <span v-if="r.reports_on_target > 1" class="kit-tag kit-tag--yellow">
          {{ r.reports_on_target }} жалоб на игрока
        </span>
        <small class="kit-muted report__time">{{ time(r.created_at) }}</small>
      </header>

      <p class="report__who">
        <button v-if="r.reporter" type="button" class="kit-user" @click="openDossier(r.reporter.id)">
          <span>{{ r.reporter.username }}</span>
        </button>
        <span class="kit-muted">жалуется на</span>
        <button v-if="r.target" type="button" class="kit-user" @click="openDossier(r.target.id)">
          <span class="kit-avatar">
            <img v-if="r.target.avatar_url" :src="r.target.avatar_url" alt="" />
            <template v-else>{{ r.target.username.charAt(0).toUpperCase() }}</template>
          </span>
          <span>{{ r.target.username }}</span>
        </button>
        <span v-else class="kit-muted">удалённого игрока</span>
        <span v-if="r.target?.is_banned" class="kit-tag kit-tag--red">уже в бане</span>
      </p>

      <p v-if="r.comment" class="report__comment">«{{ r.comment }}»</p>

      <blockquote v-if="r.snapshot && r.subject_type !== 'user'" class="report__snapshot">
        {{ r.snapshot }}
      </blockquote>

      <!-- Решение -->
      <div v-if="r.status === 'open'" class="report__decide">
        <input
            v-model="draft(r.id).note"
            class="kit-input report__note"
            maxlength="500"
            placeholder="Комментарий к решению (увидит нарушитель при предупреждении)"
        />

        <div class="report__buttons">
          <button type="button" class="kit-btn" :disabled="busyId === r.id" @click="resolve(r, 'dismiss')">
            Отклонить
          </button>
          <button type="button" class="kit-btn kit-btn--warn" :disabled="busyId === r.id || !r.target" @click="resolve(r, 'warn')">
            Предупредить
          </button>
          <button
              v-if="r.subject_type !== 'user'"
              type="button"
              class="kit-btn"
              :disabled="busyId === r.id"
              @click="resolve(r, 'remove_content')"
          >
            Удалить контент
          </button>
          <span class="report__ban">
            <select v-model="draft(r.id).banDays" class="kit-select" aria-label="Срок бана">
              <option v-for="o in BAN_OPTIONS" :key="o.label" :value="o.value">{{ o.label }}</option>
            </select>
            <button type="button" class="kit-btn kit-btn--danger" :disabled="busyId === r.id || !r.target" @click="resolve(r, 'ban')">
              Бан
            </button>
          </span>
        </div>
      </div>

      <p v-else class="report__result">
        <span class="kit-tag" :class="r.status === 'dismissed' ? '' : 'kit-tag--green'">
          {{ RESOLUTIONS[r.resolution] ?? r.status }}
        </span>
        <span class="kit-muted">
          {{ r.moderator?.username }} · {{ r.resolved_at ? time(r.resolved_at) : '' }}
          <template v-if="r.moderator_note">— {{ r.moderator_note }}</template>
        </span>
      </p>
    </article>

    <button v-if="!loading && page < lastPage" type="button" class="kit-btn reports__more" @click="loadMore">
      Показать ещё
    </button>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.reports {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.report {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.report__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.report__time {
  margin-left: auto;
}

.report__who {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.report__comment {
  margin: 0;
  color: var(--text-dim);
}

.report__snapshot {
  margin: 0;
  padding: 10px 12px;

  max-height: 160px;
  overflow: auto;

  border-left: 3px solid var(--border);
  background: rgba(255, 255, 255, 0.02);

  white-space: pre-wrap;
  font-size: 14px;
}

.report__decide {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.report__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.report__ban {
  display: flex;
  gap: 6px;
  margin-left: auto;
}

.report__result {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.reports__more {
  align-self: center;
}

@media (max-width: 600px) {
  .report__ban {
    margin-left: 0;
  }
}
</style>
