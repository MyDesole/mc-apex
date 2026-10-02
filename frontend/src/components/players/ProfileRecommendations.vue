<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
import { playersApi } from '@/services/players/players.js'
import { useAuthStore } from '@/stores/core/auth.js'
import UserName from '@/components/players/UserName.vue'
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
@import "@/components/players/ProfileRecommendations.css";
</style>
