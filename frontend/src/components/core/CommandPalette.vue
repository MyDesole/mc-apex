<script setup>
/**
 * Командная палитра: поиск по всему сайту и быстрые переходы.
 *
 * Открывается по Ctrl+K (⌘K на Mac), по «/» вне полей ввода и кнопкой
 * в шапке. Управление с клавиатуры: ↑ ↓ — выбор, Enter — переход,
 * Esc — закрыть. Пустой запрос показывает недавнее и разделы сайта.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/core/auth.js'
import { useCommandPalette } from '@/composables/core/useCommandPalette.js'
import { searchApi } from '@/services/search/search.js'
import { tierColors } from '@/data/players/profileCustomization.js'
import { userLink, clanLink } from '@/utils/links.js'

const router = useRouter()
const auth = useAuthStore()
const { isOpen, open, close, toggle } = useCommandPalette()

const query = ref('')
const loading = ref(false)
const results = ref({ players: [], clans: [], topics: [], news: [] })
const activeIndex = ref(0)
const inputRef = ref(null)
const listRef = ref(null)

const RECENT_KEY = 'apex:palette-recent'
const RECENT_LIMIT = 6
const MIN_LENGTH = 2
const DEBOUNCE_MS = 140

const recent = ref(loadRecent())

/* ------------------------------ Разделы сайта ------------------------------ */

const PAGES = [
  { label: 'Главная', to: '/', hint: 'Обзор, новости и топы' },
  { label: 'Рейтинг игроков', to: '/players', hint: 'Все игроки по тирам', auth: true },
  { label: 'Сравнить игроков', to: '/compare', hint: 'Двое игроков бок о бок', auth: true },
  { label: 'Кланы', to: '/clans', hint: 'Рейтинг и набор' },
  { label: 'Форум', to: '/forum', hint: 'Обсуждения сообщества' },
  { label: 'Турниры', to: '/tournaments', hint: 'Расписание и сетки' },
  { label: 'Новости', to: '/news', hint: 'Обновления проекта' },
  { label: 'Магазин', to: '/shop', hint: 'Рамки, эффекты, приоритет' },
  { label: 'Аукцион питомцев', to: '/auction', hint: 'Ставки на уникальных питомцев' },
  { label: 'Правила тир-тестов', to: '/rules', hint: 'Аспекты, пороги тиров' },
  { label: 'Статистика', to: '/stats', hint: 'Тиры, аспекты, тесты' },
  { label: 'Статус сервера', to: '/status', hint: 'Онлайн, версия, адрес' },
  { label: 'О проекте', to: '/about', hint: 'Команда и тестеры' },
  { label: 'Ачивки', to: '/achievements', hint: 'Каталог и прогресс', auth: true },
  { label: 'Мой профиль', to: '/profile', hint: 'Тир, аспекты, карточка', auth: true },
  { label: 'Сообщения', to: '/messages', hint: 'Личные диалоги', auth: true },
  { label: 'Друзья', to: '/friends', hint: 'Заявки и список', auth: true },
  { label: 'Мой клан', to: '/my-clan', hint: 'Форум, войны, ресурсы', auth: true },
]

const availablePages = computed(() =>
    PAGES.filter((page) => !page.auth || auth.isAuthenticated)
)

/* ------------------------------ Группы результатов ------------------------------ */

const NEWS_TYPES = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
}

function normalize(value) {
  return String(value ?? '').toLocaleLowerCase('ru')
}

const groups = computed(() => {
  const q = query.value.trim()

  if (q.length < MIN_LENGTH) {
    const list = []

    if (recent.value.length) {
      list.push({ key: 'recent', label: 'Недавнее', items: recent.value })
    }

    list.push({
      key: 'pages',
      label: 'Разделы',
      items: availablePages.value.map(pageItem),
    })

    return list
  }

  const { players, clans, topics, news } = results.value
  const needle = normalize(q)

  const pages = availablePages.value
      .filter((page) => normalize(page.label).includes(needle) || normalize(page.hint).includes(needle))
      .map(pageItem)

  return [
    { key: 'players', label: 'Игроки', items: players.map(playerItem) },
    { key: 'clans', label: 'Кланы', items: clans.map(clanItem) },
    { key: 'pages', label: 'Разделы', items: pages },
    { key: 'topics', label: 'Форум', items: topics.map(topicItem) },
    { key: 'news', label: 'Новости', items: news.map(newsItem) },
  ].filter((group) => group.items.length)
})

/** Плоский список для навигации стрелками. */
const flatItems = computed(() => groups.value.flatMap((group) => group.items))

const isEmpty = computed(() =>
    query.value.trim().length >= MIN_LENGTH && !loading.value && flatItems.value.length === 0
)

function pageItem(page) {
  return { kind: 'page', key: `page:${page.to}`, label: page.label, sub: page.hint, to: page.to }
}

function playerItem(player) {
  const nick = player.minecraft_username && player.minecraft_username !== player.username
      ? `MC: ${player.minecraft_username}`
      : null

  return {
    kind: 'player',
    key: `player:${player.id}`,
    label: player.username,
    prefix: player.clan_tag ? `[${player.clan_tag}]` : null,
    prefixColor: player.clan_color,
    sub: [nick, player.display_rank?.type === 'bridge' ? player.display_rank.label : (player.tier ? `тир ${player.tier}` : null)].filter(Boolean).join(' · ') || 'Игрок',
    tier: player.tier,
    avatar: player.avatar_url,
    to: userLink(player),
  }
}

function clanItem(clan) {
  return {
    kind: 'clan',
    key: `clan:${clan.id}`,
    label: clan.name,
    prefix: `[${clan.tag}]`,
    prefixColor: clan.banner_color,
    sub: `${clan.members_count} участников · сила ${clan.power}`,
    avatar: clan.avatar_url,
    color: clan.banner_color,
    to: clanLink(clan),
  }
}

function topicItem(topic) {
  return {
    kind: 'topic',
    key: `topic:${topic.id}`,
    label: topic.title,
    sub: [topic.category, `${topic.replies_count} ответов`].filter(Boolean).join(' · '),
    to: `/forum/${topic.id}`,
  }
}

function newsItem(item) {
  return {
    kind: 'news',
    key: `news:${item.id}`,
    label: item.title,
    sub: NEWS_TYPES[item.type] ?? 'Новость',
    to: `/news/${item.id}`,
  }
}

/* ------------------------------ Поиск ------------------------------ */

let timer = null
let requestId = 0

watch(query, (value) => {
  activeIndex.value = 0
  clearTimeout(timer)

  const q = value.trim()

  if (q.length < MIN_LENGTH) {
    loading.value = false
    results.value = { players: [], clans: [], topics: [], news: [] }
    return
  }

  loading.value = true

  timer = setTimeout(async () => {
    // Ответ на устаревший запрос не должен затирать свежий
    const id = ++requestId

    try {
      const data = await searchApi.query(q)

      if (id === requestId) results.value = data
    } catch {
      if (id === requestId) results.value = { players: [], clans: [], topics: [], news: [] }
    } finally {
      if (id === requestId) loading.value = false
    }
  }, DEBOUNCE_MS)
})

/* ------------------------------ Недавнее ------------------------------ */

function loadRecent() {
  try {
    const raw = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')

    return Array.isArray(raw) ? raw.slice(0, RECENT_LIMIT) : []
  } catch {
    return []
  }
}

function remember(item) {
  if (item.kind === 'page') return

  const list = [item, ...recent.value.filter((entry) => entry.key !== item.key)].slice(0, RECENT_LIMIT)

  recent.value = list

  try {
    localStorage.setItem(RECENT_KEY, JSON.stringify(list))
  } catch {
    /* приватный режим: недавнее просто не сохранится */
  }
}

/* ------------------------------ Действия ------------------------------ */

function select(item) {
  if (!item) return

  remember(item)
  close()
  router.push(item.to)
}

function move(step) {
  const total = flatItems.value.length

  if (!total) return

  activeIndex.value = (activeIndex.value + step + total) % total

  nextTick(() => {
    listRef.value
        ?.querySelector(`[data-index="${activeIndex.value}"]`)
        ?.scrollIntoView({ block: 'nearest' })
  })
}

function onInputKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    move(1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    move(-1)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    select(flatItems.value[activeIndex.value])
  } else if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

function indexOf(item) {
  return flatItems.value.indexOf(item)
}

function tierColor(tier) {
  return tierColors[tier] || '#a78bfa'
}

/* ------------------------------ Горячие клавиши ------------------------------ */

function isTypingTarget(target) {
  return target instanceof HTMLElement
      && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
}

function onGlobalKeydown(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    toggle()
    return
  }

  if (event.key === '/' && !isOpen.value && !isTypingTarget(event.target)) {
    event.preventDefault()
    open()
  }
}

watch(isOpen, (value) => {
  document.body.style.overflow = value ? 'hidden' : ''

  if (value) {
    query.value = ''
    activeIndex.value = 0
    recent.value = loadRecent()
    nextTick(() => inputRef.value?.focus())
  }
})

onMounted(() => window.addEventListener('keydown', onGlobalKeydown))

onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
  clearTimeout(timer)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="palette">
      <div
          v-if="isOpen"
          class="palette-backdrop"
          @mousedown.self="close"
      >
        <div
            class="palette"
            role="dialog"
            aria-modal="true"
            aria-label="Поиск по сайту"
        >
          <div class="palette__search">
            <svg class="palette__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7" />
              <path d="m20 20-3.5-3.5" stroke-linecap="round" />
            </svg>

            <input
                ref="inputRef"
                v-model="query"
                class="palette__input"
                type="text"
                placeholder="Игрок, клан, тема форума или раздел…"
                autocomplete="off"
                spellcheck="false"
                role="combobox"
                aria-expanded="true"
                aria-controls="palette-list"
                :aria-activedescendant="flatItems.length ? `palette-item-${activeIndex}` : undefined"
                @keydown="onInputKeydown"
            />

            <span v-if="loading" class="palette__spinner" aria-hidden="true" />

            <kbd class="palette__esc" @click="close">Esc</kbd>
          </div>

          <div
              id="palette-list"
              ref="listRef"
              class="palette__list"
              role="listbox"
          >
            <div v-if="isEmpty" class="palette__empty">
              <strong>Ничего не нашлось</strong>
              <span>Попробуйте ник из Minecraft, тег клана или часть названия темы</span>
            </div>

            <section
                v-for="group in groups"
                :key="group.key"
                class="palette__group"
            >
              <h3 class="palette__group-title">{{ group.label }}</h3>

              <button
                  v-for="item in group.items"
                  :id="`palette-item-${indexOf(item)}`"
                  :key="item.key"
                  type="button"
                  class="palette-item"
                  :class="{ 'palette-item--active': indexOf(item) === activeIndex }"
                  :data-index="indexOf(item)"
                  role="option"
                  :aria-selected="indexOf(item) === activeIndex"
                  @mousemove="activeIndex = indexOf(item)"
                  @click="select(item)"
              >
                <span
                    class="palette-item__icon"
                    :class="`palette-item__icon--${item.kind}`"
                    :style="item.kind === 'clan' && item.color ? { background: item.color } : {}"
                >
                  <img v-if="item.avatar" :src="item.avatar" alt="" />

                  <template v-else-if="item.kind === 'player'">{{ item.label.charAt(0).toUpperCase() }}</template>
                  <template v-else-if="item.kind === 'clan'">{{ item.prefix?.charAt(1) }}</template>

                  <svg v-else-if="item.kind === 'topic'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                  <svg v-else-if="item.kind === 'news'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 5h16v14H4z" /><path d="M8 9h8M8 13h5" stroke-linecap="round" />
                  </svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>

                <span class="palette-item__text">
                  <span class="palette-item__label">
                    <span
                        v-if="item.prefix"
                        class="palette-item__prefix"
                        :style="item.prefixColor ? { color: item.prefixColor } : {}"
                    >{{ item.prefix }}</span>
                    {{ item.label }}
                  </span>
                  <span v-if="item.sub" class="palette-item__sub">{{ item.sub }}</span>
                </span>

                <span
                    v-if="item.tier"
                    class="tier-block palette-item__tier"
                    :style="{ '--tier': tierColor(item.tier) }"
                >{{ item.tier }}</span>

                <span class="palette-item__enter" aria-hidden="true">↵</span>
              </button>
            </section>
          </div>

          <footer class="palette__footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> выбрать</span>
            <span><kbd>↵</kbd> открыть</span>
            <span><kbd>Esc</kbd> закрыть</span>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.palette-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;

  display: flex;
  justify-content: center;
  align-items: flex-start;

  padding: 12vh 16px 16px;

  background: rgba(5, 4, 8, 0.72);
}

.palette {
  width: min(640px, 100%);
  max-height: min(560px, 76vh);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  background: var(--bg-card);
  border: 1px solid var(--border-hover);
  border-radius: 8px;

  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.6);
}

/* ---------- Поле ---------- */

.palette__search {
  display: flex;
  align-items: center;
  gap: 12px;

  padding: 0 16px;

  border-bottom: 1px solid var(--border);
}

.palette__search-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;

  color: var(--text-muted);
}

.palette__input {
  flex: 1;
  min-width: 0;
  height: 56px;

  color: var(--text);
  background: transparent;
  border: 0;
  outline: none;

  font-size: 17px;
}

.palette__input::placeholder {
  color: var(--text-muted);
}

.palette__spinner {
  width: 16px;
  height: 16px;

  border: 2px solid var(--border-hover);
  border-top-color: var(--accent-light);
  border-radius: 50%;

  animation: palette-spin 0.7s linear infinite;
}

@keyframes palette-spin {
  to { transform: rotate(360deg); }
}

kbd {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 22px;
  height: 22px;
  padding: 0 6px;

  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border-hover);
  border-bottom-width: 2px;
  border-radius: 4px;

  font-family: var(--font-body);
  font-size: 11px;
  font-weight: 600;
}

.palette__esc {
  cursor: pointer;
}

/* ---------- Список ---------- */

.palette__list {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;

  padding: 8px;
}

.palette__group + .palette__group {
  margin-top: 6px;
}

.palette__group-title {
  margin: 0;
  padding: 8px 10px 6px;

  color: var(--text-muted);

  font-family: var(--font-body);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
}

.palette-item {
  width: 100%;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 8px 10px;

  color: var(--text);
  background: transparent;
  border-radius: 6px;

  text-align: left;
  cursor: pointer;
}

.palette-item--active {
  background: var(--accent-soft);
}

.palette-item__icon {
  width: 34px;
  height: 34px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;

  font-weight: 700;
}

.palette-item__icon img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.palette-item__icon svg {
  width: 17px;
  height: 17px;
}

.palette-item__icon--player {
  color: #fff;
  background: #2a2438;
}

.palette-item__icon--clan {
  color: #fff;
  border-color: transparent;
}

.palette-item--active .palette-item__icon--page {
  color: var(--accent-light);
}

.palette-item__text {
  flex: 1;
  min-width: 0;

  display: flex;
  flex-direction: column;
}

.palette-item__label {
  overflow: hidden;

  font-size: 15px;
  font-weight: 600;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.palette-item__prefix {
  margin-right: 4px;
  color: var(--accent-light);
}

.palette-item__sub {
  overflow: hidden;

  color: var(--text-muted);

  font-size: 13px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.palette-item__tier {
  width: 26px;
  height: 26px;
  font-size: 12px;
}

.palette-item__enter {
  width: 18px;

  color: var(--accent-light);

  font-size: 15px;
  text-align: center;

  opacity: 0;
}

.palette-item--active .palette-item__enter {
  opacity: 1;
}

.palette__empty {
  display: flex;
  flex-direction: column;
  gap: 4px;

  padding: 32px 16px;

  color: var(--text-muted);

  font-size: 14px;
  text-align: center;
}

.palette__empty strong {
  color: var(--text);
  font-size: 15px;
}

/* ---------- Подвал ---------- */

.palette__footer {
  display: flex;
  gap: 18px;

  padding: 10px 16px;

  color: var(--text-muted);
  border-top: 1px solid var(--border);

  font-size: 12px;
}

.palette__footer span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

/* ---------- Анимация ---------- */

.palette-enter-active,
.palette-leave-active {
  transition: opacity 0.14s ease;
}

.palette-enter-active .palette,
.palette-leave-active .palette {
  transition: transform 0.14s ease;
}

.palette-enter-from,
.palette-leave-to {
  opacity: 0;
}

.palette-enter-from .palette,
.palette-leave-to .palette {
  transform: translateY(-8px) scale(0.98);
}

@media (max-width: 600px) {
  .palette-backdrop {
    padding: 8px;
  }

  .palette {
    max-height: calc(100vh - 16px);
  }

  .palette__footer {
    display: none;
  }
}
</style>
