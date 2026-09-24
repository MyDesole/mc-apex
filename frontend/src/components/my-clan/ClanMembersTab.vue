<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { myClanApi } from '@/services/myClan.js'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  clan: { type: Object, required: true },
  myRole: { type: String, default: 'member' },
})

const auth = useAuthStore()
const members = ref([])
const loading = ref(true)

const showRoleModal = ref(false)
const selectedMember = ref(null)
const roleForm = ref({ role: 'member', permissions: [], title: '' })
const processing = ref(false)

const ROLES = [
  { value: 'member', label: 'Участник' },
  { value: 'officer', label: 'Офицер' },
]

const PERMISSIONS = [
  { value: 'news', label: 'Новости' },
  { value: 'forum', label: 'Форум' },
  { value: 'applications', label: 'Заявки' },
  { value: 'wars', label: 'Войны' },
  { value: 'resources', label: 'Ресурсы' },
]

async function load() {
  loading.value = true
  try {
    // тут можно использовать clansApi.show или собственный endpoint
    const data = await fetch(`/api/clans/${props.clan.id}`).then(r => r.json())
    members.value = data.clan.members || []
  } finally {
    loading.value = false
  }
}

function openRoleModal(member) {
  selectedMember.value = member
  roleForm.value = {
    role: member.role === 'leader' ? 'officer' : member.role,
    permissions: member.permissions ?? [],
    title: member.title ?? '',
  }
  showRoleModal.value = true
}

async function saveRole() {
  processing.value = true
  try {
    await myClanApi.updateRole(selectedMember.value.user_id, roleForm.value)
    showRoleModal.value = false
    await load()
  } finally {
    processing.value = false
  }
}

async function kick(member) {
  if (!confirm(`Кикнуть ${member.user.username}?`)) return
  await myClanApi.kickMember(member.user_id)
  await load()
}

async function transfer(member) {
  if (!confirm(`Передать лидерство ${member.user.username}?`)) return
  if (!confirm('Вы станете офицером. Продолжить?')) return
  await myClanApi.transferLeadership(member.user_id)
  await load()
}

const roleLabels = {
  leader: '👑 Лидер',
  officer: '⚔️ Офицер',
  member: 'Участник',
}

const roleColors = {
  leader: '#facc15',
  officer: '#60a5fa',
  member: '#9ca3af',
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <div v-if="loading" class="empty">Загрузка...</div>

    <div v-else class="members-list">
      <div
          v-for="m in members"
          :key="m.id"
          class="member"
          :class="`member--${m.role}`"
      >
        <RouterLink :to="`/players/${m.user.id}`" class="member__main">
          <div class="avatar">
            <img v-if="m.user.avatar_url" :src="m.user.avatar_url" />
            <template v-else>{{ m.user.username?.charAt(0).toUpperCase() }}</template>
          </div>

          <div class="info">
            <div class="name">
              {{ m.user.username }}
              <span v-if="m.title" class="custom-title">{{ m.title }}</span>
            </div>
            <div class="meta">
              Тир: {{ m.user.tier }} · Вклад: {{ m.contribution }}
              <template v-if="m.permissions?.length">
                · Права: {{ m.permissions.join(', ') }}
              </template>
            </div>
          </div>
        </RouterLink>

        <div class="role-badge" :style="{ color: roleColors[m.role] }">
          {{ roleLabels[m.role] }}
        </div>

        <div v-if="myRole === 'leader' && m.role !== 'leader'" class="actions">
          <button class="btn-action" @click="openRoleModal(m)" title="Изменить роль">⚙️</button>
          <button class="btn-action" @click="transfer(m)" title="Передать лидерство">👑</button>
          <button class="btn-action danger" @click="kick(m)" title="Кикнуть">🗑</button>
        </div>
      </div>
    </div>

    <!-- Модалка роли -->
    <div v-if="showRoleModal" class="modal-bg" @click.self="showRoleModal = false">
      <div class="modal">
        <header class="modal-head">
          <h3>Роль: {{ selectedMember?.user.username }}</h3>
          <button class="close" @click="showRoleModal = false">✕</button>
        </header>

        <div class="modal-body">
          <div class="field">
            <label>Роль</label>
            <select v-model="roleForm.role">
              <option v-for="r in ROLES" :key="r.value" :value="r.value">
                {{ r.label }}
              </option>
            </select>
          </div>

          <div class="field">
            <label>Кастомный титул</label>
            <input v-model="roleForm.title" maxlength="32" placeholder="Например: Глава PvP" />
          </div>

          <div class="field">
            <label>Доп. права</label>
            <div class="perms">
              <label v-for="p in PERMISSIONS" :key="p.value" class="perm">
                <input
                    type="checkbox"
                    :value="p.value"
                    v-model="roleForm.permissions"
                />
                <span>{{ p.label }}</span>
              </label>
            </div>
          </div>
        </div>

        <footer class="modal-foot">
          <button class="btn-cancel" @click="showRoleModal = false">Отмена</button>
          <button class="btn-save" :disabled="processing" @click="saveRole">
            {{ processing ? '...' : 'Сохранить' }}
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 12px; }
.members-list { display: flex; flex-direction: column; gap: 6px; }

.member {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.member--leader {
  border-color: rgba(250, 204, 21, 0.3);
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.04), var(--bg-card) 40%);
}

.member--officer {
  border-color: rgba(96, 165, 250, 0.2);
}

.member__main { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }

.avatar {
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  flex-shrink: 0;
  overflow: hidden;
  font-size: 15px;
}

.avatar img { width: 100%; height: 100%; object-fit: cover; }

.info { flex: 1; min-width: 0; }

.name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
}

.custom-title {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  color: var(--accent-light);
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.3);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.meta { font-size: 12px; color: var(--text-dim); }

.role-badge {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  flex-shrink: 0;
}

.actions { display: flex; gap: 4px; flex-shrink: 0; }

.btn-action {
  width: 32px;
  height: 32px;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  color: var(--text-dim);
  font-size: 14px;
}

.btn-action:hover { border-color: var(--border-hover); color: var(--text); }
.btn-action.danger:hover { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

/* Modal */
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
}

.modal-head h3 { margin: 0; font-size: 16px; font-weight: 800; }

.close {
  width: 30px;
  height: 30px;
  background: transparent;
  border: 0;
  color: var(--text-dim);
  cursor: pointer;
  border-radius: 8px;
}

.modal-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; }

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field select,
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

.perms { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

.perm {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  cursor: pointer;
  font-size: 13px;
}

.perm input { accent-color: var(--accent); }

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel, .btn-save {
  min-height: 40px;
  padding: 0 20px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-cancel { color: var(--text-dim); background: transparent; border: 1px solid var(--border); }
.btn-save { color: #fff; background: var(--accent); }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.empty {
  padding: 60px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 12px;
}
</style>