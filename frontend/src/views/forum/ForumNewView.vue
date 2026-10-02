<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { forumApi } from '@/services/forum/forum.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'
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

const availableCategories = computed(() =>
    categories.value.filter((category) => category.can_post)
)

const selectedCategory = computed(() =>
    availableCategories.value.find(
        (category) =>
            String(category.id) === String(categoryId.value)
    ) || null
)

const validation = computed(() => {
  const problems = []

  if (!categoryId.value) {
    problems.push('выбери раздел')
  }

  if (title.value.trim().length < 4) {
    problems.push('заголовок от 4 символов')
  }

  if (body.value.trim().length < 4) {
    problems.push('текст от 4 символов')
  }

  return problems
})

const ready = computed(() => validation.value.length === 0)

const titleProgress = computed(() =>
    Math.min((title.value.length / 200) * 100, 100)
)

const bodyProgress = computed(() =>
    Math.min((body.value.length / 30000) * 100, 100)
)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await forumApi.index()

    categories.value = data.categories ?? []

    const wanted = route.query.category

    const fromQuery = wanted
        ? availableCategories.value.find(
            (category) => category.slug === wanted
        )
        : null

    categoryId.value =
        (fromQuery || availableCategories.value[0])?.id ?? null
  } catch (e) {
    error.value =
        e.message || 'Не удалось загрузить разделы.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  error.value = ''

  if (!ready.value) {
    error.value =
        'Заполни форму: ' +
        validation.value.join(', ') +
        '.'

    return
  }

  sending.value = true

  try {
    const result = await forumApi.createTopic({
      category_id: Number(categoryId.value),
      title: title.value.trim(),
      body: body.value.trim(),
      attachments: attachments.value.map((attachment) => attachment.id),
    })

    if (!result?.topic?.id) {
      error.value =
          'Сервер не вернул созданную тему. Попробуй ещё раз.'

      return
    }

    router.push({
      name: 'forum-topic',
      params: { id: result.topic.id },
    })
  } catch (e) {
    if (e.status === 401) {
      error.value = 'Сессия истекла — войди заново.'

      router.push({
        name: 'login',
        query: { redirect: route.fullPath },
      })

      return
    }

    if (e.status === 403) {
      error.value =
          e.message ||
          'В этом разделе тебе нельзя создавать темы.'

      return
    }

    if (e.errors) {
      error.value = Object.values(e.errors)
          .flat()
          .join(' ')

      return
    }

    error.value =
        e.message || 'Не удалось создать тему.'
  } finally {
    sending.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="new-topic">
    <!-- HEADER -->
    <header class="page-header">
      <RouterLink
          class="back-link"
          :to="{ name: 'forum' }"
      >
        <span class="back-link__icon">
          <AppIcon
              icon="send"
              :size="13"
          />
        </span>

        <span>Вернуться к форуму</span>
      </RouterLink>

      <div class="eyebrow">
        <span class="eyebrow__line"></span>
        COMMUNITY / NEW TOPIC
      </div>

      <h1 class="page-title">
        Новая <span>тема</span>
      </h1>

      <p class="page-subtitle">
        Создай обсуждение, задай вопрос или поделись
        опытом с игроками APEX.
      </p>
    </header>

    <!-- ERROR -->
    <div
        v-if="error"
        class="alert"
    >
      <span class="alert__icon">
        <AppIcon
            icon="close"
            :size="15"
        />
      </span>

      <div>
        <strong>Не удалось опубликовать</strong>
        <span>{{ error }}</span>
      </div>
    </div>

    <!-- LOADING -->
    <div
        v-if="loading"
        class="loading-state"
    >
      <div class="loading-state__spinner"></div>

      <span>Загружаем разделы...</span>
    </div>

    <!-- NO CATEGORIES -->
    <div
        v-else-if="!availableCategories.length"
        class="empty-state"
    >
      <div class="empty-state__icon">
        <AppIcon
            icon="shield"
            :size="25"
        />
      </div>

      <strong>Создание тем недоступно</strong>

      <span>
        У тебя пока нет разделов, где можно создавать темы.
      </span>

      <RouterLink
          class="empty-state__button"
          :to="{ name: 'forum' }"
      >
        Вернуться на форум
        <AppIcon
            icon="send"
            :size="13"
        />
      </RouterLink>
    </div>

    <!-- FORM -->
    <form
        v-else
        class="editor"
        @submit.prevent="submit"
    >
      <!-- CATEGORY -->
      <section class="editor-section">
        <div class="section-heading">
          <div class="section-heading__number">
            01
          </div>

          <div>
            <span>ПУБЛИКАЦИЯ</span>
            <h2>Выбери раздел</h2>
          </div>
        </div>

        <div class="category-select">
          <select
              v-model="categoryId"
              class="select"
          >
            <option
                v-for="category in availableCategories"
                :key="category.id"
                :value="category.id"
            >
              {{ category.name }}
            </option>
          </select>

          <div class="category-select__arrow">
            <AppIcon
                icon="send"
                :size="13"
            />
          </div>
        </div>

        <div
            v-if="selectedCategory"
            class="category-preview"
            :style="{
            '--category-color':
              selectedCategory.color || 'var(--accent)',
          }"
        >
          <div class="category-preview__icon">
            <AppIcon
                :icon="selectedCategory.icon || 'globe'"
                :size="18"
            />
          </div>

          <div class="category-preview__body">
            <strong>
              {{ selectedCategory.name }}
            </strong>

            <span>
              {{ selectedCategory.description }}
            </span>
          </div>

          <div class="category-preview__check">
            <AppIcon
                icon="check"
                :size="14"
            />
          </div>
        </div>
      </section>

      <!-- TITLE -->
      <section class="editor-section">
        <div class="section-heading">
          <div class="section-heading__number">
            02
          </div>

          <div>
            <span>ЗАГОЛОВОК</span>
            <h2>О чём тема?</h2>
          </div>
        </div>

        <div class="field">
          <input
              v-model="title"
              class="input input--title"
              type="text"
              maxlength="200"
              placeholder="Например: как правильно ставить блоки в PvP?"
          >

          <div class="field-footer">
            <span>
              Сделай заголовок коротким и понятным
            </span>

            <span>
              {{ title.length }} / 200
            </span>
          </div>

          <div class="progress">
            <span
                :style="{ width: `${titleProgress}%` }"
            ></span>
          </div>
        </div>
      </section>

      <!-- BODY -->
      <section class="editor-section">
        <div class="section-heading">
          <div class="section-heading__number">
            03
          </div>

          <div>
            <span>СОДЕРЖАНИЕ</span>
            <h2>Расскажи подробнее</h2>
          </div>
        </div>

        <div class="field">
          <textarea
              v-model="body"
              class="textarea"
              rows="12"
              maxlength="30000"
              placeholder="Подробно опиши ситуацию, вопрос или свою идею..."
          ></textarea>

          <div class="field-footer">
            <span>
              Чем больше деталей — тем проще помочь
            </span>

            <span>
              {{ body.length }} / 30000
            </span>
          </div>

          <div class="progress">
            <span
                :style="{ width: `${bodyProgress}%` }"
            ></span>
          </div>
        </div>
      </section>

      <!-- ATTACHMENTS -->
      <section class="editor-section">
        <div class="section-heading">
          <div class="section-heading__number">
            04
          </div>

          <div>
            <span>МАТЕРИАЛЫ</span>
            <h2>Вложения</h2>
          </div>
        </div>

        <div class="attachments">
          <div class="attachments__header">
            <div class="attachments__icon">
              <AppIcon
                  icon="frame"
                  :size="18"
              />
            </div>

            <div>
              <strong>Добавь изображения</strong>
              <span>
                Скриншоты помогут лучше объяснить ситуацию
              </span>
            </div>
          </div>

          <ForumAttachmentsInput
              v-model="attachments"
          />
        </div>
      </section>

      <!-- VALIDATION -->
      <div
          v-if="!ready"
          class="validation"
      >
        <div class="validation__icon">
          <AppIcon
              icon="target"
              :size="15"
          />
        </div>

        <div>
          <strong>Осталось заполнить</strong>

          <span>
            {{ validation.join(' · ') }}
          </span>
        </div>
      </div>

      <!-- ACTIONS -->
      <footer class="form-actions">
        <RouterLink
            class="cancel-button"
            :to="{ name: 'forum' }"
        >
          Отмена
        </RouterLink>

        <button
            class="publish-button"
            type="submit"
            :disabled="sending"
        >
          <span class="publish-button__icon">
            <AppIcon
                icon="send"
                :size="15"
            />
          </span>

          <span>
            <small>APEX COMMUNITY</small>
            {{ sending ? 'Публикуем...' : 'Опубликовать тему' }}
          </span>

          <AppIcon
              class="publish-button__arrow"
              icon="send"
              :size="14"
          />
        </button>
      </footer>
    </form>
  </main>
</template>

<style scoped>
@import "@/views/forum/ForumNewView.css";
</style>
