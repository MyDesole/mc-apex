<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { myClanApi } from '@/services/myClan.js'
import { useAuthStore } from '@/stores/auth'
import { userLink } from '@/utils/links.js'

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
  if (!await confirmDialog(`Кикнуть ${member.user.username}?`)) return
  await myClanApi.kickMember(member.user_id)
  await load()
}

async function transfer(member) {
  if (!await confirmDialog(`Передать лидерство ${member.user.username}?`)) return
  if (!await confirmDialog('Вы станете офицером. Продолжить?')) return
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
        <RouterLink :to="userLink(m.user)" class="member__main">
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
          <button
              class="btn-action btn-action--transfer"
              @click="transfer(m)"
              title="Сделать лидером — после этого вы сможете покинуть клан"
          >
            👑 Лидер
          </button>
          <button class="btn-action" @click="openRoleModal(m)" title="Изменить роль">⚙️</button>
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
.tab {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

/* =========================
   Members
   ========================= */

.members-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.member {
  position: relative;
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
  padding: 12px 14px;
  background: linear-gradient(
      100deg,
      rgba(255, 255, 255, 0.035),
      rgba(255, 255, 255, 0.018)
  );
  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025);
  transition:
      transform 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.member:hover {
  transform: translateY(-1px);
  background: linear-gradient(
      100deg,
      rgba(255, 255, 255, 0.052),
      rgba(255, 255, 255, 0.022)
  );
  border-color: rgba(255, 255, 255, 0.11);
  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.18),
      inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.member--leader {
  border-color: rgba(250, 204, 21, 0.22);
  background:
      linear-gradient(
          100deg,
          rgba(250, 204, 21, 0.055),
          rgba(255, 255, 255, 0.018) 48%
      );
}

.member--leader:hover {
  border-color: rgba(250, 204, 21, 0.34);
  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.2),
      0 0 0 1px rgba(250, 204, 21, 0.035);
}

.member--officer {
  border-color: rgba(96, 165, 250, 0.16);
  background:
      linear-gradient(
          100deg,
          rgba(59, 130, 246, 0.035),
          rgba(255, 255, 255, 0.018) 48%
      );
}

.member--officer:hover {
  border-color: rgba(96, 165, 250, 0.28);
}

.member__main {
  display: flex;
  align-items: center;
  gap: 13px;
  flex: 1;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

/* =========================
   Avatar
   ========================= */

.avatar {
  position: relative;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;

  color: #fff;
  background:
      linear-gradient(135deg, #8b5cf6, #6d28d9);

  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 11px;

  font-size: 15px;
  font-weight: 850;

  box-shadow:
      0 7px 18px rgba(0, 0, 0, 0.2),
      inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.avatar::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(
      145deg,
      rgba(255, 255, 255, 0.12),
      transparent 45%
  );
}

.avatar img {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

/* =========================
   Member info
   ========================= */

.info {
  flex: 1;
  min-width: 0;
}

.name {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  margin-bottom: 4px;

  color: var(--text);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.25;

  overflow: hidden;
}

.name {
  white-space: nowrap;
}

.name > .custom-title {
  flex-shrink: 0;
}

.custom-title {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  padding: 3px 7px;

  color: var(--accent-light, #a78bfa);
  background: rgba(124, 58, 237, 0.09);
  border: 1px solid rgba(124, 58, 237, 0.22);
  border-radius: 999px;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 0.45px;
  line-height: 1.2;
  text-transform: uppercase;
}

.meta {
  min-width: 0;

  color: var(--text-muted);
  font-size: 10px;
  line-height: 1.45;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* =========================
   Role badge
   ========================= */

.role-badge {
  flex-shrink: 0;

  padding: 5px 8px;

  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 999px;

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.35px;
  line-height: 1;
  text-transform: uppercase;
}

/* =========================
   Actions
   ========================= */

.actions {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
}

.btn-action {
  width: 34px;
  height: 34px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  padding: 0;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;

  cursor: pointer;

  font-size: 13px;

  transition:
      color 0.16s ease,
      border-color 0.16s ease,
      background 0.16s ease,
      transform 0.16s ease;
}

.btn-action:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(255, 255, 255, 0.14);
  transform: translateY(-1px);
}

.btn-action:active {
  transform: translateY(0);
}

.btn-action--transfer {
  width: auto;
  min-width: 88px;
  padding: 0 10px;

  gap: 5px;

  color: #facc15;
  background: rgba(250, 204, 21, 0.055);
  border-color: rgba(250, 204, 21, 0.17);

  font-size: 10px;
  font-weight: 800;
  white-space: nowrap;
}

.btn-action--transfer:hover {
  color: #fde68a;
  background: rgba(250, 204, 21, 0.09);
  border-color: rgba(250, 204, 21, 0.32);
}

.btn-action.danger:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.07);
  border-color: rgba(239, 68, 68, 0.28);
}

/* =========================
   Modal
   ========================= */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 20px;

  background: rgba(3, 3, 7, 0.82);
  backdrop-filter: blur(14px);

  animation: modalFade 0.18s ease;
}

@keyframes modalFade {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

.modal {
  position: relative;

  width: 100%;
  max-width: 480px;
  max-height: calc(100vh - 40px);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(24, 24, 34, 0.98),
          rgba(12, 12, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.09);
  border-radius: 17px;

  box-shadow:
      0 35px 90px rgba(0, 0, 0, 0.7),
      0 0 0 1px rgba(124, 58, 237, 0.035);

  animation: modalIn 0.2s ease;
}

.modal::before {
  content: '';

  position: absolute;
  top: 0;
  left: 10%;
  right: 10%;

  height: 1px;

  background: linear-gradient(
      90deg,
      transparent,
      rgba(139, 92, 246, 0.65),
      transparent
  );

  pointer-events: none;
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.985);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;

  padding: 17px 20px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.modal-head h3 {
  min-width: 0;
  margin: 0;

  color: var(--text);
  font-size: 15px;
  font-weight: 850;
  letter-spacing: -0.15px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.close {
  width: 32px;
  height: 32px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 9px;

  cursor: pointer;

  font-size: 12px;

  transition:
      color 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease,
      transform 0.16s ease;
}

.close:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.07);
  border-color: rgba(255, 255, 255, 0.13);
  transform: rotate(3deg);
}

/* =========================
   Modal body
   ========================= */

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 17px;

  padding: 20px;
  overflow-y: auto;
}

.field {
  display: flex;
  flex-direction: column;
}

.field label {
  display: block;

  margin-bottom: 7px;

  color: var(--text-dim);
  font-size: 10px;
  font-weight: 850;
  letter-spacing: 0.45px;
  line-height: 1.2;
  text-transform: uppercase;
}

.field select,
.field input {
  width: 100%;
  box-sizing: border-box;

  min-height: 40px;
  padding: 0 12px;

  color: var(--text);
  background: rgba(7, 7, 13, 0.72);

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 9px;

  font: inherit;
  font-size: 12px;

  outline: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.field select:hover,
.field input:hover {
  border-color: rgba(255, 255, 255, 0.12);
}

.field select:focus,
.field input:focus {
  background: rgba(7, 7, 13, 0.9);
  border-color: var(--accent);
  box-shadow:
      0 0 0 3px rgba(124, 58, 237, 0.1),
      0 8px 25px rgba(0, 0, 0, 0.12);
}

.field input::placeholder {
  color: var(--text-muted);
}

/* =========================
   Permissions
   ========================= */

.perms {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 7px;
}

.perm {
  position: relative;

  display: flex;
  align-items: center;
  gap: 8px;

  min-height: 38px;
  box-sizing: border-box;
  padding: 0 11px;

  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.025);

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 9px;

  cursor: pointer;

  font-size: 11px;
  font-weight: 650;

  transition:
      color 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease;
}

.perm:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.045);
  border-color: rgba(255, 255, 255, 0.11);
}

.perm input {
  width: 15px;
  height: 15px;

  margin: 0;

  flex-shrink: 0;

  accent-color: var(--accent);
  cursor: pointer;
}

/* =========================
   Modal footer
   ========================= */

.modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;

  padding: 14px 20px;

  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.btn-cancel,
.btn-save {
  min-height: 38px;
  padding: 0 17px;

  border-radius: 9px;

  font-size: 11px;
  font-weight: 800;

  cursor: pointer;

  transition:
      transform 0.16s ease,
      filter 0.16s ease,
      opacity 0.16s ease,
      background 0.16s ease,
      border-color 0.16s ease;
}

.btn-cancel {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.075);
}

.btn-cancel:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.055);
  border-color: rgba(255, 255, 255, 0.13);
}

.btn-save {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          var(--accent-light, #8b5cf6),
          var(--accent, #7c3aed)
      );

  border: 1px solid rgba(255, 255, 255, 0.1);

  box-shadow:
      0 7px 20px rgba(124, 58, 237, 0.22),
      inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-1px);
  filter: brightness(1.08);
  box-shadow:
      0 10px 26px rgba(124, 58, 237, 0.3),
      inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.btn-save:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
}

/* =========================
   Empty
   ========================= */

.empty {
  display: flex;
  align-items: center;
  justify-content: center;

  min-height: 180px;
  padding: 30px;

  color: var(--text-muted);

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.055),
          transparent 50%
      ),
      rgba(255, 255, 255, 0.018);

  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 14px;

  font-size: 12px;
}

/* =========================
   Responsive
   ========================= */

@media (max-width: 760px) {
  .member {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .member__main {
    min-width: calc(100% - 58px);
  }

  .role-badge {
    margin-left: 57px;
    margin-top: -4px;
  }

  .actions {
    width: 100%;
    justify-content: flex-end;
    padding-top: 2px;
  }

  .modal-bg {
    padding: 12px;
  }

  .modal {
    max-height: calc(100vh - 24px);
    border-radius: 14px;
  }
}

@media (max-width: 460px) {
  .member {
    padding: 11px;
    gap: 10px;
  }

  .avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
  }

  .member__main {
    gap: 10px;
    min-width: calc(100% - 52px);
  }

  .name {
    font-size: 12px;
  }

  .custom-title {
    max-width: 120px;
  }

  .meta {
    white-space: normal;
    line-height: 1.45;
  }

  .role-badge {
    margin-left: 50px;
  }

  .actions {
    justify-content: stretch;
  }

  .btn-action--transfer {
    flex: 1;
  }

  .modal-head {
    padding: 15px 16px;
  }

  .modal-body {
    padding: 16px;
  }

  .modal-foot {
    padding: 12px 16px;
  }

  .perms {
    grid-template-columns: 1fr;
  }

  .btn-cancel,
  .btn-save {
    flex: 1;
  }
}

</style>