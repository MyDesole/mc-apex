<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { userLink } from '@/utils/links.js'
import AppIcon from '@/components/AppIcon.vue'

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
.forum-reply {
  --reply-indent-size: 24px;

  position: relative;
  width: 100%;
  min-width: 0;
}

.forum-reply__content {
  position: relative;
  min-width: 0;
  padding: 18px 0 10px;
}

/* ─────────────────────────
   THREAD
───────────────────────── */

.forum-reply__thread {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(
      -1 * var(--reply-indent-size)
  );
  width: 1px;
  background:
      linear-gradient(
          to bottom,
          transparent 0,
          var(--border) 16px,
          var(--border) calc(100% - 20px),
          transparent 100%
      );
}

.forum-reply__thread-node {
  position: absolute;
  top: 20px;
  left: -6px;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 12px;
  height: 12px;
  padding: 0;

  border: 1px solid var(--border);
  border-radius: 50%;

  color: var(--text-muted);
  background: var(--bg);

  cursor: pointer;
  transition:
      color 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.forum-reply__thread-node:hover {
  color: var(--accent);
  border-color: var(--accent);
  background: var(--bg-card);
  transform: scale(1.12);
}

.forum-reply__thread-node--open {
  color: var(--accent);
  border-color: color-mix(
      in srgb,
      var(--accent) 45%,
      var(--border)
  );
}

/* ─────────────────────────
   HEADER
───────────────────────── */

.forum-reply__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;

  min-height: 40px;
}

.forum-reply__identity {
  display: flex;
  align-items: center;
  gap: 11px;
  min-width: 0;
}

.forum-reply__avatar {
  position: relative;

  display: flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 auto;
  width: 36px;
  height: 36px;

  overflow: hidden;

  border: 1px solid var(--border);
  border-radius: 10px;

  color: var(--text);
  background:
      linear-gradient(
          145deg,
          var(--bg-card),
          color-mix(
              in srgb,
              var(--accent) 8%,
              var(--bg-card)
          )
      );

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;

  transition:
      border-color 0.18s ease,
      box-shadow 0.18s ease;
}

.forum-reply:hover .forum-reply__avatar {
  border-color: var(--border-hover);
  box-shadow:
      0 0 0 3px
      color-mix(
          in srgb,
          var(--accent) 5%,
          transparent
      );
}

.forum-reply__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.forum-reply__meta {
  min-width: 0;
}

.forum-reply__author-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  min-width: 0;
}

.forum-reply__author {
  max-width: 260px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  color: var(--text);
  font-size: 13px;
  font-weight: 750;
  text-decoration: none;

  transition: color 0.18s ease;
}

.forum-reply__author:hover {
  color: var(--accent-light, var(--accent));
}

.forum-reply__verified {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  width: 14px;
  height: 14px;

  border-radius: 50%;

  color: #fff;
  background: var(--accent);

  box-shadow:
      0 0 10px
      color-mix(
          in srgb,
          var(--accent) 22%,
          transparent
      );
}

.forum-reply__media,
.forum-reply__role,
.forum-reply__depth {
  display: inline-flex;
  align-items: center;

  min-height: 17px;
  padding: 0 6px;

  border: 1px solid var(--border);
  border-radius: 5px;

  color: var(--text-muted);
  background: var(--bg-card);

  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.forum-reply__media {
  color: #d9b8ff;
  border-color: color-mix(
      in srgb,
      var(--accent) 28%,
      var(--border)
  );
}

.forum-reply__role {
  color: #e9c875;
  border-color: rgba(220, 175, 75, 0.24);
}

.forum-reply__submeta {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-top: 3px;

  color: var(--text-muted);
  font-size: 10px;
  line-height: 1;
}

.forum-reply__depth {
  min-height: 15px;
  padding: 0 5px;

  color: var(--text-muted);
  background: transparent;

  font-size: 8px;
}

.forum-reply__header-actions {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
}

.forum-reply__collapse {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  min-height: 27px;
  padding: 0 8px;

  border: 1px solid transparent;
  border-radius: 7px;

  color: var(--text-muted);
  background: transparent;

  font-size: 9px;
  font-weight: 700;

  cursor: pointer;
  transition:
      color 0.18s ease,
      border-color 0.18s ease,
      background 0.18s ease;
}

.forum-reply__collapse:hover,
.forum-reply__collapse--active {
  color: var(--text);
  border-color: var(--border);
  background: var(--bg-card);
}

/* ─────────────────────────
   BODY
───────────────────────── */

.forum-reply__body {
  padding-left: 47px;
  margin-top: 8px;
}

.forum-reply__text {
  max-width: 860px;

  color: var(--text);
  font-size: 13px;
  line-height: 1.7;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.forum-reply__deleted {
  display: flex;
  align-items: center;
  gap: 10px;

  max-width: 620px;
  padding: 10px 12px;

  border: 1px dashed var(--border);
  border-radius: 8px;

  color: var(--text-muted);
  background: color-mix(
      in srgb,
      var(--bg-card) 70%,
      transparent
  );
}

.forum-reply__deleted-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 28px;
  height: 28px;
  flex: 0 0 auto;

  border-radius: 7px;

  color: var(--text-muted);
  background: var(--bg-card);
}

.forum-reply__deleted strong,
.forum-reply__deleted span {
  display: block;
}

.forum-reply__deleted strong {
  color: var(--text-dim);
  font-size: 11px;
}

.forum-reply__deleted div > span {
  margin-top: 2px;
  font-size: 10px;
}

/* ─────────────────────────
   ATTACHMENTS
───────────────────────── */

.forum-reply__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  margin-top: 12px;
}

.forum-reply__attachment {
  position: relative;

  display: flex;
  align-items: center;
  gap: 9px;

  min-width: 0;
  max-width: 280px;
  min-height: 38px;
  padding: 5px 9px 5px 5px;

  border: 1px solid var(--border);
  border-radius: 8px;

  color: var(--text-dim);
  background: var(--bg-card);

  text-decoration: none;

  transition:
      border-color 0.18s ease,
      background 0.18s ease,
      transform 0.18s ease;
}

.forum-reply__attachment:hover {
  border-color: var(--border-hover);
  background: var(--bg-card-hover, var(--bg-card));
  transform: translateY(-1px);
}

.forum-reply__attachment--image {
  width: 110px;
  height: 76px;
  padding: 0;
  overflow: hidden;
}

.forum-reply__attachment--image > img {
  width: 100%;
  height: 100%;
  object-fit: cover;

  transition: transform 0.25s ease;
}

.forum-reply__attachment--image:hover img {
  transform: scale(1.04);
}

.forum-reply__attachment-overlay {
  position: absolute;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: rgba(0, 0, 0, 0.38);

  opacity: 0;

  transition: opacity 0.18s ease;
}

.forum-reply__attachment--image:hover
.forum-reply__attachment-overlay {
  opacity: 1;
}

.forum-reply__file-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 28px;
  height: 28px;
  flex: 0 0 auto;

  border-radius: 6px;

  color: var(--accent);
  background:
      color-mix(
          in srgb,
          var(--accent) 9%,
          var(--bg)
      );
}

.forum-reply__file-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.forum-reply__file-info strong {
  max-width: 190px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  color: var(--text);
  font-size: 10px;
  font-weight: 700;
}

.forum-reply__file-info small {
  margin-top: 2px;

  color: var(--text-muted);
  font-size: 8px;
}

/* ─────────────────────────
   FOOTER
───────────────────────── */

.forum-reply__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  min-height: 30px;
  padding-left: 47px;
  margin-top: 5px;
}

.forum-reply__actions {
  display: flex;
  align-items: center;
  gap: 2px;
}

.forum-reply__action {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  min-height: 27px;
  padding: 0 7px;

  border: 1px solid transparent;
  border-radius: 6px;

  color: var(--text-muted);
  background: transparent;

  font-size: 9px;
  font-weight: 700;

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease;
}

.forum-reply__action:hover {
  color: var(--text);
  border-color: var(--border);
  background: var(--bg-card);
}

.forum-reply__action--liked {
  color: #e66b92;
}

.forum-reply__action--liked:hover {
  color: #f184a8;
  border-color: rgba(230, 107, 146, 0.2);
  background: rgba(230, 107, 146, 0.06);
}

.forum-reply__action--danger:hover {
  color: var(--danger, #ef6262);
  border-color: color-mix(
      in srgb,
      var(--danger, #ef6262) 20%,
      var(--border)
  );
  background: color-mix(
      in srgb,
      var(--danger, #ef6262) 6%,
      transparent
  );
}

.forum-reply__children-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  color: var(--text-muted);
  font-size: 9px;
  font-weight: 600;
}

.forum-reply__children-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: var(--accent);
  box-shadow:
      0 0 8px
      color-mix(
          in srgb,
          var(--accent) 35%,
          transparent
      );
}

/* ─────────────────────────
   CHILDREN
───────────────────────── */

.forum-reply__children {
  position: relative;

  margin-top: 2px;
  margin-left: var(--reply-indent-size);
  padding-left: var(--reply-indent-size);

  border-left: 1px solid var(--border);
}

.forum-reply--deep > .forum-reply__content
> .forum-reply__children {
  border-left-color: color-mix(
      in srgb,
      var(--accent) 20%,
      var(--border)
  );
}

/* ─────────────────────────
   COLLAPSED
───────────────────────── */

.forum-reply__collapsed {
  display: flex;
  align-items: center;
  gap: 10px;

  width: 100%;
  padding: 8px 0 5px 47px;

  border: 0;

  color: var(--text-muted);
  background: transparent;

  font-size: 10px;
  font-weight: 600;
  text-align: left;

  cursor: pointer;
}

.forum-reply__collapsed-line {
  width: 24px;
  height: 1px;
  flex: 0 0 auto;
  background: var(--border);
}

.forum-reply__collapsed-main {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.forum-reply__collapsed-main strong {
  color: var(--text-dim);
}

.forum-reply__collapsed-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 22px;
  height: 22px;

  border: 1px solid var(--border);
  border-radius: 6px;

  color: var(--accent);
  background: var(--bg-card);

  transition:
      border-color 0.18s ease,
      transform 0.18s ease;
}

.forum-reply__collapsed:hover {
  color: var(--text);
}

.forum-reply__collapsed:hover
.forum-reply__collapsed-icon {
  border-color: var(--border-hover);
  transform: translateX(2px);
}

/* ─────────────────────────
   TRANSITION
───────────────────────── */

.reply-children-enter-active,
.reply-children-leave-active {
  overflow: hidden;
  transition:
      opacity 0.18s ease,
      transform 0.18s ease;
}

.reply-children-enter-from,
.reply-children-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

/* ─────────────────────────
   RESPONSIVE
───────────────────────── */

@media (max-width: 700px) {
  .forum-reply {
    --reply-indent-size: 15px;
  }

  .forum-reply__content {
    padding-top: 14px;
  }

  .forum-reply__header {
    gap: 8px;
  }

  .forum-reply__identity {
    gap: 9px;
  }

  .forum-reply__avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }

  .forum-reply__author {
    max-width: 180px;
    font-size: 12px;
  }

  .forum-reply__body,
  .forum-reply__footer {
    padding-left: 41px;
  }

  .forum-reply__text {
    font-size: 12px;
    line-height: 1.65;
  }

  .forum-reply__action span {
    display: none;
  }

  .forum-reply__action {
    width: 28px;
    padding: 0;
    justify-content: center;
  }

  .forum-reply__children {
    margin-left: var(--reply-indent-size);
    padding-left: var(--reply-indent-size);
  }

  .forum-reply__children-label {
    display: none;
  }

  .forum-reply__collapsed {
    padding-left: 41px;
  }
}

@media (max-width: 480px) {
  .forum-reply__media,
  .forum-reply__role,
  .forum-reply__depth {
    display: none;
  }

  .forum-reply__header-actions {
    margin-left: auto;
  }

  .forum-reply__collapse span {
    display: none;
  }

  .forum-reply__attachments {
    gap: 6px;
  }

  .forum-reply__attachment--image {
    width: 92px;
    height: 64px;
  }

  .forum-reply__footer {
    margin-top: 7px;
  }
}
</style>