<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { clanLink } from '@/utils/links.js'

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

    router.push(clanLink(data.clan))
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
@import "@/views/clan/ClanCreateView.css";
</style>
