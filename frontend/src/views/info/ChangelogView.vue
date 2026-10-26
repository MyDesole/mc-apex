<script setup>
/**
 * Чейнджлог: лента обновлений (/changelog) и отдельный пост
 * (/changelog/:slug) — ссылку на пост отправляют в Telegram-канал.
 */
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ChangelogPost from '@/components/project/ChangelogPost.vue'
import { projectApi } from '@/services/project/project.js'

const route = useRoute()

const entries = ref([])
const entry = ref(null)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const loadingMore = ref(false)
const error = ref('')

const slug = computed(() => route.params.slug || null)

async function load() {
  loading.value = true
  error.value = ''
  entry.value = null

  try {
    if (slug.value) {
      entry.value = (await projectApi.entry(slug.value)).entry
      document.title = `${entry.value.title} · Обновления APEX`
    } else {
      const data = await projectApi.changelog(1)
      entries.value = data.data ?? []
      page.value = 1
      lastPage.value = data.meta?.last_page ?? 1
    }
  } catch (e) {
    error.value = e.status === 404 ? 'Такого обновления нет — возможно, его удалили.' : (e.message || 'Не удалось загрузить обновления.')
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  loadingMore.value = true

  try {
    const data = await projectApi.changelog(page.value + 1)
    entries.value.push(...(data.data ?? []))
    page.value = data.meta?.current_page ?? page.value + 1
    lastPage.value = data.meta?.last_page ?? lastPage.value
  } finally {
    loadingMore.value = false
  }
}

watch(slug, load, { immediate: true })
</script>

<template>
  <div class="changelog">
    <header class="changelog__head">
      <RouterLink v-if="slug" to="/changelog" class="changelog__back">← Все обновления</RouterLink>
      <h1>{{ slug ? 'Обновление' : 'Что нового' }}</h1>
      <p v-if="!slug">
        История изменений APEX: новые функции, улучшения и исправления.
        Планы на будущее — в <RouterLink to="/roadmap">роадмапе</RouterLink>.
      </p>
    </header>

    <div v-if="loading" class="changelog__muted">Загружаем…</div>
    <div v-else-if="error" class="alert alert--error" role="alert">{{ error }}</div>

    <ChangelogPost v-else-if="entry" :entry="entry" single />

    <template v-else>
      <p v-if="!entries.length" class="changelog__muted">Пока записей нет — скоро здесь появятся обновления.</p>

      <div class="changelog__list">
        <ChangelogPost v-for="item in entries" :key="item.id" :entry="item" />
      </div>

      <button
          v-if="page < lastPage"
          type="button"
          class="btn btn-secondary changelog__more"
          :disabled="loadingMore"
          @click="loadMore"
      >
        {{ loadingMore ? 'Загружаем…' : 'Показать ещё' }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.changelog {
  max-width: 820px;
  margin: 0 auto;
  padding: 28px 16px 56px;
}

.changelog__head {
  margin-bottom: 20px;
}

.changelog__head h1 {
  margin: 6px 0 8px;
  font-family: var(--font-display);
  font-size: 30px;
}

.changelog__head p,
.changelog__muted {
  margin: 0;
  color: var(--text-dim);
}

.changelog__head a {
  color: var(--accent-light);
}

.changelog__back {
  font-size: 13px;
  font-weight: 700;
}

.changelog__list {
  display: grid;
  gap: 14px;
}

.changelog__more {
  display: block;
  margin: 18px auto 0;
}
</style>
