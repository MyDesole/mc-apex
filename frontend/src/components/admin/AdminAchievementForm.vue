<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'
import AppIcon from '@/components/core/AppIcon.vue'

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
  coin_reward: props.achievement?.coin_reward ?? null,
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
  'trophy', 'crown', 'sword', 'shield', 'flame', 'bolt', 'gem', 'star',
  'target', 'chart', 'handshake', 'globe', 'sparkles', 'rocket', 'palette', 'medal',
  'leaf', 'snow', 'heart', 'send', 'coin', 'gift', 'doc', 'frame',
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
          <AppIcon class="preview__icon" :icon="form.icon" :size="30" />
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
              <AppIcon :icon="icon" :size="20" />
            </button>
            <input
                v-model="form.icon"
                type="text"
                maxlength="20"
                class="icon-input"
                placeholder="Или имя иконки"
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

          <div class="field">
            <label>Награда в ApexCoin</label>
            <input
                v-model.number="form.coin_reward"
                type="number"
                min="0"
                placeholder="пусто — по формуле"
            />
            <small class="hint">
              Пусто — награда считается как база + очки × множитель (админка → Магазин → Награды).
            </small>
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
@import "@/components/admin/AdminAchievementForm.css";
</style>
