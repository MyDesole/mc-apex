<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'
import { useAuthStore } from '@/stores/auth'
import ClanMembers from '@/components/clan/ClanMembers.vue'
import ClanEvents from '@/components/clan/ClanEvents.vue'
import ClanWars from '@/components/clan/ClanWars.vue'

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const tab = ref('members')

const clan = computed(() => data.value?.clan)
const isMember = computed(() => data.value?.is_member)
const isLeader = computed(() => clan.value?.leader_id === auth.user?.id)

async function load() {
  loading.value = true
  try {
    data.value = await clansApi.show(route.params.id)
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

onMounted(load)
</script>

<template>
  <div v-if="loading" class="loading">Загрузка...</div>

  <div v-else-if="clan" class="clan-page">
    <!-- HEADER -->
    <header class="clan-header" :style="{ borderColor: clan.banner_color }">
      <div class="banner" :style="{ background: clan.banner_color }">
        {{ clan.tag?.charAt(0) }}
      </div>

      <div class="clan-title">
        <h1>
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h1>
        <p v-if="clan.description">{{ clan.description }}</p>
        <div class="stats">
          <div class="stat"><b>{{ clan.power }}</b><span>сила</span></div>
          <div class="stat"><b>{{ data.members_count }}</b><span>участников</span></div>
          <div class="stat"><b class="win">{{ clan.wins }}</b><span>побед</span></div>
          <div class="stat"><b class="loss">{{ clan.losses }}</b><span>поражений</span></div>
        </div>
      </div>

      <div class="header-actions">
        <button v-if="!isMember && !data.application" class="btn-apply" @click="apply">
          Подать заявку
        </button>
        <div v-else-if="data.application" class="applied">
          Заявка отправлена
        </div>
        <button v-if="isMember && !isLeader" class="btn-leave" @click="leave">
          Покинуть клан
        </button>
      </div>
    </header>

    <!-- TABS -->
    <nav class="tabs">
      <button :class="{ active: tab === 'members' }" @click="tab = 'members'">
        Участники
      </button>
      <button :class="{ active: tab === 'events' }" @click="tab = 'events'">
        Мероприятия
      </button>
      <button :class="{ active: tab === 'wars' }" @click="tab = 'wars'">
        Войны
      </button>
    </nav>

    <ClanMembers
        v-if="tab === 'members'"
        :clan="clan"
        :is-leader="isLeader"
        @refresh="load"
    />

    <ClanEvents
        v-else-if="tab === 'events'"
        :clan="clan"
        :can-manage="isLeader || isMember"
    />

    <ClanWars
        v-else
        :clan="clan"
        :incoming="data.incoming_wars"
        :outgoing="data.outgoing_wars"
        :is-member="isMember"
        :is-leader="isLeader"
        @refresh="load"
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

.clan-header {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 28px;
  background: var(--bg-card);
  border: 2px solid var(--border);
  border-radius: 18px;
  margin-bottom: 28px;
}

.banner {
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
}

.clan-title { flex: 1; }

.clan-title h1 {
  margin: 0 0 8px;
  font-size: 26px;
  font-weight: 900;
}

.tag { color: var(--accent-light); }

.clan-title p {
  margin: 0 0 12px;
  color: var(--text-dim);
  font-size: 13px;
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
  color: var(--accent-light);
}

.stat b.win { color: #22c55e; }
.stat b.loss { color: #ef4444; }

.stat span {
  font-size: 10px;
  color: var(--text-dim);
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.header-actions { display: flex; gap: 10px; }

.btn-apply,
.btn-leave {
  padding: 11px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-apply {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.btn-leave {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-leave:hover { color: #f87171; border-color: rgba(239, 68, 68, 0.3); }

.applied {
  padding: 11px 20px;
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.1);
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
}

.tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

.tabs button {
  padding: 12px 20px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}
</style>