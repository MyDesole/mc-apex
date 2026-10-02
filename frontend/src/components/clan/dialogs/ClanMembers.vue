<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import UserName from "@/components/players/UserName.vue";
import { userLink } from '@/utils/links.js'

const props = defineProps({
  clan: Object,
  isLeader: Boolean,
})

const emit = defineEmits(['refresh'])

async function kick(user) {
  if (!await confirmDialog(`Кикнуть ${user.username}?`)) return
  await clansApi.kick(props.clan.id, user.id)
  emit('refresh')
}
</script>

<template>
  <div class="members">
    <div class="section-head">
      <div>
        <div class="section-title">Состав клана</div>
        <div class="section-subtitle">
          {{ clan.members?.length || 0 }} участников
        </div>
      </div>
    </div>

    <div class="members-list">
      <div
          v-for="m in clan.members"
          :key="m.id"
          class="member-card"
      >
        <RouterLink
            :to="userLink(m.user)"
            class="member-main"
        >
          <div
              class="avatar"
              :class="`tier-bg-${m.user.tier}`"
          >
            <img
                v-if="m.user.avatar_url"
                :src="m.user.avatar_url"
                :alt="m.user.username"
            >

            <template v-else>
              {{ (m.user.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>

          <div class="member-info">
            <div class="member-name">
              <UserName :user="m.user" />
            </div>

            <div class="member-meta">
              <span class="tier">{{ m.user.tier || '—' }}</span>
              <span>Тир</span>
              <i></i>
              <span>Вклад: {{ m.contribution }}</span>
            </div>
          </div>
        </RouterLink>

        <div
            class="role"
            :class="`role-${m.role}`"
        >
          <span class="role-dot"></span>

          {{
            m.role === 'leader'
                ? 'Лидер'
                : m.role === 'officer'
                    ? 'Офицер'
                    : 'Участник'
          }}
        </div>

        <button
            v-if="isLeader && m.role !== 'leader'"
            class="kick"
            @click="kick(m.user)"
        >
          Удалить
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanMembers.css";
</style>
