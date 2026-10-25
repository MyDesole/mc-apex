<script setup>
/**
 * Рефералы: кто кого привёл. Итоги, топ пригласивших (сколько из
 * приглашённых реально играют — полезно для поиска накруток) и свежие
 * приглашения.
 */
import { inject, onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const openDossier = inject('openDossier', () => {})

const data = ref(null)
const loading = ref(true)

function date(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

/** Доля приглашённых, прошедших тир-тест: низкая — повод проверить на фейки. */
function activeShare(row) {
  return row.invited ? Math.round((row.invited_active / row.invited) * 100) : 0
}

onMounted(async () => {
  try {
    data.value = await adminApi.referrals()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading" class="kit-empty">Загрузка…</div>
  <div v-else-if="!data" class="kit-empty">Не удалось загрузить рефералов</div>

  <div v-else class="refs">
    <section class="refs__totals">
      <div class="kit-card"><strong>{{ data.totals.invited }}</strong><span>приглашено всего</span></div>
      <div class="kit-card"><strong>{{ data.totals.invited_week }}</strong><span>за неделю</span></div>
      <div class="kit-card"><strong>{{ data.totals.referrers }}</strong><span>игроков кого-то привели</span></div>
      <div class="kit-card"><strong>{{ data.totals.coins_paid.toLocaleString('ru-RU') }}</strong><span>ApexCoin выплачено</span></div>
    </section>

    <div class="refs__grid">
      <section class="kit-card">
        <h3>Топ пригласивших</h3>
        <p v-if="!data.top.length" class="kit-muted">Пока никто никого не пригласил</p>

        <table v-else class="refs__table">
          <thead>
            <tr><th>Игрок</th><th>Привёл</th><th title="Прошли тир-тест">Играют</th><th>Монет</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in data.top" :key="row.id">
              <td>
                <button type="button" class="kit-user" @click="openDossier(row.id)">
                  <span class="kit-avatar">
                    <img v-if="row.avatar_url" :src="row.avatar_url" alt="" />
                    <template v-else>{{ row.username.charAt(0).toUpperCase() }}</template>
                  </span>
                  <span>{{ row.username }}</span>
                </button>
                <span v-if="row.is_banned" class="kit-tag kit-tag--red">бан</span>
              </td>
              <td><b>{{ row.invited }}</b></td>
              <td>
                {{ row.invited_active }}
                <small :class="activeShare(row) < 25 && row.invited >= 4 ? 'red' : 'kit-muted'">
                  {{ activeShare(row) }}%
                </small>
              </td>
              <td>{{ row.coins_earned.toLocaleString('ru-RU') }}</td>
            </tr>
          </tbody>
        </table>
        <p class="kit-muted refs__hint">
          Красный процент — из многих приглашённых почти никто не играет: возможно, накрутка фейками.
        </p>
      </section>

      <section class="kit-card">
        <h3>Свежие приглашения</h3>
        <p v-if="!data.recent.length" class="kit-muted">Пусто</p>
        <ul class="refs__recent">
          <li v-for="row in data.recent" :key="row.id">
            <button v-if="row.referrer" type="button" class="kit-user" @click="openDossier(row.referrer.id)">
              <span>{{ row.referrer.username }}</span>
            </button>
            <span class="kit-muted">→</span>
            <button type="button" class="kit-user" @click="openDossier(row.id)">
              <span>{{ row.username }}</span>
            </button>
            <span class="kit-tag" :class="row.tested ? 'kit-tag--green' : ''">
              {{ row.tested ? `тир ${row.tier}` : 'без теста' }}
            </span>
            <span v-if="row.is_banned" class="kit-tag kit-tag--red">бан</span>
            <small class="kit-muted">{{ date(row.created_at) }}</small>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/admin-kit.css";

.refs {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.refs__totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
}

.refs__totals .kit-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.refs__totals strong {
  font-family: var(--font-display);
  font-size: 24px;
}

.refs__totals span {
  color: var(--text-muted);
  font-size: 13px;
}

.refs__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

.refs__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.refs__table th {
  padding: 0 6px 8px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 600;
  text-align: left;
}

.refs__table td {
  padding: 7px 6px;
  border-top: 1px solid var(--border);
}

.refs__table td:first-child {
  display: flex;
  align-items: center;
  gap: 6px;
}

.refs__hint {
  margin: 10px 0 0;
}

.refs__recent {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.refs__recent li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 14px;
}

.refs__recent small {
  margin-left: auto;
}

.red {
  color: #f87171;
  font-weight: 700;
}

@media (max-width: 900px) {
  .refs__grid {
    grid-template-columns: 1fr;
  }
}
</style>
