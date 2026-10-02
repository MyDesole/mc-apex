<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { ref } from 'vue'
import { playersApi } from '@/services/players/players.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  user: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const auth = useAuthStore()
const loading = ref(false)
const error = ref('')

const form = ref({
  bio: props.user.bio || '',
  banner_color: props.user.banner_color || '#7c3aed',
  socials: {
    discord: props.user.socials?.discord || '',
    telegram: props.user.socials?.telegram || '',
    youtube: props.user.socials?.youtube || '',
    vk: props.user.socials?.vk || '',
    website: props.user.socials?.website || '',
  },
})

const avatarFile = ref(null)
const avatarPreview = ref(props.user.avatar_url)
const coverFile = ref(null)
const coverPreview = ref(props.user.cover_url)

function onAvatarChange(e) {
  const file = e.target.files[0]
  if (!file) return
  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
}

function onCoverChange(e) {
  const file = e.target.files[0]
  if (!file) return
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
}

async function removeAvatar() {
  if (!await confirmDialog('Удалить аватарку?')) return
  await playersApi.removeAvatar()
  avatarFile.value = null
  avatarPreview.value = null
  await auth.fetchMe()
  emit('updated')
}

async function removeCover() {
  if (!await confirmDialog('Удалить подложку?')) return
  await playersApi.removeCover()
  coverFile.value = null
  coverPreview.value = null
  await auth.fetchMe()
  emit('updated')
}

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { ...form.value }
    if (avatarFile.value) payload.avatar = avatarFile.value
    if (coverFile.value) payload.cover = coverFile.value

    await playersApi.updateMe(payload)
    await auth.fetchMe()
    emit('updated')
    emit('close')
  } catch (e) {
    error.value = e.message || 'Ошибка сохранения'
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
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Настройки профиля</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div v-if="error" class="error-banner">{{ error }}</div>

      <div class="form-body">
        <!-- Подложка -->
        <div class="field">
          <label>Подложка профиля</label>

          <div class="cover-drop">
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
                <button class="btn-remove" type="button" @click="removeCover">
                  Удалить
                </button>
              </div>
            </div>

            <label v-else class="cover-empty">
              <input type="file" accept="image/*" @change="onCoverChange" />
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span>Загрузить подложку</span>
              <small>JPG, PNG, WebP · до 5 МБ</small>
            </label>
          </div>
        </div>

        <!-- Аватар -->
        <div class="field">
          <label>Аватар</label>

          <div class="avatar-upload">
            <div
                v-if="avatarPreview"
                class="avatar-preview"
                :style="{ backgroundImage: `url(${avatarPreview})` }"
            />
            <div
                v-else
                class="avatar-preview avatar-placeholder"
                :style="{ background: form.banner_color }"
            >
              {{ (user.username || 'И').charAt(0).toUpperCase() }}
            </div>

            <div class="avatar-actions">
              <label class="btn-small">
                <input type="file" accept="image/*" @change="onAvatarChange" />
                Выбрать
              </label>
              <button
                  v-if="avatarPreview"
                  class="btn-small ghost"
                  type="button"
                  @click="removeAvatar"
              >
                Убрать
              </button>
            </div>
          </div>
        </div>

        <!-- Bio -->
        <div class="field">
          <label>О себе</label>
          <textarea
              v-model="form.bio"
              rows="3"
              maxlength="500"
              placeholder="Пара слов о себе..."
          />
          <small class="hint">{{ form.bio.length }} / 500</small>
        </div>

        <!-- Цвет -->
        <div class="field">
          <label>Акцентный цвет</label>
          <div class="color-row">
            <button
                v-for="c in colorPresets"
                :key="c"
                type="button"
                class="color-swatch"
                :class="{ active: form.banner_color === c }"
                :style="{ background: c }"
                @click="form.banner_color = c"
            />
            <input v-model="form.banner_color" type="color" class="color-custom" />
          </div>
        </div>

        <!-- Соцсети -->
        <div class="field">
          <label>Соцсети</label>
          <div class="socials">
            <div class="social-row">
              <span class="social-icon discord">D</span>
              <input v-model="form.socials.discord" placeholder="Discord" />
            </div>
            <div class="social-row">
              <span class="social-icon telegram">T</span>
              <input v-model="form.socials.telegram" placeholder="Telegram" />
            </div>
            <div class="social-row">
              <span class="social-icon youtube">Y</span>
              <input v-model="form.socials.youtube" placeholder="YouTube" />
            </div>
            <div class="social-row">
              <span class="social-icon vk">VK</span>
              <input v-model="form.socials.vk" placeholder="VK" />
            </div>
            <div class="social-row">
              <span class="social-icon web">🌐</span>
              <input v-model="form.socials.website" placeholder="https://..." />
            </div>
          </div>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="loading" @click="submit">
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/players/ProfileEditModal.css";
</style>
