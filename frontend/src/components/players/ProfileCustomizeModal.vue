<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { playersApi } from '@/services/players/players.js'
import { achievementsApi } from '@/services/achievements/achievements.js'
import { shopApi } from '@/services/shop/shop.js'
import {
  AVATAR_FRAMES,
  PROFILE_EFFECTS,
  ACCENT_COLORS,
  FAVORITE_MODES,
} from '@/data/players/profileCustomization.js'

const auth = useAuthStore()
const emit = defineEmits(['close', 'updated'])

const user = computed(() => auth.user)

const tab = ref('style') // style | info

// Владение косметикой из магазина (чтобы нельзя было надеть некупленное)
const ownedFrames = ref(new Set())
const ownedEffects = ref(new Set())
const ownedAccents = ref(new Set())
const paidAccents = ref(new Set())
const shopLoading = ref(false)

// Чистим массивы от null / '' / undefined — иначе валидация на бэке падает
function cleanArray(arr) {
  if (!Array.isArray(arr)) return []
  return arr.filter((v) => v !== null && v !== undefined && v !== '')
}

const form = ref({
  avatar_frame: user.value?.avatar_frame ?? 'default',
  profile_effect: user.value?.profile_effect ?? null,
  accent_color: user.value?.accent_color ?? '#7c3aed',
  status: user.value?.status ?? '',
  quote: user.value?.quote ?? '',
  bio: user.value?.bio ?? '',
  discord_tag: user.value?.discord_tag ?? '',
  favorite_modes: cleanArray(user.value?.favorite_modes),
  featured_achievements: cleanArray(user.value?.featured_achievements),
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

    // Чистим массивы от null / '' / undefined перед отправкой
    const payload = {
      ...form.value,
      favorite_modes: cleanArray(form.value.favorite_modes),
      featured_achievements: cleanArray(form.value.featured_achievements),
    }

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
@import "@/components/players/ProfileCustomizeModal.css";
</style>