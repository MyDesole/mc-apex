<script setup>
/**
 * Новые блоки конструктора профиля: текст, цитата, ссылки, статус,
 * обратный отсчёт, видео, гостевая книга, пиксель-арт, клан, разделитель.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import GuestbookBlock from '@/components/players/builder/GuestbookBlock.vue'
import { youtubeId } from '@/data/players/profileBlocks.js'

const props = defineProps({
  block: { type: Object, required: true },
  user: { type: Object, required: true },
  editing: { type: Boolean, default: false },
})

const s = computed(() => props.block.settings || {})

/* ---------- Обратный отсчёт ---------- */

const now = ref(Date.now())
let clock = null

const countdown = computed(() => {
  const target = new Date(s.value.date).getTime()
  if (!target) return null

  const left = Math.max(0, target - now.value)

  return {
    done: left === 0,
    days: Math.floor(left / 86400000),
    hours: Math.floor((left % 86400000) / 3600000),
    minutes: Math.floor((left % 3600000) / 60000),
    seconds: Math.floor((left % 60000) / 1000),
  }
})

onMounted(() => {
  if (props.block.type === 'countdown') {
    clock = setInterval(() => { now.value = Date.now() }, 1000)
  }
})

onUnmounted(() => clearInterval(clock))

/* ---------- Видео ---------- */

const videoId = computed(() => youtubeId(s.value.url))

/* ---------- Клан ---------- */

const clan = computed(() => props.user.clan_member?.clan ?? null)

function pad(n) {
  return String(n).padStart(2, '0')
}
</script>

<template>
  <!-- Текст -->
  <div v-if="block.type === 'text'" class="cb-text">
    <h3 v-if="s.title" class="block-title">{{ s.title }}</h3>
    <p v-if="s.body" class="cb-text__body">{{ s.body }}</p>
    <p v-else-if="editing" class="cb-placeholder">Напиши что-нибудь в настройках блока ⚙</p>
  </div>

  <!-- Цитата -->
  <figure v-else-if="block.type === 'quote'" class="cb-quote">
    <span class="cb-quote__mark">“</span>
    <blockquote>{{ s.text || (editing ? 'Здесь будет твоя цитата' : '') }}</blockquote>
    <figcaption v-if="s.author">— {{ s.author }}</figcaption>
  </figure>

  <!-- Кнопки-ссылки -->
  <div v-else-if="block.type === 'links'" class="cb-links">
    <a
        v-for="(item, i) in s.items || []"
        :key="i"
        :href="item.url"
        class="cb-links__item"
        target="_blank"
        rel="noopener nofollow ugc"
    >
      {{ item.label }}
      <span>↗</span>
    </a>
    <p v-if="!(s.items || []).length && editing" class="cb-placeholder">Добавь кнопки в настройках ⚙</p>
  </div>

  <!-- Сейчас -->
  <div v-else-if="block.type === 'now'" class="cb-now">
    <span class="cb-now__label"><i></i>Сейчас</span>
    <div class="cb-now__main">
      <span class="cb-now__emoji">{{ s.emoji || '🎮' }}</span>
      <strong>{{ s.text || (editing ? 'Чем ты сейчас занят?' : '—') }}</strong>
    </div>
  </div>

  <!-- Обратный отсчёт -->
  <div v-else-if="block.type === 'countdown'" class="cb-countdown">
    <span class="cb-countdown__title">{{ s.title || 'Обратный отсчёт' }}</span>
    <div v-if="countdown && !countdown.done" class="cb-countdown__digits">
      <span><b>{{ countdown.days }}</b>дн</span>
      <span><b>{{ pad(countdown.hours) }}</b>ч</span>
      <span><b>{{ pad(countdown.minutes) }}</b>мин</span>
      <span><b>{{ pad(countdown.seconds) }}</b>сек</span>
    </div>
    <strong v-else-if="countdown?.done" class="cb-countdown__done">Началось! 🎉</strong>
    <p v-else class="cb-placeholder">Выбери дату в настройках ⚙</p>
  </div>

  <!-- Видео -->
  <div v-else-if="block.type === 'video'" class="cb-video">
    <iframe
        v-if="videoId && !editing"
        :src="`https://www.youtube-nocookie.com/embed/${videoId}`"
        title="Видео из профиля"
        loading="lazy"
        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
    ></iframe>
    <div v-else class="cb-video__stub">
      <span>▶</span>
      {{ videoId ? 'Видео с YouTube' : 'Вставь ссылку на YouTube в настройках ⚙' }}
    </div>
  </div>

  <!-- Гостевая книга -->
  <GuestbookBlock
      v-else-if="block.type === 'guestbook'"
      :owner="user"
      :title="s.title"
      :preview="editing"
  />

  <!-- Пиксель-арт -->
  <div v-else-if="block.type === 'sticker'" class="cb-sticker">
    <PixelSprite :sprite="s.sprite || 'creeper'" :size="88" anim="bounce" />
    <span v-if="s.caption" class="cb-sticker__caption">{{ s.caption }}</span>
  </div>

  <!-- Клан -->
  <RouterLink v-else-if="block.type === 'clan' && clan" :to="`/clans/${clan.id}`" class="cb-clan">
    <span class="cb-clan__avatar" :style="{ background: clan.banner_color || '#7c3aed' }">
      <img v-if="clan.avatar_url" :src="clan.avatar_url" :alt="clan.name" />
      <template v-else>{{ clan.tag?.charAt(0) }}</template>
    </span>
    <span class="cb-clan__info">
      <small>Клан</small>
      <strong>[{{ clan.tag }}] {{ clan.name }}</strong>
    </span>
    <span class="cb-clan__arrow">→</span>
  </RouterLink>
  <div v-else-if="block.type === 'clan'" class="cb-placeholder">Ты пока не в клане</div>

  <!-- Разделитель -->
  <div v-else-if="block.type === 'divider'" class="cb-divider" :class="`cb-divider--${s.style || 'line'}`"></div>
</template>

<style scoped>
.cb-placeholder {
  margin: 0;
  color: var(--text-muted, #8a8598);
  font-size: 13px;
}

/* Текст */
.cb-text__body {
  margin: 0;
  color: var(--text-dim, #d4d0de);
  font-size: 14px;
  line-height: 1.6;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

/* Цитата */
.cb-quote {
  position: relative;
  margin: 0;
  padding: 6px 8px 4px 42px;
}

.cb-quote__mark {
  position: absolute;
  top: -14px;
  left: 0;

  color: var(--accent-color, #8b5cf6);

  font-family: Georgia, serif;
  font-size: 72px;
  line-height: 1;
}

.cb-quote blockquote {
  margin: 0;

  color: var(--text, #fff);

  font-family: var(--font-display);
  font-size: clamp(17px, 2.2vw, 22px);
  font-weight: 700;
  line-height: 1.35;
}

.cb-quote figcaption {
  margin-top: 8px;
  color: var(--text-muted, #8a8598);
  font-size: 13px;
}

/* Ссылки */
.cb-links {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cb-links__item {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 11px 14px;

  color: var(--text, #fff);
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 14%, rgba(255, 255, 255, 0.03));
  border: 1px solid color-mix(in srgb, var(--accent-color, #8b5cf6) 40%, transparent);
  border-radius: 12px;

  font-weight: 800;
  text-decoration: none;

  transition: transform 0.15s ease, background 0.15s ease;
}

.cb-links__item:hover {
  transform: translateX(3px);
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 24%, transparent);
}

/* Сейчас */
.cb-now__label {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #4ade80;

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.cb-now__label i {
  width: 8px;
  height: 8px;

  background: #4ade80;
  border-radius: 50%;
  box-shadow: 0 0 8px #4ade80;

  animation: cb-live 1.6s ease-in-out infinite;
}

@keyframes cb-live {
  50% { opacity: 0.35; }
}

.cb-now__main {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 10px;
}

.cb-now__emoji {
  font-size: 32px;
  line-height: 1;
}

.cb-now__main strong {
  color: var(--text, #fff);
  font-size: 15px;
  line-height: 1.35;
}

/* Обратный отсчёт */
.cb-countdown__title {
  display: block;
  margin-bottom: 10px;

  color: var(--text-muted, #8a8598);

  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.cb-countdown__digits {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

.cb-countdown__digits span {
  display: flex;
  flex-direction: column;
  align-items: center;

  padding: 8px 4px;

  color: var(--text-muted, #8a8598);
  background: rgba(0, 0, 0, 0.25);
  border-radius: 10px;

  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.cb-countdown__digits b {
  color: var(--text, #fff);

  font-family: var(--font-display);
  font-size: 22px;
  font-variant-numeric: tabular-nums;
}

.cb-countdown__done {
  color: var(--accent-color, #a78bfa);
  font-size: 20px;
}

/* Видео */
.cb-video {
  position: relative;
  overflow: hidden;

  aspect-ratio: 16 / 9;

  background: #000;
  border-radius: 12px;
}

.cb-video iframe {
  width: 100%;
  height: 100%;
  border: 0;
}

.cb-video__stub {
  position: absolute;
  inset: 0;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;

  color: var(--text-muted, #8a8598);
  font-size: 13px;
}

.cb-video__stub span {
  width: 56px;
  height: 40px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: #ef4444;
  border-radius: 12px;

  font-size: 18px;
}

/* Пиксель-арт */
.cb-sticker {
  min-height: 150px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.cb-sticker :deep(.pixel-sprite) {
  filter: drop-shadow(0 10px 16px rgba(0, 0, 0, 0.45));
}

.cb-sticker__caption {
  color: var(--text, #fff);
  font-family: var(--font-display);
  font-size: 16px;
  font-weight: 800;
  text-align: center;
}

/* Клан */
.cb-clan {
  display: flex;
  align-items: center;
  gap: 12px;

  color: inherit;
  text-decoration: none;
}

.cb-clan__avatar {
  width: 48px;
  height: 48px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;

  color: #fff;
  border-radius: 12px;

  font-size: 20px;
  font-weight: 900;
}

.cb-clan__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cb-clan__info {
  min-width: 0;
  flex: 1;

  display: flex;
  flex-direction: column;
}

.cb-clan__info small {
  color: var(--text-muted, #8a8598);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.cb-clan__info strong {
  overflow: hidden;
  color: var(--text, #fff);
  font-size: 15px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cb-clan__arrow {
  color: var(--accent-color, #a78bfa);
  font-weight: 900;
}

/* Разделитель */
.cb-divider {
  height: 2px;
  margin: 4px 0;
}

.cb-divider--line {
  background: linear-gradient(90deg, transparent, var(--border, rgba(255, 255, 255, 0.15)), transparent);
}

.cb-divider--dots {
  height: 6px;
  background: radial-gradient(circle, var(--accent-color, #8b5cf6) 2px, transparent 2.5px) 0 0 / 16px 6px repeat-x;
  opacity: 0.7;
}

.cb-divider--glow {
  background: var(--accent-color, #8b5cf6);
  box-shadow: 0 0 12px var(--accent-color, #8b5cf6), 0 0 28px var(--accent-color, #8b5cf6);
  border-radius: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .cb-now__label i {
    animation: none;
  }
}
</style>
