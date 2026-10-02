<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { friendsApi } from '@/services/friends/friends.js'
import GiftCoinsButton from '@/components/shop/GiftCoinsButton.vue'
import UserName from "@/components/players/UserName.vue";
import { userLink } from '@/utils/links.js'

const loading = ref(true)
const tab = ref('friends')
const friends = ref([])
const incoming = ref([])
const outgoing = ref([])

async function load() {
  loading.value = true
  try {
    const data = await friendsApi.list()
    friends.value = data.friends || []
    incoming.value = data.incoming_requests || []
    outgoing.value = data.outgoing_requests || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function accept(userId) {
  await friendsApi.accept(userId)
  await load()
}

async function remove(userId) {
  if (!await confirmDialog('Удалить из друзей?')) return
  await friendsApi.remove(userId)
  await load()
}

onMounted(load)

const currentList = computed(() => {
  if (tab.value === 'friends') return friends.value
  if (tab.value === 'incoming') return incoming.value.map(i => i.user)
  return outgoing.value.map(o => o.user)
})
</script>

<template>
  <div class="friends-page">
    <h1>Друзья</h1>

    <div class="tabs">
      <button :class="{ active: tab === 'friends' }" @click="tab = 'friends'">
        Друзья ({{ friends.length }})
      </button>
      <button :class="{ active: tab === 'incoming' }" @click="tab = 'incoming'">
        Входящие ({{ incoming.length }})
      </button>
      <button :class="{ active: tab === 'outgoing' }" @click="tab = 'outgoing'">
        Исходящие ({{ outgoing.length }})
      </button>
    </div>

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!currentList.length" class="empty">
      {{ tab === 'friends' ? 'Пока нет друзей' : 'Пусто' }}
    </div>

    <div v-else class="friends-list">
      <div v-for="user in currentList" :key="user.id" class="friend-row">
        <RouterLink :to="userLink(user)" class="friend-main">
          <div class="avatar" :class="`tier-bg-${user.tier}`">
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                :alt="user.username"
                class="avatar-img"
            />
            <template v-else>
              {{ (user.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>
          <div class="friend-info">
            <div class="name">
              <UserName :user="user" />
            </div>
            <div class="tier">Тир: {{ user.tier || '—' }}</div>
          </div>
        </RouterLink>

        <div class="actions">
          <button
              v-if="tab === 'incoming'"
              class="btn-accept"
              @click="accept(user.id)"
          >
            Принять
          </button>

          <GiftCoinsButton v-if="tab === 'friends'" :user="user" />

          <button
              v-if="tab === 'friends' || tab === 'outgoing'"
              class="btn-remove"
              @click="remove(user.id)"
          >
            {{ tab === 'friends' ? 'Удалить' : 'Отменить' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/views/friends/FriendsView.css";
</style>
