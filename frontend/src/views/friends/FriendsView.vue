<script setup>
/**
 * Друзья: в одном стиле с остальными страницами сообщества.
 * Шапка со сводкой, поиск по нику, вкладки; входящие заявки — отдельным
 * блоком сверху, чтобы их не пропустить; друзья — карточками с действиями.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import PageLoader from '@/components/core/PageLoader.vue'
import AppIcon from '@/components/core/AppIcon.vue'
import UserName from '@/components/players/UserName.vue'
import GiftCoinsButton from '@/components/shop/GiftCoinsButton.vue'
import { friendsApi } from '@/services/friends/friends.js'
import { chatApi } from '@/services/chat/chat.js'
import { usePresence } from '@/composables/chat/presence.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { rankLabel } from '@/utils/playerStyling.js'
import { userLink } from '@/utils/links.js'

const router = useRouter()

const loading = ref(true)
const tab = ref('friends')
const query = ref('')
const busy = ref(null)
const friends = ref([])
const incoming = ref([])
const outgoing = ref([])

async function load() {
  loading.value = true
  try {
    const data = await friendsApi.list()
    friends.value = data.friends || []
    incoming.value = (data.incoming_requests || []).map((i) => i.user)
    outgoing.value = (data.outgoing_requests || []).map((o) => o.user)
  } finally {
    loading.value = false
  }
}

async function act(userId, action) {
  busy.value = userId
  try {
    await action()
    await load()
  } finally {
    busy.value = null
  }
}

function accept(user) {
  return act(user.id, () => friendsApi.accept(user.id))
}

async function remove(user, text) {
  if (!await confirmDialog(text)) return
  return act(user.id, () => friendsApi.remove(user.id))
}

async function write(user) {
  busy.value = user.id
  try {
    const result = await chatApi.startDirect(user.id)
    if (result?.conversation?.id) router.push(`/messages/${result.conversation.id}`)
  } finally {
    busy.value = null
  }
}

const TABS = computed(() => [
  ['friends', 'Друзья', friends.value.length],
  ['outgoing', 'Отправленные', outgoing.value.length],
])

const { isOnline } = usePresence()

// Кто в сети — первыми
const list = computed(() => {
  const source = tab.value === 'friends' ? friends.value : outgoing.value
  const q = query.value.trim().toLowerCase()
  const found = q ? source.filter((u) => u.username?.toLowerCase().includes(q)) : source
  return [...found].sort((a, b) => Number(isOnline(b.id)) - Number(isOnline(a.id)))
})

function rankOf(user) {
  const label = rankLabel(user, null)
  if (!label) return 'Без тира'
  return `${user.display_rank?.type === 'bridge' ? 'Звание' : 'Тир'} ${label}`
}

onMounted(load)
</script>

<template>
  <div class="fr">
    <header class="fr-head">
      <div>
        <span class="fr-head__kicker"><AppIcon icon="handshake" :size="14" /> Сообщество</span>
        <h1>Друзья</h1>
        <p>Друзья видят твой профиль в своих списках, им можно подарить монеты и предметы, а в студии — поставить в блок «Тиммейты».</p>
      </div>

      <dl class="fr-head__stats">
        <div><dt>Друзей</dt><dd>{{ friends.length }}</dd></div>
        <div><dt>Заявок</dt><dd :class="{ 'is-hot': incoming.length }">{{ incoming.length }}</dd></div>
        <div><dt>Отправлено</dt><dd>{{ outgoing.length }}</dd></div>
      </dl>
    </header>

    <PageLoader v-if="loading" />

    <template v-else>
      <!-- Входящие заявки: сверху, чтобы не пропустить -->
      <section v-if="incoming.length" class="fr-requests">
        <h2>Хотят дружить <span>{{ incoming.length }}</span></h2>
        <div class="fr-requests__list">
          <div v-for="user in incoming" :key="`in-${user.id}`" class="fr-request">
            <RouterLink :to="userLink(user)" class="fr-person">
              <span class="fr-avatar">
                <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.username" />
                <template v-else>{{ (user.username || '?').charAt(0).toUpperCase() }}</template>
              </span>
              <span class="fr-person__text">
                <UserName :user="user" />
                <small>{{ rankOf(user) }}</small>
              </span>
            </RouterLink>
            <div class="fr-request__actions">
              <button type="button" class="fr-btn fr-btn--primary" :disabled="busy === user.id" @click="accept(user)">Принять</button>
              <button type="button" class="fr-btn" :disabled="busy === user.id" @click="remove(user, 'Отклонить заявку в друзья?')">Отклонить</button>
            </div>
          </div>
        </div>
      </section>

      <div class="fr-bar">
        <div class="fr-tabs">
          <button
              v-for="[id, label, count] in TABS"
              :key="id"
              type="button"
              :class="{ 'is-on': tab === id }"
              @click="tab = id"
          >{{ label }} <b>{{ count }}</b></button>
        </div>

        <label class="fr-search">
          <AppIcon icon="target" :size="14" />
          <input v-model="query" placeholder="Найти по нику" />
        </label>
      </div>

      <p v-if="!list.length" class="fr-empty">
        <template v-if="query">Никого с таким ником.</template>
        <template v-else-if="tab === 'friends'">
          Друзей пока нет. Зайди в профиль игрока и нажми «В друзья» —
          <RouterLink to="/players">найти игроков в рейтинге</RouterLink>.
        </template>
        <template v-else>Отправленных заявок нет.</template>
      </p>

      <div v-else class="fr-grid">
        <article
            v-for="user in list"
            :key="user.id"
            class="fr-card"
            :class="{ 'is-online': isOnline(user.id) }"
            :style="{ '--fr-c': user.banner_color || user.accent_color || '#8b5cf6' }"
        >
          <!-- Полоса в цвет профиля друга -->
          <div class="fr-card__banner">
            <span v-if="tab === 'friends'" class="fr-card__status">
              <i></i>{{ isOnline(user.id) ? 'В сети' : 'Не в сети' }}
            </span>
          </div>

          <RouterLink :to="userLink(user)" class="fr-card__person">
            <span class="fr-avatar fr-avatar--card">
              <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.username" />
              <template v-else>{{ (user.username || '?').charAt(0).toUpperCase() }}</template>
            </span>
            <span class="fr-card__name"><UserName :user="user" /></span>
            <small v-if="user.profile_title" class="fr-card__title">{{ user.profile_title }}</small>
          </RouterLink>

          <div class="fr-card__chips">
            <span class="fr-chip" :class="{ 'is-muted': rankOf(user) === 'Без тира' }">{{ rankOf(user) }}</span>
            <span v-if="user.clan_tag" class="fr-chip fr-chip--clan" :style="{ '--clan': user.clan_color || '#a8a3b8' }">[{{ user.clan_tag }}]</span>
          </div>

          <div class="fr-card__actions">
            <template v-if="tab === 'friends'">
              <button type="button" class="fr-btn fr-btn--primary fr-btn--wide" :disabled="busy === user.id" @click="write(user)">
                <AppIcon icon="send" :size="13" /> Написать
              </button>
              <GiftCoinsButton :user="user">
                <template #trigger="{ open }">
                  <button type="button" class="fr-btn fr-btn--icon fr-btn--gift" title="Подарить ApexCoin" @click="open">
                    <AppIcon icon="gift" :size="14" />
                  </button>
                </template>
              </GiftCoinsButton>
              <button type="button" class="fr-btn fr-btn--icon fr-btn--danger" title="Удалить из друзей" :disabled="busy === user.id" @click="remove(user, 'Удалить из друзей?')">
                <AppIcon icon="trash" :size="14" />
              </button>
            </template>
            <button
                v-else
                type="button"
                class="fr-btn fr-btn--wide"
                :disabled="busy === user.id"
                @click="remove(user, 'Отменить заявку?')"
            >Отменить заявку</button>
          </div>
        </article>
      </div>
    </template>
  </div>
</template>

<style scoped>
@import "@/views/friends/FriendsView.css";
</style>
