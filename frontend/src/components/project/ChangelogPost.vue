<script setup>
/**
 * Пост чейнджлога: в ленте и на отдельной странице.
 * Кнопка «Скопировать ссылку» — чтобы отправить пост в Telegram-канал.
 */
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { bodyBlocks, CATEGORY_COLORS, formatPostDate } from '@/services/project/project.js'

const props = defineProps({
  entry: { type: Object, required: true },
  // На отдельной странице заголовок не ссылка
  single: { type: Boolean, default: false },
})

const copied = ref(false)

const blocks = computed(() => bodyBlocks(props.entry.body))
const link = computed(() => `${window.location.origin}/changelog/${props.entry.slug}`)

async function copyLink() {
  try {
    await navigator.clipboard.writeText(link.value)
  } catch {
    // Без доступа к буферу (http, старый браузер) — через скрытое поле
    const field = document.createElement('textarea')
    field.value = link.value
    document.body.appendChild(field)
    field.select()
    document.execCommand('copy')
    field.remove()
  }

  copied.value = true
  setTimeout(() => { copied.value = false }, 1800)
}
</script>

<template>
  <article
      :id="`post-${entry.slug}`"
      class="post"
      :style="{ '--cat': CATEGORY_COLORS[entry.category] || '#a78bfa' }"
  >
    <header class="post__head">
      <span class="post__cat">{{ entry.category_label }}</span>
      <span v-if="entry.version" class="post__version">v{{ entry.version }}</span>
      <time class="post__date" :datetime="entry.published_at">{{ formatPostDate(entry.published_at) }}</time>
    </header>

    <h2 class="post__title">
      <span v-if="single">{{ entry.title }}</span>
      <RouterLink v-else :to="`/changelog/${entry.slug}`">{{ entry.title }}</RouterLink>
    </h2>

    <div class="post__body">
      <template v-for="(block, i) in blocks" :key="i">
        <p v-if="block.type === 'p'">{{ block.text }}</p>
        <ul v-else>
          <li v-for="(item, j) in block.items" :key="j">{{ item }}</li>
        </ul>
      </template>
    </div>

    <footer class="post__foot">
      <span v-if="entry.author" class="post__author">{{ entry.author.username }}</span>

      <button type="button" class="post__copy" :class="{ 'is-done': copied }" @click="copyLink">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <template v-if="copied"><path d="M5 12l5 5L20 7" /></template>
          <template v-else>
            <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7" />
            <path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" />
          </template>
        </svg>
        {{ copied ? 'Ссылка скопирована' : 'Скопировать ссылку' }}
      </button>
    </footer>
  </article>
</template>

<style scoped>
.post {
  position: relative;

  padding: 20px 22px 18px 24px;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 8px;

  scroll-margin-top: calc(var(--header-height) + 16px);
}

.post::before {
  content: '';

  position: absolute;
  left: 0;
  top: 18px;
  bottom: 18px;

  width: 3px;

  background: var(--cat);
  border-radius: 0 3px 3px 0;
  box-shadow: 0 0 12px color-mix(in srgb, var(--cat) 55%, transparent);
}

.post__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;

  margin-bottom: 8px;

  font-size: 12px;
}

.post__cat {
  padding: 2px 8px;

  color: var(--cat);
  background: color-mix(in srgb, var(--cat) 14%, transparent);
  border: 1px solid color-mix(in srgb, var(--cat) 35%, transparent);
  border-radius: 4px;

  font-weight: 800;
}

.post__version {
  color: var(--text-dim);
  font-family: ui-monospace, Consolas, monospace;
  font-weight: 700;
}

.post__date {
  margin-left: auto;
  color: var(--text-muted);
}

.post__title {
  margin: 0 0 10px;

  font-family: var(--font-display);
  font-size: 20px;
  line-height: 1.25;
}

.post__title a:hover {
  color: var(--accent-light);
}

.post__body {
  color: var(--text-dim);
  font-size: 14.5px;
  line-height: 1.65;
}

.post__body p {
  margin: 0 0 10px;
}

.post__body ul {
  margin: 0 0 10px;
  padding-left: 20px;
}

.post__body li {
  margin-bottom: 4px;
}

.post__body li::marker {
  color: var(--cat);
}

.post__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;

  margin-top: 12px;
  padding-top: 12px;

  border-top: 1px solid var(--border);
}

.post__author {
  color: var(--text-muted);
  font-size: 12px;
}

.post__copy {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  margin-left: auto;
  padding: 7px 12px;

  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 6px;

  font: inherit;
  font-size: 12.5px;
  font-weight: 700;

  cursor: pointer;

  transition: color 0.15s ease, border-color 0.15s ease;
}

.post__copy:hover {
  color: var(--text);
  border-color: var(--border-hover);
}

.post__copy.is-done {
  color: #86efac;
  border-color: rgba(34, 197, 94, 0.45);
}

@media (max-width: 600px) {
  .post {
    padding: 16px 14px 14px 18px;
  }

  .post__title {
    font-size: 17px;
  }
}
</style>
