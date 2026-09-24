<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/admin.js'
import AdminBanClanModal from './AdminBanClanModal.vue'
import AdminClanStatsModal from './AdminClanStatsModal.vue'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const openMenu = ref(false)
const modals = ref({
  ban: false,
  stats: false,
})

async function unban() {
  if (!confirm(`Разбанить [${props.clan.tag}] ${props.clan.name}?`)) return
  await adminApi.unbanClan(props.clan.id)
  emit('updated')
}

async function removeAvatar() {
  if (!confirm('Снять аватар клана?')) return
  await adminApi.removeClanAvatar(props.clan.id)
  emit('updated')
}

async function removeCover() {
  if (!confirm('Снять подложку клана?')) return
  await adminApi.removeClanCover(props.clan.id)
  emit('updated')
}

async function destroyClan() {
  if (!confirm(`УДАЛИТЬ клан [${props.clan.tag}] ${props.clan.name}? Это необратимо.`)) return
  if (!confirm('Точно уверен? Все данные клана будут потеряны.')) return
  await adminApi.destroyClan(props.clan.id)
  emit('updated')
}

function closeAll() {
  openMenu.value = false
  Object.keys(modals.value).forEach(k => { modals.value[k] = false })
}

function open(key) {
  closeAll()
  modals.value[key] = true
}

function onUpdated() {
  closeAll()
  emit('updated')
}
</script>

<template>
  <div class="actions">
    <button class="btn-menu" @click="openMenu = !openMenu">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="12" cy="5" r="1" />
        <circle cx="12" cy="12" r="1" />
        <circle cx="12" cy="19" r="1" />
      </svg>
    </button>

    <div v-if="openMenu" class="menu">
      <button
          v-if="!clan.is_banned"
          class="menu-item danger"
          @click="open('ban')"
      >
        🚫 Забанить клан
      </button>

      <button
          v-else
          class="menu-item success"
          @click="unban"
      >
        ✅ Разбанить клан
      </button>

      <button class="menu-item" @click="open('stats')">
        📊 Статистика (победы / поражения)
      </button>

      <button
          v-if="clan.avatar"
          class="menu-item"
          @click="removeAvatar"
      >
        🖼 Снять аватар
      </button>

      <button
          v-if="clan.cover_path"
          class="menu-item"
          @click="removeCover"
      >
        🎨 Снять подложку
      </button>

      <div class="menu-divider" />

      <button class="menu-item danger" @click="destroyClan">
        🗑 Удалить клан
      </button>
    </div>

    <AdminBanClanModal
        v-if="modals.ban"
        :clan="clan"
        @close="closeAll"
        @updated="onUpdated"
    />

    <AdminClanStatsModal
        v-if="modals.stats"
        :clan="clan"
        @close="closeAll"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
.actions {
  position: relative;
  flex-shrink: 0;
}

.btn-menu {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-menu:hover {
  color: var(--text);
  border-color: var(--border-hover);
  background: var(--bg-card-hover);
}

.menu {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 100;
  min-width: 240px;
  padding: 6px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
}

.menu-item {
  display: block;
  width: 100%;
  padding: 9px 12px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s;
}

.menu-item:hover {
  color: var(--text);
  background: rgba(255, 255, 255, 0.04);
}

.menu-item.danger:hover {
  color: #f87171;
  background: rgba(239, 68, 68, 0.08);
}

.menu-item.success:hover {
  color: #4ade80;
  background: rgba(34, 197, 94, 0.08);
}

.menu-divider {
  height: 1px;
  margin: 5px 4px;
  background: var(--border);
}
</style>