<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const router = useRouter()

const form = ref({
  name: '',
  tag: '',
  description: '',
  banner_color: '#7c3aed',
  is_open: true,
})

const loading = ref(false)
const errors = ref({})
const generalError = ref('')

const previewLetter = computed(() => {
  return form.value.tag?.charAt(0)?.toUpperCase() || 'C'
})

async function submit() {
  loading.value = true
  errors.value = {}
  generalError.value = ''

  try {
    const data = await clansApi.create({
      name: form.value.name.trim(),
      tag: form.value.tag.trim().toUpperCase(),
      description: form.value.description.trim() || null,
      banner_color: form.value.banner_color,
      is_open: form.value.is_open,
    })

    router.push(`/clans/${data.clan.id}`)
  } catch (e) {
    if (e.status === 422 && e.errors) {
      errors.value = e.errors
    } else {
      generalError.value = e.message || 'Не удалось создать клан'
    }
  } finally {
    loading.value = false
  }
}

const colorPresets = [
  '#7c3aed', '#8b5cf6', '#06b6d4', '#22c55e',
  '#f97316', '#ef4444', '#facc15', '#ec4899',
]
</script>

<template>
  <div class="clan-create-page">
    <div class="page-head">
      <h1>Создать клан</h1>
      <p class="subtitle">
        Собери команду, участвуй в войнах и поднимайся в топ
      </p>
    </div>

    <form class="clan-form" @submit.prevent="submit">
      <div v-if="generalError" class="error-banner">
        {{ generalError }}
      </div>

      <!-- Превью -->
      <div class="preview">
        <div
            class="preview-banner"
            :style="{ background: form.banner_color }"
        >
          {{ previewLetter }}
        </div>

        <div class="preview-info">
          <div class="preview-name">
                        <span class="preview-tag">
                            [{{ form.tag || 'TAG' }}]
                        </span>
            {{ form.name || 'Название клана' }}
          </div>
          <div class="preview-meta">
            {{ form.is_open ? 'Открыт для вступления' : 'Только по заявке' }}
          </div>
        </div>
      </div>

      <!-- Название -->
      <label class="field">
        <span>Название клана</span>
        <input
            v-model="form.name"
            type="text"
            placeholder="Apex Legends"
            maxlength="32"
        />
        <small v-if="errors.name" class="field-error">
          {{ errors.name[0] }}
        </small>
      </label>

      <!-- Тег -->
      <label class="field">
        <span>Тег клана</span>
        <input
            v-model="form.tag"
            type="text"
            placeholder="APX"
            maxlength="8"
            class="tag-input"
            @input="form.tag = form.tag.toUpperCase()"
        />
        <small class="hint">
          2–8 символов. Будет отображаться как [{{ form.tag || 'TAG' }}]
        </small>
        <small v-if="errors.tag" class="field-error">
          {{ errors.tag[0] }}
        </small>
      </label>

      <!-- Описание -->
      <label class="field">
        <span>Описание</span>
        <textarea
            v-model="form.description"
            rows="4"
            maxlength="1000"
            placeholder="Расскажи о своём клане, требованиях и целях..."
        />
        <small class="hint">
          {{ form.description.length }} / 1000
        </small>
        <small v-if="errors.description" class="field-error">
          {{ errors.description[0] }}
        </small>
      </label>

      <!-- Цвет баннера -->
      <div class="field">
        <span>Цвет баннера</span>
        <div class="color-row">
          <button
              v-for="color in colorPresets"
              :key="color"
              type="button"
              class="color-swatch"
              :class="{ active: form.banner_color === color }"
              :style="{ background: color }"
              @click="form.banner_color = color"
          />
          <input
              v-model="form.banner_color"
              type="color"
              class="color-custom"
          />
        </div>
        <small v-if="errors.banner_color" class="field-error">
          {{ errors.banner_color[0] }}
        </small>
      </div>

      <!-- Открытость -->
      <label class="checkbox-field">
        <input v-model="form.is_open" type="checkbox" />
        <span>
                    Открыт для вступления
                    <small>— любой игрок сможет подать заявку</small>
                </span>
      </label>

      <!-- Действия -->
      <div class="form-actions">
        <button
            type="button"
            class="btn-cancel"
            @click="router.back()"
        >
          Отмена
        </button>
        <button
            type="submit"
            class="btn-submit"
            :disabled="loading || !form.name || !form.tag"
        >
          {{ loading ? 'Создание...' : 'Создать клан' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.clan-create-page {
  width: min(640px, calc(100% - 40px));
  margin: 40px auto;
}

.page-head {
  margin-bottom: 28px;
}

.page-head h1 {
  margin: 0 0 6px;
  font-size: 28px;
  font-weight: 800;
}

.subtitle {
  margin: 0;
  color: var(--text-dim);
  font-size: 14px;
}

.clan-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.error-banner {
  padding: 12px 14px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  font-size: 13px;
}

/* === PREVIEW === */

.preview {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 12px;
}

.preview-banner {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  color: #fff;
  font-size: 24px;
  font-weight: 900;
  flex-shrink: 0;
  transition: background 0.2s ease;
}

.preview-name {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 4px;
}

.preview-tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.preview-meta {
  color: var(--text-dim);
  font-size: 12px;
}

/* === FIELDS === */

.field {
  display: block;
}

.field > span {
  display: block;
  margin-bottom: 8px;
  color: #b8b8c7;
  font-size: 13px;
  font-weight: 600;
}

.field input[type='text'],
.field textarea {
  width: 100%;
  padding: 12px 14px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  font: inherit;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  resize: vertical;
}

.field input[type='text']:focus,
.field textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

.tag-input {
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 700;
}

.hint {
  display: block;
  margin-top: 6px;
  color: var(--text-muted);
  font-size: 12px;
}

.field-error {
  display: block;
  margin-top: 6px;
  color: #fca5a5;
  font-size: 12px;
  font-weight: 600;
}

/* === COLORS === */

.color-row {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.color-swatch {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.15s, border-color 0.15s;
}

.color-swatch:hover {
  transform: scale(1.08);
}

.color-swatch.active {
  border-color: #fff;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
}

.color-custom {
  width: 36px;
  height: 36px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
}

/* === CHECKBOX === */

.checkbox-field {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s;
}

.checkbox-field:hover {
  border-color: var(--border-hover);
}

.checkbox-field input {
  margin-top: 2px;
  accent-color: var(--accent);
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.checkbox-field span {
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
}

.checkbox-field small {
  display: block;
  margin-top: 2px;
  color: var(--text-dim);
  font-weight: 400;
}

/* === ACTIONS === */

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 4px;
}

.btn-cancel,
.btn-submit {
  min-height: 42px;
  padding: 0 22px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  border: 0;
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

.btn-submit {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 5px 20px rgba(124, 58, 237, 0.25);
}

.btn-submit:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
  box-shadow: 0 7px 25px rgba(124, 58, 237, 0.35);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 600px) {
  .clan-form {
    padding: 20px;
  }

  .form-actions {
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-submit {
    width: 100%;
  }
}
</style>