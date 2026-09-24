<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'
import ClanMembers from '@/components/clan/ClanMembers.vue'
import ClanEvents from '@/components/clan/ClanEvents.vue'
import ClanWars from '@/components/clan/ClanWars.vue'
import ClanApplications from '@/components/clan/ClanApplications.vue'
import ClanEditModal from "@/components/clan/ClanEditModal.vue";

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const tab = ref('members')
const applicationsCount = ref(0)
const showEdit = ref(false)

const clan = computed(() => data.value?.clan)
const isMember = computed(() => data.value?.is_member)
const isLeader = computed(() => clan.value?.leader_id === auth.user?.id)

/**
 * Может ли текущий юзер управлять кланом:
 * лидер или офицер.
 */
const canManage = computed(() => {
  if (!isMember.value) return false
  if (isLeader.value) return true

  const me = clan.value?.members?.find(m => m.user_id === auth.user?.id)
  return me?.role === 'officer'
})

async function load() {
  loading.value = true
  try {
    data.value = await clansApi.show(route.params.id)

    // Если лидер/офицер — тянем количество заявок
    if (canManage.value) {
      try {
        const apps = await clansApi.applications(route.params.id)
        applicationsCount.value = apps.applications?.length ?? 0
      } catch {
        applicationsCount.value = 0
      }
    }
  } finally {
    loading.value = false
  }
}

async function apply() {
  const message = prompt('Сообщение лидеру (опционально):')
  if (message === null) return
  await clansApi.apply(route.params.id, message)
  await load()
}

async function leave() {
  if (!confirm('Покинуть клан?')) return
  await clansApi.leave(route.params.id)
  await load()
}
const hasSocials = computed(() => {
  return clan.value?.socials && Object.values(clan.value.socials).some(v => v)
})

const socialLabels = {
  discord: 'Discord',
  telegram: 'Telegram',
  youtube: 'YouTube',
  vk: 'VK',
  website: 'Сайт',
}
function onApplicationsChanged() {
  applicationsCount.value = 0
  load()
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="loading">Загрузка...</div>

  <div v-else-if="clan" class="clan-page">
    <!-- HEADER -->
    <header
        class="clan-header"
        :style="clan.cover_url ? {
        backgroundImage: `linear-gradient(rgba(10,10,15,0.75), rgba(10,10,15,0.9)), url(${clan.cover_url})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    } : {}"
    >
      <div
          class="banner"
          :style="{
            background: clan.banner_color,
            boxShadow: `0 8px 30px ${clan.banner_color}50`,
        }"
      >
        <img
            v-if="clan.avatar_url"
            :src="clan.avatar_url"
            alt=""
            class="banner-img"
        />
        <template v-else>{{ clan.tag?.charAt(0) }}</template>
      </div>

      <div class="clan-title">
        <h1>
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h1>

        <p v-if="clan.description">{{ clan.description }}</p>

        <div class="stats">
          <div class="stat">
            <b class="power">{{ clan.power }}</b>
            <span>сила</span>
          </div>
          <div class="stat">
            <b>{{ data.members_count }}</b>
            <span>участников</span>
          </div>
          <div class="stat">
            <b class="win">{{ clan.wins }}</b>
            <span>побед</span>
          </div>
          <div class="stat">
            <b class="loss">{{ clan.losses }}</b>
            <span>поражений</span>
          </div>
        </div>

        <div v-if="hasSocials" class="clan-socials">
          <a
              v-for="(url, key) in clan.socials"
              :key="key"
              v-show="url"
              :href="url"
              target="_blank"
              rel="noopener"
              class="social-link"
              :class="key"
              :title="socialLabels[key]"
          >
            <span class="social-label">{{ socialLabels[key] }}</span>
          </a>
        </div>
      </div>

      <div class="header-actions">

        <button v-if="isLeader" class="btn-settings" @click="showEdit = true">
          Настройки
        </button>

        <button
            v-if="!isMember && !data.application"
            class="btn-apply"
            @click="apply"
        >
          Подать заявку
        </button>



        <div v-else-if="data.application" class="applied">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Заявка отправлена
        </div>

        <button
            v-if="isMember && !isLeader"
            class="btn-leave"
            @click="leave"
        >
          Покинуть клан
        </button>
      </div>
    </header>

    <!-- TABS -->
    <nav class="tabs">
      <button
          :class="{ active: tab === 'members' }"
          @click="tab = 'members'"
      >
        Участники
      </button>

      <button
          :class="{ active: tab === 'events' }"
          @click="tab = 'events'"
      >
        Мероприятия
      </button>

      <button
          :class="{ active: tab === 'wars' }"
          @click="tab = 'wars'"
      >
        Войны
      </button>

      <button
          v-if="canManage"
          :class="{ active: tab === 'applications' }"
          @click="tab = 'applications'"
      >
        Заявки
        <span v-if="applicationsCount > 0" class="tab-badge">
                    {{ applicationsCount }}
                </span>
      </button>
    </nav>

    <!-- CONTENT -->
    <ClanMembers
        v-if="tab === 'members'"
        :clan="clan"
        :is-leader="isLeader"
        :can-manage="canManage"
        @refresh="load"
    />
    <ClanEditModal
        v-if="showEdit"
        :clan="clan"
        @close="showEdit = false"
        @updated="load"
    />
    <ClanEvents
        v-else-if="tab === 'events'"
        :clan="clan"
        :can-manage="canManage"
    />

    <ClanWars
        v-else-if="tab === 'wars'"
        :clan="clan"
        :incoming="data.incoming_wars"
        :outgoing="data.outgoing_wars"
        :is-member="isMember"
        :is-leader="isLeader"
        @refresh="load"
    />

    <ClanApplications
        v-else-if="tab === 'applications'"
        :clan="clan"
        :can-manage="canManage"
        @refresh="onApplicationsChanged"
    />
  </div>
</template>

<style scoped>
.clan-page {
  width: min(1000px, calc(100% - 40px));
  margin: 40px auto;
}

.loading {
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
}

/* === HEADER === */

.clan-header {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 28px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  margin-bottom: 28px;
}

.banner {
  position: relative;          /* ← для абсолютного позиционирования img */
  width: 84px;
  height: 84px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  color: #fff;
  font-size: 36px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
  transition: box-shadow 0.3s ease;
}

.banner-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}
.clan-title {
  flex: 1;
  min-width: 0;
}

.clan-title h1 {
  margin: 0 0 8px;
  font-size: 26px;
  font-weight: 900;
  letter-spacing: -0.5px;
}

.tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.clan-title p {
  margin: 0 0 12px;
  color: var(--text-dim);
  font-size: 13px;
  line-height: 1.5;
}

.stats {
  display: flex;
  gap: 24px;
}

.stat {
  display: flex;
  flex-direction: column;
}

.stat b {
  font-size: 18px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}
.clan-socials {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  transform: translateY(-1px);
  color: #fff;
}

/* Цвета под каждую соцсеть */
.social-link.discord:hover {
  background: #5865f2;
  border-color: #5865f2;
  box-shadow: 0 4px 15px rgba(88, 101, 242, 0.35);
}

.social-link.telegram:hover {
  background: #229ed9;
  border-color: #229ed9;
  box-shadow: 0 4px 15px rgba(34, 158, 217, 0.35);
}

.social-link.youtube:hover {
  background: #ff0000;
  border-color: #ff0000;
  box-shadow: 0 4px 15px rgba(255, 0, 0, 0.35);
}

.social-link.vk:hover {
  background: #0077ff;
  border-color: #0077ff;
  box-shadow: 0 4px 15px rgba(0, 119, 255, 0.35);
}

.social-link.website:hover {
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.35);
}

.social-label {
  line-height: 1;
}
.stat b.power { color: #a78bfa; }
.stat b.win { color: #4ade80; }
.stat b.loss { color: #f87171; }

.stat span {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

/* === ACTIONS === */

.header-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.btn-apply,
.btn-leave {
  padding: 11px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.2s ease;
  white-space: nowrap;
}
.btn-settings {
  padding: 11px 20px;
  color: var(--text);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-settings:hover {
  border-color: var(--accent);
  color: var(--accent-light);
}
.btn-apply {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.btn-apply:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 7px 25px rgba(124, 58, 237, 0.35);
}

.btn-leave {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-leave:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

.applied {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 11px 20px;
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.2);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

/* === TABS === */

.tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
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
  padding: 12px 20px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
  transition: color 0.2s ease, border-color 0.2s ease;
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
  margin-left: 8px;
  padding: 0 5px;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

/* === АДАПТИВ === */

@media (max-width: 700px) {
  .clan-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .banner {
    width: 64px;
    height: 64px;
    font-size: 28px;
    border-radius: 14px;
  }

  .clan-title h1 {
    font-size: 20px;
  }

  .stats {
    gap: 18px;
    flex-wrap: wrap;
  }

  .header-actions {
    width: 100%;
  }

  .btn-apply,
  .btn-leave,
  .applied {
    flex: 1;
    justify-content: center;
    text-align: center;
  }
  .banner {
    position: relative;
    overflow: hidden;
  }

  .banner-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .tabs button {
    padding: 10px 14px;
    font-size: 13px;
  }
}
</style>