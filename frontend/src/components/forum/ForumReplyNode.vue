<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { userLink } from '@/utils/links.js'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  reply: {
    type: Object,
    required: true,
  },

  maxIndent: {
    type: Number,
    default: 5,
  },

  canReply: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits([
  'reply',
  'like',
  'edit',
  'delete',
])

const collapsed = ref(false)

const children = computed(() => props.reply.children ?? [])

const hasChildren = computed(() => children.value.length > 0)

const hiddenCount = computed(() => {
  let total = 0

  const count = (items) => {
    for (const item of items) {
      total += 1
      count(item.children ?? [])
    }
  }

  count(children.value)

  return total
})

const depth = computed(() => {
  return Math.max(0, Number(props.reply.depth ?? 0))
})

const indent = computed(() => {
  return Math.min(depth.value, props.maxIndent)
})

const isDeep = computed(() => {
  return depth.value >= props.maxIndent
})

const isDeleted = computed(() => {
  return Boolean(props.reply.deleted_at || props.reply.is_deleted)
})

const isLiked = computed(() => {
  return Boolean(
      props.reply.liked ||
      props.reply.is_liked ||
      props.reply.user_liked
  )
})

const likeCount = computed(() => {
  return Number(props.reply.likes_count ?? props.reply.like_count ?? 0)
})

const author = computed(() => {
  return props.reply.user ?? props.reply.author ?? {}
})

/** Ссылка на профиль автора: по нику, а не по id. */
const authorLink = computed(() => {
  const person = author.value

  if (!person?.id && !person?.username) return null

  return userLink(person)
})

const authorName = computed(() => {
  return (
      author.value.username ||
      author.value.name ||
      props.reply.username ||
      'Пользователь'
  )
})

const avatar = computed(() => {
  return (
      author.value.avatar_url ||
      author.value.avatar ||
      props.reply.avatar_url ||
      null
  )
})

const userId = computed(() => {
  return author.value.id ?? props.reply.user_id ?? null
})

const isVerified = computed(() => {
  return Boolean(
      author.value.email_verified ||
      author.value.verified ||
      author.value.is_verified ||
      props.reply.user_verified
  )
})

const isAuthorMedia = computed(() => {
  return Boolean(
      author.value.is_media ||
      author.value.media ||
      props.reply.is_media
  )
})

const authorRole = computed(() => {
  if (author.value.role === 'admin') return 'ADMIN'
  if (author.value.role === 'moderator') return 'MOD'
  if (author.value.role === 'tester') return 'TESTER'

  return null
})

const body = computed(() => {
  return props.reply.body ?? props.reply.content ?? ''
})

const attachments = computed(() => {
  return props.reply.attachments ?? []
})

function formatDate(value) {
  if (!value) return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const diff = Date.now() - date.getTime()

  if (diff < 60_000) {
    return 'только что'
  }

  if (diff < 3_600_000) {
    return `${Math.floor(diff / 60_000)} мин назад`
  }

  if (diff < 86_400_000) {
    return `${Math.floor(diff / 3_600_000)} ч назад`
  }

  if (diff < 604_800_000) {
    return `${Math.floor(diff / 86_400_000)} дн назад`
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    year:
        date.getFullYear() !== new Date().getFullYear()
            ? 'numeric'
            : undefined,
  }).format(date)
}

function attachmentUrl(item) {
  return item.url || item.file_url || item.path || ''
}

function attachmentName(item) {
  return (
      item.name ||
      item.filename ||
      item.original_name ||
      'Файл'
  )
}

function isImage(item) {
  if (item.type?.startsWith?.('image/')) return true

  const url = attachmentUrl(item)

  return /\.(png|jpe?g|gif|webp|avif)$/i.test(url)
}

function toggleCollapsed() {
  if (!hasChildren.value) return

  collapsed.value = !collapsed.value
}

function onReply() {
  emit('reply', props.reply)
}

function onLike() {
  emit('like', props.reply)
}

function onEdit() {
  emit('edit', props.reply)
}

function onDelete() {
  emit('delete', props.reply)
}
</script>

<template>
  <article
      class="forum-reply"
      :class="{
      'forum-reply--deep': isDeep,
      'forum-reply--deleted': isDeleted,
      'forum-reply--liked': isLiked,
    }"
      :style="{ '--reply-depth': indent }"
  >
    <!-- THREAD -->
    <div
        v-if="depth > 0"
        class="forum-reply__thread"
        aria-hidden="true"
    >
      <button
          v-if="hasChildren"
          type="button"
          class="forum-reply__thread-node"
          :class="{
          'forum-reply__thread-node--open': !collapsed,
        }"
          :aria-label="collapsed ? 'Развернуть ответы' : 'Свернуть ответы'"
          @click="toggleCollapsed"
      >
        <AppIcon
            :icon="collapsed ? 'send' : 'close'"
            :size="10"
        />
      </button>
    </div>

    <!-- MAIN -->
    <div class="forum-reply__content">
      <!-- HEADER -->
      <header class="forum-reply__header">
        <div class="forum-reply__identity">
          <RouterLink
              v-if="authorLink"
              :to="authorLink"
              class="forum-reply__avatar"
              :class="{
              'forum-reply__avatar--image': avatar,
            }"
          >
            <img
                v-if="avatar"
                :src="avatar"
                :alt="authorName"
                loading="lazy"
            />

            <span v-else>
              {{ authorName.charAt(0).toUpperCase() }}
            </span>
          </RouterLink>

          <div
              v-else
              class="forum-reply__avatar"
              :class="{
              'forum-reply__avatar--image': avatar,
            }"
          >
            <img
                v-if="avatar"
                :src="avatar"
                :alt="authorName"
                loading="lazy"
            />

            <span v-else>
              {{ authorName.charAt(0).toUpperCase() }}
            </span>
          </div>

          <div class="forum-reply__meta">
            <div class="forum-reply__author-row">
              <RouterLink
                  v-if="userId"
                  :to="authorLink"
                  class="forum-reply__author"
              >
                {{ authorName }}
              </RouterLink>

              <span
                  v-else
                  class="forum-reply__author"
              >
                {{ authorName }}
              </span>

              <span
                  v-if="isVerified"
                  class="forum-reply__verified"
                  title="Подтверждённый аккаунт"
              >
                <AppIcon
                    icon="check"
                    :size="10"
                />
              </span>

              <span
                  v-if="isAuthorMedia"
                  class="forum-reply__media"
              >
                MEDIA
              </span>

              <span
                  v-if="authorRole"
                  class="forum-reply__role"
              >
                {{ authorRole }}
              </span>
            </div>

            <div class="forum-reply__submeta">
              <time
                  :datetime="reply.created_at || reply.createdAt"
              >
                {{ formatDate(reply.created_at || reply.createdAt) }}
              </time>

              <span
                  v-if="depth > 0"
                  class="forum-reply__depth"
              >
                #{{ depth }}
              </span>
            </div>
          </div>
        </div>

        <div class="forum-reply__header-actions">
          <button
              v-if="hasChildren"
              type="button"
              class="forum-reply__collapse"
              :class="{
              'forum-reply__collapse--active': collapsed,
            }"
              @click="toggleCollapsed"
          >
            <AppIcon
                :icon="collapsed ? 'send' : 'close'"
                :size="12"
            />

            <span v-if="collapsed">
              {{ hiddenCount }} {{ hiddenCount === 1 ? 'ответ' : 'ответов' }}
            </span>
          </button>
        </div>
      </header>

      <!-- BODY -->
      <div class="forum-reply__body">
        <div
            v-if="isDeleted"
            class="forum-reply__deleted"
        >
          <span class="forum-reply__deleted-icon">
            <AppIcon
                icon="trash"
                :size="14"
            />
          </span>

          <div>
            <strong>Сообщение удалено</strong>
            <span>Содержимое этого ответа больше недоступно.</span>
          </div>
        </div>

        <div
            v-else
            class="forum-reply__text"
        >
          {{ body }}
        </div>

        <!-- ATTACHMENTS -->
        <div
            v-if="!isDeleted && attachments.length"
            class="forum-reply__attachments"
        >
          <a
              v-for="(attachment, index) in attachments"
              :key="attachment.id ?? attachment.uuid ?? index"
              :href="attachmentUrl(attachment)"
              target="_blank"
              rel="noopener noreferrer"
              class="forum-reply__attachment"
              :class="{
              'forum-reply__attachment--image': isImage(attachment),
            }"
          >
            <template v-if="isImage(attachment)">
              <img
                  :src="attachmentUrl(attachment)"
                  :alt="attachmentName(attachment)"
                  loading="lazy"
              />

              <span class="forum-reply__attachment-overlay">
                <AppIcon
                    icon="send"
                    :size="15"
                />
              </span>
            </template>

            <template v-else>
              <span class="forum-reply__file-icon">
                <AppIcon
                    icon="doc"
                    :size="15"
                />
              </span>

              <span class="forum-reply__file-info">
                <strong>{{ attachmentName(attachment) }}</strong>

                <small>
                  {{ attachment.size_human || attachment.type || 'Файл' }}
                </small>
              </span>

              <AppIcon
                  icon="send"
                  :size="13"
              />
            </template>
          </a>
        </div>
      </div>

      <!-- ACTIONS -->
      <footer
          v-if="!isDeleted"
          class="forum-reply__footer"
      >
        <div class="forum-reply__actions">
          <button
              type="button"
              class="forum-reply__action"
              :class="{
              'forum-reply__action--liked': isLiked,
            }"
              @click="onLike"
          >
            <AppIcon
                icon="heart"
                :size="13"
            />

            <span v-if="likeCount">
              {{ likeCount }}
            </span>
          </button>

          <button
              v-if="canReply"
              type="button"
              class="forum-reply__action"
              @click="onReply"
          >
            <AppIcon
                icon="send"
                :size="13"
            />

            <span>Ответить</span>
          </button>

          <button
              v-if="reply.can_edit || reply.is_owner"
              type="button"
              class="forum-reply__action"
              @click="onEdit"
          >
            <AppIcon
                icon="palette"
                :size="13"
            />

            <span>Изменить</span>
          </button>

          <button
              v-if="reply.can_delete || reply.is_owner"
              type="button"
              class="forum-reply__action forum-reply__action--danger"
              @click="onDelete"
          >
            <AppIcon
                icon="trash"
                :size="13"
            />

            <span>Удалить</span>
          </button>
        </div>

        <div
            v-if="hasChildren && !collapsed"
            class="forum-reply__children-label"
        >
          <span class="forum-reply__children-dot" />
          {{ children.length }}
          {{ children.length === 1 ? 'ответ' : 'ответов' }}
        </div>
      </footer>

      <!-- CHILDREN -->
      <Transition name="reply-children">
        <div
            v-if="hasChildren && !collapsed"
            class="forum-reply__children"
        >
          <ForumReplyNode
              v-for="child in children"
              :key="child.id"
              :reply="child"
              :max-indent="maxIndent"
              :can-reply="canReply"
              @reply="$emit('reply', $event)"
              @like="$emit('like', $event)"
              @edit="$emit('edit', $event)"
              @delete="$emit('delete', $event)"
          />
        </div>
      </Transition>

      <!-- COLLAPSED -->
      <button
          v-if="hasChildren && collapsed"
          type="button"
          class="forum-reply__collapsed"
          @click="toggleCollapsed"
      >
        <span class="forum-reply__collapsed-line" />

        <span class="forum-reply__collapsed-main">
          <span class="forum-reply__collapsed-icon">
            <AppIcon
                icon="send"
                :size="12"
            />
          </span>

          <span>
            Показать
            <strong>{{ hiddenCount }}</strong>
            {{ hiddenCount === 1 ? 'ответ' : 'ответов' }}
          </span>
        </span>

        <AppIcon
            icon="send"
            :size="12"
        />
      </button>
    </div>
  </article>
</template>

<style scoped>
@import "@/components/forum/ForumReplyNode.css";
</style>
