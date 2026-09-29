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
            :to="`/players/${m.user.id}`"
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
.members {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.section-head {
  display: flex;
  justify-content: space-between;
}

.section-title {
  font-size: 17px;
  font-weight: 850;
}

.section-subtitle {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 11px;
}

.members-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.member-card {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 10px 12px;
  background:
      linear-gradient(90deg, rgba(124,58,237,.025), transparent),
      var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: .18s;
}

.member-card:hover {
  border-color: rgba(139,92,246,.25);
  transform: translateX(2px);
}

.member-main {
  min-width: 0;
  flex: 1;
  display: flex;
  align-items: center;
  gap: 11px;
  text-decoration: none;
  color: inherit;
}

.avatar {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 11px;
  color: #fff;
  font-size: 14px;
  font-weight: 850;
}

.avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tier-bg-S { background: linear-gradient(135deg,#facc15,#d97706); }
.tier-bg-A { background: linear-gradient(135deg,#f97316,#c2410c); }
.tier-bg-B { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
.tier-bg-C { background: linear-gradient(135deg,#06b6d4,#0e7490); }
.tier-bg-D { background: linear-gradient(135deg,#22c55e,#15803d); }
.tier-bg-E { background: linear-gradient(135deg,#6b7280,#374151); }

.member-info {
  min-width: 0;
}

.member-name {
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 10px;
}

.member-meta i {
  width: 3px;
  height: 3px;
  background: var(--text-muted);
  border-radius: 50%;
  opacity: .5;
}

.tier {
  color: #c4b5fd;
  font-weight: 850;
}

.role {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  padding: 5px 9px;
  border-radius: 7px;
  font-size: 9px;
  font-weight: 850;
}

.role-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
}

.role-leader {
  color: #facc15;
  background: rgba(250,204,21,.08);
}

.role-leader .role-dot { background: #facc15; }

.role-officer {
  color: #60a5fa;
  background: rgba(96,165,250,.08);
}

.role-officer .role-dot { background: #60a5fa; }

.role-member {
  color: var(--text-muted);
  background: rgba(255,255,255,.035);
}

.role-member .role-dot { background: var(--text-muted); }

.kick {
  padding: 6px 9px;
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239,68,68,.18);
  border-radius: 7px;
  font-size: 9px;
  font-weight: 800;
  cursor: pointer;
  transition: .18s;
}

.kick:hover {
  background: rgba(239,68,68,.07);
  border-color: rgba(239,68,68,.35);
}

@media (max-width: 600px) {
  .member-card {
    gap: 8px;
  }

  .role {
    padding: 4px 6px;
  }

  .role-dot {
    display: none;
  }

  .kick {
    padding: 5px 7px;
  }
}
</style>