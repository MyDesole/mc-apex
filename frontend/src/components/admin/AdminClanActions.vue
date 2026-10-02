<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AdminBanClanModal from '@/components/admin/AdminBanClanModal.vue'
import AdminClanStatsModal from '@/components/admin/AdminClanStatsModal.vue'

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
  if (!await confirmDialog(`Разбанить [${props.clan.tag}] ${props.clan.name}?`)) return
  await adminApi.unbanClan(props.clan.id)
  emit('updated')
}

async function removeAvatar() {
  if (!await confirmDialog('Снять аватар клана?')) return
  await adminApi.removeClanAvatar(props.clan.id)
  emit('updated')
}

async function removeCover() {
  if (!await confirmDialog('Снять подложку клана?')) return
  await adminApi.removeClanCover(props.clan.id)
  emit('updated')
}

async function destroyClan() {
  if (!await confirmDialog(`УДАЛИТЬ клан [${props.clan.tag}] ${props.clan.name}? Это необратимо.`)) return
  if (!await confirmDialog('Точно уверен? Все данные клана будут потеряны.')) return
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
@import "@/components/admin/AdminClanActions.css";
</style>
