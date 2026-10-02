<script setup>
import { highlightColor, highlightEffect, DEFAULT_HIGHLIGHT_EFFECT } from '@/data/clan/clanHighlight.js'
import ClanHighlightStylePicker from '@/components/clan/dialogs/ClanHighlightStylePicker.vue'
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
  // Купленное оформление подсветки: цвета и эффекты из магазина
  highlightStyles: {
    type: Object,
    default: () => ({ colors: [], effects: [] }),
  },
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

              <!--
                Применение купленного оформления.
                Раньше его можно было надеть только через инвентарь.
              -->
              <ClanHighlightStylePicker
                  v-if="clan.is_highlighted"
                  :clan="clan"
                  :styles="props.highlightStyles"
                  @updated="$emit('updated')"
              />
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
@import "@/components/clan/dialogs/ClanEditModal.css";
</style>
