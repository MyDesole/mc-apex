<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  clan: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const loading = ref(true)
const applications = ref([])
const processing = ref(null)

async function load() {
  loading.value = true
  try {
    const data = await clansApi.applications(props.clan.id)
    applications.value = data.applications || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function accept(app) {
  processing.value = app.id
  try {
    await clansApi.acceptApplication(props.clan.id, app.id)
    await load()
    emit('refresh')
  } finally {
    processing.value = null
  }
}

async function decline(app) {
  processing.value = app.id
  try {
    await clansApi.declineApplication(props.clan.id, app.id)
    await load()
    emit('refresh')
  } finally {
    processing.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="applications">
    <div class="section-head">
      <div>
        <div class="section-title">Заявки</div>
        <div class="section-subtitle">Игроки, которые хотят вступить в клан</div>
      </div>

      <div v-if="applications.length" class="count-badge">
        {{ applications.length }}
      </div>
    </div>

    <div v-if="loading" class="state-card">
      <div class="loader"></div>
      <span>Загружаем заявки...</span>
    </div>

    <div v-else-if="!applications.length" class="state-card state-empty">
      <div class="state-icon">✓</div>
      <strong>Новых заявок нет</strong>
      <span>Когда кто-нибудь подаст заявку, она появится здесь</span>
    </div>

    <div v-else class="apps-list">
      <article
          v-for="app in applications"
          :key="app.id"
          class="app-card"
      >
        <RouterLink
            :to="userLink(app.user)"
            class="app-user"
        >
          <div class="avatar">
            <img
                v-if="app.user.avatar_url"
                :src="app.user.avatar_url"
                :alt="app.user.username"
            />
            <template v-else>
              {{ (app.user.username || 'И').charAt(0).toUpperCase() }}
            </template>
          </div>

          <div class="user-info">
            <div class="user-name">{{ app.user.username }}</div>

            <div class="user-meta">
              <span class="tier">
                {{ app.user.tier || '—' }}
              </span>
              <span>Тир</span>
              <i></i>
              <span>{{ app.user.tier_score ?? 0 }}%</span>
              <span>рейтинг</span>
            </div>
          </div>
        </RouterLink>

        <div v-if="app.message" class="app-message">
          <span class="message-mark">“</span>
          <span>{{ app.message }}</span>
        </div>

        <div v-if="canManage" class="app-actions">
          <button
              class="action action-accept"
              :disabled="processing === app.id"
              @click="accept(app)"
          >
            <span>✓</span>
            Принять
          </button>

          <button
              class="action action-decline"
              :disabled="processing === app.id"
              @click="decline(app)"
          >
            Отклонить
          </button>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanApplications.css";
</style>
