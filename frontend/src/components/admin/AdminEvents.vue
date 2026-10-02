<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'

const events = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

let timer = null

async function load() {
  loading.value = true
  try {
    const data = await adminApi.events({
      search: search.value,
      page: page.value,
    })
    events.value = data.data
    lastPage.value = data.last_page
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 300)
})

watch(page, load)

async function remove(event) {
  if (!await confirmDialog(`Удалить пост «${event.title}»?`)) return
  await adminApi.deleteEvent(event.id)
  await load()
}

const typeLabels = {
  announcement: 'Анонс',
  event: 'Мероприятие',
  training: 'Тренировка',
}

onMounted(load)
</script>

<template>
  <div>
    <input
        v-model="search"
        type="text"
        placeholder="Поиск по заголовку или тексту..."
        class="search"
    />

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!events.length" class="empty">Нет постов</div>

    <div v-else class="list">
      <div v-for="e in events" :key="e.id" class="event-row">
        <div class="event-head">
                    <span class="type" :class="`type-${e.type}`">
                        {{ typeLabels[e.type] }}
                    </span>

          <RouterLink :to="`/clans/${e.clan.id}`" class="clan">
            [{{ e.clan.tag }}] {{ e.clan.name }}
          </RouterLink>

          <span class="date">
                        {{ new Date(e.created_at).toLocaleString('ru-RU') }}
                    </span>
        </div>

        <h3 class="title">{{ e.title }}</h3>

        <p v-if="e.body" class="body">{{ e.body }}</p>

        <div class="actions">
                    <span class="author">
                        Автор: {{ e.author?.username }}
                    </span>
          <button class="btn-del" @click="remove(e)">
            Удалить
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button :disabled="page <= 1" @click="page--">←</button>
      <span>{{ page }} / {{ lastPage }}</span>
      <button :disabled="page >= lastPage" @click="page++">→</button>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminEvents.css";
</style>
