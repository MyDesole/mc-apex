<script setup>
/**
 * Панель прикреплённых к сообщению файлов + кнопка выбора.
 * Картинки показываются превью, остальное — плашкой с именем.
 */
import { computed, ref } from 'vue'
import { chatApi } from '@/services/chat.js'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 5 },
})

const emit = defineEmits(['update:modelValue'])

const uploading = ref(false)
const error = ref('')
const previewOpen = ref(null)

const files = computed(() => props.modelValue)

async function onPick(event) {
  const picked = Array.from(event.target.files || [])
  event.target.value = ''

  if (!picked.length) return

  error.value = ''

  const room = props.max - files.value.length

  if (room <= 0) {
    error.value = `Можно приложить не больше ${props.max} файлов.`
    return
  }

  uploading.value = true

  try {
    const next = [...files.value]

    for (const file of picked.slice(0, room)) {
      if (file.size > 12 * 1024 * 1024) {
        error.value = `«${file.name}» больше 12 МБ — пропущен.`
        continue
      }

      const result = await chatApi.upload(file)

      next.push({
        id: result.attachment.id,
        name: result.attachment.name,
        url: result.attachment.url,
        size: result.attachment.size,
        is_image: result.attachment.is_image,
      })
    }

    emit('update:modelValue', next)
  } catch (e) {
    error.value = e.status === 422
        ? 'Такой файл приложить нельзя (разрешены картинки, видео, pdf, txt, архивы).'
        : (e.message || 'Не удалось загрузить файл.')
  } finally {
    uploading.value = false
  }
}

function remove(index) {
  const next = [...files.value]
  next.splice(index, 1)
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="attach-bar">
    <p v-if="error" class="attach-bar__error">{{ error }}</p>

    <div v-if="files.length" class="attach-bar__list">
      <div v-for="(file, index) in files" :key="file.id" class="attach-chip">
        <button
            v-if="file.is_image"
            class="attach-chip__thumb"
            type="button"
            @click="previewOpen = file"
        >
          <img :src="file.url" :alt="file.name">
        </button>
        <span v-else class="attach-chip__icon">📎</span>

        <span class="attach-chip__name" :title="file.name">{{ file.name }}</span>
        <span class="attach-chip__size">{{ file.size }}</span>

        <button class="attach-chip__remove" type="button" title="Убрать" @click="remove(index)">
          ×
        </button>
      </div>
    </div>

    <label class="attach-btn" :class="{ 'attach-btn--busy': uploading }">
      <input
          type="file"
          multiple
          :disabled="uploading || files.length >= max"
          @change="onPick"
      >
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
      </svg>
      <span>{{ uploading ? 'Загрузка…' : 'Файл' }}</span>
    </label>

    <!-- Просмотр картинки -->
    <div v-if="previewOpen" class="attach-preview" @click.self="previewOpen = null">
      <img :src="previewOpen.url" :alt="previewOpen.name">
      <button class="attach-preview__close" type="button" @click="previewOpen = null">×</button>
    </div>
  </div>
</template>

<style scoped>
.attach-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.attach-bar__error {
  width: 100%;
  margin: 0;
  color: #fca5a5;
  font-size: 12px;
}

.attach-bar__list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.attach-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  max-width: 240px;
  padding: 4px 6px 4px 4px;
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #22222e);
  border-radius: 9px;
}

.attach-chip__thumb {
  display: inline-flex;
  padding: 0;
  overflow: hidden;
  background: transparent;
  border: 0;
  border-radius: 6px;
  cursor: zoom-in;
  flex-shrink: 0;
}

.attach-chip__thumb img {
  width: 32px;
  height: 32px;
  object-fit: cover;
}

.attach-chip__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: var(--bg, #0a0a0f);
  border-radius: 6px;
  font-size: 14px;
  flex-shrink: 0;
}

.attach-chip__name {
  overflow: hidden;
  color: var(--text, #e2e2e8);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attach-chip__size {
  color: var(--text-muted, #5e5e70);
  font-size: 11px;
  flex-shrink: 0;
}

.attach-chip__remove {
  padding: 0 4px;
  color: var(--text-dim, #8888a0);
  background: transparent;
  border: 0;
  font-size: 15px;
  line-height: 1;
  cursor: pointer;
}

.attach-chip__remove:hover {
  color: #f87171;
}

.attach-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  color: var(--text-dim, #8888a0);
  background: transparent;
  border: 1px solid var(--border, #22222e);
  border-radius: 9px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.18s ease, border-color 0.18s ease;
}

.attach-btn:hover {
  color: var(--text, #e2e2e8);
  border-color: var(--border-hover, #343443);
}

.attach-btn--busy {
  opacity: 0.6;
  cursor: progress;
}

.attach-btn input {
  display: none;
}

.attach-preview {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 30px;
  background: rgba(0, 0, 0, 0.85);
}

.attach-preview img {
  max-width: 92vw;
  max-height: 88vh;
  border-radius: 10px;
}

.attach-preview__close {
  position: absolute;
  top: 18px;
  right: 22px;
  color: #fff;
  background: transparent;
  border: 0;
  font-size: 30px;
  line-height: 1;
  cursor: pointer;
}
</style>
