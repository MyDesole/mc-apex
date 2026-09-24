<script setup>
import { ref } from 'vue'
import { adminApi } from '@/services/admin.js'

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
.modal-bg {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
}

.modal {
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
}

.modal-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.close {
  width: 30px;
  height: 30px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 8px;
  cursor: pointer;
}

.close:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--text);
}

.body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.error {
  padding: 10px 12px;
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  font-size: 13px;
}

.row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--accent);
}

.banner-upload {
  border-radius: 10px;
  overflow: hidden;
}

.banner-preview {
  position: relative;
  height: 140px;
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-change {
  padding: 9px 16px;
  color: #fff;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  backdrop-filter: blur(8px);
}

.btn-change input { display: none; }

.banner-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 24px;
  background: #0d0d14;
  border: 2px dashed var(--border);
  border-radius: 10px;
  cursor: pointer;
  color: var(--text-dim);
}

.banner-empty:hover {
  border-color: var(--accent);
  color: var(--accent-light);
}

.banner-empty input { display: none; }

.banner-empty span {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.banner-empty small {
  font-size: 11px;
  color: var(--text-muted);
}

.modal-foot {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
}

.btn-cancel,
.btn-save {
  min-height: 40px;
  padding: 0 22px;
  border-radius: 9px;
  font-size: 13px;
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
  background: var(--accent);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>