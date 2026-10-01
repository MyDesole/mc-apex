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
.new-topic {
  width: min(820px, calc(100% - 40px));
  margin: 30px auto 90px;
}

/* =========================================================
   HEADER
========================================================= */

.page-header {
  margin-bottom: 24px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  margin-bottom: 22px;

  color: var(--text-muted);

  font-size: 11px;
  font-weight: 800;

  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--text);
}

.back-link__icon {
  display: flex;

  transform: rotate(180deg);
}

.eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;

  color: var(--accent-light, #a78bfa);

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1.5px;
}

.eyebrow__line {
  width: 22px;
  height: 2px;

  background: currentColor;
  border-radius: 99px;

  box-shadow: 0 0 12px currentColor;
}

.page-title {
  margin: 7px 0 7px;

  color: var(--text);

  font-size: clamp(30px, 5vw, 42px);
  line-height: 1;
  font-weight: 950;
  letter-spacing: -1.7px;
}

.page-title span {
  color: transparent;

  background:
      linear-gradient(
          135deg,
          #c4b5fd,
          var(--accent),
          #7c3aed
      );

  -webkit-background-clip: text;
  background-clip: text;
}

.page-subtitle {
  max-width: 600px;

  margin: 0;

  color: var(--text-dim);

  font-size: 13px;
  line-height: 1.55;
}

/* =========================================================
   ALERT
========================================================= */

.alert {
  display: flex;
  align-items: flex-start;
  gap: 11px;

  margin-bottom: 16px;
  padding: 13px 15px;

  color: #fca5a5;

  background: rgba(239, 68, 68, 0.07);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 11px;
}

.alert__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 27px;
  height: 27px;
  flex-shrink: 0;

  background: rgba(239, 68, 68, 0.1);
  border-radius: 7px;
}

.alert div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.alert strong {
  font-size: 11px;
  font-weight: 850;
}

.alert span:last-child {
  font-size: 11px;
  line-height: 1.45;
}

/* =========================================================
   LOADING / EMPTY
========================================================= */

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;

  min-height: 280px;

  color: var(--text-muted);
  font-size: 11px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 15px;
}

.loading-state__spinner {
  width: 22px;
  height: 22px;

  border: 2px solid rgba(139, 92, 246, 0.15);
  border-top-color: var(--accent);

  border-radius: 50%;

  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;

  padding: 60px 20px;

  text-align: center;

  background:
      radial-gradient(
          circle at 50% 0,
          rgba(139, 92, 246, 0.08),
          transparent 45%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 15px;
}

.empty-state__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 55px;
  height: 55px;
  margin-bottom: 14px;

  color: var(--accent-light, #a78bfa);

  background: rgba(139, 92, 246, 0.08);
  border: 1px solid rgba(139, 92, 246, 0.15);
  border-radius: 14px;
}

.empty-state strong {
  color: var(--text);

  font-size: 15px;
  font-weight: 850;
}

.empty-state > span {
  max-width: 320px;
  margin-top: 5px;

  color: var(--text-muted);

  font-size: 11px;
  line-height: 1.5;
}

.empty-state__button {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  margin-top: 17px;
  padding: 9px 13px;

  color: #fff;
  background: rgba(139, 92, 246, 0.14);
  border: 1px solid rgba(139, 92, 246, 0.28);
  border-radius: 8px;

  font-size: 11px;
  font-weight: 800;
}

/* =========================================================
   EDITOR
========================================================= */

.editor {
  position: relative;

  overflow: hidden;

  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.025),
          transparent 30%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 16px;
}

.editor::before {
  content: "";

  position: absolute;
  top: 0;
  left: 0;
  right: 0;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139, 92, 246, 0.45),
          transparent
      );
}

.editor-section {
  padding: 24px;

  border-bottom: 1px solid var(--border);
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 11px;

  margin-bottom: 17px;
}

.section-heading__number {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 30px;
  height: 30px;
  flex-shrink: 0;

  color: var(--accent-light, #a78bfa);

  background: rgba(139, 92, 246, 0.08);
  border: 1px solid rgba(139, 92, 246, 0.16);
  border-radius: 8px;

  font-size: 9px;
  font-weight: 900;
}

.section-heading > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.section-heading span {
  color: var(--text-muted);

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1px;
}

.section-heading h2 {
  margin: 0;

  color: var(--text);

  font-size: 14px;
  font-weight: 850;
}

/* =========================================================
   CATEGORY
========================================================= */

.category-select {
  position: relative;
}

.select {
  width: 100%;
  height: 45px;

  padding: 0 42px 0 13px;

  color: var(--text);

  appearance: none;

  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  outline: none;

  font-family: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;

  transition: border-color 0.2s ease;
}

.select:focus {
  border-color: rgba(139, 92, 246, 0.55);
}

.category-select__arrow {
  position: absolute;
  top: 50%;
  right: 14px;

  color: var(--text-muted);

  pointer-events: none;

  transform: translateY(-50%) rotate(90deg);
}

.category-preview {
  display: flex;
  align-items: center;
  gap: 10px;

  margin-top: 9px;
  padding: 10px 12px;

  background: color-mix(
      in srgb,
      var(--category-color) 4%,
      var(--bg)
  );

  border: 1px solid color-mix(
      in srgb,
      var(--category-color) 17%,
      var(--border)
  );

  border-radius: 10px;
}

.category-preview__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 35px;
  height: 35px;
  flex-shrink: 0;

  color: var(--category-color);

  background: color-mix(
      in srgb,
      var(--category-color) 9%,
      transparent
  );

  border-radius: 8px;
}

.category-preview__body {
  display: flex;
  flex-direction: column;
  gap: 2px;

  min-width: 0;
  flex: 1;
}

.category-preview__body strong {
  color: var(--text);

  font-size: 11px;
  font-weight: 850;
}

.category-preview__body span {
  overflow: hidden;

  color: var(--text-muted);

  font-size: 10px;
  line-height: 1.4;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-preview__check {
  display: flex;

  color: #4ade80;
}

/* =========================================================
   FIELDS
========================================================= */

.field {
  display: flex;
  flex-direction: column;
}

.input,
.textarea {
  width: 100%;

  padding: 12px 13px;

  color: var(--text);
  background: var(--bg);

  border: 1px solid var(--border);
  border-radius: 9px;
  outline: none;

  font-family: inherit;
  font-size: 13px;

  transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.input:focus,
.textarea:focus {
  border-color: rgba(139, 92, 246, 0.5);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, 0.05);
}

.input--title {
  height: 47px;

  font-size: 13px;
  font-weight: 600;
}

.textarea {
  min-height: 250px;

  resize: vertical;

  line-height: 1.65;
}

.input::placeholder,
.textarea::placeholder {
  color: var(--text-muted);
}

.field-footer {
  display: flex;
  justify-content: space-between;
  gap: 15px;

  margin-top: 6px;

  color: var(--text-muted);

  font-size: 9px;
}

.progress {
  height: 2px;

  margin-top: 5px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.04);
  border-radius: 99px;
}

.progress span {
  display: block;

  height: 100%;

  background: linear-gradient(
      90deg,
      var(--accent),
      #a78bfa
  );

  border-radius: inherit;

  transition: width 0.2s ease;
}

/* =========================================================
   ATTACHMENTS
========================================================= */

.attachments {
  padding: 13px;

  background: rgba(0, 0, 0, 0.12);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.attachments__header {
  display: flex;
  align-items: center;
  gap: 10px;

  margin-bottom: 12px;
}

.attachments__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 35px;
  height: 35px;

  color: var(--accent-light, #a78bfa);
  background: rgba(139, 92, 246, 0.08);
  border-radius: 8px;
}

.attachments__header > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.attachments__header strong {
  color: var(--text);

  font-size: 11px;
  font-weight: 800;
}

.attachments__header span {
  color: var(--text-muted);

  font-size: 9px;
}

/* =========================================================
   VALIDATION
========================================================= */

.validation {
  display: flex;
  align-items: center;
  gap: 10px;

  margin: 16px 24px 0;
  padding: 11px 13px;

  color: #fbbf24;

  background: rgba(251, 191, 36, 0.06);
  border: 1px solid rgba(251, 191, 36, 0.18);
  border-radius: 9px;
}

.validation__icon {
  display: flex;
  flex-shrink: 0;
}

.validation > div:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.validation strong {
  font-size: 10px;
  font-weight: 850;
}

.validation span {
  color: rgba(251, 191, 36, 0.75);

  font-size: 9px;
}

/* =========================================================
   ACTIONS
========================================================= */

.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 9px;

  padding: 18px 24px;
}

.cancel-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  height: 42px;
  padding: 0 15px;

  color: var(--text-dim);

  background: transparent;
  border: 1px solid var(--border);
  border-radius: 9px;

  font-size: 11px;
  font-weight: 750;

  transition:
      color 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease;
}

.cancel-button:hover {
  color: var(--text);
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
}

.publish-button {
  display: flex;
  align-items: center;
  gap: 9px;

  min-width: 205px;
  height: 42px;

  padding: 0 10px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.26),
          rgba(109, 40, 217, 0.16)
      );

  border: 1px solid rgba(139, 92, 246, 0.38);
  border-radius: 9px;

  cursor: pointer;

  text-align: left;

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      box-shadow 0.2s ease,
      opacity 0.2s ease;
}

.publish-button:hover:not(:disabled) {
  transform: translateY(-1px);

  border-color: rgba(167, 139, 250, 0.65);

  box-shadow:
      0 8px 25px rgba(124, 58, 237, 0.15);
}

.publish-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.publish-button__icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 31px;
  height: 31px;
  flex-shrink: 0;

  color: #c4b5fd;

  background: rgba(139, 92, 246, 0.14);
  border-radius: 7px;
}

.publish-button > span:nth-child(2) {
  display: flex;
  flex-direction: column;
  gap: 1px;

  flex: 1;

  font-size: 11px;
  font-weight: 850;
}

.publish-button small {
  color: var(--text-muted);

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 0.9px;
}

.publish-button__arrow {
  color: var(--text-muted);

  transform: rotate(-45deg);
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 650px) {
  .new-topic {
    width: min(100% - 20px, 600px);
    margin: 18px auto 55px;
  }

  .page-header {
    margin-bottom: 18px;
  }

  .back-link {
    margin-bottom: 17px;
  }

  .page-title {
    font-size: 31px;
    letter-spacing: -1.2px;
  }

  .page-subtitle {
    font-size: 11px;
  }

  .editor-section {
    padding: 18px 15px;
  }

  .section-heading {
    margin-bottom: 14px;
  }

  .textarea {
    min-height: 210px;
  }

  .category-preview__body span {
    white-space: normal;
  }

  .validation {
    margin: 14px 15px 0;
  }

  .form-actions {
    flex-direction: column-reverse;

    padding: 15px;
  }

  .cancel-button,
  .publish-button {
    width: 100%;
  }

  .cancel-button {
    height: 40px;
  }

  .publish-button {
    height: 44px;
  }
}

@media (max-width: 400px) {
  .new-topic {
    width: calc(100% - 14px);
  }

  .editor-section {
    padding: 16px 12px;
  }

  .category-preview {
    align-items: flex-start;
  }

  .category-preview__check {
    display: none;
  }

  .field-footer span:first-child {
    max-width: 190px;
  }
}
</style>