<script setup>
/**
 * Загрузка вложений к теме или ответу.
 * Файлы сразу уходят на сервер, наружу отдаём их id через v-model.
 */
import { computed, ref } from 'vue'
import { forumApi } from '@/services/forum.js'
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 5 },
})

const emit = defineEmits(['update:modelValue'])

const uploading = ref(false)
const error = ref('')

const files = computed(() => props.modelValue)

function sizeLabel(bytes) {
  if (!bytes) return ''
  const units = ['Б', 'КБ', 'МБ']
  let value = Number(bytes)
  let i = 0
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024
    i++
  }
  return `${value.toFixed(i ? 1 : 0)} ${units[i]}`
}

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
      if (file.size > 10 * 1024 * 1024) {
        error.value = `«${file.name}» больше 10 МБ — пропущен.`
        continue
      }

      const result = await forumApi.upload(file)

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
    error.value = e.message || 'Не удалось загрузить файл.'
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
  <div class="attach">
    <div class="attach__head">
      <label class="attach__pick" :class="{ 'attach__pick--busy': uploading }">
        <input
            type="file"
            multiple
            :disabled="uploading || files.length >= max"
            @change="onPick"
        >
        <AppIcon icon="doc" :size="15" />
        <span>{{ uploading ? 'Загружаем…' : 'Приложить файл' }}</span>
      </label>

      <span class="attach__hint">
        до 10 МБ · картинки, pdf, txt, архивы · {{ files.length }}/{{ max }}
      </span>
    </div>

    <p v-if="error" class="attach__error">{{ error }}</p>

    <div v-if="files.length" class="attach__list">
      <div v-for="(file, index) in files" :key="file.id" class="attach__item">
        <img
            v-if="file.is_image"
            :src="file.url"
            :alt="file.name"
            class="attach__thumb"
        >
        <span v-else class="attach__file-icon">
          <AppIcon icon="doc" :size="16" />
        </span>

        <div class="attach__meta">
          <a :href="file.url" target="_blank" rel="noopener" class="attach__name">
            {{ file.name }}
          </a>
          <span class="attach__size">{{ file.size }}</span>
        </div>

        <button class="attach__remove" type="button" title="Убрать" @click="remove(index)">
          <AppIcon icon="close" :size="14" />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.attach {
  margin-top: 12px;
}

.attach__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.attach__pick {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  color: var(--text);
  background: var(--bg-card);
  border: 1px dashed var(--border-hover, #343443);
  border-radius: 9px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.attach__pick:hover {
  background: var(--bg-card-hover, #1a1a26);
  border-color: var(--accent);
}

.attach__pick--busy {
  opacity: 0.6;
  cursor: progress;
}

.attach__pick input {
  display: none;
}

.attach__hint {
  color: var(--text-muted, #5e5e70);
  font-size: 12px;
}

.attach__error {
  margin: 8px 0 0;
  color: #fca5a5;
  font-size: 12px;
}

.attach__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 10px;
}

.attach__item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 9px;
}

.attach__thumb {
  width: 38px;
  height: 38px;
  object-fit: cover;
  border-radius: 6px;
  flex-shrink: 0;
}

.attach__file-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  color: var(--text-dim);
  background: var(--bg);
  border-radius: 6px;
  flex-shrink: 0;
}

.attach__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.attach__name {
  overflow: hidden;
  color: var(--text);
  font-size: 13px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attach__name:hover {
  color: var(--accent-light);
}

.attach__size {
  color: var(--text-muted, #5e5e70);
  font-size: 11px;
}

.attach__remove {
  display: inline-flex;
  padding: 5px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-radius: 6px;
  cursor: pointer;
}

.attach__remove:hover {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
}
</style>
