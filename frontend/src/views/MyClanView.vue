<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { myClanApi } from '@/services/myClan.js'

import ClanNewsTab from '@/components/my-clan/ClanNewsTab.vue'
import ClanForumTab from '@/components/my-clan/ClanForumTab.vue'
import ClanMembersTab from '@/components/my-clan/ClanMembersTab.vue'
import ClanApplicationsTab from '@/components/my-clan/ClanApplicationsTab.vue'
import ClanWarsTab from '@/components/my-clan/ClanWarsTab.vue'
import ClanResourcesTab from '@/components/my-clan/ClanResourcesTab.vue'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const data = ref(null)
const tab = ref('forum')

const clan = computed(() => data.value?.clan)
const permissions = computed(() => data.value?.my_permissions ?? {})

const tabs = computed(() => {
  const base = [
    { id: 'forum', label: '💬 Форум', show: true },
    { id: 'resources', label: '📦 Ресурсы', show: true },
    { id: 'members', label: '👥 Участники', show: true },
  ]

  if (permissions.value.applications) {
    base.splice(1, 0, {
      id: 'applications',
      label: '📋 Заявки',
      badge: data.value?.stats?.applications,
    })
  }

  if (permissions.value.wars) {
    base.push({ id: 'wars', label: '⚔️ Войны' })
  }

  if (permissions.value.news) {
    base.unshift({ id: 'news', label: '📰 Новости' })
  }

  return base.filter(t => t.show)
})

async function load() {
  loading.value = true
  try {
    data.value = await myClanApi.dashboard()

    if (!data.value.clan) {
      router.push('/clans')
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="loading">
    <div class="spinner" />
    <span>Загрузка клана...</span>
  </div>

  <div v-else-if="clan" class="my-clan-page">
    <!-- ===== HEADER ===== -->
    <header
        class="clan-header"
        :style="{ '--clan-color': clan.banner_color || '#7c3aed' }"
    >
      <div class="clan-banner">
        <img
            v-if="clan.avatar_url"
            :src="clan.avatar_url"
            :alt="clan.name"
            class="avatar-img"
        />
        <template v-else>{{ clan.tag?.charAt(0) || 'C' }}</template>
      </div>

      <div class="clan-info">
        <h1>
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h1>
        <p class="role-line">
          Ваша роль:
          <span class="role-badge" :class="`role-${data.my_role}`">
                        {{ data.my_role === 'leader' ? '👑 Лидер'
              : data.my_role === 'officer' ? '⚔️ Офицер'
                  : '👤 Участник' }}
                    </span>
        </p>
      </div>

      <div class="clan-stats">
        <div class="stat">
          <span class="stat__value">{{ data.stats.members }}</span>
          <span class="stat__label">участников</span>
        </div>
        <div class="stat">
          <span class="stat__value power">{{ clan.power }}</span>
          <span class="stat__label">силы</span>
        </div>
        <div class="stat">
          <span class="stat__value win">{{ clan.wins }}</span>
          <span class="stat__label">побед</span>
        </div>
        <div class="stat">
          <span class="stat__value loss">{{ clan.losses }}</span>
          <span class="stat__label">поражений</span>
        </div>
      </div>
    </header>

    <!-- ===== TABS ===== -->
    <nav class="tabs">
      <button
          v-for="t in tabs"
          :key="t.id"
          :class="{ active: tab === t.id }"
          @click="tab = t.id"
      >
        {{ t.label }}
        <span v-if="t.badge" class="tab-badge">{{ t.badge }}</span>
      </button>
    </nav>

    <!-- ===== CONTENT ===== -->
    <div class="tab-content">
      <ClanNewsTab
          v-if="tab === 'news'"
          :clan="clan"
          :permissions="permissions"
      />
      <ClanForumTab
          v-else-if="tab === 'forum'"
          :clan="clan"
          :permissions="permissions"
      />
      <ClanApplicationsTab
          v-else-if="tab === 'applications'"
          :clan="clan"
      />
      <ClanMembersTab
          v-else-if="tab === 'members'"
          :clan="clan"
          :my-role="data.my_role"
      />
      <ClanWarsTab
          v-else-if="tab === 'wars'"
          :clan="clan"
      />
      <ClanResourcesTab
          v-else-if="tab === 'resources'"
          :clan="clan"
          :permissions="permissions"
      />
    </div>
  </div>
</template>

<style scoped>
/* ============================================
   MY CLAN PAGE
   ============================================ */

.my-clan-page {
  width: min(1100px, calc(100% - 40px));
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ============================================
   LOADING
   ============================================ */

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ============================================
   HEADER
   ============================================ */

.clan-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 24px 28px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  overflow: hidden;
}

/* Цветная полоска слева в цвет клана */
.clan-header::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--clan-color);
  box-shadow: 0 0 20px var(--clan-color);
}

/* Мягкое свечение в углу */
.clan-header::after {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: radial-gradient(
      circle,
      color-mix(in srgb, var(--clan-color) 15%, transparent),
      transparent 70%
  );
  pointer-events: none;
}

.clan-banner {
  position: relative;
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--clan-color);
  border-radius: 16px;
  color: #fff;
  font-size: 32px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 8px 30px color-mix(in srgb, var(--clan-color) 40%, transparent);
  z-index: 1;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.clan-info {
  flex: 1;
  min-width: 0;
  z-index: 1;
}

.clan-info h1 {
  margin: 0 0 8px;
  font-size: 26px;
  font-weight: 900;
  letter-spacing: -0.5px;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-info h1 .tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.role-line {
  margin: 0;
  color: var(--text-dim);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.role-badge {
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  border: 1px solid;
}

.role-leader {
  color: #facc15;
  background: rgba(250, 204, 21, 0.1);
  border-color: rgba(250, 204, 21, 0.35);
}

.role-officer {
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.1);
  border-color: rgba(96, 165, 250, 0.35);
}

.role-member {
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border-color: var(--border);
}

/* Stats */
.clan-stats {
  display: flex;
  gap: 24px;
  padding-left: 24px;
  border-left: 1px solid var(--border);
  z-index: 1;
  flex-shrink: 0;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 60px;
}

.stat__value {
  font-size: 22px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}

.stat__value.power { color: #a78bfa; }
.stat__value.win { color: #4ade80; }
.stat__value.loss { color: #f87171; }

.stat__label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 700;
  margin-top: 2px;
}

/* ============================================
   TABS
   ============================================ */

.tabs {
  display: flex;
  gap: 4px;
  padding-bottom: 2px;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}

.tabs::-webkit-scrollbar {
  display: none;
}

.tabs button {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 12px 18px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
  transition: color 0.2s, border-color 0.2s;
}

.tabs button:hover {
  color: var(--text);
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

/* ============================================
   CONTENT
   ============================================ */

.tab-content {
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ============================================
   АДАПТИВ
   ============================================ */

@media (max-width: 900px) {
  .clan-stats {
    gap: 16px;
    padding-left: 16px;
  }

  .stat {
    min-width: 48px;
  }

  .stat__value {
    font-size: 18px;
  }
}

@media (max-width: 700px) {
  .my-clan-page {
    width: calc(100% - 24px);
    margin: 20px auto;
    gap: 16px;
  }

  .clan-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .clan-banner {
    width: 64px;
    height: 64px;
    font-size: 26px;
    border-radius: 14px;
  }

  .clan-info h1 {
    font-size: 20px;
  }

  .clan-stats {
    width: 100%;
    padding-left: 0;
    padding-top: 14px;
    border-left: 0;
    border-top: 1px solid var(--border);
    justify-content: space-between;
  }

  .stat {
    align-items: center;
    min-width: 0;
    flex: 1;
  }

  .tabs button {
    padding: 10px 14px;
    font-size: 13px;
  }

  .tabs button.active::after {
    left: 14px;
    right: 14px;
  }
}
</style>