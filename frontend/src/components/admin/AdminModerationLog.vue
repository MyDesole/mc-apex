<script setup>
/** Журнал действий персонала: баны, предупреждения, роли, монеты, жалобы. */
import { inject, onMounted, ref, watch } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const openDossier = inject('openDossier', () => {})

const FILTERS = [
  { key: '', label: 'Все' },
  { key: 'user.ban', label: 'Баны' },
  { key: 'user.unban', label: 'Разбаны' },
  { key: 'user.warn', label: 'Предупреждения' },
  { key: 'report', label: 'Жалобы' },
  { key: 'content', label: 'Удаление контента' },
  { key: 'user.role', label: 'Роли' },
  { key: 'user.coins', label: 'ApexCoin' },
]

const ROLE_LABELS = {
  user: 'игрок', media: 'медийка', tester: 'тестер', moderator: 'модератор',
  admin: 'админ', bridge_tester: 'бридж-тестер', bridge_curator: 'бридж-куратор',
}

const filter = ref('')
const logs = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)

async function load(reset = true) {
  if (reset) {
    loading.value = true
    page.value = 1
  }

  try {
    const data = await adminApi.moderationLog({ action: filter.value || undefined, page: page.value })
    logs.value = reset ? data.data ?? [] : [...logs.value, ...(data.data ?? [])]
    lastPage.value = data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

/** Человеческое описание подробностей записи. */
function details(log) {
  const d = log.details ?? {}

  switch (log.action) {
    case 'user.ban':
      return [d.reason, d.days ? `на ${d.days} дн.` : d.until ? `до ${new Date(d.until).toLocaleDateString('ru-RU')}` : 'навсегда']
          .filter(Boolean).join(' · ')
    case 'user.role':
      return `${ROLE_LABELS[d.from] ?? d.from} → ${ROLE_LABELS[d.to] ?? d.to}`
    case 'user.coins':
      return `${d.amount > 0 ? '+' : ''}${d.amount} ApexCoin${d.reason ? ` · ${d.reason}` : ''}`
    case 'report.resolve':
    case 'report.dismiss':
      return [d.reports > 1 ? `закрыто жалоб: ${d.reports}` : null, d.note].filter(Boolean).join(' · ')
    default:
      return d.note || d.reason || ''
  }
}

function time(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

watch(filter, () => load())
onMounted(load)
</script>

<template>
  <div class="log">
    <div class="kit-chips">
      <button
          v-for="f in FILTERS"
          :key="f.key"
          type="button"
          :class="{ active: filter === f.key }"
          @click="filter = f.key"
      >
        {{ f.label }}
      </button>
    </div>

    <div v-if="loading" class="kit-empty">Загрузка…</div>
    <div v-else-if="!logs.length" class="kit-empty">Записей нет</div>

    <ul v-else class="log__list">
      <li v-for="log in logs" :key="log.id" class="kit-card log__row">
        <span class="kit-tag" :class="{
          'kit-tag--red': log.action === 'user.ban' || log.action === 'content.remove',
          'kit-tag--green': log.action === 'user.unban' || log.action === 'report.resolve',
          'kit-tag--yellow': log.action === 'user.warn',
          'kit-tag--blue': log.action === 'user.role' || log.action === 'user.coins',
        }">
          {{ log.label }}
        </span>

        <span class="log__who">
          <button v-if="log.actor" type="button" class="kit-user" @click="openDossier(log.actor.id)">
            <span>{{ log.actor.username }}</span>
          </button>
          <template v-if="log.target">
            <span class="kit-muted">→</span>
            <button type="button" class="kit-user" @click="openDossier(log.target.id)">
              <span>{{ log.target.username }}</span>
            </button>
          </template>
        </span>

        <span class="log__details kit-muted">{{ details(log) }}</span>

        <small class="kit-muted">{{ time(log.created_at) }}</small>
      </li>
    </ul>

    <button v-if="!loading && page < lastPage" type="button" class="kit-btn log__more" @click="page++; load(false)">
      Показать ещё
    </button>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.log {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.log__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.log__row {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr) minmax(0, 1.4fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px 14px;
}

.log__row .kit-tag {
  justify-self: start;
}

.log__who {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.log__details {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.log__more {
  align-self: center;
}

@media (max-width: 760px) {
  .log__row {
    grid-template-columns: 1fr;
    gap: 4px;
  }

  .log__details {
    white-space: normal;
  }
}
</style>
