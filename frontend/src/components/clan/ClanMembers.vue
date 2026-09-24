<script setup>
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'
import UserName from "@/components/UserName.vue";

const props = defineProps({
  clan: Object,
  isLeader: Boolean,
})

const emit = defineEmits(['refresh'])

async function kick(user) {
  if (!confirm(`Кикнуть ${user.username}?`)) return
  await clansApi.kick(props.clan.id, user.id)
  emit('refresh')
}
</script>

<template>
  <div class="members">
    <div
        v-for="m in clan.members"
        :key="m.id"
        class="member-row"
    >
      <RouterLink :to="`/players/${m.user.id}`" class="member-main">
        <div class="avatar">
          {{ (m.user.username || 'И').charAt(0).toUpperCase() }}
        </div>
        <div>
          <div class="name">
            <UserName :user="m.user" />
          </div>
          <div class="meta">
            Тир: {{ m.user.tier || '—' }} · Вклад: {{ m.contribution }}
          </div>
        </div>
      </RouterLink>

      <div class="role" :class="`role-${m.role}`">
        {{ m.role === 'leader' ? 'Лидер' : m.role === 'officer' ? 'Офицер' : 'Участник' }}
      </div>

      <button
          v-if="isLeader && m.role !== 'leader'"
          class="kick"
          @click="kick(m.user)"
      >
        Кик
      </button>
    </div>

  </div>
</template>

<style scoped>
.members { display: flex; flex-direction: column; gap: 8px; }

.member-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.member-main {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.avatar {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
}

.name { font-weight: 700; font-size: 14px; }
.meta { color: var(--text-dim); font-size: 12px; }

.role {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.role-leader { color: #facc15; background: rgba(250, 204, 21, 0.1); }
.role-officer { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.role-member { color: var(--text-dim); background: rgba(255, 255, 255, 0.04); }

.kick {
  padding: 6px 12px;
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
}

.kick:hover { background: rgba(239, 68, 68, 0.08); }
</style>