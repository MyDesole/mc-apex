<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AdminBanModal from '@/components/admin/AdminBanModal.vue'
import AdminRoleModal from '@/components/admin/AdminRoleModal.vue'
import AdminAchievementsModal from '@/components/admin/AdminAchievementsModal.vue'
import AdminAspectsModal from '@/components/admin/AdminAspectsModal.vue'
import AdminTierTestModal from '@/components/admin/AdminTierTestModal.vue'

const props = defineProps({
  user: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const openMenu = ref(false)

const modals = ref({
  ban: false,
  role: false,
  achievements: false,
  aspects: false,
  tierTest: false,
})

async function unban() {
  if (!await confirmDialog(`Разбанить ${props.user.username}?`)) return
  await adminApi.unban(props.user.id)
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
          v-if="!user.is_banned"
          class="menu-item danger"
          @click="open('ban')"
      >
        🚫 Забанить
      </button>

      <button
          v-else
          class="menu-item success"
          @click="unban"
      >
        ✅ Разбанить
      </button>

      <button class="menu-item" @click="open('role')">
        👑 Изменить роль
      </button>

      <button class="menu-item" @click="open('achievements')">
        🏆 Ачивки
      </button>

      <button class="menu-item" @click="open('aspects')">
        ⚙️ Аспекты
      </button>

      <button class="menu-item" @click="open('tierTest')">
        🎯 Провести тир-тест
      </button>
    </div>

    <AdminBanModal
        v-if="modals.ban"
        :user="user"
        @close="closeAll"
        @updated="onUpdated"
    />

    <AdminRoleModal
        v-if="modals.role"
        :user="user"
        @close="closeAll"
        @updated="onUpdated"
    />

    <AdminAchievementsModal
        v-if="modals.achievements"
        :user="user"
        @close="closeAll"
        @updated="onUpdated"
    />

    <AdminAspectsModal
        v-if="modals.aspects"
        :user="user"
        @close="closeAll"
        @updated="onUpdated"
    />

    <AdminTierTestModal
        v-if="modals.tierTest"
        :user="user"
        @close="closeAll"
        @updated="onUpdated"
    />
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminUserActions.css";
</style>
