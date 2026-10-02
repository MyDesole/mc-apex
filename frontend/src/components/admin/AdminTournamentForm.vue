<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({
  tournament: { type: Object, default: null },  // null = создание
})

const emit = defineEmits(['close', 'updated'])

const isEdit = !!props.tournament

const form = ref({
  name: props.tournament?.name ?? '',
  description: props.tournament?.description ?? '',
  type: props.tournament?.type ?? 'solo',
  format: props.tournament?.format ?? 'single_elim',
  status: props.tournament?.status ?? 'draft',
  prize_pool: props.tournament?.prize_pool ?? 0,
  prize_currency: props.tournament?.prize_currency ?? 'RUB',
  prize_description: props.tournament?.prize_description ?? '',
  min_tier: props.tournament?.min_tier ?? '',
  max_tier: props.tournament?.max_tier ?? '',
  max_participants: props.tournament?.max_participants ?? 16,
  registration_starts_at: props.tournament?.registration_starts_at
      ? props.tournament.registration_starts_at.slice(0, 16)
      : '',
  registration_ends_at: props.tournament?.registration_ends_at
      ? props.tournament.registration_ends_at.slice(0, 16)
      : '',
  starts_at: props.tournament?.starts_at
      ? props.tournament.starts_at.slice(0, 16)
      : '',
  ends_at: props.tournament?.ends_at
      ? props.tournament.ends_at.slice(0, 16)
      : '',
})

const bannerFile = ref(null)
const bannerPreview = ref(props.tournament?.banner_url ?? null)
const loading = ref(false)
const error = ref('')

function onBannerChange(e) {
  const file = e.target.files[0]
  if (!file) return
  bannerFile.value = file
  bannerPreview.value = URL.createObjectURL(file)
}

async function submit() {
  loading.value = true
  error.value = ''

  try {
    if (isEdit) {
      const payload = { ...form.value }
      // убираем пустые строки, чтобы Laravel не падал на nullable date
      Object.keys(payload).forEach(k => {
        if (payload[k] === '') payload[k] = null
      })
      await adminApi.updateTournament(props.tournament.id, payload)
    } else {
      const fd = new FormData()
      for (const [k, v] of Object.entries(form.value)) {
        if (v === undefined || v === null || v === '') continue
        fd.append(k, v)
      }
      if (bannerFile.value) fd.append('banner', bannerFile.value)

      await adminApi.createTournament(fd)
    }

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

const tiers = ['S', 'A', 'B', 'C', 'D', 'E']
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>{{ isEdit ? 'Редактировать турнир' : 'Создать турнир' }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <div class="field">
          <label>Название *</label>
          <input v-model="form.name" type="text" maxlength="120" placeholder="APEX Winter Cup" />
        </div>

        <div class="field">
          <label>Описание</label>
          <textarea v-model="form.description" rows="4" placeholder="Правила, формат, призы..." />
        </div>

        <div class="field">
          <label>Баннер</label>
          <div class="banner-upload">
            <div
                v-if="bannerPreview"
                class="banner-preview"
                :style="{ backgroundImage: `url(${bannerPreview})` }"
            >
              <label class="btn-change">
                <input type="file" accept="image/*" @change="onBannerChange" />
                Заменить
              </label>
            </div>
            <label v-else class="banner-empty">
              <input type="file" accept="image/*" @change="onBannerChange" />
              <span>Загрузить баннер</span>
              <small>JPG, PNG, WebP · до 5 МБ</small>
            </label>
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Тип *</label>
            <select v-model="form.type">
              <option value="solo">1 vs 1 (люди)</option>
              <option value="clan">Клан vs Клан</option>
            </select>
          </div>

          <div class="field">
            <label>Формат *</label>
            <select v-model="form.format">
              <option value="single_elim">Single Elimination</option>
              <option value="double_elim">Double Elimination</option>
              <option value="round_robin">Round Robin</option>
            </select>
          </div>

          <div v-if="isEdit" class="field">
            <label>Статус</label>
            <select v-model="form.status">
              <option value="draft">Черновик</option>
              <option value="registration">Регистрация</option>
              <option value="ongoing">Идёт</option>
              <option value="completed">Завершён</option>
              <option value="cancelled">Отменён</option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Призовой фонд</label>
            <input v-model.number="form.prize_pool" type="number" min="0" step="0.01" />
          </div>

          <div class="field">
            <label>Валюта</label>
            <input v-model="form.prize_currency" type="text" maxlength="8" placeholder="RUB" />
          </div>

          <div class="field">
            <label>Описание приза</label>
            <input v-model="form.prize_description" type="text" maxlength="255" placeholder="Например: 50/30/20" />
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Мин. тир</label>
            <select v-model="form.min_tier">
              <option value="">Без ограничения</option>
              <option v-for="t in tiers" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>

          <div class="field">
            <label>Макс. тир</label>
            <select v-model="form.max_tier">
              <option value="">Без ограничения</option>
              <option v-for="t in tiers" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>

          <div class="field">
            <label>Макс. участников *</label>
            <input v-model.number="form.max_participants" type="number" min="2" max="128" />
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Регистрация с</label>
            <input v-model="form.registration_starts_at" type="datetime-local" />
          </div>

          <div class="field">
            <label>Регистрация до</label>
            <input v-model="form.registration_ends_at" type="datetime-local" />
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Начало</label>
            <input v-model="form.starts_at" type="datetime-local" />
          </div>

          <div class="field">
            <label>Конец</label>
            <input v-model="form.ends_at" type="datetime-local" />
          </div>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="loading || !form.name" @click="submit">
          {{ loading ? '...' : (isEdit ? 'Сохранить' : 'Создать') }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminTournamentForm.css";
</style>
