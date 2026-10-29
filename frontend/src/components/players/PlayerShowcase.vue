<script setup>
/**
 * Витрина игрока в профиле: 3D-скин (свой или из Minecraft), ник,
 * карточка игрока и сравнение с другим игроком. В своём профиле можно
 * загрузить свой PNG-скин: сначала предпросмотр в 3D, потом сохранение.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import MinecraftSkinViewer from '@/components/minecraft/MinecraftSkinViewer.vue'
import PlayerCardModal from '@/components/players/PlayerCardModal.vue'
import BrandCube from '@/components/core/BrandCube.vue'
import { guessSkinModel, loadSkin, readSkinFile } from '@/utils/minecraftSkin.js'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  user: { type: Object, required: true },
  rank: { type: Object, default: null },
  isMe: { type: Boolean, default: false },
})

const emit = defineEmits(['link-minecraft'])

const auth = useAuthStore()

const skin = ref(null)
const skinLoading = ref(true)
const cardOpen = ref(false)
const copied = ref(false)

const nickname = computed(() => props.user?.minecraft_username || null)

/** Сравнение: чужой профиль — я против него, свой — выбрать соперника. */
const compareLink = computed(() => {
  if (props.isMe || !auth.user) {
    return { path: '/compare', query: { a: props.user.username } }
  }

  return { path: '/compare', query: { a: auth.user.username, b: props.user.username } }
})

/* ------------------------------ Свой скин ------------------------------ */

const MODELS = [
  { id: 'classic', label: 'Обычные руки' },
  { id: 'slim', label: 'Тонкие руки' },
]

// Версия своего скина: меняется сразу после загрузки, не дожидаясь профиля
const version = ref(props.user?.skin_version ?? null)
const pending = ref(null)
const skinBusy = ref(false)
const skinError = ref('')
const fileInput = ref(null)

watch(() => props.user?.skin_version, (v) => { version.value = v ?? null })

/** На сцене — предпросмотр выбранного файла или сохранённый скин. */
const shownSkin = computed(() => pending.value ?? skin.value)

/** Карточке игрока — с актуальной версией скина. */
const cardUser = computed(() => ({ ...props.user, skin_version: version.value }))

watch(
    () => [props.user?.id, props.user?.minecraft_username, version.value],
    async ([id, , v]) => {
      skinLoading.value = true
      skin.value = null

      try {
        skin.value = await loadSkin(id, v)
      } finally {
        skinLoading.value = false
      }
    },
    { immediate: true },
)

function dropPending() {
  if (pending.value) URL.revokeObjectURL(pending.value.url)
  pending.value = null
}

async function pickFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  skinError.value = ''

  if (file.type !== 'image/png') {
    skinError.value = 'Скин должен быть PNG-файлом.'
    return
  }

  try {
    const { url, image } = await readSkinFile(file)

    if (image.naturalWidth !== 64 || ![32, 64].includes(image.naturalHeight)) {
      URL.revokeObjectURL(url)
      skinError.value = `Картинка ${image.naturalWidth}×${image.naturalHeight}, а скин — 64×64 (или старый 64×32).`
      return
    }

    dropPending()
    pending.value = { file, url, image, model: guessSkinModel(image) }
  } catch {
    skinError.value = 'Не получилось прочитать файл.'
  }
}

function setModel(model) {
  pending.value = { ...pending.value, model }
}

/** Новая версия скина — во все места, где он показывается. */
function applyVersion(v) {
  version.value = v
  if (auth.user && auth.user.id === props.user.id) auth.user.skin_version = v
}

async function saveSkin() {
  if (!pending.value || skinBusy.value) return

  skinBusy.value = true
  skinError.value = ''

  try {
    const form = new FormData()
    form.append('skin', pending.value.file)
    form.append('model', pending.value.model)

    const data = await api.post('/players/me/skin', form)
    dropPending()
    applyVersion(data.skin_version)
  } catch (e) {
    skinError.value = e.errors?.skin?.[0] || e.message || 'Не получилось загрузить скин.'
  } finally {
    skinBusy.value = false
  }
}

async function removeSkin() {
  if (skinBusy.value) return

  skinBusy.value = true
  skinError.value = ''

  try {
    await api.delete('/players/me/skin')
    applyVersion(null)
  } catch (e) {
    skinError.value = e.message || 'Не получилось.'
  } finally {
    skinBusy.value = false
  }
}

onBeforeUnmount(dropPending)

async function copyNickname() {
  if (!nickname.value) return

  try {
    await navigator.clipboard.writeText(nickname.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1800)
  } catch {
    /* буфер недоступен — ник и так виден */
  }
}
</script>

<template>
  <section class="showcase">
    <div
        class="showcase__stage mc-backdrop"
        :class="user.skin_backdrop ? `mc-backdrop--${user.skin_backdrop}` : null"
    >
      <MinecraftSkinViewer
          v-if="shownSkin"
          :skin="shownSkin"
          :name-tag="nickname"
      />

      <div v-else class="showcase__empty">
        <BrandCube class="showcase__cube" :class="{ 'showcase__cube--loading': skinLoading }" />

        <template v-if="!skinLoading">
          <strong>{{ nickname ? 'Скин не загрузился' : 'Minecraft не привязан' }}</strong>
          <span v-if="nickname">Mojang не ответил — попробуйте позже</span>
          <span v-else-if="isMe">Укажите ник или загрузите свой скин — здесь появится 3D-модель</span>
          <span v-else>Игрок ещё не указал ник из игры</span>
        </template>
      </div>

      <span v-if="pending" class="showcase__preview">Предпросмотр</span>
    </div>

    <div class="showcase__info">
      <span class="showcase__label">Minecraft</span>

      <div v-if="nickname" class="showcase__nick">
        <strong>{{ nickname }}</strong>
        <button
            type="button"
            class="showcase__copy"
            :title="copied ? 'Скопировано' : 'Скопировать ник'"
            @click="copyNickname"
        >
          {{ copied ? 'Скопировано' : 'Копировать' }}
        </button>
      </div>

      <button
          v-else-if="isMe"
          type="button"
          class="btn btn-secondary showcase__link"
          @click="emit('link-minecraft')"
      >
        Привязать ник
      </button>

      <div v-if="isMe" class="showcase__skin">
        <input ref="fileInput" type="file" accept="image/png" hidden @change="pickFile" />

        <template v-if="pending">
          <div class="showcase__models" role="radiogroup" aria-label="Модель рук">
            <button
                v-for="m in MODELS"
                :key="m.id"
                type="button"
                role="radio"
                :aria-checked="pending.model === m.id"
                :class="{ on: pending.model === m.id }"
                @click="setModel(m.id)"
            >{{ m.label }}</button>
          </div>
          <div class="showcase__skin-actions">
            <button type="button" class="btn btn-primary" :disabled="skinBusy" @click="saveSkin">
              {{ skinBusy ? 'Сохраняем…' : 'Сохранить скин' }}
            </button>
            <button type="button" class="btn btn-secondary" :disabled="skinBusy" @click="dropPending">Отмена</button>
          </div>
        </template>

        <div v-else class="showcase__skin-actions">
          <button type="button" class="btn btn-secondary" :disabled="skinBusy" @click="fileInput?.click()">
            {{ version ? 'Сменить свой скин' : 'Загрузить свой скин' }}
          </button>
          <button v-if="version" type="button" class="showcase__reset" :disabled="skinBusy" @click="removeSkin">
            {{ nickname ? 'Вернуть скин из Minecraft' : 'Убрать свой скин' }}
          </button>
        </div>

        <p v-if="skinError" class="showcase__error">{{ skinError }}</p>
        <p v-else class="showcase__hint">PNG 64×64, как для Minecraft. Свой скин виден в профиле, на карточке и в сравнении.</p>
      </div>

      <p class="showcase__text">
        Соберите карточку игрока с тиром, аспектами и скином — её удобно
        кинуть в Discord. Или сравните себя с любым игроком по каждому навыку.
      </p>

      <div class="showcase__actions">
        <button
            type="button"
            class="btn btn-primary"
            @click="cardOpen = true"
        >
          Карточка игрока
        </button>

        <RouterLink
            v-if="auth.isAuthenticated"
            :to="compareLink"
            class="btn btn-secondary"
        >
          {{ isMe ? 'Сравнить с игроком' : 'Сравнить со мной' }}
        </RouterLink>
      </div>
    </div>

    <PlayerCardModal
        v-model="cardOpen"
        :user="cardUser"
        :rank="rank"
    />
  </section>
</template>

<style scoped>
.showcase {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);

  overflow: hidden;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.showcase__stage {
  height: 360px;

  /* Фон — из assets/backdrops.css: шахматка или купленная сцена */
  border-right: 1px solid var(--border);
}

/* 3D-модель поверх декоративных слоёв сцены */
.showcase__stage > * {
  position: relative;
  z-index: 1;
}

.showcase__empty {
  height: 100%;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;

  padding: 24px;

  color: var(--text-muted);

  font-size: 14px;
  text-align: center;
}

.showcase__empty strong {
  color: var(--text);
  font-size: 15px;
}

.showcase__cube {
  width: 56px;
  height: 56px;
  margin-bottom: 10px;
}

.showcase__cube--loading {
  animation: showcase-hop 0.9s ease-in-out infinite;
}

@keyframes showcase-hop {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

.showcase__info {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;

  padding: 28px;
}

.showcase__label {
  color: var(--text-muted);

  font-size: 13px;
  font-weight: 600;
}

.showcase__nick {
  display: flex;
  align-items: center;
  gap: 12px;
}

.showcase__nick strong {
  color: #fff;

  font-family: var(--font-display);
  font-size: 24px;
  letter-spacing: -0.02em;
}

.showcase__copy {
  padding: 4px 10px;

  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;

  font-size: 12px;
  font-weight: 600;

  cursor: pointer;
}

.showcase__copy:hover {
  color: #fff;
  border-color: var(--border-hover);
}

.showcase__link {
  align-self: flex-start;
}

.showcase__text {
  margin: 0;

  color: var(--text-dim);

  font-size: 14px;
}

.showcase__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

/* Свой скин */
.showcase__stage {
  position: relative;
}

.showcase__preview {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 2;

  padding: 4px 10px;

  color: #1a1206;
  background: #facc15;
  border-radius: 99px;

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.showcase__skin {
  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 14px;

  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.showcase__skin-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.showcase__models {
  display: inline-flex;
  align-self: flex-start;
  gap: 4px;

  padding: 3px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;
}

.showcase__models button {
  padding: 6px 12px;

  color: var(--text-dim);
  background: none;
  border: 0;
  border-radius: 6px;

  font: inherit;
  font-size: 13px;
  font-weight: 600;

  cursor: pointer;
}

.showcase__models button.on {
  color: #fff;
  background: var(--border-hover, rgba(255, 255, 255, 0.12));
}

.showcase__reset {
  padding: 0;

  color: var(--text-dim);
  background: none;
  border: 0;

  font: inherit;
  font-size: 13px;
  text-decoration: underline;
  text-underline-offset: 3px;

  cursor: pointer;
}

.showcase__reset:hover {
  color: #fff;
}

.showcase__hint,
.showcase__error {
  margin: 0;

  font-size: 12.5px;
}

.showcase__hint {
  color: var(--text-muted);
}

.showcase__error {
  color: #fca5a5;
}

@media (prefers-reduced-motion: reduce) {
  .showcase__cube--loading {
    animation: none;
  }
}

@media (max-width: 760px) {
  .showcase {
    grid-template-columns: 1fr;
  }

  .showcase__stage {
    height: 300px;
    border-right: 0;
    border-bottom: 1px solid var(--border);
  }

  .showcase__info {
    padding: 20px;
  }
}
</style>
