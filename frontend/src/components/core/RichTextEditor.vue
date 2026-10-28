<script setup>
/**
 * Визуальный редактор текста (новости): заголовки, жирный, курсив,
 * подчёркнутый, зачёркнутый, списки, цитата, ссылка, кнопка-ссылка,
 * картинка по ссылке, разделитель. Отдаёт HTML; сервер всё равно
 * чистит его по белому списку (HtmlSanitizer).
 */
import { onMounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Текст…' },
})

const emit = defineEmits(['update:modelValue'])

const el = ref(null)
let lastEmitted = null

/** Старый текст без разметки → абзацы, чтобы не слиплось в строку. */
function toHtml(value) {
  if (!value || typeof value !== 'string') return ''
  if (/<[a-z][\s\S]*>/i.test(value)) return value
  const escape = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return value.split(/\n{2,}/).map((p) => `<p>${escape(p).replace(/\n/g, '<br>')}</p>`).join('')
}

function sync() {
  const html = el.value?.innerHTML ?? ''
  // Пустой редактор — пустая строка, а не «<br>»
  const value = el.value?.textContent.trim() || el.value?.querySelector('img, hr') ? html : ''
  lastEmitted = value
  emit('update:modelValue', value)
}

function exec(command, value = null) {
  el.value?.focus()
  document.execCommand(command, false, value)
  sync()
}

function block(tag) {
  exec('formatBlock', tag)
}

function link() {
  const url = window.prompt('Ссылка (https://… или /страница сайта)', 'https://')
  if (!url || url === 'https://') return
  exec('createLink', url)
}

function button() {
  const text = window.prompt('Текст кнопки', 'Подробнее')
  if (!text) return
  const url = window.prompt('Куда ведёт кнопка (https://… или /страница)', '/')
  if (!url) return
  const safe = (s) => s.replace(/"/g, '&quot;').replace(/</g, '&lt;')
  exec('insertHTML', `<p><a href="${safe(url)}" class="news-btn">${safe(text)}</a></p><p><br></p>`)
}

function image() {
  const url = window.prompt('Ссылка на картинку (https://…)', 'https://')
  if (!url || !url.startsWith('https://')) return
  exec('insertHTML', `<p><img src="${url.replace(/"/g, '&quot;')}" alt=""></p>`)
}

// Вставка из Word и сайтов: только текст, чтобы не тащить чужие стили
function onPaste(event) {
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') ?? ''
  document.execCommand('insertText', false, text)
  sync()
}

const TOOLS = [
  ['H2', 'Крупный заголовок', () => block('h2')],
  ['H3', 'Подзаголовок', () => block('h3')],
  ['¶', 'Обычный абзац', () => block('p')],
  ['B', 'Жирный', () => exec('bold')],
  ['I', 'Курсив', () => exec('italic')],
  ['U', 'Подчёркнутый', () => exec('underline')],
  ['S', 'Зачёркнутый', () => exec('strikeThrough')],
  ['•', 'Список', () => exec('insertUnorderedList')],
  ['1.', 'Нумерованный список', () => exec('insertOrderedList')],
  ['❝', 'Цитата', () => block('blockquote')],
  ['Ссылка', 'Ссылка на выделенный текст', link],
  ['Кнопка', 'Кнопка-ссылка', button],
  ['Картинка', 'Картинка по ссылке', image],
  ['—', 'Разделитель', () => exec('insertHorizontalRule')],
  ['✕', 'Убрать оформление', () => exec('removeFormat')],
]

onMounted(() => {
  if (el.value) el.value.innerHTML = toHtml(props.modelValue)
})

// Значение поменяли снаружи (не из этого редактора) — перерисовать
watch(() => props.modelValue, (value) => {
  if (el.value && value !== lastEmitted) el.value.innerHTML = toHtml(value)
})
</script>

<template>
  <div class="rte">
    <div class="rte__bar" role="toolbar">
      <button
          v-for="[label, title, action] in TOOLS"
          :key="title"
          type="button"
          class="rte__btn"
          :title="title"
          @mousedown.prevent
          @click="action"
      >{{ label }}</button>
    </div>
    <div
        ref="el"
        class="rte__area news-prose"
        contenteditable="true"
        :data-placeholder="placeholder"
        @input="sync"
        @paste="onPaste"
    ></div>
  </div>
</template>

<style>
@import "@/assets/newsProse.css";

.rte {
  overflow: hidden;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
}

.rte:focus-within { border-color: rgba(139, 92, 246, 0.6); }

.rte__bar {
  position: sticky;
  top: 0;
  z-index: 1;

  display: flex;
  flex-wrap: wrap;
  gap: 4px;

  padding: 6px;

  background: rgba(20, 17, 30, 0.96);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.rte__btn {
  min-width: 30px;
  height: 30px;
  padding: 0 8px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 7px;

  font: inherit;
  font-size: 12px;
  font-weight: 800;

  cursor: pointer;
  font-variant-emoji: text;
}

.rte__btn:hover { color: #fff; background: rgba(139, 92, 246, 0.3); }

.rte__area {
  min-height: 220px;
  max-height: 520px;
  overflow-y: auto;

  padding: 12px 14px;

  outline: none;
}

.rte__area:empty::before {
  content: attr(data-placeholder);
  color: #6f6a80;
}

</style>
