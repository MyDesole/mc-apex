<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const applications = ref([])
const loading = ref(true)
const processing = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await clansApi.applications(props.clan.id)
    applications.value = data.applications || []
  } finally {
    loading.value = false
  }
}

async function accept(app) {
  processing.value = app.id
  try {
    await clansApi.acceptApplication(props.clan.id, app.id)
    await load()
  } finally {
    processing.value = null
  }
}

async function decline(app) {
  processing.value = app.id
  try {
    await clansApi.declineApplication(props.clan.id, app.id)
    await load()
  } finally {
    processing.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="tab">
    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!applications.length" class="empty">
      <div class="empty__icon">📋</div>
      <div>Новых заявок нет</div>
    </div>

    <div v-else class="list">
      <div v-for="app in applications" :key="app.id" class="app">
        <RouterLink :to="userLink(app.user)" class="app__main">
          <div class="avatar">
            <img v-if="app.user.avatar_url" :src="app.user.avatar_url" />
            <template v-else>{{ app.user.username?.charAt(0).toUpperCase() }}</template>
          </div>
          <div class="info">
            <div class="name">{{ app.user.username }}</div>
            <div class="meta">
              Тир: {{ app.user.tier }} · {{ app.user.tier_score }}%
            </div>
            <p v-if="app.message" class="message">"{{ app.message }}"</p>
          </div>
        </RouterLink>

        <div class="actions">
          <button class="btn-accept" :disabled="processing === app.id" @click="accept(app)">
            Принять
          </button>
          <button class="btn-decline" :disabled="processing === app.id" @click="decline(app)">
            Отклонить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/tabs/ClanApplicationsTab.css";
</style>
