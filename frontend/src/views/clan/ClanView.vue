<script setup>
import { confirm as confirmDialog, prompt as promptDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import ClanMembers from '@/components/clan/dialogs/ClanMembers.vue'
import ClanEvents from '@/components/clan/dialogs/ClanEvents.vue'
import ClanWars from '@/components/clan/dialogs/ClanWars.vue'
import ClanApplications from '@/components/clan/dialogs/ClanApplications.vue'
import ClanEditModal from "@/components/clan/dialogs/ClanEditModal.vue";

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
  const fee = clan.value?.entry_fee ?? 0

  // Если вступление платное — предупреждаем до отправки заявки
  if (fee > 0) {
    const ok = await confirmDialog(
        `Вступление в этот клан стоит ${fee} ApexCoin. `
        + 'Деньги спишутся при принятии заявки. Отправить заявку?',
        { title: 'Платное вступление' }
    )

    if (!ok) return
  }

  const message = await promptDialog('Сообщение лидеру (опционально):')
  if (message === null) return
  await clansApi.apply(route.params.id, message)
  await load()
}

async function leave() {
  if (!await confirmDialog('Покинуть клан?')) return
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
    <TabTransition :active="tab">
      <ClanMembers
          v-if="tab === 'members'"
          :clan="clan"
          :is-leader="isLeader"
          :can-manage="canManage"
          @refresh="load"
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
    </TabTransition>

    <!--
      Окно редактирования вне цепочки вкладок: раньше оно стояло между
      v-if и v-else-if и обрывало связь между ними.
    -->
    <ClanEditModal
        v-if="showEdit"
        :clan="clan"
        @close="showEdit = false"
        @updated="load"
    />
  </div>
</template>

<style scoped>
@import "@/views/clan/ClanView.css";
</style>
