<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { playersApi } from '@/services/players.js'
import { achievementsApi } from '@/services/achievements.js'
import {
  AVATAR_FRAMES,
  PROFILE_EFFECTS,
  ACCENT_COLORS,
  RARITY_COLORS,
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
  favorite_modes: Array.isArray(user.value?.favorite_modes) ? user.value.favorite_modes : [],
  featured_achievements: Array.isArray(user.value?.featured_achievements)
      ? user.value.featured_achievements
      : [],
})

const loading = ref(false)
const error = ref('')

const cardBgFile = ref(null)
const cardBgPreview = ref(user.value?.card_background_url ?? null)

const myAchievements = ref([])
const achievementsLoading = ref(true)

function onCardBgChange(e) {
  const f = e.target.files[0]
  if (!f) return
  cardBgFile.value = f
  cardBgPreview.value = URL.createObjectURL(f)
}

async function removeCardBg() {
  if (!confirm('Удалить фон карточки?')) return
  await playersApi.removeCardBackground()
  cardBgFile.value = null
  cardBgPreview.value = null
  await auth.fetchMe()
}

function toggleMode(mode) {
  if (!Array.isArray(form.value.favorite_modes)) {
    form.value.favorite_modes = []
  }

  const list = form.value.favorite_modes
  const idx = list.indexOf(mode)

  if (idx >= 0) {
    list.splice(idx, 1)
  } else if (list.length < 6) {
    list.push(mode)
  }
}
function toggleFeatured(a) {
  if (!Array.isArray(form.value.featured_achievements)) {
    form.value.featured_achievements = []
  }

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

async function loadAchievements() {
  achievementsLoading.value = true
  try {
    const data = await achievementsApi.list()
    myAchievements.value = data.achievements.filter(a => a.earned)
  } catch (e) {
    console.error(e)
  } finally {
    achievementsLoading.value = false
  }
}

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { ...form.value }

    if (cardBgFile.value) {
      payload.card_background = cardBgFile.value
    }

    await playersApi.updateProfile(payload)
    await auth.fetchMe()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

onMounted(loadAchievements)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <div>
          <h2>Кастомизация профиля</h2>
          <p class="sub">Сделай профиль крутым</p>
        </div>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <!-- TABS -->
      <nav class="tabs">
        <button :class="{ active: tab === 'style' }" @click="tab = 'style'">
          🎨 Стиль
        </button>
        <button :class="{ active: tab === 'info' }" @click="tab = 'info'">
          📝 Инфо
        </button>
        <button :class="{ active: tab === 'achievements' }" @click="tab = 'achievements'">
          🏆 Витрина
        </button>
      </nav>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- === STYLE === -->
        <template v-if="tab === 'style'">
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

          <!-- Кастомный фон карточки -->
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
                    <input type="file" accept="image/*" @change="onCardBgChange" />
                    Заменить
                  </label>
                  <button
                      type="button"
                      class="btn-remove"
                      @click="removeCardBg"
                  >
                    Удалить
                  </button>
                </div>
              </div>

              <label v-else class="bg-empty">
                <input type="file" accept="image/*" @change="onCardBgChange" />
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
                placeholder="Например: 🎮 Играю в PvP"
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
  max-width: 680px;
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
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

/* TABS */

.tabs {
  display: flex;
  gap: 4px;
  padding: 0 22px;
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}

.tabs::-webkit-scrollbar { display: none; }

.tabs button {
  padding: 12px 16px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
  white-space: nowrap;
  transition: all 0.15s;
}

.tabs button:hover {
  color: var(--text);
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

/* BODY */

.body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

/* SECTION */

.section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.section__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.section__title {
  margin: 0;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.section__count {
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 700;
}

/* FRAMES */

.frames-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
}

.frame-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 6px;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
}

.frame-btn:hover {
  border-color: var(--border-hover);
  transform: translateY(-1px);
}

.frame-btn.active {
  border-color: var(--accent);
  background: rgba(124, 58, 237, 0.08);
  box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.15);
}

.frame-preview {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 2px solid var(--bg-card);
}

.frame-name {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-dim);
  text-align: center;
}

.frame-btn.active .frame-name {
  color: var(--accent-light);
}

/* EFFECTS */

.effects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 8px;
}

.effect-btn {
  padding: 10px 12px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.effect-btn:hover {
  border-color: var(--border-hover);
  color: var(--text);
}

.effect-btn.active {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}

/* COLORS */

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

.color-btn:hover {
  transform: scale(1.1);
}

.color-btn.active {
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

/* BACKGROUND UPLOAD */

.bg-upload {
  border-radius: 10px;
  overflow: hidden;
}

.bg-preview {
  position: relative;
  height: 140px;
  background-size: cover;
  background-position: center;
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
  background: var(--accent);
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
  padding: 28px;
  background: #0d0d14;
  border: 2px dashed var(--border);
  border-radius: 10px;
  cursor: pointer;
  color: var(--text-muted);
  transition: all 0.2s;
}

.bg-empty:hover {
  border-color: var(--accent);
  color: var(--accent-light);
}

.bg-empty input { display: none; }

.bg-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.bg-empty small {
  font-size: 11px;
  color: var(--text-muted);
}

/* FIELDS */

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input,
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
}

.field input:focus,
.field textarea:focus {
  border-color: var(--accent);
}

.hint {
  color: var(--text-muted);
  font-size: 11px;
}

/* MODES */

.modes-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.mode-btn {
  padding: 8px 14px;
  color: var(--text-dim);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.mode-btn:hover {
  border-color: var(--border-hover);
}

.mode-btn.active {
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border-color: color-mix(in srgb, var(--color) 50%, transparent);
}

/* ACHIEVEMENTS */

.achievements-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
}

.ach-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #0d0d14;
  border: 1px solid var(--border);
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

.ach-btn__icon {
  font-size: 20px;
  flex-shrink: 0;
}

.ach-btn__name {
  flex: 1;
  font-size: 12px;
  font-weight: 700;
  color: var(--text);
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
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 900;
  color: var(--text-muted);
}

.ach-btn.active .ach-btn__check {
  background: var(--color);
  border-color: var(--color);
  color: #000;
}

/* EMPTY */

.empty {
  padding: 30px;
  text-align: center;
  color: var(--text-dim);
  font-size: 13px;
  background: #0d0d14;
  border: 1px dashed var(--border);
  border-radius: 12px;
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

@media (max-width: 600px) {
  .modal-foot {
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-save {
    width: 100%;
  }

  .frames-grid {
    grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
  }

  .achievements-grid {
    grid-template-columns: 1fr;
  }
}
</style>