<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { playersApi } from '@/services/players.js'
import { achievementsApi } from '@/services/achievements.js'
import { shopApi } from '@/services/shop.js'
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

// Владение косметикой из магазина (чтобы нельзя было надеть некупленное)
const ownedFrames = ref(new Set())
const ownedEffects = ref(new Set())
const ownedAccents = ref(new Set())
const paidAccents = ref(new Set())
const shopLoading = ref(false)

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
  if (!await confirmDialog('Удалить аватар?')) return
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
  if (!await confirmDialog('Удалить обложку?')) return
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
  if (!await confirmDialog('Удалить фон карточки?')) return
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

// Купленная косметика: какие рамки/эффекты/акценты можно надевать
async function loadOwned() {
  shopLoading.value = true
  try {
    const data = await shopApi.inventory()
    const items = data.items ?? []

    ownedFrames.value = new Set(
      items.filter((i) => i.type === 'avatar_frame').map((i) => i.effect_value).filter(Boolean)
    )
    ownedEffects.value = new Set(
      items.filter((i) => i.type === 'profile_effect').map((i) => i.effect_value).filter(Boolean)
    )
    ownedAccents.value = new Set(
      items.filter((i) => i.type === 'accent_color').map((i) => i.effect_value).filter(Boolean)
    )
  } catch (e) {
    console.error('Не удалось загрузить инвентарь косметики', e)
  } finally {
    shopLoading.value = false
  }

  // Какие акценты продаются в магазине (нельзя надевать бесплатно, пока не купил)
  try {
    const catalog = await shopApi.catalog()
    paidAccents.value = new Set(
      (catalog.items ?? [])
        .filter((i) => i.type === 'accent_color')
        .map((i) => i.effect_value)
        .filter(Boolean)
    )
  } catch (e) {
    console.error('Не удалось загрузить каталог', e)
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

onMounted(() => {
  loadAchievements()
  loadOwned()
})

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
              <p class="section__hint">
                Купленное в магазине доступно сразу; остальное — <RouterLink :to="{ name: 'shop' }" class="link">купить в магазине</RouterLink>.
              </p>
              <div class="frames-grid">
                <button
                    v-for="f in AVATAR_FRAMES"
                    :key="f.id"
                    type="button"
                    class="frame-btn"
                    :class="{ active: form.avatar_frame === f.id, locked: f.id !== 'default' && !ownedFrames.has(f.id) }"
                    :disabled="f.id !== 'default' && !ownedFrames.has(f.id)"
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
                    :class="{ active: form.profile_effect === e.id, locked: e.id !== null && !ownedEffects.has(e.id) }"
                    :disabled="e.id !== null && !ownedEffects.has(e.id)"
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
                    :class="{ active: form.accent_color === c, locked: paidAccents.has(c) && !ownedAccents.has(c) }"
                    :style="{ background: c }"
                    :disabled="paidAccents.has(c) && !ownedAccents.has(c)"
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
/* ============================================================
   PROFILE CUSTOMIZATION MODAL
   APEX / CINEMATIC / ESPORTS
   ============================================================ */

.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 20px;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(139, 92, 246, .075),
          transparent 45%
      ),
      rgba(3, 4, 10, .82);

  backdrop-filter: blur(12px);
}

/* ============================================================
   MODAL
   ============================================================ */

.modal {
  --modal-purple: #8b5cf6;
  --modal-purple-light: #a78bfa;
  --modal-bg: #090a18;
  --modal-surface: #0d0e1c;
  --modal-surface-2: #111225;
  --modal-border: rgba(255, 255, 255, .065);

  position: relative;

  width: 100%;
  max-width: 880px;
  max-height: 92vh;

  display: flex;
  flex-direction: column;

  overflow: hidden;

  color: #f8fafc;

  background:
      linear-gradient(
          145deg,
          rgba(17, 18, 37, .985),
          rgba(7, 8, 18, .99)
      );

  border: 1px solid var(--modal-border);
  border-radius: 18px;

  box-shadow:
      0 35px 100px rgba(0, 0, 0, .72),
      0 0 0 1px rgba(139, 92, 246, .05) inset,
      0 0 70px rgba(139, 92, 246, .07);
}

/* atmospheric glow */
.modal::before {
  content: '';

  position: absolute;
  top: -180px;
  right: -150px;

  width: 380px;
  height: 380px;

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          rgba(139, 92, 246, .09),
          transparent 68%
      );

  pointer-events: none;
}

/* ============================================================
   HEADER
   ============================================================ */

.modal-head {
  position: relative;
  z-index: 2;

  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;

  padding: 20px 24px 19px;

  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, .025),
          transparent
      );

  border-bottom: 1px solid rgba(255, 255, 255, .055);
}

.modal-head h2 {
  position: relative;

  margin: 0 0 5px;
  padding-left: 13px;

  color: #f8fafc;

  font-size: 18px;
  font-weight: 950;
  letter-spacing: -.35px;
}

.modal-head h2::before {
  content: '';

  position: absolute;
  left: 0;
  top: 50%;

  width: 4px;
  height: 20px;

  transform: translateY(-50%);

  border-radius: 2px;

  background:
      linear-gradient(
          180deg,
          #a78bfa,
          #7c3aed
      );

  box-shadow:
      0 0 9px rgba(139, 92, 246, .65),
      0 0 18px rgba(139, 92, 246, .25);
}

.sub {
  margin: 0;

  color: #64748b;

  font-size: 11px;
  font-weight: 700;

  letter-spacing: .2px;
}

.close {
  position: relative;

  width: 32px;
  height: 32px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #64748b;

  background:
      rgba(255, 255, 255, .025);

  border: 1px solid rgba(255, 255, 255, .065);
  border-radius: 8px;

  cursor: pointer;

  font-size: 12px;

  transition:
      color .18s ease,
      background .18s ease,
      border-color .18s ease,
      transform .18s ease;
}

.close:hover {
  color: #f8fafc;

  background:
      rgba(139, 92, 246, .09);

  border-color:
      rgba(139, 92, 246, .3);

  transform: rotate(2deg);
}

/* ============================================================
   LAYOUT
   ============================================================ */

.modal-layout {
  position: relative;
  z-index: 1;

  flex: 1;

  display: grid;
  grid-template-columns: 205px minmax(0, 1fr);

  min-height: 0;
}

/* ============================================================
   SIDEBAR
   ============================================================ */

.sidebar {
  position: relative;

  overflow-y: auto;

  padding: 14px 11px;

  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, .018),
          rgba(255, 255, 255, .006)
      );

  border-right: 1px solid rgba(255, 255, 255, .055);
}

.sidebar::after {
  content: '';

  position: absolute;
  top: 16px;
  bottom: 16px;
  right: 0;

  width: 1px;

  background:
      linear-gradient(
          180deg,
          transparent,
          rgba(139, 92, 246, .16),
          transparent
      );

  pointer-events: none;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.sidebar-btn {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;

  width: 100%;

  padding: 11px 12px;

  color: #64748b;

  background: transparent;

  border: 1px solid transparent;
  border-radius: 9px;

  cursor: pointer;

  text-align: left;

  transition:
      color .18s ease,
      background .18s ease,
      border-color .18s ease,
      transform .18s ease;
}

.sidebar-btn:hover {
  color: #cbd5e1;

  background:
      rgba(255, 255, 255, .025);

  border-color:
      rgba(255, 255, 255, .045);
}

.sidebar-btn.active {
  color: #f8fafc;

  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, .13),
          rgba(139, 92, 246, .045)
      );

  border-color:
      rgba(139, 92, 246, .28);

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .025),
      0 0 18px rgba(139, 92, 246, .055);
}

.sidebar-btn.active::before {
  content: '';

  position: absolute;
  left: -1px;
  top: 8px;
  bottom: 8px;

  width: 2px;

  border-radius: 2px;

  background: #8b5cf6;

  box-shadow:
      0 0 8px rgba(139, 92, 246, .8),
      0 0 15px rgba(139, 92, 246, .3);
}

.sidebar-btn__label {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .15px;
}

.sidebar-btn__desc {
  color: #475569;

  font-size: 9px;
  font-weight: 700;

  letter-spacing: .2px;
}

.sidebar-btn.active .sidebar-btn__desc {
  color: #8b7bbd;
}

/* ============================================================
   BODY
   ============================================================ */

.body {
  flex: 1;
  min-width: 0;

  overflow-y: auto;

  padding: 22px 24px;

  display: flex;
  flex-direction: column;
  gap: 22px;

  scrollbar-width: thin;
  scrollbar-color:
      rgba(139, 92, 246, .22)
      transparent;
}

.body::-webkit-scrollbar {
  width: 5px;
}

.body::-webkit-scrollbar-track {
  background: transparent;
}

.body::-webkit-scrollbar-thumb {
  background: rgba(139, 92, 246, .22);
  border-radius: 10px;
}

/* ============================================================
   ERROR
   ============================================================ */

.error {
  position: relative;

  padding: 10px 12px;

  color: #fca5a5;

  background:
      linear-gradient(
          135deg,
          rgba(239, 68, 68, .08),
          rgba(239, 68, 68, .025)
      );

  border: 1px solid rgba(239, 68, 68, .2);
  border-radius: 8px;

  font-size: 10px;
  font-weight: 750;
}

/* ============================================================
   SECTION
   ============================================================ */

.section {
  display: flex;
  flex-direction: column;
  gap: 11px;
}

.section__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.section__title {
  position: relative;

  margin: 0;
  padding-left: 10px;

  color: #a7b0c0;

  font-size: 10px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: 1.25px;
}

.section__title::before {
  content: '';

  position: absolute;
  left: 0;
  top: 50%;

  width: 3px;
  height: 3px;

  transform: translateY(-50%);

  border-radius: 50%;

  background: #8b5cf6;

  box-shadow:
      0 0 6px #8b5cf6;
}

.section__count {
  color: #475569;

  font-size: 9px;
  font-weight: 900;

  letter-spacing: .5px;
}

/* ============================================================
   COVER
   ============================================================ */

.cover-upload,
.bg-upload {
  overflow: hidden;
  border-radius: 11px;
}

.cover-preview,
.bg-preview {
  position: relative;

  height: 145px;

  overflow: hidden;

  background-color: #080914;
  background-size: cover;
  background-position: center;

  border: 1px solid rgba(255, 255, 255, .065);
  border-radius: 11px;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .04);
}

.cover-preview::after,
.bg-preview::after {
  content: '';

  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          180deg,
          rgba(5, 5, 13, .05),
          rgba(5, 5, 13, .45)
      );

  pointer-events: none;
}

.cover-overlay,
.bg-overlay {
  position: absolute;
  inset: 0;
  z-index: 2;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  background:
      rgba(4, 5, 12, .68);

  opacity: 0;

  backdrop-filter: blur(3px);

  transition: opacity .2s ease;
}

.cover-preview:hover .cover-overlay,
.bg-preview:hover .bg-overlay {
  opacity: 1;
}

.cover-empty,
.bg-empty {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;

  min-height: 145px;
  padding: 25px;

  color: #475569;

  background:
      radial-gradient(
          circle at 50% 40%,
          rgba(139, 92, 246, .07),
          transparent 60%
      ),
      rgba(255, 255, 255, .012);

  border: 1px dashed rgba(255, 255, 255, .09);
  border-radius: 11px;

  cursor: pointer;

  transition:
      color .18s ease,
      background .18s ease,
      border-color .18s ease;
}

.cover-empty:hover,
.bg-empty:hover {
  color: #a78bfa;

  background:
      radial-gradient(
          circle at 50% 40%,
          rgba(139, 92, 246, .11),
          transparent 65%
      ),
      rgba(139, 92, 246, .025);

  border-color: rgba(139, 92, 246, .35);
}

.cover-empty input,
.bg-empty input {
  display: none;
}

.cover-empty span,
.bg-empty span {
  color: #cbd5e1;

  font-size: 11px;
  font-weight: 900;
}

.cover-empty small,
.bg-empty small {
  color: #475569;

  font-size: 9px;
  font-weight: 700;
}

/* ============================================================
   UPLOAD BUTTONS
   ============================================================ */

.btn-change,
.btn-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-height: 31px;
  padding: 7px 11px;

  border-radius: 7px;

  font-size: 9px;
  font-weight: 900;

  cursor: pointer;

  transition:
      transform .16s ease,
      background .16s ease,
      border-color .16s ease,
      box-shadow .16s ease;
}

.btn-change {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, .3);

  box-shadow:
      0 5px 15px rgba(109, 40, 217, .25);
}

.btn-change:hover {
  transform: translateY(-1px);

  box-shadow:
      0 7px 19px rgba(109, 40, 217, .35);
}

.btn-change input {
  display: none;
}

.btn-remove {
  color: #fca5a5;

  background:
      rgba(239, 68, 68, .07);

  border: 1px solid rgba(239, 68, 68, .22);
}

.btn-remove:hover {
  background: rgba(239, 68, 68, .13);
  border-color: rgba(239, 68, 68, .35);
}

.btn-change--sm,
.btn-remove--sm {
  min-height: 28px;
  padding: 6px 9px;
}

/* ============================================================
   AVATAR
   ============================================================ */

.avatar-upload {
  display: flex;
  align-items: center;
  gap: 15px;

  margin-top: 2px;
}

.avatar-wrap {
  position: relative;

  width: 82px;
  height: 82px;
  flex-shrink: 0;

  overflow: hidden;

  border-radius: 50%;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(139, 92, 246, .16),
          transparent 65%
      ),
      #090a14;

  border: 2px solid rgba(139, 92, 246, .25);

  box-shadow:
      0 8px 25px rgba(0, 0, 0, .4),
      0 0 20px rgba(139, 92, 246, .07);
}

.avatar-wrap::after {
  content: '';

  position: absolute;
  inset: 0;

  border-radius: inherit;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .08);

  pointer-events: none;
}

.avatar-img {
  width: 100%;
  height: 100%;

  display: block;

  object-fit: cover;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #c4b5fd;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(139, 92, 246, .2),
          transparent 65%
      ),
      #090a14;

  font-size: 28px;
  font-weight: 950;

  text-shadow:
      0 0 14px rgba(139, 92, 246, .4);
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  z-index: 2;

  display: flex;
  align-items: center;
  justify-content: center;

  background: rgba(4, 5, 12, .7);

  opacity: 0;

  transition: opacity .2s ease;
}

.avatar-wrap:hover .avatar-overlay {
  opacity: 1;
}

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hint {
  color: #475569;

  font-size: 9px;
  font-weight: 700;
}

/* ============================================================
   FRAMES
   ============================================================ */

.frames-grid {
  display: grid;
  grid-template-columns:
      repeat(auto-fill, minmax(82px, 1fr));

  gap: 7px;
}

.frame-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;

  padding: 9px 5px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, .025),
          rgba(255, 255, 255, .01)
      );

  border: 1px solid rgba(255, 255, 255, .06);
  border-radius: 9px;

  cursor: pointer;

  transition:
      transform .17s ease,
      border-color .17s ease,
      background .17s ease,
      box-shadow .17s ease;
}

.frame-btn:hover {
  transform: translateY(-1px);

  border-color:
      rgba(139, 92, 246, .28);

  background:
      rgba(139, 92, 246, .035);
}

.frame-btn.active {
  border-color:
      rgba(139, 92, 246, .5);

  background:
      rgba(139, 92, 246, .09);

  box-shadow:
      0 0 0 2px rgba(139, 92, 246, .09),
      0 0 18px rgba(139, 92, 246, .06);
}

.frame-preview {
  width: 36px;
  height: 36px;

  border-radius: 10px;

  border: 2px solid #090a18;

  box-shadow:
      0 4px 10px rgba(0, 0, 0, .35);
}

.frame-name {
  color: #64748b;

  font-size: 9px;
  font-weight: 800;

  text-align: center;
}

.frame-btn.active .frame-name {
  color: #c4b5fd;
}

/* ============================================================
   EFFECTS
   ============================================================ */

.effects-grid {
  display: grid;
  grid-template-columns:
      repeat(auto-fill, minmax(115px, 1fr));

  gap: 7px;
}

.effect-btn {
  padding: 9px 10px;

  color: #64748b;

  background:
      rgba(255, 255, 255, .018);

  border: 1px solid rgba(255, 255, 255, .06);
  border-radius: 8px;

  font-size: 10px;
  font-weight: 850;

  cursor: pointer;

  transition:
      color .17s ease,
      background .17s ease,
      border-color .17s ease,
      transform .17s ease;
}

.effect-btn:hover {
  color: #cbd5e1;

  border-color: rgba(255, 255, 255, .1);

  transform: translateY(-1px);
}

.effect-btn.active {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border-color:
      rgba(167, 139, 250, .45);

  box-shadow:
      0 6px 18px rgba(109, 40, 217, .2);
}

/* ============================================================
   COLORS
   ============================================================ */

.colors-grid {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 7px;
}

.color-btn {
  position: relative;

  width: 31px;
  height: 31px;
  padding: 0;

  border-radius: 8px;

  border: 2px solid transparent;

  cursor: pointer;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .15),
      0 3px 8px rgba(0, 0, 0, .25);

  transition:
      transform .16s ease,
      border-color .16s ease,
      box-shadow .16s ease;
}

.color-btn:hover {
  transform: scale(1.08);
}

.color-btn.active {
  border-color: #fff;

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, .18),
      0 0 13px rgba(255, 255, 255, .1);
}

.color-custom {
  width: 31px;
  height: 31px;

  padding: 2px;

  background: #090a14;

  border: 1px solid rgba(255, 255, 255, .08);
  border-radius: 8px;

  cursor: pointer;
}

/* ============================================================
   FORM FIELDS
   ============================================================ */

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  color: #64748b;

  font-size: 9px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: 1px;
}

.field input,
.field textarea {
  width: 100%;
  box-sizing: border-box;

  padding: 10px 11px;

  color: #e2e8f0;

  background:
      linear-gradient(
          145deg,
          #0d0e1a,
          #090a14
      );

  border: 1px solid rgba(255, 255, 255, .065);
  border-radius: 8px;

  outline: none;

  font: inherit;
  font-size: 12px;
  line-height: 1.45;

  resize: vertical;

  transition:
      border-color .18s ease,
      box-shadow .18s ease,
      background .18s ease;
}

.field input::placeholder,
.field textarea::placeholder {
  color: #3f4a5d;
}

.field input:focus,
.field textarea:focus {
  background:
      linear-gradient(
          145deg,
          #101122,
          #090a14
      );

  border-color:
      rgba(139, 92, 246, .45);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, .07),
      0 0 18px rgba(139, 92, 246, .05);
}

/* ============================================================
   MODES
   ============================================================ */

.modes-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.mode-btn {
  padding: 7px 11px;

  color: #64748b;

  background:
      rgba(255, 255, 255, .018);

  border: 1px solid rgba(255, 255, 255, .06);
  border-radius: 6px;

  font-size: 9px;
  font-weight: 900;

  cursor: pointer;

  transition:
      color .16s ease,
      background .16s ease,
      border-color .16s ease,
      transform .16s ease;
}

.mode-btn:hover {
  color: #cbd5e1;
  border-color: rgba(255, 255, 255, .11);
  transform: translateY(-1px);
}

.mode-btn.active {
  color: var(--color);

  background:
      color-mix(
          in srgb,
          var(--color) 11%,
          transparent
      );

  border-color:
      color-mix(
          in srgb,
          var(--color) 45%,
          transparent
      );

  box-shadow:
      0 0 12px
      color-mix(
          in srgb,
          var(--color) 8%,
          transparent
      );
}

/* ============================================================
   ACHIEVEMENTS
   ============================================================ */

.achievements-grid {
  display: grid;
  grid-template-columns:
      repeat(auto-fill, minmax(180px, 1fr));

  gap: 7px;
}

.ach-btn {
  position: relative;

  display: flex;
  align-items: center;
  gap: 9px;

  min-width: 0;

  padding: 10px 11px;

  text-align: left;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, .025),
          rgba(255, 255, 255, .01)
      );

  border: 1px solid rgba(255, 255, 255, .06);
  border-radius: 9px;

  cursor: pointer;

  transition:
      transform .17s ease,
      border-color .17s ease,
      background .17s ease,
      box-shadow .17s ease;
}

.ach-btn:hover {
  transform: translateY(-1px);

  border-color:
      color-mix(
          in srgb,
          var(--color) 45%,
          transparent
      );

  box-shadow:
      0 6px 18px rgba(0, 0, 0, .2);
}

.ach-btn.active {
  background:
      color-mix(
          in srgb,
          var(--color) 9%,
          transparent
      );

  border-color: var(--color);

  box-shadow:
      0 0 0 1px
      color-mix(
          in srgb,
          var(--color) 25%,
          transparent
      ),
      0 0 18px
      color-mix(
          in srgb,
          var(--color) 7%,
          transparent
      );
}

.ach-btn__icon {
  width: 29px;
  height: 29px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  background:
      rgba(255, 255, 255, .025);

  border-radius: 7px;

  font-size: 17px;
}

.ach-btn__name {
  flex: 1;
  min-width: 0;

  color: #aeb8c8;

  font-size: 10px;
  font-weight: 850;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ach-btn.active .ach-btn__name {
  color: #f1f5f9;
}

.ach-btn__check {
  width: 21px;
  height: 21px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #475569;

  background:
      rgba(255, 255, 255, .025);

  border: 1px solid rgba(255, 255, 255, .055);
  border-radius: 6px;

  font-size: 10px;
  font-weight: 950;
}

.ach-btn.active .ach-btn__check {
  color: #05050d;

  background: var(--color);
  border-color: var(--color);

  box-shadow:
      0 0 10px
      color-mix(
          in srgb,
          var(--color) 35%,
          transparent
      );
}

/* ============================================================
   EMPTY
   ============================================================ */

.empty {
  padding: 28px 18px;

  color: #475569;

  background:
      radial-gradient(
          circle at 50% 30%,
          rgba(139, 92, 246, .045),
          transparent 65%
      ),
      rgba(255, 255, 255, .012);

  border: 1px dashed rgba(255, 255, 255, .065);
  border-radius: 10px;

  text-align: center;

  font-size: 10px;
  font-weight: 750;

  line-height: 1.5;
}

/* ============================================================
   FOOTER
   ============================================================ */

.modal-foot {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;

  padding: 13px 24px;

  background:
      linear-gradient(
          0deg,
          rgba(255, 255, 255, .025),
          transparent
      );

  border-top: 1px solid rgba(255, 255, 255, .055);
}

.btn-cancel,
.btn-save {
  min-height: 36px;

  padding: 0 17px;

  border-radius: 8px;

  font-size: 10px;
  font-weight: 900;

  cursor: pointer;

  transition:
      transform .17s ease,
      color .17s ease,
      background .17s ease,
      border-color .17s ease,
      box-shadow .17s ease;
}

.btn-cancel {
  color: #64748b;

  background:
      rgba(255, 255, 255, .018);

  border: 1px solid rgba(255, 255, 255, .065);
}

.btn-cancel:hover {
  color: #cbd5e1;

  background:
      rgba(255, 255, 255, .035);

  border-color:
      rgba(255, 255, 255, .1);
}

.btn-save {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, .3);

  box-shadow:
      0 6px 20px rgba(109, 40, 217, .22),
      inset 0 1px rgba(255, 255, 255, .1);
}

.btn-save:hover:not(:disabled) {
  transform: translateY(-1px);

  background:
      linear-gradient(
          135deg,
          #9a6bff,
          #7c3aed
      );

  box-shadow:
      0 9px 26px rgba(109, 40, 217, .32),
      0 0 18px rgba(139, 92, 246, .1);
}

.btn-save:disabled {
  opacity: .45;
  cursor: not-allowed;
}

/* ============================================================
   FOCUS
   ============================================================ */

button:focus-visible,
label:focus-visible,
input:focus-visible,
textarea:focus-visible {
  outline: 2px solid #8b5cf6;
  outline-offset: 2px;
}

/* ============================================================
   MOBILE
   ============================================================ */

@media (max-width: 720px) {
  .modal-bg {
    align-items: flex-end;
    padding: 10px;
  }

  .modal {
    max-height: 94vh;
    border-radius: 16px;
  }

  .modal-head {
    padding: 17px 17px 16px;
  }

  .modal-head h2 {
    font-size: 16px;
  }

  .modal-layout {
    grid-template-columns: 1fr;
  }

  .sidebar {
    padding: 9px 10px;

    border-right: 0;
    border-bottom: 1px solid rgba(255, 255, 255, .055);
  }

  .sidebar::after {
    display: none;
  }

  .sidebar-nav {
    flex-direction: row;
    gap: 5px;

    overflow-x: auto;

    scrollbar-width: none;
  }

  .sidebar-nav::-webkit-scrollbar {
    display: none;
  }

  .sidebar-btn {
    width: auto;
    flex: 0 0 auto;

    padding: 8px 12px;
  }

  .sidebar-btn__desc {
    display: none;
  }

  .sidebar-btn.active::before {
    left: 8px;
    right: 8px;
    top: auto;
    bottom: -10px;

    width: auto;
    height: 2px;
  }

  .body {
    padding: 17px 15px;
    gap: 19px;
  }

  .cover-preview,
  .bg-preview,
  .cover-empty,
  .bg-empty {
    height: 125px;
    min-height: 125px;
  }

  .avatar-upload {
    align-items: flex-start;
  }

  .frames-grid {
    grid-template-columns:
        repeat(auto-fill, minmax(74px, 1fr));
  }

  .effects-grid {
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
  }

  .achievements-grid {
    grid-template-columns: 1fr;
  }

  .modal-foot {
    padding: 11px 15px;
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-save {
    width: 100%;
  }
}

@media (max-width: 430px) {
  .modal-bg {
    padding: 6px;
  }

  .modal {
    max-height: 96vh;
    border-radius: 14px;
  }

  .modal-head {
    padding: 15px;
  }

  .body {
    padding: 15px 12px;
  }

  .avatar-upload {
    gap: 11px;
  }

  .avatar-wrap {
    width: 72px;
    height: 72px;
  }

  .avatar-placeholder {
    font-size: 24px;
  }

  .effects-grid {
    grid-template-columns: 1fr;
  }
}

/* ============================================================
   REDUCED MOTION
   ============================================================ */

@media (prefers-reduced-motion: reduce) {
  .close,
  .sidebar-btn,
  .cover-empty,
  .bg-empty,
  .btn-change,
  .btn-remove,
  .frame-btn,
  .effect-btn,
  .color-btn,
  .field input,
  .field textarea,
  .mode-btn,
  .ach-btn,
  .btn-cancel,
  .btn-save {
    transition: none;
  }
}

/* Владение косметикой (магазин) */
.locked { opacity: 0.35; cursor: not-allowed; filter: grayscale(0.7); }
.locked:hover { transform: none; }
.section__hint { margin: -6px 0 12px; color: var(--text-dim, #8888a0); font-size: 13px; }
.link { color: var(--accent-light, #8b5cf6); text-decoration: none; }
.link:hover { text-decoration: underline; }
</style>