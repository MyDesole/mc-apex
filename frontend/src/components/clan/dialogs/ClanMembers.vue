<script setup>
/**
 * Состав клана на публичной странице: поиск по нику, фильтр по роли и сортировка.
 */
import { computed, ref } from 'vue'
import { rankLabel } from '@/utils/playerStyling.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import UserName from '@/components/players/UserName.vue'
import PlayerHead from '@/components/players/PlayerHead.vue'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  clan: Object,
  isLeader: Boolean,
})

const emit = defineEmits(['refresh'])

const search = ref('')
const role = ref('all')
const sort = ref('role')

const ROLE_ORDER = { leader: 0, officer: 1, member: 2 }
const ROLE_LABEL = { leader: 'Лидер', officer: 'Офицер', member: 'Участник' }

const members = computed(() => (props.clan?.members ?? []).filter((m) => m.user))

const counts = computed(() => ({
  all: members.value.length,
  officer: members.value.filter((m) => m.role === 'officer').length,
  member: members.value.filter((m) => m.role === 'member').length,
  online: members.value.filter((m) => isOnline(m.user)).length,
}))

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()

  const list = members.value.filter((m) => {
    if (q && !m.user.username.toLowerCase().includes(q)) return false
    if (role.value === 'online') return isOnline(m.user)
    if (role.value !== 'all' && m.role !== role.value && !(role.value === 'officer' && m.role === 'leader')) return false
    return true
  })

  const byRating = (a, b) => (b.user.rating_points ?? 0) - (a.user.rating_points ?? 0)

  return [...list].sort((a, b) => {
    if (sort.value === 'rating') return byRating(a, b)
    if (sort.value === 'new') return new Date(b.joined_at || b.created_at) - new Date(a.joined_at || a.created_at)
    if (sort.value === 'name') return a.user.username.localeCompare(b.user.username)
    return (ROLE_ORDER[a.role] - ROLE_ORDER[b.role]) || byRating(a, b)
  })
})

function isOnline(user) {
  return user?.last_seen_at && Date.now() - new Date(user.last_seen_at).getTime() < 5 * 60 * 1000
}

function joined(m) {
  const at = m.joined_at || m.created_at
  if (!at) return ''
  const days = Math.floor((Date.now() - new Date(at).getTime()) / 86400000)
  return days < 1 ? 'в клане с сегодня' : `в клане ${days} дн`
}

async function kick(user) {
  if (!await confirmDialog(`Кикнуть ${user.username}?`)) return
  await clansApi.kick(props.clan.id, user.id)
  emit('refresh')
}
</script>

<template>
  <div class="members">
    <div class="toolbar">
      <input v-model="search" type="search" class="toolbar__search" placeholder="Поиск по нику" />

      <div class="toolbar__chips">
        <button type="button" :class="{ on: role === 'all' }" @click="role = 'all'">Все <b>{{ counts.all }}</b></button>
        <button type="button" :class="{ on: role === 'officer' }" @click="role = 'officer'">Руководство <b>{{ counts.officer + 1 }}</b></button>
        <button type="button" :class="{ on: role === 'member' }" @click="role = 'member'">Участники <b>{{ counts.member }}</b></button>
        <button type="button" :class="{ on: role === 'online' }" @click="role = 'online'">
          <i class="dot" /> В сети <b>{{ counts.online }}</b>
        </button>
      </div>

      <select v-model="sort" class="toolbar__sort" aria-label="Сортировка">
        <option value="role">По роли</option>
        <option value="rating">По рейтингу</option>
        <option value="new">Новые сначала</option>
        <option value="name">По нику</option>
      </select>
    </div>

    <p v-if="!shown.length" class="empty">Никого не нашлось.</p>

    <div class="members-list">
      <div
          v-for="m in shown"
          :key="m.id"
          class="member-card"
          :class="`member-card--${m.role}`"
      >
        <RouterLink :to="userLink(m.user)" class="member-main">
          <span class="member-head">
            <PlayerHead :user="m.user" :size="40" />
            <i v-if="isOnline(m.user)" class="member-online" />
          </span>

          <div class="member-info">
            <div class="member-name">
              <UserName :user="m.user" />
              <span v-if="m.title" class="member-title">{{ m.title }}</span>
            </div>

            <div class="member-meta">
              <span class="tier">{{ rankLabel(m.user) }}</span>
              <i></i>
              <span>{{ m.user.rating_points ?? 0 }} очк.</span>
              <i></i>
              <span>вклад {{ m.contribution }}</span>
              <i></i>
              <span>{{ joined(m) }}</span>
            </div>
          </div>
        </RouterLink>

        <div class="role" :class="`role-${m.role}`">
          <span class="role-dot"></span>
          {{ ROLE_LABEL[m.role] }}
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
@import "@/components/clan/dialogs/ClanMembers.css";
@import "@/components/clan/dialogs/ClanMembersToolbar.css";
</style>
