<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AdminUsers from '@/components/admin/AdminUsers.vue'
import AdminComments from '@/components/admin/AdminComments.vue'
import AdminEvents from '@/components/admin/AdminEvents.vue'
import AdminClans from '@/components/admin/AdminClans.vue'

const auth = useAuthStore()
const router = useRouter()
const tab = ref('users')

const role = computed(() => auth.user?.role)
const isAdmin = computed(() => role.value === 'admin')
const isModerator = computed(() => ['moderator', 'admin'].includes(role.value))

const roleLabels = {
  user: 'Пользователь',
  tester: 'Тестер',
  moderator: 'Модератор',
  admin: 'Администратор',
}

onMounted(() => {
  if (!isModerator.value) router.push('/')

  // модератор не может видеть вкладку users
  if (!isAdmin.value && tab.value === 'users') {
    tab.value = 'clans'
  }
})
</script>

<template>
  <div class="admin-page">
    <header class="admin-head">
      <div>
        <h1>Админ-панель</h1>
        <p class="subtitle">Управление платформой APEX TIERS</p>
      </div>

      <span class="role-badge" :class="`role-${role}`">
                {{ roleLabels[role] }}
            </span>
    </header>

    <nav class="admin-tabs">
      <button
          v-if="isAdmin"
          :class="{ active: tab === 'users' }"
          @click="tab = 'users'"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Пользователи
      </button>

      <button
          :class="{ active: tab === 'clans' }"
          @click="tab = 'clans'"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Кланы
      </button>

      <button
          :class="{ active: tab === 'comments' }"
          @click="tab = 'comments'"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Комментарии
      </button>

      <button
          :class="{ active: tab === 'events' }"
          @click="tab = 'events'"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Клан-посты
      </button>
    </nav>

    <div class="admin-content">
      <AdminUsers v-if="tab === 'users' && isAdmin" />
      <AdminClans v-else-if="tab === 'clans'" />
      <AdminComments v-else-if="tab === 'comments'" />
      <AdminEvents v-else-if="tab === 'events'" />
    </div>
  </div>
</template>

<style scoped>
.admin-page {
  width: min(1200px, calc(100% - 40px));
  margin: 40px auto;
}

.admin-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
  gap: 20px;
}

.admin-head h1 {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.role-badge {
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border: 1px solid;
}

.role-user {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border);
}

.role-tester {
  color: #06b6d4;
  background: rgba(6, 182, 212, 0.1);
  border-color: rgba(6, 182, 212, 0.3);
}

.role-moderator {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.1);
  border-color: rgba(96, 165, 250, 0.3);
}

.role-admin {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border-color: rgba(250, 204, 21, 0.3);
}

/* === TABS === */

.admin-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}

.admin-tabs::-webkit-scrollbar {
  display: none;
}

.admin-tabs button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 18px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.2s;
}

.admin-tabs button:hover {
  color: var(--text);
}

.admin-tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

.admin-tabs button.active svg {
  color: var(--accent-light);
}

.admin-content {
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 700px) {
  .admin-head {
    flex-direction: column;
  }

  .admin-tabs button {
    padding: 10px 14px;
    font-size: 13px;
  }
}
</style>