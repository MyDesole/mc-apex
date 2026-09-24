<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/admin.js'
import UserName from '@/components/UserName.vue'

const users = ref([])
const loading = ref(true)
const search = ref('')
const filter = ref('unverified') // all | verified | unverified
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const processing = ref(null)

// модалка
const showModal = ref(false)
const modalUser = ref(null)
const modalAction = ref('verify') // verify | unverify
const modalReason = ref('')

let debounceTimer = null

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (search.value) params.search = search.value
    if (filter.value !== 'all') params.status = filter.value

    const data = await adminApi.verifiedUsers(params)
    users.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 300)
})

watch(filter, () => { page.value = 1; load() })
watch(page, load)

function openVerify(user) {
  modalUser.value = user
  modalAction.value = 'verify'
  modalReason.value = ''
  showModal.value = true
}

function openUnverify(user) {
  modalUser.value = user
  modalAction.value = 'unverify'
  modalReason.value = ''
  showModal.value = true
}

async function submitModal() {
  if (!modalUser.value) return

  processing.value = modalUser.value.id

  try {
    if (modalAction.value === 'verify') {
      await adminApi.verifyUser(modalUser.value.id, modalReason.value || null)
    } else {
      await adminApi.unverifyUser(modalUser.value.id)
    }

    showModal.value = false
    modalUser.value = null
    await load()
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    processing.value = null
  }
}

function closeModal() {
  showModal.value = false
  modalUser.value = null
  modalReason.value = ''
}

const filterCounts = computed(() => ({
  verified: users.value.filter(u => u.is_verified).length,
}))

onMounted(load)
</script>

<template>
  <div>
    <!-- HEAD -->
    <div class="head">
      <div>
        <h2>Верификация</h2>
        <p class="subtitle">
          Всего: <b>{{ total }}</b>
        </p>
      </div>
    </div>

    <!-- ФИЛЬТРЫ -->
    <div class="filters">
      <input
          v-model="search"
          type="text"
          placeholder="Поиск по нику или email..."
          class="search"
      />

      <div class="filter-tabs">
        <button
            :class="{ active: filter === 'unverified' }"
            @click="filter = 'unverified'"
        >
          Без галочки
        </button>
        <button
            :class="{ active: filter === 'verified' }"
            @click="filter = 'verified'"
        >
          ✅ Верифицированные
        </button>
        <button
            :class="{ active: filter === 'all' }"
            @click="filter = 'all'"
        >
          Все
        </button>
      </div>
    </div>

    <!-- STATES -->
    <div v-if="loading" class="empty">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <div v-else-if="!users.length" class="empty">
      <div class="empty__icon">✅</div>
      <div class="empty__title">
        {{ filter === 'unverified' ? 'Все верифицированы' : 'Никого не найдено' }}
      </div>
    </div>

    <!-- СПИСОК -->
    <div v-else class="list">
      <div
          v-for="user in users"
          :key="user.id"
          class="row"
          :class="{ 'row--verified': user.is_verified }"
      >
        <RouterLink :to="`/players/${user.id}`" class="row__main">
          <div class="user-avatar">
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                :alt="user.username"
                class="avatar-img"
            />
            <template v-else>
              {{ (user.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>

          <div class="user-info">
            <div class="user-name">
              <UserName :user="user" />

              <span v-if="user.is_verified" class="verified-icon" title="Верифицирован">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" fill="#1da1f2" />
                                    <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                                </svg>
                            </span>
            </div>

            <div class="user-meta">
              <span>{{ user.email }}</span>
              <span class="sep">·</span>
              <span>Тир: {{ user.tier }}</span>
              <span class="sep">·</span>
              <span class="role-pill" :class="`role-${user.role}`">
                                {{ user.role }}
                            </span>
            </div>

            <div v-if="user.is_verified && user.verified_reason" class="user-reason">
              💬 {{ user.verified_reason }}
            </div>
          </div>
        </RouterLink>

        <div class="row__actions">
          <button
              v-if="!user.is_verified"
              class="btn-action btn-action--primary"
              :disabled="processing === user.id"
              @click="openVerify(user)"
          >
            ✅ Верифицировать
          </button>

          <button
              v-else
              class="btn-action btn-action--danger"
              :disabled="processing === user.id"
              @click="openUnverify(user)"
          >
            ❌ Снять
          </button>
        </div>
      </div>
    </div>

    <!-- PAGINATION -->
    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">← Назад</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">Вперёд →</button>
    </div>

    <!-- МОДАЛКА -->
    <div v-if="showModal" class="modal-bg" @click.self="closeModal">
      <div class="modal">
        <header class="modal-head">
          <h3>
            {{ modalAction === 'verify' ? 'Верифицировать' : 'Снять верификацию' }}
            {{ modalUser?.username }}
          </h3>
          <button class="close" @click="closeModal">✕</button>
        </header>

        <div class="modal-body">
          <!-- Юзер превью -->
          <div class="modal-user">
            <div class="user-avatar">
              <img
                  v-if="modalUser?.avatar_url"
                  :src="modalUser.avatar_url"
                  class="avatar-img"
              />
              <template v-else>
                {{ (modalUser?.username || 'И').charAt(0).toUpperCase() }}
              </template>
            </div>
            <div>
              <div class="modal-user__name">{{ modalUser?.username }}</div>
              <div class="modal-user__email">{{ modalUser?.email }}</div>
            </div>
          </div>

          <!-- Причина -->
          <div v-if="modalAction === 'verify'" class="field">
            <label>Причина верификации</label>
            <input
                v-model="modalReason"
                type="text"
                maxlength="128"
                placeholder="Например: Известный стример, Топ-1 сезона"
            />
            <small class="hint">
              Показывается в профиле при наведении на галочку
            </small>
          </div>

          <div v-else class="warning">
            <p>
              Снять верификацию с <b>{{ modalUser?.username }}</b>?
            </p>
            <p class="warning__hint">
              Галочка исчезнет из профиля, а причина будет удалена.
            </p>
          </div>
        </div>

        <footer class="modal-foot">
          <button class="btn-cancel" @click="closeModal">Отмена</button>
          <button
              class="btn-save"
              :class="{ 'btn-save--danger': modalAction === 'unverify' }"
              :disabled="processing"
              @click="submitModal"
          >
            {{ processing ? '...' : (modalAction === 'verify' ? 'Верифицировать' : 'Снять') }}
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* HEAD */
.head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.head h2 {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 13px;
}

.subtitle b {
  color: var(--text);
  font-weight: 900;
}

/* FILTERS */
.filters {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search {
  flex: 1;
  min-width: 220px;
  min-height: 42px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  outline: none;
}

.search:focus {
  border-color: var(--accent);
}

.filter-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.filter-tabs button {
  padding: 8px 14px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.filter-tabs button:hover {
  color: var(--text);
}

.filter-tabs button.active {
  color: #fff;
  background: var(--accent);
}

/* LIST */
.list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s;
}

.row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.row--verified {
  border-color: rgba(29, 161, 242, 0.25);
  background: linear-gradient(90deg, rgba(29, 161, 242, 0.04), var(--bg-card) 40%);
}

.row__main {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
  min-width: 0;
}

.user-avatar {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
}

.verified-icon {
  display: inline-flex;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(29, 161, 242, 0.6));
}

.user-meta {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--text-dim);
}

.sep {
  opacity: 0.4;
}

.role-pill {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border: 1px solid;
}

.role-user {
  color: var(--text-muted);
  border-color: var(--border);
}

.role-tester {
  color: #06b6d4;
  border-color: rgba(6, 182, 212, 0.3);
  background: rgba(6, 182, 212, 0.1);
}

.role-moderator {
  color: #60a5fa;
  border-color: rgba(96, 165, 250, 0.3);
  background: rgba(96, 165, 250, 0.1);
}

.role-admin {
  color: #facc15;
  border-color: rgba(250, 204, 21, 0.3);
  background: rgba(250, 204, 21, 0.1);
}

.user-reason {
  margin-top: 4px;
  font-size: 11px;
  color: #8895f5;
  font-style: italic;
}

/* ACTIONS */
.row__actions {
  flex-shrink: 0;
}

.btn-action {
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid;
  transition: all 0.15s;
  white-space: nowrap;
}

.btn-action--primary {
  color: #fff;
  background: rgba(29, 161, 242, 0.15);
  border-color: rgba(29, 161, 242, 0.4);
}

.btn-action--primary:hover:not(:disabled) {
  background: #1da1f2;
  border-color: #1da1f2;
}

.btn-action--danger {
  color: #f87171;
  background: transparent;
  border-color: rgba(239, 68, 68, 0.25);
}

.btn-action--danger:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.08);
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* STATES */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 60px 20px;
  text-align: center;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
  color: var(--text-dim);
  font-size: 13px;
}

.empty__icon {
  font-size: 42px;
  opacity: 0.6;
}

.empty__title {
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* PAGINATION */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
}

.pagination button {
  min-height: 36px;
  padding: 0 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination span {
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

/* MODAL */
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 480px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}

.modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.modal-body {
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.modal-user__name {
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
}

.modal-user__email {
  font-size: 12px;
  color: var(--text-dim);
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
}

.field input:focus {
  border-color: var(--accent);
}

.hint {
  display: block;
  margin-top: 5px;
  color: var(--text-muted);
  font-size: 11px;
}

.warning {
  padding: 14px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  font-size: 13px;
}

.warning p {
  margin: 0 0 4px;
}

.warning__hint {
  color: #b8b8c7 !important;
  font-size: 12px;
  margin: 0 !important;
}

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-save {
  min-height: 40px;
  padding: 0 18px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: #1da1f2;
}

.btn-save:hover:not(:disabled) {
  background: #0d8ddb;
}

.btn-save--danger {
  background: var(--danger);
}

.btn-save--danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>