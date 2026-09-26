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
        <div class="avatar" :class="`tier-bg-${m.user.tier}`">
          <img
              v-if="m.user.avatar_url"
              :src="m.user.avatar_url"
              :alt="m.user.username"
              class="avatar-img"
          />
          <template v-else>
            {{ (m.user.username || 'И').charAt(0).toUpperCase() }}
          </template>
        </div>
        <div class="member-info">
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
.members {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

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
  min-width: 0;
  text-decoration: none;
  color: inherit;
}

/* === AVATAR === */

.avatar {
  position: relative;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 10px;
  color: #fff;
  font-size: 15px;
  font-weight: 800;
  overflow: hidden;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
}

/* фоны по тиру, если нет аватарки */
.tier-bg-S { background: linear-gradient(135deg, #facc15, #d97706); }
.tier-bg-A { background: linear-gradient(135deg, #f97316, #c2410c); }
.tier-bg-B { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.tier-bg-C { background: linear-gradient(135deg, #06b6d4, #0e7490); }
.tier-bg-D { background: linear-gradient(135deg, #22c55e, #15803d); }
.tier-bg-E { background: linear-gradient(135deg, #6b7280, #374151); }

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

/* === INFO === */

.member-info {
  min-width: 0;
  flex: 1;
}

.name {
  font-weight: 700;
  font-size: 14px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta {
  color: var(--text-dim);
  font-size: 12px;
}

/* === ROLE === */

.role {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  flex-shrink: 0;
}

.role-leader { color: #facc15; background: rgba(250, 204, 21, 0.1); }
.role-officer { color: #60a5fa; background: rgba(96, 165, 250, 0.1); }
.role-member { color: var(--text-dim); background: rgba(255, 255, 255, 0.04); }

/* === KICK === */

.kick {
  padding: 6px 12px;
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
  transition: background 0.15s;
}

.kick:hover {
  background: rgba(239, 68, 68, 0.08);
}

/* === MOBILE === */

@media (max-width: 640px) {
  .member-row {
    padding: 10px 12px;
    gap: 10px;
  }

  .avatar {
    width: 38px;
    height: 38px;
    font-size: 14px;
  }

  .name {
    font-size: 13.5px;
  }

  .meta {
    font-size: 11.5px;
  }

  .role {
    padding: 3px 8px;
    font-size: 10px;
  }

  .kick {
    padding: 5px 10px;
    font-size: 11px;
  }
}
</style>