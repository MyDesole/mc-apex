<script setup>
import { computed, ref, watch } from 'vue'
import { clansApi } from '@/services/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const loading = ref(false)
const error = ref('')

const form = ref({
  name: props.clan.name,
  tag: props.clan.tag,
  description: props.clan.description || '',
  banner_color: props.clan.banner_color || '#7c3aed',
  is_open: props.clan.is_open,
  is_highlighted: props.clan.is_highlighted,
  socials: {
    discord: props.clan.socials?.discord || '',
    telegram: props.clan.socials?.telegram || '',
    youtube: props.clan.socials?.youtube || '',
    vk: props.clan.socials?.vk || '',
    website: props.clan.socials?.website || '',
  },
})

const avatarFile = ref(null)
const avatarPreview = ref(props.clan.avatar_url)
const coverFile = ref(null)
const coverPreview = ref(props.clan.cover_url)

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
  if (!confirm('Удалить аватарку?')) return
  await clansApi.removeAvatar(props.clan.id)
  avatarFile.value = null
  avatarPreview.value = null
  emit('updated')
}

async function removeCover() {
  if (!confirm('Удалить подложку?')) return
  await clansApi.removeCover(props.clan.id)
  coverFile.value = null
  coverPreview.value = null
  emit('updated')
}

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { ...form.value }
    if (avatarFile.value) payload.avatar = avatarFile.value
    if (coverFile.value) payload.cover = coverFile.value

    await clansApi.update(props.clan.id, payload)
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
        <h2>Настройки клана</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div v-if="error" class="error-banner">{{ error }}</div>

      <div class="form-body">
        <!-- Подложка -->
        <div class="field">
          <label>Подложка клана</label>

          <div class="cover-drop">
            <div
                v-if="coverPreview"
                class="cover-preview"
                :style="{ backgroundImage: `url(${coverPreview})` }"
            >
              <div class="cover-overlay">
                <button class="btn-change" type="button">
                  <input type="file" accept="image/*" @change="onCoverChange" />
                  Заменить
                </button>
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

        <!-- Аватар + базовое -->
        <div class="row">
          <div class="field avatar-field">
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
                {{ form.tag?.charAt(0) || 'C' }}
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

          <div class="field flex-1">
            <label>Название</label>
            <input v-model="form.name" type="text" maxlength="32" />

            <label class="mt">Тег</label>
            <input
                v-model="form.tag"
                type="text"
                maxlength="8"
                class="tag-input"
                @input="form.tag = form.tag.toUpperCase()"
            />
          </div>
        </div>

        <!-- Описание -->
        <div class="field">
          <label>Описание</label>
          <textarea v-model="form.description" rows="3" maxlength="1000" />
        </div>

        <!-- Цвет -->
        <div class="field">
          <label>Цвет баннера</label>
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
              <input v-model="form.socials.discord" placeholder="Discord invite" />
            </div>
            <div class="social-row">
              <span class="social-icon telegram">T</span>
              <input v-model="form.socials.telegram" placeholder="Telegram link" />
            </div>
            <div class="social-row">
              <span class="social-icon youtube">Y</span>
              <input v-model="form.socials.youtube" placeholder="YouTube link" />
            </div>
            <div class="social-row">
              <span class="social-icon vk">VK</span>
              <input v-model="form.socials.vk" placeholder="VK link" />
            </div>
            <div class="social-row">
              <span class="social-icon web">🌐</span>
              <input v-model="form.socials.website" placeholder="https://..." />
            </div>
          </div>
        </div>

        <!-- Опции -->
        <div class="options">
          <label class="check">
            <input v-model="form.is_open" type="checkbox" />
            <span>
                            Открыт для вступления
                            <small>Любой может подать заявку</small>
                        </span>
          </label>

          <label class="check highlight">
            <input v-model="form.is_highlighted" type="checkbox" />
            <span>
                            ⭐ Выделить в списке кланов
                            <small>Ваш клан будет подсвечен в общем списке</small>
                        </span>
          </label>
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
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 620px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 18px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.close {
  width: 32px;
  height: 32px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  font-size: 16px;
  cursor: pointer;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.error-banner {
  margin: 0 24px;
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.form-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* === FIELDS === */

.field label {
  display: block;
  margin-bottom: 8px;
  color: #b8b8c7;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input[type='text'],
.field textarea {
  width: 100%;
  padding: 11px 13px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
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

.mt { margin-top: 12px; }

/* === COVER === */

.cover-drop {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
}

.cover-preview {
  position: relative;
  height: 140px;
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
  background: rgba(0, 0, 0, 0.5);
  opacity: 0;
  transition: opacity 0.2s;
}

.cover-preview:hover .cover-overlay {
  opacity: 1;
}

.btn-change,
.btn-remove {
  position: relative;
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

.btn-change input { display: none; }

.btn-remove {
  color: #fff;
  background: rgba(239, 68, 68, 0.85);
}

.cover-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 30px;
  background: #0d0d14;
  border: 2px dashed var(--border);
  border-radius: 12px;
  cursor: pointer;
  color: var(--text-dim);
  transition: all 0.2s;
}

.cover-empty:hover {
  border-color: var(--accent);
  color: var(--accent-light);
}

.cover-empty input { display: none; }

.cover-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.cover-empty small {
  font-size: 11px;
  color: var(--text-muted);
}

/* === AVATAR === */

.row {
  display: flex;
  gap: 20px;
}

.avatar-field { flex-shrink: 0; }

.avatar-upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.avatar-preview {
  width: 84px;
  height: 84px;
  border-radius: 14px;
  background-size: cover;
  background-position: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.avatar-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 32px;
  font-weight: 900;
}

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 84px;
}

.btn-small {
  display: block;
  padding: 6px 10px;
  text-align: center;
  color: #fff;
  background: var(--accent);
  border-radius: 7px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-small input { display: none; }

.btn-small.ghost {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-small.ghost:hover {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
}

.flex-1 { flex: 1; }

/* === COLORS === */

.color-row {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.color-swatch {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.15s, border-color 0.15s;
}

.color-swatch:hover { transform: scale(1.08); }

.color-swatch.active {
  border-color: #fff;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
}

.color-custom {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: transparent;
  cursor: pointer;
}

/* === SOCIALS === */

.socials {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.social-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.social-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 900;
  color: #fff;
  flex-shrink: 0;
}

.social-icon.discord { background: #5865f2; }
.social-icon.telegram { background: #229ed9; }
.social-icon.youtube { background: #ff0000; }
.social-icon.vk { background: #0077ff; font-size: 11px; }
.social-icon.web { background: #4b5563; }

.social-row input {
  flex: 1;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
}

.social-row input:focus {
  border-color: var(--accent);
}

/* === OPTIONS === */

.options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.check {
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

.check:hover { border-color: var(--border-hover); }

.check.highlight {
  border-color: rgba(250, 204, 21, 0.25);
  background: rgba(250, 204, 21, 0.04);
}

.check input {
  margin-top: 2px;
  accent-color: var(--accent);
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.check span {
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
}

.check small {
  display: block;
  margin-top: 2px;
  color: var(--text-dim);
  font-weight: 400;
  font-size: 11px;
}

/* === FOOT === */

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 16px 24px;
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
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 5px 20px rgba(124, 58, 237, 0.25);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 600px) {
  .row { flex-direction: column; }
  .avatar-actions { width: 100%; flex-direction: row; }
  .modal-foot { flex-direction: column-reverse; }
  .btn-cancel, .btn-save { width: 100%; }
}
</style>