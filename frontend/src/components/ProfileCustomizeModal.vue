<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { playersApi } from '@/services/players.js'
import { achievementsApi } from '@/services/achievements.js'
import {
  AVATAR_FRAMES,
  PROFILE_EFFECTS,
  ACCENT_COLORS,
  FAVORITE_MODES,
} from '@/data/profileCustomization'

const auth = useAuthStore()
const emit = defineEmits(['close', 'updated'])

const user = computed(() => auth.user)

const tab = ref('style') // style | info | achievements

const form = ref({
  avatar_frame: user.value?.avatar_frame ?? 'default',
  profile_effect: user.value?.profile_effect ?? null,
  accent_color: user.value?.accent_color ?? '#7c3aed',
  status: user.value?.status ?? '',
  quote: user.value?.quote ?? '',
  bio: user.value?.bio ?? '',
  discord_tag: user.value?.discord_tag ?? '',
  favorite_modes: Array.isArray(user.value?.favorite_modes)
      ? [...user.value.favorite_modes]
      : [],
  featured_achievements: Array.isArray(user.value?.featured_achievements)
      ? [...user.value.featured_achievements]
      : [],
})

const loading = ref(false)
const error = ref('')

// === Файлы и превью ===
const avatarFile = ref(null)
const avatarPreview = ref(user.value?.avatar_url ?? null)

const coverFile = ref(null)
const coverPreview = ref(user.value?.cover_url ?? null)

const cardBgFile = ref(null)
const cardBgPreview = ref(user.value?.card_background_url ?? null)

// === Ачивки ===
const myAchievements = ref([])
const achievementsLoading = ref(true)

// === Хэндлеры файлов ===

function replaceBlobUrl(oldUrl, newFile) {
  if (oldUrl && oldUrl.startsWith('blob:')) {
    URL.revokeObjectURL(oldUrl)
  }
  return URL.createObjectURL(newFile)
}

function onAvatarChange(e) {
  const f = e.target.files?.[0]
  if (!f) return
  avatarFile.value = f
  avatarPreview.value = replaceBlobUrl(avatarPreview.value, f)
  e.target.value = ''
}

function onCoverChange(e) {
  const f = e.target.files?.[0]
  if (!f) return
  coverFile.value = f
  coverPreview.value = replaceBlobUrl(coverPreview.value, f)
  e.target.value = ''
}

function onCardBgChange(e) {
  const f = e.target.files?.[0]
  if (!f) return
  cardBgFile.value = f
  cardBgPreview.value = replaceBlobUrl(cardBgPreview.value, f)
  e.target.value = ''
}

// === Удаление ===

async function removeAvatar() {
  if (!confirm('Удалить аватар?')) return
  error.value = ''
  try {
    await playersApi.removeAvatar()
    avatarFile.value = null
    if (avatarPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(avatarPreview.value)
    }
    avatarPreview.value = null
    await auth.fetchMe()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось удалить аватар.'
  }
}

async function removeCover() {
  if (!confirm('Удалить обложку?')) return
  error.value = ''
  try {
    await playersApi.removeCover()
    coverFile.value = null
    if (coverPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview.value)
    }
    coverPreview.value = null
    await auth.fetchMe()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось удалить обложку.'
  }
}

async function removeCardBg() {
  if (!confirm('Удалить фон карточки?')) return
  error.value = ''
  try {
    await playersApi.removeCardBackground()
    cardBgFile.value = null
    if (cardBgPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(cardBgPreview.value)
    }
    cardBgPreview.value = null
    await auth.fetchMe()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось удалить фон.'
  }
}

// === Тогглы ===

function toggleMode(mode) {
  const list = form.value.favorite_modes
  const idx = list.indexOf(mode)

  if (idx >= 0) {
    list.splice(idx, 1)
  } else if (list.length < 6) {
    list.push(mode)
  }
}

function toggleFeatured(a) {
  const list = form.value.featured_achievements
  const idx = list.indexOf(a.id)

  if (idx >= 0) {
    list.splice(idx, 1)
  } else if (list.length < 6) {
    list.push(a.id)
  }
}

function isFeatured(id) {
  return form.value.featured_achievements.includes(id)
}

// === Загрузка ачивок ===

async function loadAchievements() {
  achievementsLoading.value = true
  try {
    const data = await achievementsApi.list()
    myAchievements.value = (data.achievements ?? []).filter((a) => a.earned)
  } catch (e) {
    console.error(e)
  } finally {
    achievementsLoading.value = false
  }
}

// === Сохранение ===

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const mePayload = {}
    if (avatarFile.value) mePayload.avatar = avatarFile.value
    if (coverFile.value) mePayload.cover = coverFile.value

    if (Object.keys(mePayload).length) {
      await playersApi.updateMe(mePayload)
    }

    const payload = { ...form.value }
    if (cardBgFile.value) {
      payload.card_background = cardBgFile.value
    }

    await playersApi.updateProfile(payload)

    await auth.fetchMe()

    avatarFile.value = null
    coverFile.value = null
    cardBgFile.value = null

    if (avatarPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(avatarPreview.value)
    }
    if (coverPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview.value)
    }
    if (cardBgPreview.value?.startsWith('blob:')) {
      URL.revokeObjectURL(cardBgPreview.value)
    }

    avatarPreview.value = auth.user?.avatar_url ?? null
    coverPreview.value = auth.user?.cover_url ?? null
    cardBgPreview.value = auth.user?.card_background_url ?? null

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

onMounted(loadAchievements)

onBeforeUnmount(() => {
  if (avatarPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(avatarPreview.value)
  }
  if (coverPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(coverPreview.value)
  }
  if (cardBgPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(cardBgPreview.value)
  }
})
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <!-- HEAD -->
      <header class="modal-head">
        <div>
          <h2>Кастомизация профиля</h2>
          <p class="sub">Настрой внешний вид и информацию</p>
        </div>
        <button class="close" @click="$emit('close')" aria-label="Закрыть">✕</button>
      </header>

      <!-- LAYOUT: SIDEBAR + BODY -->
      <div class="modal-layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
          <nav class="sidebar-nav">
            <button
                class="sidebar-btn"
                :class="{ active: tab === 'style' }"
                @click="tab = 'style'"
            >
              <span class="sidebar-btn__label">Стиль</span>
              <span class="sidebar-btn__desc">Аватар, рамки, эффекты</span>
            </button>

            <button
                class="sidebar-btn"
                :class="{ active: tab === 'info' }"
                @click="tab = 'info'"
            >
              <span class="sidebar-btn__label">Инфо</span>
              <span class="sidebar-btn__desc">Био, статус, соцсети</span>
            </button>


          </nav>
        </aside>

        <!-- BODY -->
        <div class="body">
          <div v-if="error" class="error">{{ error }}</div>

          <!-- === STYLE === -->
          <template v-if="tab === 'style'">
            <!-- Аватар и обложка -->
            <section class="section">
              <h3 class="section__title">Аватар и обложка</h3>

              <div class="cover-upload">
                <div
                    v-if="coverPreview"
                    class="cover-preview"
                    :style="{ backgroundImage: `url(${coverPreview})` }"
                >
                  <div class="cover-overlay">
                    <label class="btn-change">
                      <input
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          @change="onCoverChange"
                      />
                      Заменить
                    </label>
                    <button type="button" class="btn-remove" @click="removeCover">
                      Удалить
                    </button>
                  </div>
                </div>

                <label v-else class="cover-empty">
                  <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      @change="onCoverChange"
                  />
                  <span>Загрузить обложку</span>
                  <small>JPG, PNG, WebP · до 5 МБ</small>
                </label>
              </div>

              <div class="avatar-upload">
                <div class="avatar-wrap">
                  <img
                      v-if="avatarPreview"
                      :src="avatarPreview"
                      alt="avatar"
                      class="avatar-img"
                  />
                  <div v-else class="avatar-placeholder">
                    {{ user?.username?.[0]?.toUpperCase() ?? '?' }}
                  </div>

                  <div class="avatar-overlay">
                    <label class="btn-change btn-change--sm">
                      <input
                          type="file"
                          accept="image/jpeg,image/png,image/webp,image/gif"
                          @change="onAvatarChange"
                      />
                      Заменить
                    </label>
                  </div>
                </div>

                <div class="avatar-actions">
                  <button
                      v-if="avatarPreview"
                      type="button"
                      class="btn-remove btn-remove--sm"
                      @click="removeAvatar"
                  >
                    Удалить аватар
                  </button>
                  <small class="hint">JPG, PNG, WebP, GIF · до 2 МБ</small>
                </div>
              </div>
            </section>

            <!-- Рамка -->
            <section class="section">
              <h3 class="section__title">Рамка аватара</h3>
              <div class="frames-grid">
                <button
                    v-for="f in AVATAR_FRAMES"
                    :key="f.id"
                    type="button"
                    class="frame-btn"
                    :class="{ active: form.avatar_frame === f.id }"
                    @click="form.avatar_frame = f.id"
                >
                  <div
                      class="frame-preview"
                      :style="f.gradient
                          ? { background: f.gradient }
                          : { background: f.color }"
                  />
                  <span class="frame-name">{{ f.name }}</span>
                </button>
              </div>
            </section>

            <!-- Эффект -->
            <section class="section">
              <h3 class="section__title">Эффект профиля</h3>
              <div class="effects-grid">
                <button
                    v-for="e in PROFILE_EFFECTS"
                    :key="e.id ?? 'none'"
                    type="button"
                    class="effect-btn"
                    :class="{ active: form.profile_effect === e.id }"
                    @click="form.profile_effect = e.id"
                >
                  {{ e.name }}
                </button>
              </div>
            </section>

            <!-- Акцентный цвет -->
            <section class="section">
              <h3 class="section__title">Акцентный цвет</h3>
              <div class="colors-grid">
                <button
                    v-for="c in ACCENT_COLORS"
                    :key="c"
                    type="button"
                    class="color-btn"
                    :class="{ active: form.accent_color === c }"
                    :style="{ background: c }"
                    @click="form.accent_color = c"
                />
                <input
                    v-model="form.accent_color"
                    type="color"
                    class="color-custom"
                />
              </div>
            </section>

            <!-- Фон карточки -->
            <section class="section">
              <h3 class="section__title">Фон карточки</h3>

              <div class="bg-upload">
                <div
                    v-if="cardBgPreview"
                    class="bg-preview"
                    :style="{ backgroundImage: `url(${cardBgPreview})` }"
                >
                  <div class="bg-overlay">
                    <label class="btn-change">
                      <input
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          @change="onCardBgChange"
                      />
                      Заменить
                    </label>
                    <button type="button" class="btn-remove" @click="removeCardBg">
                      Удалить
                    </button>
                  </div>
                </div>

                <label v-else class="bg-empty">
                  <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      @change="onCardBgChange"
                  />
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <path d="M21 15l-5-5L5 21" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                  <span>Загрузить фон карточки</span>
                  <small>JPG, PNG, WebP · до 5 МБ</small>
                </label>
              </div>
            </section>
          </template>

          <!-- === INFO === -->
          <template v-else-if="tab === 'info'">
            <div class="field">
              <label>Статус</label>
              <input
                  v-model="form.status"
                  type="text"
                  maxlength="64"
                  placeholder="Например: играю в PvP"
              />
              <small class="hint">{{ form.status.length }} / 64</small>
            </div>

            <div class="field">
              <label>Любимая цитата</label>
              <input
                  v-model="form.quote"
                  type="text"
                  maxlength="160"
                  placeholder="Твоя фраза"
              />
              <small class="hint">{{ form.quote.length }} / 160</small>
            </div>

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

            <div class="field">
              <label>Discord tag</label>
              <input
                  v-model="form.discord_tag"
                  type="text"
                  maxlength="64"
                  placeholder="username#0000"
              />
            </div>

            <div class="field">
              <label>Любимые режимы (до 6)</label>
              <div class="modes-grid">
                <button
                    v-for="m in FAVORITE_MODES"
                    :key="m.value"
                    type="button"
                    class="mode-btn"
                    :class="{ active: form.favorite_modes.includes(m.value) }"
                    :style="{ '--color': m.color }"
                    @click="toggleMode(m.value)"
                >
                  {{ m.label }}
                </button>
              </div>
            </div>
          </template>

          <!-- === ACHIEVEMENTS === -->
          <template v-else-if="tab === 'achievements'">
            <div class="section">
              <div class="section__head">
                <h3 class="section__title">Витрина ачивок</h3>
                <span class="section__count">
                  {{ form.featured_achievements.length }} / 6
                </span>
              </div>

              <div v-if="achievementsLoading" class="empty">
                Загрузка...
              </div>

              <div v-else-if="!myAchievements.length" class="empty">
                У тебя пока нет ачивок. Пройди тир-тест или вступи в клан.
              </div>

              <div v-else class="achievements-grid">
                <button
                    v-for="a in myAchievements"
                    :key="a.id"
                    type="button"
                    class="ach-btn"
                    :class="{ active: isFeatured(a.id) }"
                    :style="{ '--color': a.color }"
                    :title="a.description"
                    @click="toggleFeatured(a)"
                >
                  <span class="ach-btn__icon">{{ a.icon }}</span>
                  <span class="ach-btn__name">{{ a.name }}</span>
                  <span class="ach-btn__check">
                    {{ isFeatured(a.id) ? '✓' : '+' }}
                  </span>
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- FOOT -->
      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button
            class="btn-save"
            :disabled="loading"
            @click="submit"
        >
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
/* === CARD === */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 6, 10, 0.72);
  backdrop-filter: blur(8px);
}

.modal {
  width: 100%;
  max-width: 860px;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #23232e);
  border-radius: 18px;
  overflow: hidden;
  box-shadow:
      0 24px 60px -20px rgba(0, 0, 0, 0.6),
      0 0 0 1px rgba(255, 255, 255, 0.02) inset;
}

/* === HEAD === */

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 22px 26px;
  border-bottom: 1px solid var(--border, #23232e);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), transparent);
}

.modal-head h2 {
  margin: 0 0 4px;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.2px;
}

.sub {
  margin: 0;
  color: var(--text-dim, #8a8a97);
  font-size: 12.5px;
}

.close {
  width: 32px;
  height: 32px;
  color: var(--text-dim, #8a8a97);
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border, #23232e);
  border-radius: 9px;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 13px;
  transition: all 0.15s;
}

.close:hover {
  background: rgba(255, 255, 255, 0.06);
  color: var(--text, #fff);
}

/* === LAYOUT === */

.modal-layout {
  flex: 1;
  display: grid;
  grid-template-columns: 220px 1fr;
  min-height: 0;
}

/* === SIDEBAR === */

.sidebar {
  border-right: 1px solid var(--border, #23232e);
  background: rgba(255, 255, 255, 0.01);
  padding: 14px 12px;
  overflow-y: auto;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sidebar-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  padding: 11px 13px;
  color: var(--text-dim, #8a8a97);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
}

.sidebar-btn:hover {
  background: rgba(255, 255, 255, 0.03);
  color: var(--text, #fff);
}

.sidebar-btn.active {
  color: var(--text, #fff);
  background: color-mix(in srgb, var(--accent, #7c3aed) 14%, transparent);
  border-color: color-mix(in srgb, var(--accent, #7c3aed) 45%, transparent);
}

.sidebar-btn__label {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.1px;
}

.sidebar-btn__desc {
  font-size: 11px;
  color: var(--text-muted, #6c6c78);
  font-weight: 500;
}

.sidebar-btn.active .sidebar-btn__desc {
  color: color-mix(in srgb, var(--accent, #7c3aed) 70%, #fff);
}

/* === BODY === */

.body {
  flex: 1;
  min-width: 0;
  overflow-y: auto;
  padding: 22px 26px;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.error {
  padding: 11px 14px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.22);
  border-radius: 10px;
  font-size: 13px;
}

/* === SECTION === */

.section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.section__title {
  margin: 0;
  color: var(--text-dim, #8a8a97);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

.section__count {
  font-size: 11px;
  color: var(--text-muted, #6c6c78);
  font-weight: 700;
}

/* === AVATAR & COVER === */

.cover-upload {
  border-radius: 12px;
  overflow: hidden;
}

.cover-preview {
  position: relative;
  height: 150px;
  background-size: cover;
  background-position: center;
  border-radius: 12px;
  border: 1px solid var(--border, #23232e);
}

.cover-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(0, 0, 0, 0.55);
  opacity: 0;
  transition: opacity 0.2s;
  border-radius: 12px;
}

.cover-preview:hover .cover-overlay { opacity: 1; }

.cover-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 30px;
  background: rgba(255, 255, 255, 0.015);
  border: 2px dashed var(--border, #23232e);
  border-radius: 12px;
  cursor: pointer;
  color: var(--text-muted, #6c6c78);
  transition: all 0.2s;
}

.cover-empty:hover {
  border-color: var(--accent, #7c3aed);
  color: color-mix(in srgb, var(--accent, #7c3aed) 70%, #fff);
  background: color-mix(in srgb, var(--accent, #7c3aed) 6%, transparent);
}

.cover-empty input { display: none; }
.cover-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text, #fff);
}
.cover-empty small {
  font-size: 11px;
  color: var(--text-muted, #6c6c78);
}

.avatar-upload {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 4px;
}

.avatar-wrap {
  position: relative;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid var(--border, #23232e);
  background: #0d0d14;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0d0d14;
  color: var(--text-dim, #8a8a97);
  font-size: 32px;
  font-weight: 800;
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  opacity: 0;
  transition: opacity 0.2s;
}

.avatar-wrap:hover .avatar-overlay { opacity: 1; }

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.btn-change--sm,
.btn-remove--sm {
  padding: 6px 11px;
  font-size: 11px;
  border-radius: 7px;
}

.btn-remove--sm {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.25);
  cursor: pointer;
  font-weight: 700;
}

.btn-remove--sm:hover {
  background: rgba(239, 68, 68, 0.18);
}

/* === FRAMES === */

.frames-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: 8px;
}

.frame-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 6px;
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.frame-btn:hover {
  border-color: var(--border-hover, #35354a);
  transform: translateY(-1px);
}

.frame-btn.active {
  border-color: var(--accent, #7c3aed);
  background: color-mix(in srgb, var(--accent, #7c3aed) 10%, transparent);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #7c3aed) 20%, transparent);
}

.frame-preview {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 2px solid var(--bg-card, #12121a);
}

.frame-name {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-dim, #8a8a97);
  text-align: center;
}

.frame-btn.active .frame-name {
  color: color-mix(in srgb, var(--accent, #7c3aed) 60%, #fff);
}

/* === EFFECTS === */

.effects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 8px;
}

.effect-btn {
  padding: 10px 12px;
  color: var(--text-dim, #8a8a97);
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.effect-btn:hover {
  border-color: var(--border-hover, #35354a);
  color: var(--text, #fff);
}

.effect-btn.active {
  color: #fff;
  background: var(--accent, #7c3aed);
  border-color: var(--accent, #7c3aed);
  box-shadow: 0 4px 12px -4px color-mix(in srgb, var(--accent, #7c3aed) 60%, transparent);
}

/* === COLORS === */

.colors-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.color-btn {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.15s, border-color 0.15s;
}

.color-btn:hover { transform: scale(1.08); }

.color-btn.active {
  border-color: #fff;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.14);
}

.color-custom {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--border, #23232e);
  border-radius: 9px;
  background: transparent;
  cursor: pointer;
}

/* === CARD BACKGROUND === */

.bg-upload { border-radius: 12px; overflow: hidden; }

.bg-preview {
  position: relative;
  height: 150px;
  background-size: cover;
  background-position: center;
  border-radius: 12px;
  border: 1px solid var(--border, #23232e);
}

.bg-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(0, 0, 0, 0.55);
  opacity: 0;
  transition: opacity 0.2s;
  border-radius: 12px;
}

.bg-preview:hover .bg-overlay { opacity: 1; }

.btn-change,
.btn-remove {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  border: 0;
}

.btn-change {
  color: #fff;
  background: var(--accent, #7c3aed);
}

.btn-change input { display: none; }

.btn-remove {
  color: #fff;
  background: rgba(239, 68, 68, 0.85);
}

.bg-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 30px;
  background: rgba(255, 255, 255, 0.015);
  border: 2px dashed var(--border, #23232e);
  border-radius: 12px;
  cursor: pointer;
  color: var(--text-muted, #6c6c78);
  transition: all 0.2s;
}

.bg-empty:hover {
  border-color: var(--accent, #7c3aed);
  color: color-mix(in srgb, var(--accent, #7c3aed) 70%, #fff);
  background: color-mix(in srgb, var(--accent, #7c3aed) 6%, transparent);
}

.bg-empty input { display: none; }
.bg-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text, #fff);
}
.bg-empty small {
  font-size: 11px;
  color: var(--text-muted, #6c6c78);
}

/* === FIELDS === */

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: var(--text-dim, #8a8a97);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.field input,
.field textarea {
  width: 100%;
  padding: 11px 13px;
  color: var(--text, #fff);
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 10px;
  font: inherit;
  font-size: 13.5px;
  outline: none;
  resize: vertical;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.field input:focus,
.field textarea:focus {
  border-color: var(--accent, #7c3aed);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent, #7c3aed) 18%, transparent);
}

.hint {
  color: var(--text-muted, #6c6c78);
  font-size: 11px;
}

/* === MODES === */

.modes-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.mode-btn {
  padding: 8px 14px;
  color: var(--text-dim, #8a8a97);
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.mode-btn:hover { border-color: var(--border-hover, #35354a); }

.mode-btn.active {
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border-color: color-mix(in srgb, var(--color) 50%, transparent);
}

/* === ACHIEVEMENTS === */

.achievements-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 8px;
}

.ach-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 13px;
  background: #0d0d14;
  border: 1px solid var(--border, #23232e);
  border-radius: 10px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
}

.ach-btn:hover {
  border-color: var(--color);
  transform: translateY(-1px);
}

.ach-btn.active {
  border-color: var(--color);
  background: color-mix(in srgb, var(--color) 10%, transparent);
  box-shadow: 0 0 0 1px var(--color);
}

.ach-btn__icon { font-size: 20px; flex-shrink: 0; }

.ach-btn__name {
  flex: 1;
  font-size: 12.5px;
  font-weight: 700;
  color: var(--text, #fff);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ach-btn__check {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border, #23232e);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 900;
  color: var(--text-muted, #6c6c78);
}

.ach-btn.active .ach-btn__check {
  background: var(--color);
  border-color: var(--color);
  color: #000;
}

/* === EMPTY === */

.empty {
  padding: 32px;
  text-align: center;
  color: var(--text-dim, #8a8a97);
  font-size: 13px;
  background: rgba(255, 255, 255, 0.015);
  border: 1px dashed var(--border, #23232e);
  border-radius: 12px;
}

/* === FOOT === */

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 16px 26px;
  border-top: 1px solid var(--border, #23232e);
  background: linear-gradient(0deg, rgba(255, 255, 255, 0.02), transparent);
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
  color: var(--text-dim, #8a8a97);
  background: transparent;
  border: 1px solid var(--border, #23232e);
}

.btn-cancel:hover {
  color: var(--text, #fff);
  border-color: var(--border-hover, #35354a);
}

.btn-save {
  color: #fff;
  background: var(--accent, #7c3aed);
  box-shadow: 0 6px 20px -6px color-mix(in srgb, var(--accent, #7c3aed) 60%, transparent);
}

.btn-save:hover:not(:disabled) {
  background: var(--accent-light, #8b5cf6);
  transform: translateY(-1px);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* === RESPONSIVE === */

@media (max-width: 720px) {
  .modal-layout {
    grid-template-columns: 1fr;
  }

  .sidebar {
    border-right: 0;
    border-bottom: 1px solid var(--border, #23232e);
    padding: 10px 12px;
  }

  .sidebar-nav {
    flex-direction: row;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .sidebar-nav::-webkit-scrollbar { display: none; }

  .sidebar-btn {
    flex: 0 0 auto;
    padding: 9px 14px;
  }

  .sidebar-btn__desc { display: none; }

  .body { padding: 18px 18px; }

  .modal-foot { flex-direction: column-reverse; }
  .btn-cancel,
  .btn-save { width: 100%; }

  .avatar-upload {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>