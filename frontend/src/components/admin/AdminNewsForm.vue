<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

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
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 720px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

/* HEAD */

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0 0 2px;
  font-size: 17px;
  font-weight: 800;
}

.sub {
  margin: 0;
  color: var(--text-dim);
  font-size: 12px;
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

/* BODY */

.body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

/* FIELDS */

.field label {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.counter {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: none;
  letter-spacing: 0;
  font-weight: 600;
}

.counter.over {
  color: #f87171;
}

.field input[type='text'],
.field input[type='datetime-local'],
.field textarea {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
  transition: border-color 0.15s;
}

.field input:focus,
.field textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.body-textarea {
  min-height: 140px;
  line-height: 1.6;
  font-family: inherit;
}

.hint {
  display: block;
  margin-top: 5px;
  color: var(--text-muted);
  font-size: 11px;
}

/* COVER */

.cover-upload {
  border-radius: 12px;
  overflow: hidden;
}

.cover-preview {
  position: relative;
  height: 160px;
  background-size: cover;
  background-position: center;
}

.cover-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: rgba(0, 0, 0, 0.55);
  opacity: 0;
  transition: opacity 0.2s;
}

.cover-preview:hover .cover-overlay {
  opacity: 1;
}

.btn-change,
.btn-remove {
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-change {
  color: #fff;
  background: var(--accent);
}

.btn-change input {
  display: none;
}

.btn-remove {
  color: #fff;
  background: rgba(239, 68, 68, 0.85);
}

.cover-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 30px;
  background: #0d0d14;
  border: 2px dashed var(--border);
  border-radius: 12px;
  cursor: pointer;
  color: var(--text-muted);
  transition: all 0.2s;
}

.cover-empty:hover {
  border-color: var(--accent);
  color: var(--accent-light);
}

.cover-empty input {
  display: none;
}

.cover-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.cover-empty small {
  font-size: 11px;
  color: var(--text-muted);
}

/* TYPE PICKER */

.type-picker {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.type-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 12px 8px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.type-btn:hover {
  border-color: var(--border-hover);
  color: var(--text);
}

.type-btn.active {
  border-color: var(--type-color);
  background: color-mix(in srgb, var(--type-color) 8%, transparent);
  color: var(--type-color);
}

.type-btn__icon {
  font-size: 20px;
}

.type-btn__label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.2px;
}

/* OPTIONS */

.options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.check {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s;
}

.check:hover {
  border-color: var(--border-hover);
}

.check.highlight {
  border-color: rgba(250, 204, 21, 0.25);
  background: rgba(250, 204, 21, 0.04);
}

.check input {
  margin-top: 3px;
  accent-color: var(--accent);
  width: 16px;
  height: 16px;
  cursor: pointer;
  flex-shrink: 0;
}

.check__title {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 2px;
}

.check__desc {
  display: block;
  font-size: 11px;
  color: var(--text-dim);
}

/* PREVIEW */

.preview-block {
  padding: 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.preview-block__title {
  margin-bottom: 10px;
  color: var(--text-muted);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.preview-card {
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  max-width: 320px;
}

.preview-card__cover {
  position: relative;
  height: 100px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.preview-card__letter {
  font-size: 32px;
  font-weight: 900;
  color: #fff;
  opacity: 0.4;
}

.preview-card__badge {
  position: absolute;
  top: 6px;
  left: 6px;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(6px);
}

.preview-card__body {
  padding: 10px 12px;
}

.preview-card__title {
  font-size: 13px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.preview-card__excerpt {
  font-size: 11px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* FOOT */

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-save {
  min-height: 42px;
  padding: 0 22px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
  transition: all 0.2s;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-cancel:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.btn-save {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.btn-save:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .type-picker {
    grid-template-columns: 1fr 1fr;
  }

  .modal-foot {
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-save {
    width: 100%;
  }

  .preview-card {
    max-width: 100%;
  }
}
</style>