<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({
  news: { type: Object, default: null },
})

const emit = defineEmits(['close', 'updated'])

const isEdit = !!props.news

const form = ref({
  title: props.news?.title ?? '',
  excerpt: props.news?.excerpt ?? '',
  body: props.news?.body ?? '',
  type: props.news?.type ?? 'news',
  is_pinned: props.news?.is_pinned ?? false,
  is_published: props.news?.is_published ?? true,
  published_at: props.news?.published_at
      ? props.news.published_at.slice(0, 16)
      : '',
})

const coverFile = ref(null)
const coverPreview = ref(props.news?.cover_url ?? null)

const loading = ref(false)
const error = ref('')

const titleLength = computed(() => form.value.title.length)
const excerptLength = computed(() => form.value.excerpt.length)
const bodyLength = computed(() => form.value.body.length)

function onCoverChange(e) {
  const file = e.target.files[0]
  if (!file) return
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
}

function removeCover() {
  coverFile.value = null
  coverPreview.value = null
}

async function submit() {
  if (!form.value.title.trim()) {
    error.value = 'Укажи заголовок'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const payload = { ...form.value }

    // пустые поля → null (чтобы Laravel не ругался на date)
    Object.keys(payload).forEach(k => {
      if (payload[k] === '') payload[k] = null
    })

    if (coverFile.value) {
      payload.cover = coverFile.value
    }

    if (isEdit) {
      await adminApi.updateNews(props.news.id, payload)
    } else {
      await adminApi.createNews(payload)
    }

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

const types = [
  { value: 'news', label: 'Новость', color: '#a78bfa', icon: '📰' },
  { value: 'update', label: 'Обновление', color: '#4ade80', icon: '🔧' },
  { value: 'event', label: 'Событие', color: '#f472b6', icon: '🎉' },
  { value: 'announcement', label: 'Анонс', color: '#fbbf24', icon: '📢' },
]
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <div>
          <h2>{{ isEdit ? 'Редактировать новость' : 'Создать новость' }}</h2>
          <p class="sub">
            {{ isEdit ? 'Измени содержимое и сохрани' : 'Заполни поля и опубликуй' }}
          </p>
        </div>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Заголовок -->
        <div class="field">
          <label>
            Заголовок
            <span class="counter" :class="{ over: titleLength > 120 }">
                            {{ titleLength }} / 120
                        </span>
          </label>
          <input
              v-model="form.title"
              type="text"
              maxlength="120"
              placeholder="Например: Запуск сезона 1"
          />
        </div>

        <!-- Короткое описание -->
        <div class="field">
          <label>
            Короткое описание
            <span class="counter" :class="{ over: excerptLength > 255 }">
                            {{ excerptLength }} / 255
                        </span>
          </label>
          <textarea
              v-model="form.excerpt"
              rows="2"
              maxlength="255"
              placeholder="Появится в карточке новости на главной"
          />
        </div>

        <!-- Полный текст -->
        <div class="field">
          <label>
            Полный текст
            <span class="counter">{{ bodyLength }} символов</span>
          </label>
          <textarea
              v-model="form.body"
              rows="8"
              placeholder="Текст новости... Переносы строк сохранятся"
              class="body-textarea"
          />
        </div>

        <!-- Обложка -->
        <div class="field">
          <label>Обложка</label>

          <div class="cover-upload">
            <div
                v-if="coverPreview"
                class="cover-preview"
                :style="{ backgroundImage: `url(${coverPreview})` }"
            >
              <div class="cover-overlay">
                <label class="btn-change">
                  <input type="file" accept="image/*" @change="onCoverChange" />
                  Заменить
                </label>
                <button type="button" class="btn-remove" @click="removeCover">
                  Удалить
                </button>
              </div>
            </div>

            <label v-else class="cover-empty">
              <input type="file" accept="image/*" @change="onCoverChange" />
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <circle cx="8.5" cy="8.5" r="1.5" />
                <path d="M21 15l-5-5L5 21" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span>Загрузить обложку</span>
              <small>JPG, PNG, WebP · до 5 МБ</small>
            </label>
          </div>
        </div>

        <!-- Тип -->
        <div class="field">
          <label>Тип</label>
          <div class="type-picker">
            <button
                v-for="t in types"
                :key="t.value"
                type="button"
                class="type-btn"
                :class="{ active: form.type === t.value }"
                :style="{ '--type-color': t.color }"
                @click="form.type = t.value"
            >
              <span class="type-btn__icon">{{ t.icon }}</span>
              <span class="type-btn__label">{{ t.label }}</span>
            </button>
          </div>
        </div>

        <!-- Дата публикации -->
        <div class="field">
          <label>Дата публикации</label>
          <input
              v-model="form.published_at"
              type="datetime-local"
          />
          <small class="hint">
            Оставь пустым — опубликуется сразу. Можно указать будущее.
          </small>
        </div>

        <!-- Опции -->
        <div class="options">
          <label class="check">
            <input v-model="form.is_published" type="checkbox" />
            <div>
              <span class="check__title">Опубликовать</span>
              <span class="check__desc">
                                Если выключено — новость будет черновиком
                            </span>
            </div>
          </label>

          <label class="check highlight">
            <input v-model="form.is_pinned" type="checkbox" />
            <div>
              <span class="check__title">📌 Закрепить на главной</span>
              <span class="check__desc">
                                Новость будет показываться первой
                            </span>
            </div>
          </label>
        </div>

        <!-- Превью -->
        <div class="preview-block">
          <div class="preview-block__title">Превью</div>
          <div class="preview-card">
            <div
                class="preview-card__cover"
                :style="coverPreview ? { backgroundImage: `url(${coverPreview})` } : {}"
            >
                            <span v-if="!coverPreview" class="preview-card__letter">
                                {{ (form.title || 'N').charAt(0).toUpperCase() }}
                            </span>
              <span
                  class="preview-card__badge"
                  :style="{ color: types.find(t => t.value === form.type)?.color }"
              >
                                {{ types.find(t => t.value === form.type)?.label }}
                            </span>
            </div>
            <div class="preview-card__body">
              <div class="preview-card__title">
                <span v-if="form.is_pinned">📌</span>
                {{ form.title || 'Заголовок новости' }}
              </div>
              <div class="preview-card__excerpt">
                {{ form.excerpt || 'Короткое описание появится здесь' }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button
            class="btn-save"
            :disabled="loading || !form.title"
            @click="submit"
        >
          {{ loading ? 'Сохранение...' : (isEdit ? 'Сохранить' : 'Создать') }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminNewsForm.css";
</style>
