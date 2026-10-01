<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
import { playersApi } from '@/services/players.js'
import { useAuthStore } from '@/stores/auth'
import UserName from '@/components/UserName.vue'
import { userLink } from '@/utils/links.js'

const props = defineProps({
  targetUser: { type: Object, required: true },
  recommendations: { type: Array, default: () => [] },
  myRecommendation: { type: Object, default: null },
  canRecommend: { type: Boolean, default: false },
})

const emit = defineEmits(['updated'])

const auth = useAuthStore()

const showForm = ref(false)
const body = ref(props.myRecommendation?.body ?? '')
const rating = ref(props.myRecommendation?.rating ?? null)
const processing = ref(false)
const error = ref('')

const isMine = computed(() =>
    props.myRecommendation?.author_id === auth.user?.id
)

async function openForm() {
  body.value = props.myRecommendation?.body ?? ''
  rating.value = props.myRecommendation?.rating ?? null
  error.value = ''
  showForm.value = true
}

async function closeForm() {
  showForm.value = false
  error.value = ''
}

async function submit() {
  if (body.value.trim().length < 10) {
    error.value = 'Минимум 10 символов'
    return
  }
  processing.value = true
  error.value = ''

  try {
    await playersApi.saveRecommendation(props.targetUser.id, {
      body: body.value.trim(),
      rating: rating.value,
    })
    showForm.value = false
    emit('updated')
  } catch (e) {
    error.value = e.errors?.body?.[0] || e.message || 'Не удалось сохранить'
  } finally {
    processing.value = false
  }
}

async function remove() {
  if (!await confirmDialog('Удалить свой отзыв?')) return
  await playersApi.deleteRecommendation(props.targetUser.id)
  emit('updated')
}

async function hide(rec) {
  if (!await confirmDialog('Скрыть этот отзыв у себя в профиле?')) return
  await playersApi.hideRecommendation(rec.id)
  emit('updated')
}
</script>

<template>
  <aside class="recommendations">
    <header class="recommendations__head">
      <h3 class="recommendations__title">Отзывы</h3>
      <span class="recommendations__count">{{ recommendations.length }}</span>
    </header>

    <!-- Кнопка "оставить отзыв" -->
    <div v-if="canRecommend" class="recommendations__actions">
      <button
          v-if="!myRecommendation"
          class="btn-write"
          @click="openForm"
      >
        Оставить отзыв
      </button>

      <div v-else class="my-rec-actions">
        <button class="btn-write btn-write--edit" @click="openForm">
          Редактировать мой отзыв
        </button>
        <button class="btn-remove" @click="remove">Удалить</button>
      </div>
    </div>

    <!-- Форма -->
    <div v-if="showForm" class="rec-form">
      <textarea
          v-model="body"
          maxlength="280"
          rows="3"
          placeholder="Что скажешь об этом игроке? Например: очень красивый!"
      />
      <div class="rec-form__meta">
        <span class="rec-form__counter">{{ body.length }} / 280</span>
        <div class="rec-form__stars">
          <button
              v-for="n in 5"
              :key="n"
              type="button"
              class="star"
              :class="{ 'star--active': rating && n <= rating }"
              @click="rating = rating === n ? null : n"
          >★</button>
        </div>
      </div>
      <div v-if="error" class="rec-form__error">{{ error }}</div>
      <div class="rec-form__actions">
        <button class="btn-cancel" @click="closeForm">Отмена</button>
        <button class="btn-save" :disabled="processing" @click="submit">
          {{ processing ? '...' : (myRecommendation ? 'Сохранить' : 'Опубликовать') }}
        </button>
      </div>
    </div>

    <!-- Список -->
    <div v-if="!recommendations.length && !showForm" class="rec-empty">
      Пока нет отзывов
    </div>

    <ul v-else class="rec-list">
      <li
          v-for="rec in recommendations"
          :key="rec.id"
          class="rec"
          :class="{ 'rec--mine': rec.author_id === auth.user?.id }"
      >
        <div class="rec__head">
          <RouterLink :to="userLink(rec.author)" class="rec__author">
            <div class="rec__avatar">
              <img v-if="rec.author.avatar_url" :src="rec.author.avatar_url" :alt="rec.author.username" />
              <template v-else>{{ (rec.author.username || 'И').charAt(0).toUpperCase() }}</template>
            </div>
            <div class="rec__author-info">
              <div class="rec__author-name">
                <UserName :user="rec.author" />
              </div>
              <div v-if="rec.rating" class="rec__stars">
                <span v-for="n in 5" :key="n" :class="{ 'star-filled': n <= rec.rating }">★</span>
              </div>
            </div>
          </RouterLink>

          <button
              v-if="rec.author_id === auth.user?.id"
              class="rec__hide"
              title="Скрыть у себя"
              @click="hide(rec)"
          >Скрыть</button>
        </div>

        <p class="rec__body">{{ rec.body }}</p>

        <div class="rec__date">
          {{ new Date(rec.created_at).toLocaleDateString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' }) }}
        </div>
      </li>
    </ul>
  </aside>
</template>

<style scoped>
/* ============================================================
   RECOMMENDATIONS — APEX / CINEMATIC
   ============================================================ */

.recommendations {
  position: relative;

  display: flex;
  flex-direction: column;
  gap: 13px;

  padding: 16px;

  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, .055),
          rgba(9, 10, 24, .98)
      );

  border: 1px solid rgba(255, 255, 255, .065);
  border-radius: 16px;

  box-shadow:
      0 16px 40px rgba(0, 0, 0, .28),
      inset 0 1px rgba(255, 255, 255, .035);
}

/* ambient purple light */
.recommendations::before {
  content: '';
  position: absolute;
  top: -90px;
  right: -80px;

  width: 190px;
  height: 190px;

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          rgba(139, 92, 246, .1),
          transparent 70%
      );

  pointer-events: none;
}

/* bottom cinematic line */
.recommendations::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139, 92, 246, .22),
          transparent
      );

  pointer-events: none;
}

/* ============================================================
   HEADER
   ============================================================ */

.recommendations__head {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 10px;

  padding-bottom: 11px;

  border-bottom: 1px solid rgba(255, 255, 255, .055);
}

.recommendations__title {
  position: relative;

  margin: 0;
  padding-left: 11px;

  color: #e2e8f0;

  font-size: 12px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: 1.5px;
}

.recommendations__title::before {
  content: '';

  position: absolute;
  left: 0;
  top: 50%;

  width: 4px;
  height: 4px;

  transform: translateY(-50%);

  border-radius: 50%;

  background: #8b5cf6;

  box-shadow:
      0 0 7px #8b5cf6,
      0 0 14px rgba(139, 92, 246, .45);
}

.recommendations__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 20px;
  height: 18px;
  padding: 0 6px;

  color: #64748b;

  background: rgba(255, 255, 255, .025);

  border: 1px solid rgba(255, 255, 255, .05);
  border-radius: 5px;

  font-size: 9px;
  font-weight: 900;
  letter-spacing: .4px;
}

/* ============================================================
   ACTIONS
   ============================================================ */

.recommendations__actions {
  position: relative;
  z-index: 1;

  display: flex;
  gap: 6px;
}

.btn-write {
  position: relative;

  flex: 1;

  min-height: 34px;
  padding: 8px 12px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, .28);
  border-radius: 8px;

  font-size: 11px;
  font-weight: 900;

  letter-spacing: .15px;

  cursor: pointer;

  box-shadow:
      0 5px 16px rgba(109, 40, 217, .2),
      inset 0 1px rgba(255, 255, 255, .1);

  transition:
      transform .18s ease,
      border-color .18s ease,
      box-shadow .18s ease,
      background .18s ease;
}

.btn-write:hover {
  transform: translateY(-1px);

  background:
      linear-gradient(
          135deg,
          #9a6bff,
          #7c3aed
      );

  border-color: rgba(167, 139, 250, .45);

  box-shadow:
      0 8px 22px rgba(109, 40, 217, .28),
      0 0 16px rgba(139, 92, 246, .12),
      inset 0 1px rgba(255, 255, 255, .12);
}

.btn-write:active {
  transform: translateY(0);
}

.btn-write--edit {
  color: #a7b0c0;

  background:
      rgba(255, 255, 255, .025);

  border: 1px solid rgba(255, 255, 255, .07);

  box-shadow: inset 0 1px rgba(255, 255, 255, .025);
}

.btn-write--edit:hover {
  color: #ddd6fe;

  background: rgba(139, 92, 246, .07);

  border-color: rgba(139, 92, 246, .3);

  box-shadow:
      0 0 16px rgba(139, 92, 246, .08);
}

.my-rec-actions {
  display: flex;
  flex: 1;
  gap: 6px;
}

/* ============================================================
   REMOVE
   ============================================================ */

.btn-remove {
  min-height: 34px;
  padding: 8px 11px;

  color: #f87171;

  background: rgba(239, 68, 68, .025);

  border: 1px solid rgba(239, 68, 68, .2);
  border-radius: 8px;

  font-size: 10px;
  font-weight: 900;

  cursor: pointer;

  transition:
      background .18s ease,
      border-color .18s ease,
      box-shadow .18s ease;
}

.btn-remove:hover {
  background: rgba(239, 68, 68, .08);
  border-color: rgba(239, 68, 68, .35);

  box-shadow:
      0 0 14px rgba(239, 68, 68, .08);
}

/* ============================================================
   FORM
   ============================================================ */

.rec-form {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;
  gap: 9px;

  padding: 12px;

  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, .055),
          rgba(255, 255, 255, .018)
      );

  border: 1px solid rgba(139, 92, 246, .16);
  border-radius: 11px;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .025);
}

.rec-form textarea {
  width: 100%;
  min-height: 74px;

  box-sizing: border-box;

  padding: 10px 11px;

  color: #e2e8f0;

  background:
      linear-gradient(
          145deg,
          #0d0e1a,
          #090a14
      );

  border: 1px solid rgba(255, 255, 255, .07);
  border-radius: 8px;

  outline: none;

  font: inherit;
  font-size: 12px;
  line-height: 1.45;

  resize: vertical;

  transition:
      border-color .18s ease,
      box-shadow .18s ease;
}

.rec-form textarea::placeholder {
  color: #475569;
}

.rec-form textarea:focus {
  border-color: rgba(139, 92, 246, .45);

  box-shadow:
      0 0 0 3px rgba(139, 92, 246, .08),
      0 0 18px rgba(139, 92, 246, .06);
}

.rec-form__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.rec-form__counter {
  color: #475569;

  font-size: 9px;
  font-weight: 800;

  letter-spacing: .5px;
  text-transform: uppercase;
}

.rec-form__stars {
  display: flex;
  align-items: center;
  gap: 1px;
}

/* ============================================================
   STARS
   ============================================================ */

.star {
  width: 20px;
  height: 22px;

  padding: 0;

  color: rgba(255, 255, 255, .12);

  background: transparent;
  border: 0;

  font-size: 17px;
  line-height: 1;

  cursor: pointer;

  transition:
      color .15s ease,
      transform .15s ease,
      filter .15s ease;
}

.star:hover {
  color: rgba(250, 204, 21, .65);
  transform: scale(1.12);
}

.star--active {
  color: #facc15;

  filter:
      drop-shadow(0 0 5px rgba(250, 204, 21, .35));
}

/* ============================================================
   FORM ERROR
   ============================================================ */

.rec-form__error {
  padding: 7px 9px;

  color: #fca5a5;

  background:
      rgba(239, 68, 68, .055);

  border: 1px solid rgba(239, 68, 68, .12);
  border-radius: 6px;

  font-size: 10px;
  font-weight: 700;
}

/* ============================================================
   FORM ACTIONS
   ============================================================ */

.rec-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.btn-cancel,
.btn-save {
  min-height: 30px;
  padding: 7px 13px;

  border-radius: 7px;

  font-size: 10px;
  font-weight: 900;

  cursor: pointer;
}

.btn-cancel {
  color: #64748b;

  background: rgba(255, 255, 255, .02);

  border: 1px solid rgba(255, 255, 255, .065);

  transition:
      color .15s ease,
      border-color .15s ease,
      background .15s ease;
}

.btn-cancel:hover {
  color: #cbd5e1;

  background: rgba(255, 255, 255, .04);
  border-color: rgba(255, 255, 255, .1);
}

.btn-save {
  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, .25);

  box-shadow:
      0 4px 12px rgba(109, 40, 217, .18);
}

.btn-save:hover:not(:disabled) {
  box-shadow:
      0 6px 18px rgba(109, 40, 217, .28);
}

.btn-save:disabled {
  opacity: .45;
  cursor: not-allowed;
}

/* ============================================================
   EMPTY
   ============================================================ */

.rec-empty {
  position: relative;
  z-index: 1;

  padding: 20px 12px;

  color: #475569;

  background:
      rgba(255, 255, 255, .015);

  border: 1px dashed rgba(255, 255, 255, .055);
  border-radius: 9px;

  text-align: center;

  font-size: 10px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: .7px;
}

/* ============================================================
   LIST
   ============================================================ */

.rec-list {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;
  gap: 7px;

  margin: 0;
  padding: 0;

  list-style: none;
}

/* ============================================================
   RECOMMENDATION
   ============================================================ */

.rec {
  position: relative;

  padding: 11px 12px;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, .028),
          rgba(255, 255, 255, .012)
      );

  border: 1px solid rgba(255, 255, 255, .055);
  border-radius: 10px;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .018);

  transition:
      border-color .18s ease,
      background .18s ease,
      transform .18s ease;
}

.rec:hover {
  transform: translateY(-1px);

  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, .045),
          rgba(255, 255, 255, .015)
      );

  border-color: rgba(139, 92, 246, .16);
}

.rec--mine {
  background:
      linear-gradient(
          145deg,
          rgba(139, 92, 246, .085),
          rgba(109, 40, 217, .035)
      );

  border-color: rgba(139, 92, 246, .3);

  box-shadow:
      inset 2px 0 rgba(139, 92, 246, .55),
      inset 0 1px rgba(255, 255, 255, .025);
}

/* ============================================================
   REC HEADER
   ============================================================ */

.rec__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;

  margin-bottom: 7px;
}

.rec__author {
  display: flex;
  align-items: center;
  gap: 8px;

  flex: 1;
  min-width: 0;

  color: inherit;

  text-decoration: none;
}

.rec__avatar {
  position: relative;

  width: 30px;
  height: 30px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #c4b5fd;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(139, 92, 246, .2),
          transparent 65%
      ),
      #0c0d19;

  border: 1px solid rgba(139, 92, 246, .22);
  border-radius: 7px;

  font-size: 12px;
  font-weight: 900;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .035);
}

.rec__avatar::after {
  content: '';

  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, .08),
          transparent 45%
      );

  pointer-events: none;
}

.rec__avatar img {
  position: absolute;
  inset: 0;

  width: 100%;
  height: 100%;

  object-fit: cover;

  display: block;
}

.rec__author-info {
  min-width: 0;
}

.rec__author-name {
  color: #dbe3ef;

  font-size: 11px;
  font-weight: 850;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rec__stars {
  display: flex;
  align-items: center;
  gap: 1px;

  margin-top: 2px;

  color: rgba(255, 255, 255, .12);

  font-size: 10px;
  line-height: 1;
}

.star-filled {
  color: #facc15;

  filter:
      drop-shadow(0 0 4px rgba(250, 204, 21, .3));
}

/* ============================================================
   HIDE
   ============================================================ */

.rec__hide {
  padding: 3px 6px;

  color: #475569;

  background: transparent;

  border: 1px solid transparent;
  border-radius: 5px;

  font-size: 9px;
  font-weight: 800;

  cursor: pointer;

  transition:
      color .15s ease,
      background .15s ease,
      border-color .15s ease;
}

.rec__hide:hover {
  color: #f87171;

  background: rgba(239, 68, 68, .06);

  border-color: rgba(239, 68, 68, .12);
}

/* ============================================================
   BODY
   ============================================================ */

.rec__body {
  margin: 0;

  color: #aeb8c8;

  font-size: 11.5px;
  line-height: 1.55;

  white-space: pre-wrap;
  word-break: break-word;
}

/* ============================================================
   DATE
   ============================================================ */

.rec__date {
  margin-top: 8px;

  color: #475569;

  font-size: 8px;
  font-weight: 800;

  text-transform: uppercase;
  letter-spacing: .55px;
}

/* ============================================================
   FOCUS
   ============================================================ */

.btn-write:focus-visible,
.btn-remove:focus-visible,
.btn-cancel:focus-visible,
.btn-save:focus-visible,
.star:focus-visible,
.rec__hide:focus-visible {
  outline: 2px solid #8b5cf6;
  outline-offset: 2px;
}

/* ============================================================
   MOBILE
   ============================================================ */

@media (max-width: 600px) {
  .recommendations {
    padding: 13px;
    border-radius: 15px;
  }

  .recommendations__actions,
  .my-rec-actions {
    gap: 5px;
  }

  .btn-write {
    min-height: 33px;
    padding: 7px 9px;
    font-size: 10px;
  }

  .btn-remove {
    padding: 7px 9px;
    font-size: 9px;
  }

  .rec {
    padding: 10px;
    border-radius: 9px;
  }

  .rec__body {
    font-size: 11px;
  }
}

/* ============================================================
   REDUCED MOTION
   ============================================================ */

@media (prefers-reduced-motion: reduce) {
  .btn-write,
  .btn-remove,
  .btn-cancel,
  .btn-save,
  .star,
  .rec,
  .rec-form textarea,
  .rec__hide {
    transition: none;
  }
}
</style>