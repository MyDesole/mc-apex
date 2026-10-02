<script setup>
import { highlightColor, highlightEffect, DEFAULT_HIGHLIGHT_EFFECT } from '@/data/clanHighlight.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
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
  entry_fee: props.clan.entry_fee ?? 0,
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

const styleLabel = computed(() => {
  if (!props.clan?.is_highlighted) return ''

  const color = props.clan.highlight_color
      ? highlightColor(props.clan.highlight_color).name
      : null

  const effectKey = props.clan.highlight_effect ?? DEFAULT_HIGHLIGHT_EFFECT

  const effect = effectKey === DEFAULT_HIGHLIGHT_EFFECT
      ? null
      : highlightEffect(effectKey).name

  return [color, effect].filter(Boolean).join(' · ')
})

function formatUntil(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

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

  await clansApi.removeAvatar(props.clan.id)

  avatarFile.value = null
  avatarPreview.value = null

  emit('updated')
}

async function removeCover() {
  if (!await confirmDialog('Удалить подложку?')) return

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

    if (avatarFile.value) {
      payload.avatar = avatarFile.value
    }

    if (coverFile.value) {
      payload.cover = coverFile.value
    }

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
  '#7c3aed',
  '#8b5cf6',
  '#06b6d4',
  '#22c55e',
  '#f97316',
  '#ef4444',
  '#facc15',
  '#ec4899',
]
</script>

<template>
  <Teleport to="body">
    <Transition name="clan-modal">
      <div
          class="clan-modal"
          @click.self="$emit('close')"
      >
        <div class="clan-modal__backdrop"></div>

        <div
            class="clan-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clan-settings-title"
        >
          <!-- =========================
               HEADER
          ========================== -->

          <header class="clan-modal__header">
            <div class="clan-modal__title-group">
              <div class="clan-modal__eyebrow">
                <span class="clan-modal__eyebrow-dot"></span>
                CLAN MANAGEMENT
              </div>

              <h2 id="clan-settings-title">
                Настройки клана
              </h2>

              <p>
                Настрой внешний вид, информацию и правила вступления.
              </p>
            </div>

            <button
                type="button"
                class="clan-modal__close"
                aria-label="Закрыть"
                @click="$emit('close')"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M6 6l12 12" />
                <path d="M18 6 6 18" />
              </svg>
            </button>
          </header>

          <!-- =========================
               ERROR
          ========================== -->

          <Transition name="error">
            <div
                v-if="error"
                class="clan-modal__error"
            >
              <div class="clan-modal__error-icon">
                !
              </div>

              <div>
                <strong>Не удалось сохранить изменения</strong>
                <span>{{ error }}</span>
              </div>
            </div>
          </Transition>

          <!-- =========================
               BODY
          ========================== -->

          <div class="clan-modal__body">

            <!-- =====================
                 APPEARANCE
            ====================== -->

            <section class="settings-block">
              <div class="settings-heading">
                <div class="settings-heading__icon settings-heading__icon--purple">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8l-4 3V5Z" />
                    <circle cx="9" cy="9" r="1" />
                    <circle cx="15" cy="9" r="1" />
                    <path d="M7 14c1.5 1.3 3.2 2 5 2s3.5-.7 5-2" />
                  </svg>
                </div>

                <div>
                  <h3>Оформление</h3>
                  <p>
                    Создай визуальный образ своего клана.
                  </p>
                </div>
              </div>

              <!-- Cover -->
              <div class="cover-editor">
                <div class="cover-editor__label">
                  <span>Обложка клана</span>
                  <small>Рекомендуется 1200 × 400</small>
                </div>

                <div
                    v-if="coverPreview"
                    class="cover"
                    :style="{
                    backgroundImage: `url(${coverPreview})`
                  }"
                >
                  <div class="cover__shade"></div>

                  <div class="cover__grid"></div>

                  <div class="cover__content">
                    <div class="cover__clan">
                      <div
                          class="cover__avatar"
                          :style="{
                          background: form.banner_color
                        }"
                      >
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            :alt="clan.name"
                        >

                        <span v-else>
                          {{ form.tag?.charAt(0) || 'C' }}
                        </span>
                      </div>

                      <div>
                        <div class="cover__tag">
                          [{{ form.tag || 'CLAN' }}]
                        </div>

                        <strong>
                          {{ form.name || 'Название клана' }}
                        </strong>
                      </div>
                    </div>

                    <div class="cover__actions">
                      <label class="cover-action">
                        <input
                            type="file"
                            accept="image/*"
                            @change="onCoverChange"
                        >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                          <path d="M12 20h9" />
                          <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
                        </svg>

                        Заменить
                      </label>

                      <button
                          type="button"
                          class="cover-action cover-action--danger"
                          @click="removeCover"
                      >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                          <path d="M4 7h16" />
                          <path d="M10 11v6" />
                          <path d="M14 11v6" />
                          <path d="M6 7l1 14h10l1-14" />
                          <path d="M9 7V4h6v3" />
                        </svg>

                        Удалить
                      </button>
                    </div>
                  </div>
                </div>

                <label
                    v-else
                    class="cover-empty"
                >
                  <input
                      type="file"
                      accept="image/*"
                      @change="onCoverChange"
                  >

                  <div class="cover-empty__icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                      <path d="M12 16V4" />
                      <path d="m7 9 5-5 5 5" />
                      <path d="M5 20h14" />
                    </svg>
                  </div>

                  <strong>Загрузить обложку</strong>
                  <span>
                    JPG, PNG или WebP · до 5 МБ
                  </span>
                </label>
              </div>

              <!-- Identity -->
              <div class="identity-editor">
                <div class="avatar-editor">
                  <div class="field-caption">
                    Аватар
                  </div>

                  <div
                      class="avatar"
                      :class="{ 'avatar--empty': !avatarPreview }"
                      :style="avatarPreview
                      ? { backgroundImage: `url(${avatarPreview})` }
                      : { background: form.banner_color }"
                  >
                    <div class="avatar__shine"></div>

                    <template v-if="avatarPreview">
                      <img
                          :src="avatarPreview"
                          :alt="form.name"
                      >
                    </template>

                    <template v-else>
                      <span>
                        {{ form.tag?.charAt(0) || 'C' }}
                      </span>
                    </template>
                  </div>

                  <div class="avatar-actions">
                    <label class="small-action small-action--primary">
                      <input
                          type="file"
                          accept="image/*"
                          @change="onAvatarChange"
                      >

                      <svg
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="1.8"
                      >
                        <path d="M12 16V4" />
                        <path d="m7 9 5-5 5 5" />
                        <path d="M5 20h14" />
                      </svg>

                      {{ avatarPreview ? 'Заменить' : 'Загрузить' }}
                    </label>

                    <button
                        v-if="avatarPreview"
                        type="button"
                        class="small-action small-action--danger"
                        @click="removeAvatar"
                    >
                      Удалить
                    </button>
                  </div>
                </div>

                <div class="identity-fields">
                  <div class="field">
                    <label for="clan-name">
                      Название
                    </label>

                    <input
                        id="clan-name"
                        v-model="form.name"
                        type="text"
                        maxlength="32"
                        placeholder="Название клана"
                    >

                    <div class="field-meta">
                      <span>Название видно всем игрокам</span>
                      <span>{{ form.name.length }}/32</span>
                    </div>
                  </div>

                  <div class="field">
                    <label for="clan-tag">
                      Тег
                    </label>

                    <div class="tag-input">
                      <span>[</span>

                      <input
                          id="clan-tag"
                          v-model="form.tag"
                          type="text"
                          maxlength="8"
                          placeholder="TAG"
                          @input="form.tag = form.tag.toUpperCase()"
                      >

                      <span>]</span>
                    </div>

                    <div class="field-meta">
                      <span>До 8 символов</span>
                      <span>{{ form.tag.length }}/8</span>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- =====================
                 DESCRIPTION
            ====================== -->

            <section class="settings-block">
              <div class="settings-heading">
                <div class="settings-heading__icon settings-heading__icon--cyan">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8l-4 3V5Z" />
                    <path d="M8 8h8" />
                    <path d="M8 12h6" />
                  </svg>
                </div>

                <div>
                  <h3>О клане</h3>
                  <p>
                    Расскажи игрокам, кто вы и чего хотите добиться.
                  </p>
                </div>
              </div>

              <div class="field">
                <div class="field-label-row">
                  <label for="clan-description">
                    Описание
                  </label>

                  <span>
                    {{ form.description.length }}/1000
                  </span>
                </div>

                <textarea
                    id="clan-description"
                    v-model="form.description"
                    rows="5"
                    maxlength="1000"
                    placeholder="Например: PvP-клан для активных игроков. Играем каждый вечер, участвуем в войнах и ищем новых сильных участников..."
                ></textarea>
              </div>
            </section>

            <!-- =====================
                 COLOR
            ====================== -->

            <section class="settings-block">
              <div class="settings-heading">
                <div class="settings-heading__icon settings-heading__icon--pink">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <circle cx="8" cy="10" r="1" />
                    <circle cx="13" cy="7" r="1" />
                    <circle cx="17" cy="11" r="1" />
                    <path d="M7 15c1.5 1.5 3.2 2.3 5 2.3 2.8 0 5-1.5 6-3.3" />
                  </svg>
                </div>

                <div>
                  <h3>Цвет клана</h3>
                  <p>
                    Используется для акцентов и элементов профиля.
                  </p>
                </div>
              </div>

              <div class="color-editor">
                <div
                    class="color-current"
                    :style="{ background: form.banner_color }"
                >
                  <span></span>

                  <div>
                    <strong>{{ form.banner_color.toUpperCase() }}</strong>
                    <small>Основной цвет</small>
                  </div>
                </div>

                <div class="color-presets">
                  <button
                      v-for="c in colorPresets"
                      :key="c"
                      type="button"
                      class="color-swatch"
                      :class="{ 'color-swatch--active': form.banner_color === c }"
                      :style="{ '--swatch': c }"
                      :aria-label="`Выбрать цвет ${c}`"
                      @click="form.banner_color = c"
                  >
                    <span></span>

                    <svg
                        v-if="form.banner_color === c"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                    >
                      <path d="m5 12 4 4L19 6" />
                    </svg>
                  </button>

                  <label
                      class="color-custom"
                      :style="{ '--swatch': form.banner_color }"
                  >
                    <input
                        v-model="form.banner_color"
                        type="color"
                    >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                      <path d="M12 3v18" />
                      <path d="M3 12h18" />
                    </svg>
                  </label>
                </div>
              </div>
            </section>

            <!-- =====================
                 SOCIALS
            ====================== -->

            <section class="settings-block">
              <div class="settings-heading">
                <div class="settings-heading__icon settings-heading__icon--blue">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1" />
                    <path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1" />
                  </svg>
                </div>

                <div>
                  <h3>Ссылки</h3>
                  <p>
                    Добавь площадки, где игроки могут найти ваш клан.
                  </p>
                </div>
              </div>

              <div class="social-grid">
                <div class="social-field">
                  <span class="social-field__icon social-field__icon--discord">
                    D
                  </span>

                  <div class="social-field__body">
                    <label>Discord</label>

                    <input
                        v-model="form.socials.discord"
                        type="url"
                        placeholder="https://discord.gg/..."
                    >
                  </div>
                </div>

                <div class="social-field">
                  <span class="social-field__icon social-field__icon--telegram">
                    T
                  </span>

                  <div class="social-field__body">
                    <label>Telegram</label>

                    <input
                        v-model="form.socials.telegram"
                        type="url"
                        placeholder="https://t.me/..."
                    >
                  </div>
                </div>

                <div class="social-field">
                  <span class="social-field__icon social-field__icon--youtube">
                    Y
                  </span>

                  <div class="social-field__body">
                    <label>YouTube</label>

                    <input
                        v-model="form.socials.youtube"
                        type="url"
                        placeholder="https://youtube.com/..."
                    >
                  </div>
                </div>

                <div class="social-field">
                  <span class="social-field__icon social-field__icon--vk">
                    VK
                  </span>

                  <div class="social-field__body">
                    <label>VK</label>

                    <input
                        v-model="form.socials.vk"
                        type="url"
                        placeholder="https://vk.com/..."
                    >
                  </div>
                </div>

                <div class="social-field social-field--full">
                  <span class="social-field__icon social-field__icon--web">
                    ↗
                  </span>

                  <div class="social-field__body">
                    <label>Сайт</label>

                    <input
                        v-model="form.socials.website"
                        type="url"
                        placeholder="https://..."
                    >
                  </div>
                </div>
              </div>
            </section>

            <!-- =====================
                 ACCESS
            ====================== -->

            <section class="settings-block">
              <div class="settings-heading">
                <div class="settings-heading__icon settings-heading__icon--green">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                    <path d="m9 12 2 2 4-4" />
                  </svg>
                </div>

                <div>
                  <h3>Доступ и вступление</h3>
                  <p>
                    Настрой правила для новых участников.
                  </p>
                </div>
              </div>

              <!-- Open clan -->
              <label class="access-card">
                <div class="access-card__check">
                  <input
                      v-model="form.is_open"
                      type="checkbox"
                  >

                  <span>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                    >
                      <path d="m5 12 4 4L19 6" />
                    </svg>
                  </span>
                </div>

                <div class="access-card__icon access-card__icon--green">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M12 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                    <path d="M19 21a7 7 0 0 0-14 0" />
                  </svg>
                </div>

                <div class="access-card__content">
                  <strong>Открытый набор</strong>
                  <span>
                    Любой игрок сможет отправить заявку в ваш клан.
                  </span>
                </div>

                <div
                    class="access-card__status"
                    :class="{ 'access-card__status--off': !form.is_open }"
                >
                  {{ form.is_open ? 'Открыт' : 'Закрыт' }}
                </div>
              </label>

              <!-- Entry fee -->
              <div class="fee-card">
                <div class="fee-card__top">
                  <div class="fee-card__icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                      <circle cx="12" cy="12" r="9" />
                      <path d="M15 8.5c-.8-.6-1.8-.9-3-.9-1.7 0-3 .8-3 2s1.2 1.8 3 2.1c1.8.3 3 1 3 2.3s-1.3 2.2-3 2.2c-1.2 0-2.3-.4-3.1-1" />
                      <path d="M12 5v14" />
                    </svg>
                  </div>

                  <div>
                    <strong>Плата за вступление</strong>
                    <span>
                      Получайте оплату при принятии заявки.
                    </span>
                  </div>
                </div>

                <div class="fee-card__input">
                  <input
                      v-model.number="form.entry_fee"
                      type="number"
                      min="0"
                      max="100000"
                      step="50"
                  >

                  <span>ApexCoin</span>
                </div>

                <p>
                  0 — бесплатное вступление. Средства списываются
                  с игрока только после принятия заявки.
                </p>
              </div>

              <!-- Highlight -->
              <RouterLink
                  class="highlight-card"
                  to="/shop?type=clan_highlight"
              >
                <div class="highlight-card__visual">
                  <div class="highlight-card__star">
                    ★
                  </div>

                  <div class="highlight-card__orb"></div>
                </div>

                <div class="highlight-card__content">
                  <div class="highlight-card__title">
                    <strong>Выделение клана</strong>

                    <span
                        v-if="clan.is_highlighted"
                        class="highlight-active"
                    >
                      АКТИВНО
                    </span>
                  </div>

                  <span v-if="clan.is_highlighted && clan.highlight_until">
                    До {{ formatUntil(clan.highlight_until) }}
                    <template v-if="styleLabel">
                      · {{ styleLabel }}
                    </template>
                  </span>

                  <span v-else>
                    Сделайте клан заметнее в списках и профиле.
                  </span>

                  <small>
                    Цвет и эффект подсветки покупаются отдельно в магазине.
                  </small>
                </div>

                <div class="highlight-card__arrow">
                  <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                  >
                    <path d="M5 12h14" />
                    <path d="m13 6 6 6-6 6" />
                  </svg>
                </div>
              </RouterLink>
            </section>

          </div>

          <!-- =========================
               FOOTER
          ========================== -->

          <footer class="clan-modal__footer">
            <div class="save-hint">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.6"
              >
                <circle cx="12" cy="12" r="9" />
                <path d="M12 11v5" />
                <path d="M12 8h.01" />
              </svg>

              Изменения применятся после сохранения
            </div>

            <div class="footer-actions">
              <button
                  type="button"
                  class="footer-button footer-button--cancel"
                  @click="$emit('close')"
              >
                Отмена
              </button>

              <button
                  type="button"
                  class="footer-button footer-button--save"
                  :disabled="loading"
                  @click="submit"
              >
                <span
                    v-if="loading"
                    class="button-spinner"
                ></span>

                <svg
                    v-else
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                  <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" />
                  <path d="M17 21v-6H7v6" />
                  <path d="M7 3v5h8" />
                </svg>

                {{ loading ? 'Сохраняем…' : 'Сохранить изменения' }}
              </button>
            </div>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* =========================================================
   ROOT
========================================================= */

.clan-modal {
  --modal-bg: var(--bg-card, #111114);
  --modal-border: var(--border, rgba(255, 255, 255, 0.08));
  --modal-text: var(--text, #f4f4f5);
  --modal-dim: var(--text-dim, #a1a1aa);
  --modal-muted: var(--text-muted, #71717a);
  --modal-accent: var(--accent, #8b5cf6);
  --modal-accent-light: var(--accent-light, #a78bfa);

  position: fixed;
  inset: 0;
  z-index: 3000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 24px;

  color: var(--modal-text);
}

.clan-modal__backdrop {
  position: absolute;
  inset: 0;

  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(124, 58, 237, 0.1),
          transparent 42%
      ),
      rgba(3, 3, 8, 0.82);

  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}

.clan-modal__window {
  position: relative;
  z-index: 1;

  width: min(760px, 100%);
  max-height: min(900px, calc(100vh - 48px));

  display: flex;
  flex-direction: column;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, 0.085);
  border-radius: 24px;

  background:
      radial-gradient(
          circle at 0% 0%,
          rgba(139, 92, 246, 0.055),
          transparent 35%
      ),
      var(--modal-bg);

  box-shadow:
      0 40px 120px rgba(0, 0, 0, 0.55),
      0 0 0 1px rgba(255, 255, 255, 0.015),
      inset 0 1px 0 rgba(255, 255, 255, 0.035);
}

/* =========================================================
   TRANSITION
========================================================= */

.clan-modal-enter-active .clan-modal__backdrop,
.clan-modal-leave-active .clan-modal__backdrop {
  transition: opacity 0.22s ease;
}

.clan-modal-enter-active .clan-modal__window,
.clan-modal-leave-active .clan-modal__window {
  transition:
      opacity 0.22s ease,
      transform 0.22s ease;
}

.clan-modal-enter-from .clan-modal__backdrop,
.clan-modal-leave-to .clan-modal__backdrop {
  opacity: 0;
}

.clan-modal-enter-from .clan-modal__window,
.clan-modal-leave-to .clan-modal__window {
  opacity: 0;
  transform: translateY(12px) scale(0.985);
}

/* =========================================================
   HEADER
========================================================= */

.clan-modal__header {
  position: relative;

  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 20px;

  padding: 24px 25px 21px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.055);

  background:
      linear-gradient(
          135deg,
          rgba(139, 92, 246, 0.04),
          transparent 50%
      );
}

.clan-modal__title-group {
  min-width: 0;
}

.clan-modal__eyebrow {
  display: flex;
  align-items: center;
  gap: 7px;

  margin-bottom: 7px;

  color: var(--modal-accent-light);

  font-size: 9px;
  line-height: 1;
  font-weight: 850;
  letter-spacing: 0.17em;
}

.clan-modal__eyebrow-dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: var(--modal-accent);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, 0.08),
      0 0 14px rgba(139, 92, 246, 0.55);
}

.clan-modal__header h2 {
  margin: 0;

  color: #fafafa;

  font-size: 21px;
  line-height: 1.15;
  font-weight: 850;
  letter-spacing: -0.035em;
}

.clan-modal__header p {
  margin: 7px 0 0;

  color: var(--modal-muted);

  font-size: 11px;
  line-height: 1.45;
}

.clan-modal__close {
  flex: 0 0 auto;

  width: 38px;
  height: 38px;

  display: grid;
  place-items: center;

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 11px;

  background: rgba(255, 255, 255, 0.035);

  color: var(--modal-dim);

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease,
      transform 0.18s ease;
}

.clan-modal__close svg {
  width: 17px;
  height: 17px;
}

.clan-modal__close:hover {
  color: #fff;

  background: rgba(255, 255, 255, 0.075);
  border-color: rgba(255, 255, 255, 0.13);

  transform: rotate(3deg);
}

/* =========================================================
   ERROR
========================================================= */

.clan-modal__error {
  display: flex;
  align-items: center;
  gap: 11px;

  margin: 14px 24px 0;
  padding: 11px 13px;

  border: 1px solid rgba(248, 113, 113, 0.16);
  border-radius: 12px;

  background: rgba(248, 113, 113, 0.055);
}

.clan-modal__error-icon {
  width: 28px;
  height: 28px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 8px;

  background: rgba(248, 113, 113, 0.1);

  color: #fca5a5;

  font-size: 13px;
  font-weight: 900;
}

.clan-modal__error div:last-child {
  min-width: 0;
}

.clan-modal__error strong,
.clan-modal__error span {
  display: block;
}

.clan-modal__error strong {
  color: #fecaca;
  font-size: 11px;
  font-weight: 800;
}

.clan-modal__error span {
  margin-top: 2px;

  color: #a1a1aa;

  font-size: 10px;
  line-height: 1.4;

  overflow-wrap: anywhere;
}

/* =========================================================
   BODY
========================================================= */

.clan-modal__body {
  min-height: 0;

  overflow-y: auto;

  padding: 0 25px;

  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}

.clan-modal__body::-webkit-scrollbar {
  width: 5px;
}

.clan-modal__body::-webkit-scrollbar-track {
  background: transparent;
}

.clan-modal__body::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 99px;
}

/* =========================================================
   SETTINGS BLOCK
========================================================= */

.settings-block {
  padding: 25px 0;

  border-bottom: 1px solid rgba(255, 255, 255, 0.055);
}

.settings-block:last-child {
  border-bottom: 0;
}

.settings-heading {
  display: flex;
  align-items: center;
  gap: 11px;

  margin-bottom: 17px;
}

.settings-heading__icon {
  width: 34px;
  height: 34px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 10px;
}

.settings-heading__icon svg {
  width: 16px;
  height: 16px;
}

.settings-heading__icon--purple {
  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.1);
}

.settings-heading__icon--cyan {
  color: #67e8f9;
  background: rgba(6, 182, 212, 0.09);
}

.settings-heading__icon--pink {
  color: #f9a8d4;
  background: rgba(236, 72, 153, 0.09);
}

.settings-heading__icon--blue {
  color: #93c5fd;
  background: rgba(59, 130, 246, 0.09);
}

.settings-heading__icon--green {
  color: #86efac;
  background: rgba(34, 197, 94, 0.09);
}

.settings-heading h3 {
  margin: 0;

  color: #f4f4f5;

  font-size: 13px;
  font-weight: 800;
  letter-spacing: -0.015em;
}

.settings-heading p {
  margin: 3px 0 0;

  color: var(--modal-muted);

  font-size: 10px;
  line-height: 1.4;
}

/* =========================================================
   COVER
========================================================= */

.cover-editor__label {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 7px;
}

.cover-editor__label span {
  color: var(--modal-dim);

  font-size: 10px;
  font-weight: 750;
}

.cover-editor__label small {
  color: var(--modal-muted);

  font-size: 9px;
}

.cover {
  position: relative;

  min-height: 188px;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, 0.075);
  border-radius: 15px;

  background-position: center;
  background-size: cover;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.cover__shade {
  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          180deg,
          rgba(5, 5, 10, 0.12),
          rgba(5, 5, 10, 0.72)
      );
}

.cover__grid {
  position: absolute;
  inset: 0;

  opacity: 0.15;

  background-image:
      linear-gradient(
          rgba(255, 255, 255, 0.08) 1px,
          transparent 1px
      ),
      linear-gradient(
          90deg,
          rgba(255, 255, 255, 0.08) 1px,
          transparent 1px
      );

  background-size: 35px 35px;

  mask-image: linear-gradient(to bottom, black, transparent);
}

.cover__content {
  position: absolute;
  inset: 0;

  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 15px;

  padding: 18px;
}

.cover__clan {
  display: flex;
  align-items: center;
  gap: 11px;

  min-width: 0;
}

.cover__avatar {
  width: 50px;
  height: 50px;

  display: grid;
  place-items: center;

  overflow: hidden;

  border: 2px solid rgba(255, 255, 255, 0.65);
  border-radius: 13px;

  box-shadow:
      0 8px 25px rgba(0, 0, 0, 0.3);
}

.cover__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover__avatar span {
  color: #fff;

  font-size: 18px;
  font-weight: 900;
}

.cover__tag {
  margin-bottom: 2px;

  color: rgba(255, 255, 255, 0.65);

  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.cover__clan strong {
  display: block;

  color: #fff;

  font-size: 15px;
  font-weight: 850;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cover__actions {
  display: flex;
  gap: 6px;
}

.cover-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 7px 10px;

  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 8px;

  background: rgba(0, 0, 0, 0.35);

  color: #fff;

  font-family: inherit;
  font-size: 9px;
  font-weight: 800;

  cursor: pointer;

  backdrop-filter: blur(8px);

  transition: 0.18s;
}

.cover-action input {
  display: none;
}

.cover-action svg {
  width: 12px;
  height: 12px;
}

.cover-action:hover {
  background: rgba(255, 255, 255, 0.1);
}

.cover-action--danger {
  color: #fca5a5;
}

.cover-empty {
  min-height: 188px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  border: 1px dashed rgba(255, 255, 255, 0.1);
  border-radius: 15px;

  background:
      radial-gradient(
          circle at 50% 20%,
          rgba(139, 92, 246, 0.06),
          transparent 45%
      ),
      rgba(255, 255, 255, 0.015);

  cursor: pointer;

  transition: 0.18s;
}

.cover-empty:hover {
  border-color: rgba(139, 92, 246, 0.35);
  background-color: rgba(139, 92, 246, 0.025);
}

.cover-empty input {
  display: none;
}

.cover-empty__icon {
  width: 42px;
  height: 42px;

  display: grid;
  place-items: center;

  margin-bottom: 9px;

  border: 1px solid rgba(139, 92, 246, 0.15);
  border-radius: 12px;

  background: rgba(139, 92, 246, 0.08);

  color: var(--modal-accent-light);
}

.cover-empty__icon svg {
  width: 18px;
  height: 18px;
}

.cover-empty strong {
  color: #e4e4e7;

  font-size: 11px;
  font-weight: 800;
}

.cover-empty span {
  margin-top: 4px;

  color: var(--modal-muted);

  font-size: 9px;
}

/* =========================================================
   IDENTITY
========================================================= */

.identity-editor {
  display: grid;
  grid-template-columns: 104px minmax(0, 1fr);
  gap: 18px;

  margin-top: 17px;
}

.field-caption {
  margin-bottom: 7px;

  color: var(--modal-dim);

  font-size: 10px;
  font-weight: 750;
}

.avatar {
  position: relative;

  width: 88px;
  height: 88px;

  display: grid;
  place-items: center;

  overflow: hidden;

  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;

  background-position: center;
  background-size: cover;

  box-shadow:
      0 12px 30px rgba(0, 0, 0, 0.25),
      inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.avatar img {
  position: relative;
  z-index: 1;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.avatar__shine {
  position: absolute;
  inset: 0;

  z-index: 2;

  pointer-events: none;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.16),
          transparent 35%
      );
}

.avatar span {
  position: relative;
  z-index: 1;

  color: #fff;

  font-size: 30px;
  font-weight: 900;
  letter-spacing: -0.06em;
}

.avatar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;

  margin-top: 7px;
}

.small-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-height: 24px;

  padding: 0 7px;

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 6px;

  font-family: inherit;
  font-size: 8px;
  font-weight: 800;

  cursor: pointer;

  transition: 0.18s;
}

.small-action input {
  display: none;
}

.small-action svg {
  width: 11px;
  height: 11px;

  margin-right: 4px;
}

.small-action--primary {
  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.08);
  border-color: rgba(139, 92, 246, 0.15);
}

.small-action--primary:hover {
  background: rgba(139, 92, 246, 0.15);
}

.small-action--danger {
  color: #fca5a5;
  background: rgba(248, 113, 113, 0.05);
}

.small-action--danger:hover {
  background: rgba(248, 113, 113, 0.1);
}

.identity-fields {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 130px;

  gap: 12px;

  align-content: start;
}

/* =========================================================
   FIELDS
========================================================= */

.field {
  min-width: 0;
}

.field > label,
.field-label-row label {
  display: block;

  color: var(--modal-dim);

  font-size: 10px;
  font-weight: 750;
}

.field input:not(.tag-input input),
.field textarea,
.social-field input {
  width: 100%;
  box-sizing: border-box;

  color: var(--modal-text);
  background: rgba(5, 5, 10, 0.55);

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;

  outline: none;

  font-family: inherit;
  font-size: 11px;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      box-shadow 0.18s ease;
}

.field input:not(.tag-input input),
.social-field input {
  height: 39px;
  margin-top: 7px;
  padding: 0 11px;
}

.field textarea {
  display: block;

  min-height: 112px;

  margin-top: 7px;
  padding: 11px;

  resize: vertical;

  line-height: 1.55;
}

.field input:focus,
.field textarea:focus,
.social-field input:focus {
  border-color: rgba(139, 92, 246, 0.55);

  background: rgba(139, 92, 246, 0.025);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, 0.075);
}

.field input::placeholder,
.field textarea::placeholder,
.social-field input::placeholder {
  color: #52525b;
}

.field-meta,
.field-label-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.field-meta {
  margin-top: 5px;

  color: #52525b;

  font-size: 8px;
}

.field-label-row {
  align-items: center;
}

.field-label-row > span {
  color: #52525b;

  font-size: 8px;
}

.tag-input {
  height: 39px;

  display: flex;
  align-items: center;

  margin-top: 7px;
  padding: 0 10px;

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;

  background: rgba(5, 5, 10, 0.55);

  color: #71717a;

  transition: 0.18s;
}

.tag-input:focus-within {
  border-color: rgba(139, 92, 246, 0.55);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.075);
}

.tag-input input {
  width: 100%;

  padding: 0 2px;

  border: 0;
  outline: 0;

  background: transparent;

  color: #f4f4f5;

  font-family: inherit;
  font-size: 11px;
  font-weight: 850;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

/* =========================================================
   COLOR
========================================================= */

.color-editor {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 20px;

  padding: 13px;

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;

  background: rgba(255, 255, 255, 0.018);
}

.color-current {
  display: flex;
  align-items: center;
  gap: 9px;
}

.color-current > span {
  width: 35px;
  height: 35px;

  border-radius: 10px;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.25),
      0 5px 18px rgba(0, 0, 0, 0.25);
}

.color-current strong,
.color-current small {
  display: block;
}

.color-current strong {
  color: #e4e4e7;

  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.color-current small {
  margin-top: 2px;

  color: var(--modal-muted);

  font-size: 8px;
}

.color-presets {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.color-swatch,
.color-custom {
  position: relative;

  width: 28px;
  height: 28px;

  padding: 0;

  display: grid;
  place-items: center;

  border-radius: 8px;

  cursor: pointer;

  transition: 0.15s;
}

.color-swatch {
  border: 1px solid rgba(255, 255, 255, 0.08);

  background: var(--swatch);
}

.color-swatch:hover,
.color-custom:hover {
  transform: translateY(-1px) scale(1.04);
}

.color-swatch--active {
  box-shadow:
      0 0 0 2px var(--modal-bg),
      0 0 0 4px var(--swatch);
}

.color-swatch svg {
  width: 12px;
  height: 12px;

  color: #fff;

  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.4));
}

.color-custom {
  border: 1px dashed rgba(255, 255, 255, 0.2);

  background:
      conic-gradient(
          #ef4444,
          #facc15,
          #22c55e,
          #06b6d4,
          #3b82f6,
          #a855f7,
          #ef4444
      );
}

.color-custom input {
  position: absolute;

  width: 1px;
  height: 1px;

  opacity: 0;
}

.color-custom svg {
  width: 12px;
  height: 12px;

  padding: 3px;

  box-sizing: content-box;

  border-radius: 5px;

  color: #fff;

  background: rgba(0, 0, 0, 0.45);
}

/* =========================================================
   SOCIALS
========================================================= */

.social-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.social-field {
  min-width: 0;

  display: flex;
  align-items: center;
  gap: 9px;

  padding: 9px;

  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 11px;

  background: rgba(255, 255, 255, 0.016);
}

.social-field--full {
  grid-column: 1 / -1;
}

.social-field__icon {
  width: 29px;
  height: 29px;

  flex: 0 0 29px;

  display: grid;
  place-items: center;

  border-radius: 8px;

  color: #fff;

  font-size: 9px;
  font-weight: 900;
}

.social-field__icon--discord {
  background: #5865f2;
}

.social-field__icon--telegram {
  background: #229ed9;
}

.social-field__icon--youtube {
  background: #ef4444;
}

.social-field__icon--vk {
  background: #0077ff;
}

.social-field__icon--web {
  background: #52525b;
  font-size: 14px;
}

.social-field__body {
  min-width: 0;
  flex: 1;
}

.social-field__body label {
  display: block;

  color: #a1a1aa;

  font-size: 8px;
  font-weight: 750;
}

.social-field input {
  height: 28px;

  margin-top: 3px;
  padding: 0;

  border: 0;

  background: transparent;

  font-size: 10px;

  box-shadow: none;
}

.social-field input:focus {
  box-shadow: none;
  background: transparent;
}

/* =========================================================
   ACCESS
========================================================= */

.access-card {
  position: relative;

  display: flex;
  align-items: center;

  gap: 10px;

  padding: 13px;

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;

  background: rgba(255, 255, 255, 0.018);

  cursor: pointer;

  transition: 0.18s;
}

.access-card:hover {
  border-color: rgba(255, 255, 255, 0.11);
  background: rgba(255, 255, 255, 0.027);
}

.access-card__check {
  position: relative;

  flex: 0 0 auto;

  width: 22px;
  height: 22px;
}

.access-card__check input {
  position: absolute;

  inset: 0;

  opacity: 0;

  cursor: pointer;
}

.access-card__check span {
  width: 22px;
  height: 22px;

  display: grid;
  place-items: center;

  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 7px;

  background: #0a0a0f;

  color: transparent;

  transition: 0.18s;
}

.access-card__check svg {
  width: 12px;
  height: 12px;
}

.access-card__check input:checked + span {
  border-color: var(--modal-accent);
  background: var(--modal-accent);
  color: #fff;

  box-shadow: 0 0 16px rgba(139, 92, 246, 0.2);
}

.access-card__icon {
  width: 31px;
  height: 31px;

  display: grid;
  place-items: center;

  border-radius: 9px;
}

.access-card__icon svg {
  width: 15px;
  height: 15px;
}

.access-card__icon--green {
  color: #86efac;
  background: rgba(34, 197, 94, 0.08);
}

.access-card__content {
  min-width: 0;
  flex: 1;
}

.access-card__content strong,
.access-card__content span {
  display: block;
}

.access-card__content strong {
  color: #e4e4e7;

  font-size: 11px;
  font-weight: 800;
}

.access-card__content span {
  margin-top: 3px;

  color: var(--modal-muted);

  font-size: 9px;
  line-height: 1.4;
}

.access-card__status {
  padding: 5px 8px;

  border-radius: 999px;

  background: rgba(34, 197, 94, 0.08);

  color: #86efac;

  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.access-card__status--off {
  background: rgba(255, 255, 255, 0.045);
  color: #71717a;
}

/* =========================================================
   FEE
========================================================= */

.fee-card {
  margin-top: 8px;

  padding: 14px;

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 13px;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.025),
          transparent 55%
      ),
      rgba(255, 255, 255, 0.016);
}

.fee-card__top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fee-card__icon {
  width: 31px;
  height: 31px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 9px;

  background: rgba(250, 204, 21, 0.08);

  color: #facc15;
}

.fee-card__icon svg {
  width: 15px;
  height: 15px;
}

.fee-card__top strong,
.fee-card__top span {
  display: block;
}

.fee-card__top strong {
  color: #e4e4e7;

  font-size: 11px;
  font-weight: 800;
}

.fee-card__top span {
  margin-top: 3px;

  color: var(--modal-muted);

  font-size: 9px;
}

.fee-card__input {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-top: 12px;
}

.fee-card__input input {
  width: 150px;
  height: 36px;

  box-sizing: border-box;

  padding: 0 10px;

  color: #f4f4f5;

  background: rgba(5, 5, 10, 0.6);

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 9px;

  outline: none;

  font-family: inherit;
  font-size: 11px;
  font-weight: 750;
}

.fee-card__input input:focus {
  border-color: rgba(250, 204, 21, 0.45);

  box-shadow:
      0 0 0 3px rgba(250, 204, 21, 0.06);
}

.fee-card__input span {
  color: #facc15;

  font-size: 10px;
  font-weight: 800;
}

.fee-card p {
  margin: 8px 0 0;

  color: #52525b;

  font-size: 9px;
  line-height: 1.5;
}

/* =========================================================
   HIGHLIGHT
========================================================= */

.highlight-card {
  position: relative;

  display: flex;
  align-items: center;

  gap: 12px;

  margin-top: 8px;
  padding: 13px;

  overflow: hidden;

  border: 1px solid rgba(250, 204, 21, 0.12);
  border-radius: 13px;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(250, 204, 21, 0.075),
          transparent 35%
      ),
      rgba(250, 204, 21, 0.018);

  color: inherit;

  text-decoration: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.highlight-card:hover {
  border-color: rgba(250, 204, 21, 0.28);

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(250, 204, 21, 0.11),
          transparent 40%
      ),
      rgba(250, 204, 21, 0.025);

  transform: translateY(-1px);
}

.highlight-card__visual {
  position: relative;

  width: 38px;
  height: 38px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  overflow: hidden;

  border-radius: 11px;

  background:
      linear-gradient(
          135deg,
          rgba(250, 204, 21, 0.17),
          rgba(245, 158, 11, 0.05)
      );
}

.highlight-card__star {
  position: relative;
  z-index: 2;

  color: #fde68a;

  font-size: 19px;

  text-shadow:
      0 0 15px rgba(250, 204, 21, 0.65);
}

.highlight-card__orb {
  position: absolute;

  width: 25px;
  height: 25px;

  border-radius: 50%;

  background: #facc15;

  opacity: 0.15;

  filter: blur(10px);
}

.highlight-card__content {
  min-width: 0;
  flex: 1;
}

.highlight-card__title {
  display: flex;
  align-items: center;
  gap: 7px;
}

.highlight-card__title strong {
  color: #f4f4f5;

  font-size: 11px;
  font-weight: 850;
}

.highlight-card__content > span {
  display: block;

  margin-top: 3px;

  color: #a1a1aa;

  font-size: 9px;
  line-height: 1.4;
}

.highlight-card__content small {
  display: block;

  margin-top: 5px;

  color: #71717a;

  font-size: 8px;
}

.highlight-active {
  padding: 3px 5px;

  border-radius: 5px;

  background: rgba(34, 197, 94, 0.08);

  color: #86efac;

  font-size: 7px;
  font-weight: 850;
  letter-spacing: 0.06em;
}

.highlight-card__arrow {
  width: 28px;
  height: 28px;

  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 8px;

  background: rgba(250, 204, 21, 0.06);

  color: #facc15;
}

.highlight-card__arrow svg {
  width: 14px;
  height: 14px;
}

/* =========================================================
   FOOTER
========================================================= */

.clan-modal__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 15px;

  padding: 14px 25px;

  border-top: 1px solid rgba(255, 255, 255, 0.055);

  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.012),
          rgba(0, 0, 0, 0.08)
      );
}

.save-hint {
  display: flex;
  align-items: center;
  gap: 6px;

  color: #52525b;

  font-size: 8px;
}

.save-hint svg {
  width: 13px;
  height: 13px;
}

.footer-actions {
  display: flex;
  gap: 7px;
}

.footer-button {
  min-height: 39px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  padding: 0 14px;

  border-radius: 9px;

  font-family: inherit;

  font-size: 10px;
  font-weight: 800;

  cursor: pointer;

  transition:
      transform 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease,
      opacity 0.18s ease;
}

.footer-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.footer-button svg {
  width: 14px;
  height: 14px;
}

.footer-button--cancel {
  border: 1px solid rgba(255, 255, 255, 0.075);

  background: rgba(255, 255, 255, 0.035);

  color: #a1a1aa;
}

.footer-button--cancel:hover {
  background: rgba(255, 255, 255, 0.065);
  color: #e4e4e7;
}

.footer-button--save {
  border: 1px solid rgba(139, 92, 246, 0.3);

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  color: #fff;

  box-shadow:
      0 8px 22px rgba(109, 40, 217, 0.2);
}

.footer-button--save:hover:not(:disabled) {
  box-shadow:
      0 10px 28px rgba(109, 40, 217, 0.3);
}

.footer-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.button-spinner {
  width: 13px;
  height: 13px;

  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;

  border-radius: 50%;

  animation: spinner 0.7s linear infinite;
}

@keyframes spinner {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   ERROR TRANSITION
========================================================= */

.error-enter-active,
.error-leave-active {
  transition:
      opacity 0.18s ease,
      transform 0.18s ease;
}

.error-enter-from,
.error-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {
  .clan-modal {
    padding: 12px;
  }

  .clan-modal__window {
    max-height: calc(100vh - 24px);
    border-radius: 19px;
  }

  .clan-modal__header {
    padding: 19px;
  }

  .clan-modal__body {
    padding: 0 19px;
  }

  .clan-modal__footer {
    padding: 12px 19px;
  }

  .settings-block {
    padding: 21px 0;
  }

  .social-grid {
    grid-template-columns: 1fr;
  }

  .social-field--full {
    grid-column: auto;
  }
}

@media (max-width: 560px) {
  .clan-modal {
    padding: 0;
    align-items: flex-end;
  }

  .clan-modal__window {
    width: 100%;
    max-height: 94vh;

    border-radius: 20px 20px 0 0;
  }

  .clan-modal__header {
    padding: 17px;
  }

  .clan-modal__body {
    padding: 0 17px;
  }

  .clan-modal__footer {
    flex-direction: column;
    align-items: stretch;

    padding: 12px 17px;
  }

  .save-hint {
    justify-content: center;
  }

  .footer-actions {
    width: 100%;
  }

  .footer-button {
    flex: 1;
  }

  .identity-editor {
    grid-template-columns: 1fr;
  }

  .avatar-editor {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .avatar-editor .field-caption {
    width: 100%;
    margin-bottom: -4px;
  }

  .avatar {
    width: 70px;
    height: 70px;
  }

  .avatar-actions {
    margin-top: 0;
  }

  .identity-fields {
    grid-template-columns: 1fr 105px;
  }

  .color-editor {
    align-items: flex-start;
    flex-direction: column;
  }

  .color-presets {
    width: 100%;
  }

  .color-swatch,
  .color-custom {
    width: 31px;
    height: 31px;
  }

  .cover {
    min-height: 160px;
  }

  .cover__content {
    padding: 13px;
  }

  .cover__actions {
    position: absolute;
    top: 13px;
    right: 13px;
  }

  .cover-action {
    padding: 7px;
  }

  .cover-action svg {
    margin: 0;
  }

  .cover-action {
    font-size: 0;
  }

  .cover-action svg {
    width: 14px;
    height: 14px;
  }

  .highlight-card {
    align-items: flex-start;
  }
}

@media (max-width: 390px) {
  .identity-fields {
    grid-template-columns: 1fr;
  }

  .cover__clan {
    max-width: 70%;
  }

  .cover__avatar {
    width: 42px;
    height: 42px;
  }

  .cover__clan strong {
    font-size: 12px;
  }

  .access-card__status {
    display: none;
  }

  .fee-card__input input {
    width: 120px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .clan-modal *,
  .clan-modal *::before,
  .clan-modal *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}
</style>
