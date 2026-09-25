<script setup>
import { computed, ref } from 'vue'
import { playersApi } from '@/services/players.js'
import { useAuthStore } from '@/stores/auth'
import UserName from '@/components/UserName.vue'

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

function openForm() {
  body.value = props.myRecommendation?.body ?? ''
  rating.value = props.myRecommendation?.rating ?? null
  error.value = ''
  showForm.value = true
}

function closeForm() {
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
  if (!confirm('Удалить свой отзыв?')) return
  await playersApi.deleteRecommendation(props.targetUser.id)
  emit('updated')
}

async function hide(rec) {
  if (!confirm('Скрыть этот отзыв у себя в профиле?')) return
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
          <RouterLink :to="`/players/${rec.author.id}`" class="rec__author">
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
.recommendations {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.recommendations__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.recommendations__title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: #c7d5e0;
  text-transform: uppercase;
  letter-spacing: 1.2px;
}

.recommendations__count {
  font-size: 11px;
  color: #4a5568;
  font-weight: 700;
}

.recommendations__actions {
  display: flex;
  gap: 6px;
}

.btn-write {
  flex: 1;
  padding: 8px 12px;
  color: #fff;
  background: #7c3aed;
  border: 0;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-write:hover { background: #8b5cf6; }

.btn-write--edit {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text-dim);
}

.btn-write--edit:hover { color: var(--text); border-color: var(--border-hover); background: rgba(255,255,255,0.03); }

.my-rec-actions {
  display: flex;
  gap: 6px;
  flex: 1;
}

.btn-remove {
  padding: 8px 12px;
  color: #f87171;
  background: transparent;
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-remove:hover { background: rgba(239, 68, 68, 0.08); }

.rec-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.rec-form textarea {
  width: 100%;
  padding: 10px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 8px;
  font: inherit;
  font-size: 12.5px;
  resize: vertical;
  outline: none;
}

.rec-form textarea:focus { border-color: var(--accent); }

.rec-form__meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.rec-form__counter {
  font-size: 10px;
  color: var(--text-muted);
  font-weight: 600;
}

.rec-form__stars {
  display: flex;
  gap: 2px;
}

.star {
  background: transparent;
  border: 0;
  color: rgba(255,255,255,0.15);
  font-size: 16px;
  cursor: pointer;
  transition: color 0.15s, transform 0.15s;
  padding: 0;
}

.star:hover { transform: scale(1.15); }
.star--active { color: #facc15; }

.rec-form__error {
  padding: 6px 8px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border-radius: 6px;
  font-size: 11px;
}

.rec-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.btn-cancel, .btn-save {
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 12px;
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
  background: #7c3aed;
}

.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.rec-empty {
  padding: 20px;
  text-align: center;
  color: var(--text-muted);
  font-size: 12px;
}

.rec-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  list-style: none;
  padding: 0;
  margin: 0;
}

.rec {
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
}

.rec--mine {
  border-color: rgba(139, 92, 246, 0.4);
  background: rgba(124, 58, 237, 0.08);
}

.rec__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 6px;
}

.rec__author {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex: 1;
  min-width: 0;
}

.rec__avatar {
  position: relative;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 7px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff;
  font-size: 12px;
  font-weight: 900;
  overflow: hidden;
}

.rec__avatar img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rec__author-info {
  min-width: 0;
}

.rec__author-name {
  font-size: 12px;
  font-weight: 700;
  color: #e5e7eb;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rec__stars {
  display: flex;
  gap: 1px;
  font-size: 10px;
  color: rgba(255, 255, 255, 0.15);
  line-height: 1;
}

.star-filled { color: #facc15; }

.rec__hide {
  padding: 2px 6px;
  color: var(--text-muted);
  background: transparent;
  border: 0;
  font-size: 10px;
  cursor: pointer;
  border-radius: 4px;
}

.rec__hide:hover { color: #f87171; background: rgba(239, 68, 68, 0.08); }

.rec__body {
  margin: 0;
  color: #d1d1db;
  font-size: 12.5px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}

.rec__date {
  margin-top: 6px;
  font-size: 10px;
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
</style>