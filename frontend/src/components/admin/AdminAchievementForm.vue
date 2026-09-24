<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const props = defineProps({
  achievement: { type: Object, default: null },
})

const emit = defineEmits(['close', 'updated'])

const isEdit = !!props.achievement

const form = ref({
  name: props.achievement?.name ?? '',
  description: props.achievement?.description ?? '',
  icon: props.achievement?.icon ?? '🏆',
  color: props.achievement?.color ?? '#7c3aed',
  rarity: props.achievement?.rarity ?? 'common',
  points: props.achievement?.points ?? 10,
  code: props.achievement?.code ?? '',
})

const loading = ref(false)
const error = ref('')

// превью — как будет выглядеть
const previewColor = computed(() => form.value.color)

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { ...form.value }

    // code только при создании и если не пустой
    if (isEdit || !payload.code) {
      delete payload.code
    }

    if (isEdit) {
      await adminApi.updateAchievement(props.achievement.id, payload)
    } else {
      await adminApi.createAchievement(payload)
    }

    emit('updated')
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

const rarityLabels = {
  common: 'Обычная',
  rare: 'Редкая',
  epic: 'Эпическая',
  legendary: 'Легендарная',
}

const iconPresets = [
  '🏆', '👑', '⚔️', '🛡️', '🔥', '⚡', '💎', '🌟',
  '🎯', '📊', '🤝', '🌐', '✨', '🎮', '🎨', '🚀',
  '💀', '🗡️', '🩸', '🥇', '🥈', '🥉', '🎖️', '🏅',
]
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>{{ isEdit ? 'Редактировать ачивку' : 'Создать ачивку' }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Превью -->
        <div class="preview" :style="{ '--color': previewColor }">
          <div class="preview__icon">{{ form.icon }}</div>
          <div class="preview__info">
            <div class="preview__name">{{ form.name || 'Название ачивки' }}</div>
            <div class="preview__desc">
              {{ form.description || 'Описание появится здесь' }}
            </div>
            <div class="preview__meta">
              +{{ form.points }} · {{ rarityLabels[form.rarity] }}
            </div>
          </div>
        </div>

        <div class="field">
          <label>Название *</label>
          <input
              v-model="form.name"
              type="text"
              maxlength="80"
              placeholder="Командный игрок"
          />
        </div>

        <div class="field">
          <label>Описание *</label>
          <textarea
              v-model="form.description"
              rows="2"
              maxlength="255"
              placeholder="Вступил в первый клан"
          />
        </div>

        <!-- Иконка -->
        <div class="field">
          <label>Иконка *</label>
          <div class="icon-picker">
            <button
                v-for="icon in iconPresets"
                :key="icon"
                type="button"
                class="icon-btn"
                :class="{ active: form.icon === icon }"
                @click="form.icon = icon"
            >
              {{ icon }}
            </button>
            <input
                v-model="form.icon"
                type="text"
                maxlength="4"
                class="icon-input"
                placeholder="Или свой"
            />
          </div>
        </div>

        <!-- Цвет -->
        <div class="field">
          <label>Цвет *</label>
          <div class="color-row">
            <button
                v-for="c in colorPresets"
                :key="c"
                type="button"
                class="color-swatch"
                :class="{ active: form.color === c }"
                :style="{ background: c }"
                @click="form.color = c"
            />
            <input
                v-model="form.color"
                type="color"
                class="color-custom"
            />
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>Редкость *</label>
            <select v-model="form.rarity">
              <option
                  v-for="(label, key) in rarityLabels"
                  :key="key"
                  :value="key"
              >
                {{ label }}
              </option>
            </select>
          </div>

          <div class="field">
            <label>Очки *</label>
            <input
                v-model.number="form.points"
                type="number"
                min="0"
                max="10000"
            />
          </div>
        </div>

        <div v-if="!isEdit" class="field">
          <label>Код (опционально)</label>
          <input
              v-model="form.code"
              type="text"
              maxlength="64"
              placeholder="Оставь пустым — сгенерируется автоматически"
          />
          <small class="hint">
            Латиница, цифры, подчёркивания. Используется в коде для выдачи.
          </small>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button
            class="btn-save"
            :disabled="loading || !form.name || !form.description"
            @click="submit"
        >
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
  max-width: 560px;
  max-height: 92vh;
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

/* Превью */
.preview {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  background: #0d0d14;
  border: 1px solid var(--color);
  border-radius: 12px;
  position: relative;
  overflow: hidden;
}

.preview::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle at 0% 50%, color-mix(in srgb, var(--color) 15%, transparent), transparent 60%);
  pointer-events: none;
}

.preview__icon {
  position: relative;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--color);
  border-radius: 12px;
  font-size: 24px;
  flex-shrink: 0;
}

.preview__info {
  position: relative;
  min-width: 0;
}

.preview__name {
  font-size: 15px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 2px;
}

.preview__desc {
  font-size: 12px;
  color: var(--text-dim);
  margin-bottom: 4px;
}

.preview__meta {
  font-size: 11px;
  color: var(--color);
  font-weight: 800;
}

/* Поля */
.row {
  display: grid;
  grid-template-columns: 1fr 1fr;
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

.hint {
  display: block;
  margin-top: 5px;
  color: var(--text-muted);
  font-size: 11px;
}

/* Иконки */
.icon-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.icon-btn {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 18px;
  cursor: pointer;
  transition: all 0.15s;
}

.icon-btn:hover {
  border-color: var(--border-hover);
  transform: scale(1.08);
}

.icon-btn.active {
  border-color: var(--accent);
  background: rgba(124, 58, 237, 0.1);
  box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.2);
}

.icon-input {
  width: 80px;
  padding: 0 10px;
  text-align: center;
  font-size: 14px;
}

/* Цвета */
.color-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.color-swatch {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.15s, border-color 0.15s;
}

.color-swatch:hover {
  transform: scale(1.08);
}

.color-swatch.active {
  border-color: #fff;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
}

.color-custom {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: transparent;
  cursor: pointer;
}

/* Футер */
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

@media (max-width: 500px) {
  .row {
    grid-template-columns: 1fr;
  }
}
</style>