<script setup>
import PageLoader from '@/components/core/PageLoader.vue'
import { socialHref } from '@/utils/socialLinks.js'
import { confirm as confirmDialog, prompt as promptDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import ClanMembers from '@/components/clan/dialogs/ClanMembers.vue'
import ClanEvents from '@/components/clan/dialogs/ClanEvents.vue'
import ClanWars from '@/components/clan/dialogs/ClanWars.vue'
import ClanApplications from '@/components/clan/dialogs/ClanApplications.vue'
import TabTransition from '@/components/core/TabTransition.vue'
import ClanEditModal from "@/components/clan/dialogs/ClanEditModal.vue";
import PageState from '@/components/core/PageState.vue'
import ShareToChatButton from '@/components/chat/ShareToChatButton.vue'
import ClanInviteDialog from '@/components/clan/dialogs/ClanInviteDialog.vue'

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const tab = ref('members')
const applicationsCount = ref(0)
const showEdit = ref(false)
const showInvite = ref(false)

const clan = computed(() => data.value?.clan)

// Цвета шапки: тема клана, а без неё — цвет баннера
const heroVars = computed(() => {
  const t = clan.value?.style?.theme
  const base = clan.value?.banner_color || '#7c3aed'

  return {
    '--c1': t?.from || base,
    '--c2': t?.to || '#06b6d4',
  }
})

const winrate = computed(() => {
  const w = Number(clan.value?.wins || 0)
  const l = Number(clan.value?.losses || 0)

  return w + l === 0 ? 0 : Math.round((w / (w + l)) * 100)
})
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

/** Ошибка загрузки: 404 или прочая — без неё страница оставалась пустой. */
const loadError = ref(null)

async function load() {
  loading.value = true
  loadError.value = null
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
  } catch (e) {
    loadError.value = e?.status === 404 ? 'notFound' : 'error'
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
  <PageLoader v-if="loading" />

  <div v-else-if="clan" class="clan-page">
    <!-- HEADER -->
    <header
        class="clan-header"
        :class="{ 'clan-header--cover': clan.cover_url }"
        :style="heroVars"
    >
      <!-- Фон: обложка клана или градиент темы, поверх — свечение и сетка -->
      <div
          class="hero-bg"
          :style="clan.cover_url ? { backgroundImage: `url(${clan.cover_url})` } : null"
          aria-hidden="true"
      />
      <div class="hero-shade" aria-hidden="true" />
      <div class="hero-grid" aria-hidden="true" />
      <div class="hero-orb hero-orb--a" aria-hidden="true" />
      <div class="hero-orb hero-orb--b" aria-hidden="true" />

      <div class="hero-main">
        <div class="crest">
          <span class="crest__ring" aria-hidden="true" />
          <div class="banner">
            <img
                v-if="clan.avatar_url"
                :src="clan.avatar_url"
                alt=""
                class="banner-img"
            />
            <template v-else>{{ clan.tag?.charAt(0) }}</template>
          </div>
          <span v-if="clan.style?.emblem" class="crest__emblem" :title="clan.style.emblem.title">
            {{ clan.style.emblem.glyph }}
          </span>
        </div>

        <div class="clan-title">
          <div class="hero-chips">
            <span class="chip chip--level">Уровень {{ clan.level || 1 }}</span>
            <span class="chip" :class="clan.is_open ? 'chip--open' : 'chip--closed'">
              {{ clan.is_open ? 'Открытый набор' : 'Закрытый набор' }}
            </span>
            <span v-if="clan.entry_fee > 0" class="chip chip--fee">Вход: {{ clan.entry_fee }} ₳</span>
          </div>

          <h1>
            <span class="tag" :style="clan.style?.tag_color ? { color: clan.style.tag_color } : null">[{{ clan.tag }}]</span>
            {{ clan.name }}
          </h1>

          <p v-if="clan.style?.motto" class="hero-motto">«{{ clan.style.motto }}»</p>
          <p v-if="clan.description" class="hero-desc">{{ clan.description }}</p>

          <!-- «Кого ищем» -->
          <p v-if="clan.style?.recruit_text" class="hero-recruit">
            🔎 {{ clan.style.recruit_text }}
          </p>

          <!-- Порог набора: заявителю видно до подачи -->
          <p v-if="!isMember && clan.min_rating > 0" class="min-rating-note">
            Заявки принимаются от {{ clan.min_rating }} очков рейтинга. По приглашению — без порога.
          </p>
        </div>
      </div>

      <div class="hero-stats">
        <div class="stat stat--power">
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
        <div class="stat stat--rate">
          <b>{{ winrate }}%</b>
          <span>винрейт</span>
          <i class="rate-bar"><u :style="{ width: `${winrate}%` }" /></i>
        </div>
      </div>

      <div class="hero-bottom">
        <div class="hero-bottom__left">

        <div v-if="hasSocials" class="clan-socials">
          <!-- «@канал» и ссылки без https превращаем в рабочие ссылки -->
          <component
              :is="socialHref(key, url) ? 'a' : 'span'"
              v-for="(url, key) in clan.socials"
              :key="key"
              v-show="url"
              :href="socialHref(key, url) || undefined"
              :target="socialHref(key, url) ? '_blank' : undefined"
              :rel="socialHref(key, url) ? 'noopener' : undefined"
              class="social-link"
              :class="key"
              :title="socialHref(key, url) ? socialLabels[key] : `${socialLabels[key]}: ${url}`"
          >
            <span class="social-label">{{ socialLabels[key] }}</span>
          </component>
        </div>
      </div>

      <div class="header-actions">

        <ShareToChatButton v-if="clan?.id" :path="`/clans/${clan.id}`" label="Поделиться" />

        <button v-if="canManage" class="btn-settings" @click="showInvite = true">
          Пригласить игрока
        </button>

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

        <!-- Свой клан: ведём во внутренний раздел с отметками и войнами -->
        <RouterLink
            v-if="isMember"
            to="/my-clan"
            class="btn-apply"
        >
          Открыть «Мой клан»
        </RouterLink>

        <button
            v-if="isMember && !isLeader"
            class="btn-leave"
            @click="leave"
        >
          Покинуть клан
        </button>
      </div>
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

    <ClanInviteDialog v-if="showInvite" :clan="clan" @close="showInvite = false" />
  </div>

  <PageState
      v-else
      :kind="loadError || 'notFound'"
      :title="loadError === 'error' ? null : 'Клан не найден'"
      :text="loadError === 'error' ? null : 'Возможно, клан распущен или переименован.'"
      :action="{ to: '/clans', label: 'Все кланы' }"
  />
</template>

<style scoped>
@import "@/views/clan/ClanView.css";
</style>
