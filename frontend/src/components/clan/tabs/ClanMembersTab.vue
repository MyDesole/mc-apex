<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { myClanApi } from '@/services/clan/myClan.js'
import { useAuthStore } from '@/stores/core/auth.js'
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
@import "@/components/clan/tabs/ClanMembersTab.css";
</style>
