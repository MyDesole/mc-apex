<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum.js'
import { useAuthStore } from '@/stores/auth'
import AppIcon from '@/components/AppIcon.vue'
import ForumAttachmentsInput from '@/components/forum/ForumAttachmentsInput.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const sending = ref(false)
const error = ref('')

const categories = ref([])

const categoryId = ref(null)
const title = ref('')
const body = ref('')
const attachments = ref([])

const availableCategories = computed(() => categories.value.filter((c) => c.can_post))

const selectedCategory = computed(() =>
    availableCategories.value.find((c) => String(c.id) === String(categoryId.value)) || null
)

// Подсказки, чего не хватает для публикации
const validation = computed(() => {
  const problems = []

  if (!categoryId.value) problems.push('выбери раздел')
  if (title.value.trim().length < 4) problems.push('заголовок от 4 символов')
  if (body.value.trim().length < 4) problems.push('текст от 4 символов')

  return problems
})

const ready = computed(() => validation.value.length === 0)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await forumApi.index()

    categories.value = data.categories ?? []

    // Раздел из ссылки ?category=slug либо первый доступный
    const wanted = route.query.category
    const fromQuery = wanted
        ? availableCategories.value.find((c) => c.slug === wanted)
        : null

    categoryId.value = (fromQuery || availableCategories.value[0])?.id ?? null
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить разделы.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  error.value = ''

  if (!ready.value) {
    error.value = 'Заполни форму: ' + validation.value.join(', ') + '.'
    return
  }

  sending.value = true

  try {
    const result = await forumApi.createTopic({
      category_id: Number(categoryId.value),
      title: title.value.trim(),
      body: body.value.trim(),
      attachments: attachments.value.map((a) => a.id),
    })

    if (!result?.topic?.id) {
      error.value = 'Сервер не вернул созданную тему. Попробуй ещё раз.'
      return
    }

    router.push({ name: 'forum-topic', params: { id: result.topic.id } })
  } catch (e) {
    if (e.status === 401) {
      error.value = 'Сессия истекла — войди заново.'
      router.push({ name: 'login', query: { redirect: route.fullPath } })
      return
    }

    if (e.status === 403) {
      error.value = e.message || 'В этом разделе тебе нельзя создавать темы.'
      return
    }

    if (e.errors) {
      error.value = Object.values(e.errors).flat().join(' ')
      return
    }

    error.value = e.message || 'Не удалось создать тему.'
  } finally {
    sending.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="new-topic">
    <RouterLink class="back" :to="{ name: 'forum' }">
      ← Назад к форуму
    </RouterLink>

    <h1 class="new-topic__title">Новая тема</h1>
    <p class="new-topic__subtitle">
      Опиши вопрос подробно: чем больше деталей, тем быстрее помогут.
    </p>

    <!-- Ошибку показываем всегда, в любом состоянии страницы -->
    <p v-if="error" class="alert">{{ error }}</p>

    <p v-if="loading" class="state">Загружаем разделы…</p>

    <p v-else-if="!availableCategories.length" class="state">
      У тебя пока нет разделов, где можно создавать темы.
      <RouterLink class="link" :to="{ name: 'forum' }">
        Вернуться на форум
      </RouterLink>
    </p>

    <form v-else class="form" @submit.prevent="submit">
      <label class="field">
        <span class="field__label">Раздел</span>
        <select v-model="categoryId" class="select">
          <option v-for="category in availableCategories" :key="category.id" :value="category.id">
            {{ category.name }}
          </option>
        </select>
        <small v-if="selectedCategory" class="field__hint">
          {{ selectedCategory.description }}
        </small>
      </label>

      <label class="field">
        <span class="field__label">Заголовок</span>
        <input
            v-model="title"
            class="input"
            type="text"
            maxlength="200"
            placeholder="Например: как правильно ставить блоки в PvP?"
        >
        <small class="field__hint">{{ title.length }} / 200</small>
      </label>

      <label class="field">
        <span class="field__label">Текст</span>
        <textarea
            v-model="body"
            class="textarea"
            rows="12"
            maxlength="30000"
            placeholder="Подробно опиши ситуацию…"
        />
        <small class="field__hint">{{ body.length }} / 30000</small>
      </label>

      <div class="field">
        <span class="field__label">Вложения</span>
        <ForumAttachmentsInput v-model="attachments" />
      </div>

      <p v-if="!ready" class="hint-warn">
        Осталось: {{ validation.join(', ') }}
      </p>

      <div class="form__actions">
        <RouterLink class="btn btn-secondary" :to="{ name: 'forum' }">
          Отмена
        </RouterLink>

        <button
            class="btn btn-primary"
            type="button"
            :disabled="sending"
            @click="submit"
        >
          <AppIcon icon="send" :size="15" />
          {{ sending ? 'Публикуем…' : 'Опубликовать тему' }}
        </button>
      </div>
    </form>
  </main>
</template>

<style scoped>
.new-topic {
  width: min(760px, calc(100% - 40px));
  margin: 34px auto 80px;
}

.back {
  display: inline-block;
  margin-bottom: 16px;
  color: var(--text-dim);
  font-size: 13px;
  font-weight: 600;
}

.back:hover {
  color: var(--accent-light);
}

.new-topic__title {
  margin: 0 0 6px;
  font-size: 26px;
  font-weight: 900;
}

.new-topic__subtitle {
  margin: 0 0 22px;
  color: var(--text-dim);
  font-size: 14px;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 22px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.field__label {
  color: var(--text);
  font-size: 13px;
  font-weight: 700;
}

.field__hint {
  color: var(--text-muted);
  font-size: 12px;
}

.input,
.textarea,
.select {
  width: 100%;
  padding: 11px 14px;
  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-family: inherit;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s ease;
}

.input:focus,
.textarea:focus,
.select:focus {
  border-color: var(--accent);
}

.textarea {
  resize: vertical;
  line-height: 1.6;
}

.select {
  cursor: pointer;
}

.form__actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding-top: 6px;
}

.form__actions .btn-primary {
  display: inline-flex;
  gap: 7px;
  align-items: center;
}

.hint-warn {
  margin: 0;
  padding: 10px 14px;
  color: #fbbf24;
  background: rgba(251, 191, 36, 0.1);
  border: 1px solid rgba(251, 191, 36, 0.3);
  border-radius: 10px;
  font-size: 13px;
}

.state {
  padding: 40px 0;
  color: var(--text-dim);
  text-align: center;
}

.link {
  color: var(--accent-light);
}

.alert {
  margin: 0 0 16px;
  padding: 12px 16px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.35);
  border-radius: 10px;
  font-size: 13px;
}
</style>
