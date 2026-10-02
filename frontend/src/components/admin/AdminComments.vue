<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { adminApi } from '@/services/core/admin.js'
import { userLink, clanLink } from '@/utils/links.js'

const comments = ref([])
const loading = ref(true)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

let timer = null

async function load() {
  loading.value = true
  try {
    const data = await adminApi.comments({
      search: search.value,
      page: page.value,
    })
    comments.value = data.data
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

async function remove(comment) {
  if (!await confirmDialog('Удалить комментарий?')) return
  await adminApi.deleteComment(comment.id)
  await load()
}

function formatDate(date) {
  return new Date(date).toLocaleString('ru-RU')
}

onMounted(load)
</script>

<template>
  <div>
    <input
        v-model="search"
        type="text"
        placeholder="Поиск по тексту..."
        class="search"
    />

    <div v-if="loading" class="empty">Загрузка...</div>
    <div v-else-if="!comments.length" class="empty">Нет комментариев</div>

    <div v-else class="list">
      <div v-for="c in comments" :key="c.id" class="comment-row">
        <div class="comment-main">
          <RouterLink :to="userLink(c.user)" class="author">
            {{ c.user.username }}
          </RouterLink>

          <span class="where">
                        в
                        <RouterLink :to="clanLink({ id: c.event.clan_id })">
                            [{{ c.event.clan?.tag }}] {{ c.event.clan?.name }}
                        </RouterLink>
                        · «{{ c.event.title }}»
                    </span>

          <span class="date">{{ formatDate(c.created_at) }}</span>
        </div>

        <p class="body">{{ c.body }}</p>

        <div class="actions">
          <button class="btn-del" @click="remove(c)">
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
@import "@/components/admin/AdminComments.css";
</style>
