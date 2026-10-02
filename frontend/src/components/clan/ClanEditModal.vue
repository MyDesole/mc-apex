<script setup>
import { highlightColor, highlightEffect, DEFAULT_HIGHLIGHT_EFFECT } from '@/data/clanHighlight.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'
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
  entry_fee: props.clan.entry_fee ?? 0,
  // is_highlighted не отправляется: подсветка выдаётся покупкой в магазине
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

/** Дата окончания подсветки в читаемом виде. */
/** Подпись текущего оформления подсветки: «Фиолетовая · Пульсация». */
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
        <div>
          <div class="eyebrow">УПРАВЛЕНИЕ КЛАНОМ</div>
          <h2>Настройки клана</h2>
        </div>

        <button class="close" @click="$emit('close')">×</button>
      </header>

      <div v-if="error" class="error-banner">
        <span>!</span>
        {{ error }}
      </div>

      <div class="form-body">

        <section class="settings-section">
          <div class="section-label">
            <strong>Оформление</strong>
            <span>Как клан выглядит для остальных игроков</span>
          </div>

          <div class="cover-drop">
            <div
                v-if="coverPreview"
                class="cover-preview"
                :style="{ backgroundImage: `url(${coverPreview})` }"
            >
              <div class="cover-gradient"></div>

              <div class="cover-overlay">
                <label class="cover-btn primary">
                  <input type="file" accept="image/*" @change="onCoverChange">
                  Заменить
                </label>

                <button
                    class="cover-btn danger"
                    type="button"
                    @click="removeCover"
                >
                  Удалить
                </button>
              </div>
            </div>

            <label v-else class="cover-empty">
              <input type="file" accept="image/*" @change="onCoverChange">

              <div class="upload-icon">↑</div>

              <strong>Загрузить подложку</strong>
              <span>JPG, PNG или WebP · до 5 МБ</span>
            </label>
          </div>

          <div class="identity-row">
            <div class="avatar-block">
              <div class="avatar-label">Аватар</div>

              <div
                  class="avatar-preview"
                  :class="{ placeholder: !avatarPreview }"
                  :style="avatarPreview
                  ? { backgroundImage: `url(${avatarPreview})` }
                  : { background: form.banner_color }"
              >
                <template v-if="!avatarPreview">
                  {{ form.tag?.charAt(0) || 'C' }}
                </template>
              </div>

              <div class="avatar-actions">
                <label class="mini-btn">
                  <input type="file" accept="image/*" @change="onAvatarChange">
                  Выбрать
                </label>

                <button
                    v-if="avatarPreview"
                    class="mini-btn danger"
                    type="button"
                    @click="removeAvatar"
                >
                  Убрать
                </button>
              </div>
            </div>

            <div class="identity-fields">
              <div class="field">
                <label>Название</label>
                <input v-model="form.name" type="text" maxlength="32">
              </div>

              <div class="field">
                <label>Тег</label>
                <input
                    v-model="form.tag"
                    type="text"
                    maxlength="8"
                    class="tag-input"
                    @input="form.tag = form.tag.toUpperCase()"
                >
              </div>
            </div>
          </div>
        </section>

        <section class="settings-section">
          <div class="section-label">
            <strong>Информация</strong>
            <span>Кратко расскажи игрокам о клане</span>
          </div>

          <div class="field">
            <label>Описание</label>
            <textarea
                v-model="form.description"
                rows="4"
                maxlength="1000"
                placeholder="Расскажи о клане..."
            />
          </div>
        </section>

        <section class="settings-section">
          <div class="section-label">
            <strong>Цвет клана</strong>
            <span>Используется в карточках и элементах оформления</span>
          </div>

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

            <input
                v-model="form.banner_color"
                type="color"
                class="color-custom"
            >
          </div>
        </section>

        <section class="settings-section">
          <div class="section-label">
            <strong>Ссылки</strong>
            <span>Соцсети и сайт клана</span>
          </div>

          <div class="socials">
            <div class="social-row">
              <span class="social-icon discord">D</span>
              <input v-model="form.socials.discord" placeholder="Ссылка на Discord">
            </div>

            <div class="social-row">
              <span class="social-icon telegram">T</span>
              <input v-model="form.socials.telegram" placeholder="Ссылка на Telegram">
            </div>

            <div class="social-row">
              <span class="social-icon youtube">Y</span>
              <input v-model="form.socials.youtube" placeholder="Ссылка на YouTube">
            </div>

            <div class="social-row">
              <span class="social-icon vk">VK</span>
              <input v-model="form.socials.vk" placeholder="Ссылка на VK">
            </div>

            <div class="social-row">
              <span class="social-icon web">↗</span>
              <input v-model="form.socials.website" placeholder="Сайт клана">
            </div>
          </div>
        </section>

        <section class="settings-section">
          <div class="section-label">
            <strong>Доступ</strong>
            <span>Управление вступлением и отображением</span>
          </div>

          <label class="option-card">
            <input v-model="form.is_open" type="checkbox">

            <span class="fake-check">✓</span>

            <span class="option-content">
              <strong>Открытый набор</strong>
              <small>Любой игрок сможет отправить заявку в клан</small>
            </span>
          </label>

          <!-- Плата за вступление: деньги уходят лидеру при принятии -->
          <div class="fee-field">
            <label class="fee-field__label" for="entry-fee">
              Плата за вступление
            </label>

            <div class="fee-field__row">
              <input
                  id="entry-fee"
                  v-model.number="form.entry_fee"
                  class="fee-field__input"
                  type="number"
                  min="0"
                  max="100000"
                  step="50"
              >
              <span class="fee-field__unit">ApexCoin</span>
            </div>

            <small class="fee-field__hint">
              0 — вступление бесплатное. Деньги списываются у игрока
              в момент принятия заявки и уходят вам.
            </small>
          </div>

          <!--
            Подсветка клана — платная услуга из магазина, а не настройка.
            Раньше её включали этой галочкой бесплатно и навсегда.
          -->
          <RouterLink
              class="option-card highlight option-card--link"
              to="/shop?type=clan_highlight"
          >
            <span class="fake-check">★</span>

            <span class="option-content">
              <strong>Выделение клана</strong>
              <small v-if="clan.is_highlighted && clan.highlight_until">
                Активно до {{ formatUntil(clan.highlight_until) }}
                <template v-if="styleLabel"> · {{ styleLabel }}</template>
              </small>
              <small v-else>
                Покупается в магазине: 30 дней. Купить может только лидер.
              </small>
              <small class="option-content__hint">
                Цвет и эффект подсветки докупаются отдельно.
              </small>
            </span>

            <span class="option-link">В магазин</span>
          </RouterLink>
        </section>

      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">
          Отмена
        </button>

        <button
            class="btn-save"
            :disabled="loading"
            @click="submit"
        >
          {{ loading ? 'Сохраняем...' : 'Сохранить изменения' }}
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
  background: rgba(3, 3, 8, .78);
  backdrop-filter: blur(12px);
}

.modal {
  width: 100%;
  max-width: 680px;
  max-height: min(900px, 92vh);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background:
      linear-gradient(135deg, rgba(124,58,237,.045), transparent 35%),
      var(--bg-card);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 20px;
  box-shadow: 0 30px 100px rgba(0,0,0,.5);
}

.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 24px;
  border-bottom: 1px solid var(--border);
}

.eyebrow {
  margin-bottom: 4px;
  color: var(--accent-light);
  font-size: 9px;
  font-weight: 850;
  letter-spacing: 1.4px;
}

.modal-head h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 850;
}

.close {
  width: 36px;
  height: 36px;
  color: var(--text-dim);
  background: rgba(255,255,255,.035);
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 23px;
  cursor: pointer;
  transition: .18s;
}

.close:hover {
  color: var(--text);
  background: rgba(255,255,255,.07);
}

.error-banner {
  display: flex;
  gap: 9px;
  align-items: center;
  margin: 14px 24px 0;
  padding: 11px 13px;
  color: #fca5a5;
  background: rgba(239,68,68,.07);
  border: 1px solid rgba(239,68,68,.18);
  border-radius: 10px;
  font-size: 12px;
}

.form-body {
  overflow-y: auto;
  padding: 22px 24px;
}

.settings-section {
  padding: 20px 0;
  border-bottom: 1px solid rgba(255,255,255,.055);
}

.settings-section:first-child {
  padding-top: 0;
}

.settings-section:last-child {
  border-bottom: 0;
}

.section-label {
  margin-bottom: 13px;
}

.section-label strong {
  display: block;
  font-size: 13px;
  font-weight: 800;
}

.section-label span {
  display: block;
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 11px;
}

.cover-drop {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 13px;
}

.cover-preview {
  position: relative;
  height: 150px;
  background-position: center;
  background-size: cover;
}

.cover-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,.55), transparent);
}

.cover-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  opacity: 0;
  background: rgba(0,0,0,.4);
  transition: .2s;
}

.cover-preview:hover .cover-overlay {
  opacity: 1;
}

.cover-btn {
  padding: 8px 13px;
  border-radius: 8px;
  border: 0;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}

.cover-btn input,
.cover-empty input,
.mini-btn input {
  display: none;
}

.cover-btn.primary {
  color: #fff;
  background: var(--accent);
}

.cover-btn.danger {
  color: #fff;
  background: rgba(220,38,38,.9);
}

.cover-empty {
  min-height: 150px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: var(--text-dim);
  cursor: pointer;
  background: rgba(255,255,255,.012);
}

.upload-icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 3px;
  color: var(--accent-light);
  background: rgba(124,58,237,.1);
  border-radius: 11px;
  font-size: 22px;
  font-weight: 300;
}

.cover-empty strong {
  color: var(--text);
  font-size: 12px;
}

.cover-empty span {
  color: var(--text-muted);
  font-size: 10px;
}

.identity-row {
  display: flex;
  gap: 20px;
  margin-top: 16px;
}

.avatar-block {
  width: 100px;
  flex: 0 0 100px;
}

.avatar-label,
.field label {
  display: block;
  margin-bottom: 7px;
  color: var(--text-muted);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .7px;
}

.avatar-preview {
  width: 84px;
  height: 84px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background-position: center;
  background-size: cover;
  border-radius: 14px;
  color: #fff;
  font-size: 30px;
  font-weight: 900;
  box-shadow: 0 8px 25px rgba(0,0,0,.25);
}

.avatar-actions {
  display: flex;
  gap: 5px;
  margin-top: 7px;
}

.mini-btn {
  padding: 5px 8px;
  color: #fff;
  background: var(--accent);
  border-radius: 6px;
  font-size: 9px;
  font-weight: 800;
  cursor: pointer;
  border: 0;
}

.mini-btn.danger {
  color: #fca5a5;
  background: rgba(239,68,68,.08);
}

.identity-fields {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 130px;
  gap: 12px;
  align-content: start;
}

.field input[type=text],
.field textarea,
.social-row input {
  width: 100%;
  box-sizing: border-box;
  padding: 11px 12px;
  color: var(--text);
  background: #0c0c13;
  border: 1px solid var(--border);
  border-radius: 9px;
  outline: none;
  font: inherit;
  font-size: 12px;
  transition: .18s;
}

.field textarea {
  resize: vertical;
  line-height: 1.5;
}

.field input:focus,
.field textarea:focus,
.social-row input:focus {
  border-color: rgba(139,92,246,.65);
  box-shadow: 0 0 0 3px rgba(124,58,237,.1);
}

.tag-input {
  text-transform: uppercase;
  font-weight: 850 !important;
  letter-spacing: 1px;
}

.color-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.color-swatch,
.color-custom {
  width: 32px;
  height: 32px;
  padding: 0;
  border-radius: 9px;
  cursor: pointer;
}

.color-swatch {
  border: 2px solid transparent;
  transition: .15s;
}

.color-swatch:hover {
  transform: scale(1.08);
}

.color-swatch.active {
  border-color: #fff;
  box-shadow: 0 0 0 3px rgba(255,255,255,.12);
}

.color-custom {
  border: 1px solid var(--border);
  background: transparent;
}

.socials {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.social-row {
  display: flex;
  align-items: center;
  gap: 9px;
}

.social-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 900;
}

.discord { background: #5865f2; }
.telegram { background: #229ed9; }
.youtube { background: #ef4444; }
.vk { background: #0077ff; }
.web { background: #4b5563; }

.option-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 13px;
  margin-top: 8px;
  background: rgba(255,255,255,.018);
  border: 1px solid var(--border);
  border-radius: 11px;
  cursor: pointer;
  transition: .18s;
}

.option-card:hover {
  border-color: var(--border-hover);
  background: rgba(255,255,255,.025);
}

.option-card.highlight {
  border-color: rgba(250,204,21,.16);
  background: rgba(250,204,21,.025);
}

/* Платная опция ведёт в магазин: карточка целиком кликабельна */
.option-card--link {
  cursor: pointer;
  text-decoration: none;
}

.option-card--link:hover {
  border-color: rgba(250,204,21,.36);
  background: rgba(250,204,21,.06);
}

/* Плата за вступление */
.fee-field {
  margin-bottom: 14px;
  padding: 13px 15px;
  background: rgba(255,255,255,.025);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.fee-field__label {
  display: block;
  margin-bottom: 8px;
  color: var(--text);
  font-size: 12.5px;
  font-weight: 700;
}

.fee-field__row {
  display: flex;
  align-items: center;
  gap: 9px;
}

.fee-field__input {
  width: 130px;
  padding: 8px 11px;
  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
}

.fee-field__input:focus {
  border-color: var(--accent);
}

.fee-field__unit {
  color: #facc15;
  font-size: 12px;
  font-weight: 700;
}

.fee-field__hint {
  display: block;
  margin-top: 7px;
  color: var(--text-dim);
  font-size: 11.5px;
  line-height: 1.45;
}

.option-content__hint {
  margin-top: 3px;
  opacity: .75;
}

.option-link {
  margin-left: auto;
  padding: 6px 11px;
  color: #facc15;
  background: rgba(250,204,21,.1);
  border: 1px solid rgba(250,204,21,.28);
  border-radius: 8px;
  font-size: 11.5px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}

.option-link:hover {
  background: rgba(250,204,21,.18);
}

.option-card input {
  position: absolute;
  opacity: 0;
}

.fake-check {
  width: 25px;
  height: 25px;
  flex: 0 0 25px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: transparent;
  background: #0b0b11;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 12px;
  font-weight: 900;
}

.option-card input:checked + .fake-check {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
}

.highlight input:checked + .fake-check {
  color: #facc15;
  background: rgba(250,204,21,.1);
  border-color: rgba(250,204,21,.3);
}

.option-content strong {
  display: block;
  font-size: 12px;
}

.option-content small {
  display: block;
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 10px;
}

.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 15px 24px;
  border-top: 1px solid var(--border);
  background: rgba(0,0,0,.08);
}

.btn-cancel,
.btn-save {
  min-height: 40px;
  padding: 0 16px;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
}

.btn-cancel {
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
}

.btn-save {
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border: 0;
  box-shadow: 0 7px 20px rgba(124,58,237,.2);
}

.btn-save:disabled {
  opacity: .55;
  cursor: not-allowed;
}

@media (max-width: 600px) {
  .modal-bg {
    padding: 0;
    align-items: flex-end;
  }

  .modal {
    max-height: 94vh;
    border-radius: 18px 18px 0 0;
  }

  .identity-row {
    flex-direction: column;
  }

  .identity-fields {
    grid-template-columns: 1fr 110px;
  }

  .modal-foot {
    flex-direction: column-reverse;
  }

  .btn-cancel,
  .btn-save {
    width: 100%;
  }
}
</style>