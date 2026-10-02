<script setup>
import { alert as alertDialog } from '@/utils/dialog.js'
import { onMounted, ref, watch, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import UserName from '@/components/players/UserName.vue'
import { userLink } from '@/utils/links.js'

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
    await alertDialog(e.message || 'Ошибка')
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
        <RouterLink :to="userLink(user)" class="row__main">
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
@import "@/components/admin/AdminVerification.css";
</style>
