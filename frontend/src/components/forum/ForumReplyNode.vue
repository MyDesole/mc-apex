<script setup>
/**
 * Один ответ в дереве форума + все его вложенные ответы.
 * Компонент рекурсивный: дети рисуются тем же компонентом,
 * поэтому глубина вложенности не ограничена кодом.
 */
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps({
  reply: { type: Object, required: true },
  // Ограничение визуального отступа, чтобы глубокие ветки не уезжали вправо
  maxIndent: { type: Number, default: 5 },
  canReply: { type: Boolean, default: true },
})

const emit = defineEmits(['reply', 'like', 'edit', 'delete'])

const collapsed = ref(false)

const children = computed(() => props.reply.children ?? [])

const hiddenCount = computed(() => {
  let total = 0

  const count = (list) => {
    for (const item of list) {
      total++
      count(item.children ?? [])
    }
  }

  count(children.value)

  return total
})

// Отступ: после предела вложенности дальше не сдвигаем
const indent = computed(() => Math.min(props.reply.depth ?? 0, props.maxIndent))

const isDeep = computed(() => (props.reply.depth ?? 0) >= props.maxIndent)

function formatDate(value) {
  if (!value) return ''

  const date = new Date(value)
  const diff = (Date.now() - date.getTime()) / 1000

  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 604800) return `${Math.floor(diff / 86400)} дн назад`

  return date.toLocaleDateString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="reply" :style="{ '--indent': indent }">
    <article class="reply__body" :class="{ 'reply__body--deep': isDeep }">
      <!-- Аватар -->
      <span class="reply__avatar">
        <img
            v-if="reply.author?.avatar_url"
            :src="reply.author.avatar_url"
            :alt="reply.author.username"
        >
        <template v-else>
          {{ (reply.author?.username || 'И').charAt(0).toUpperCase() }}
        </template>
      </span>

      <div class="reply__content">
        <!-- Автор и метки -->
        <div class="reply__head">
          <RouterLink
              v-if="reply.author"
              class="reply__author"
              :class="{ 'reply__author--media': reply.author.is_media }"
              :to="{ name: 'player', params: { id: reply.author.id } }"
          >
            {{ reply.author.username }}
          </RouterLink>

          <span v-if="reply.author?.is_media" class="tag tag--media">
            <AppIcon icon="star" :size="11" /> Медийка
          </span>

          <span v-if="reply.author?.is_verified" class="verified" title="Верифицирован">✓</span>

          <span class="reply__date">
            {{ formatDate(reply.created_at) }}
            <template v-if="reply.edited_at"> · изменено</template>
          </span>
        </div>

        <!-- Текст или режим правки -->
        <template v-if="reply.is_deleted">
          <p class="reply__deleted">Сообщение удалено.</p>
        </template>

        <template v-else>
          <p class="reply__text">{{ reply.body }}</p>

          <!-- Вложения -->
          <div v-if="reply.attachments?.length" class="reply__attachments">
            <template v-for="file in reply.attachments" :key="file.id">
              <a
                  v-if="file.is_image"
                  :href="file.url"
                  target="_blank"
                  rel="noopener"
                  class="reply__image"
              >
                <img :src="file.url" :alt="file.name" loading="lazy">
              </a>
              <a v-else :href="file.url" target="_blank" rel="noopener" class="reply__file">
                <AppIcon icon="doc" :size="14" />
                {{ file.name }}
                <span class="reply__file-size">{{ file.size }}</span>
              </a>
            </template>
          </div>
        </template>

        <!-- Действия -->
        <div class="reply__actions">
          <button
              class="reply__action"
              :class="{ 'reply__action--on': reply.liked }"
              type="button"
              @click="emit('like', reply)"
          >
            <AppIcon icon="heart" :size="13" />
            {{ reply.likes_count || 0 }}
          </button>

          <button
              v-if="canReply && !reply.is_deleted"
              class="reply__action"
              type="button"
              @click="emit('reply', reply)"
          >
            <AppIcon icon="send" :size="13" />
            Ответить
          </button>

          <button
              v-if="reply.can_edit && !reply.is_deleted"
              class="reply__action"
              type="button"
              @click="emit('edit', reply)"
          >
            Изменить
          </button>

          <button
              v-if="reply.can_delete && !reply.is_deleted"
              class="reply__action reply__action--danger"
              type="button"
              @click="emit('delete', reply)"
          >
            Удалить
          </button>

          <button
              v-if="children.length"
              class="reply__action reply__action--muted"
              type="button"
              @click="collapsed = !collapsed"
          >
            {{ collapsed ? `Показать ответы (${hiddenCount})` : 'Свернуть ветку' }}
          </button>
        </div>

        <!-- Дочерние ответы: та же ветка с отступом -->
        <div v-if="!collapsed && children.length" class="reply__children">
          <ForumReplyNode
              v-for="child in children"
              :key="child.id"
              :reply="child"
              :max-indent="maxIndent"
              :can-reply="canReply"
              @reply="emit('reply', $event)"
              @like="emit('like', $event)"
              @edit="emit('edit', $event)"
              @delete="emit('delete', $event)"
          />
        </div>
      </div>
    </article>
  </div>
</template>

<style scoped>
.reply {
  position: relative;
}

.reply__body {
  display: flex;
  gap: 11px;
  padding: 11px 0 11px calc(var(--indent, 0) * 26px);
  border-top: 1px solid rgba(34, 34, 46, 0.7);
}

/* Линия ветки: показывает, что ответ вложен */
.reply__body:not(.reply__body--deep)::before {
  content: '';
  position: absolute;
  left: calc(var(--indent, 0) * 26px - 13px);
  top: 0;
  bottom: 0;
  width: 2px;
  background: linear-gradient(180deg, rgba(124, 58, 237, 0.5), rgba(124, 58, 237, 0.05));
  border-radius: 2px;
}

.reply__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  overflow: hidden;
  color: #fff;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 9px;
  font-size: 13px;
  font-weight: 800;
  flex-shrink: 0;
}

.reply__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.reply__content {
  flex: 1;
  min-width: 0;
}

.reply__head {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  align-items: center;
  margin-bottom: 5px;
}

.reply__author {
  color: var(--text, #e2e2e8);
  font-size: 13px;
  font-weight: 700;
}

.reply__author:hover {
  color: var(--accent-light, #8b5cf6);
}

.reply__author--media {
  color: #f472b6;
}

.tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.tag--media {
  color: #f472b6;
  background: linear-gradient(135deg, rgba(244, 114, 182, 0.18), rgba(168, 85, 247, 0.18));
  border: 1px solid rgba(244, 114, 182, 0.45);
}

.verified {
  color: #38bdf8;
  font-size: 12px;
}

.reply__date {
  color: var(--text-muted, #5e5e70);
  font-size: 11px;
}

.reply__text {
  margin: 0;
  color: var(--text, #e2e2e8);
  font-size: 14px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-word;
}

.reply__deleted {
  margin: 0;
  color: var(--text-muted, #5e5e70);
  font-size: 13px;
  font-style: italic;
}

.reply__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.reply__image img {
  max-width: 200px;
  max-height: 160px;
  border: 1px solid var(--border, #22222e);
  border-radius: 9px;
  object-fit: cover;
}

.reply__file {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 11px;
  color: var(--text-dim, #8888a0);
  background: var(--bg, #0a0a0f);
  border: 1px solid var(--border, #22222e);
  border-radius: 8px;
  font-size: 12px;
}

.reply__file-size {
  color: var(--text-muted, #5e5e70);
  font-size: 11px;
}

.reply__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  margin-top: 7px;
}

.reply__action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0;
  color: var(--text-muted, #5e5e70);
  background: transparent;
  border: 0;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: color 0.18s ease;
}

.reply__action:hover {
  color: var(--text, #e2e2e8);
}

.reply__action--on {
  color: #f472b6;
}

.reply__action--danger:hover {
  color: #f87171;
}

.reply__action--muted {
  color: var(--accent-light, #8b5cf6);
}

.reply__children {
  margin-top: 2px;
}
</style>
