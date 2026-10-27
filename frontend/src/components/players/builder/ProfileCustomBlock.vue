<script setup>
/**
 * Свои блоки конструктора профиля: текст, цитата, факты, навыки, путь,
 * тиммейты, обратный отсчёт, видео, гостевая книга, пиксель-арт,
 * разделитель и пустое место. Заголовок блока рисует сетка.
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

/* ---------- Списки: факты, навыки, путь, тиммейты ---------- */

const items = computed(() => s.value.items || [])

function pad(n) {
  return String(n).padStart(2, '0')
}
</script>

<template>
  <!-- Текст -->
  <div v-if="block.type === 'text'" class="cb-text">
    <p v-if="s.body" class="cb-text__body" :class="`cb-text__body--${s.size || 'm'}`">{{ s.body }}</p>
    <p v-else-if="editing" class="cb-placeholder">Напиши что-нибудь в настройках блока ⚙</p>
  </div>

  <!-- Цитата -->
  <figure v-else-if="block.type === 'quote'" class="cb-quote">
    <span class="cb-quote__mark">“</span>
    <blockquote>{{ s.text || (editing ? 'Здесь будет твоя цитата' : '') }}</blockquote>
    <figcaption v-if="s.author">— {{ s.author }}</figcaption>
  </figure>

  <!-- Факты -->
  <dl v-else-if="block.type === 'facts'" class="cb-facts">
    <div v-for="(item, i) in items" :key="i" class="cb-facts__row">
      <dt>{{ item.label }}</dt>
      <dd>{{ item.value || '—' }}</dd>
    </div>
    <p v-if="!items.length && editing" class="cb-placeholder">Добавь строки в настройках ⚙</p>
  </dl>

  <!-- Навыки -->
  <div v-else-if="block.type === 'bars'" class="cb-bars">
    <div v-for="(item, i) in items" :key="i" class="cb-bars__row">
      <div class="cb-bars__head">
        <span>{{ item.label }}</span>
        <b>{{ item.value }}</b>
      </div>
      <div class="cb-bars__track">
        <i :style="{ width: `${item.value}%`, animationDelay: `${i * 90}ms` }"></i>
      </div>
    </div>
    <p v-if="!items.length && editing" class="cb-placeholder">Добавь навыки в настройках ⚙</p>
  </div>

  <!-- Мой путь -->
  <ol v-else-if="block.type === 'timeline'" class="cb-timeline">
    <li v-for="(item, i) in items" :key="i">
      <span v-if="item.when" class="cb-timeline__when">{{ item.when }}</span>
      <span class="cb-timeline__text">{{ item.text || (editing ? 'Что случилось?' : '') }}</span>
    </li>
  </ol>

  <!-- Тиммейты -->
  <div v-else-if="block.type === 'players'" class="cb-players">
    <RouterLink
        v-for="(item, i) in items.filter((p) => p.username)"
        :key="i"
        :to="`/user/${encodeURIComponent(item.username)}`"
        class="cb-players__item"
    >
      <i class="cb-players__avatar">{{ item.username.charAt(0).toUpperCase() }}</i>
      <span>
        <strong>{{ item.username }}</strong>
        <small v-if="item.note">{{ item.note }}</small>
      </span>
    </RouterLink>
    <p v-if="!items.length && editing" class="cb-placeholder">Добавь ники в настройках ⚙</p>
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
      :preview="editing"
  />

  <!-- Пиксель-арт -->
  <div v-else-if="block.type === 'sticker'" class="cb-sticker">
    <PixelSprite :sprite="s.sprite || 'creeper'" :size="88" anim="bounce" />
    <span v-if="s.caption" class="cb-sticker__caption">{{ s.caption }}</span>
  </div>

  <!-- Разделитель -->
  <div v-else-if="block.type === 'divider'" class="cb-divider" :class="`cb-divider--${s.style || 'line'}`"></div>

  <!-- Пустое место -->
  <div v-else-if="block.type === 'spacer'" class="cb-spacer" :class="`cb-spacer--${s.size || 'm'}`">
    <span v-if="editing">пустое место</span>
  </div>
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

.cb-text__body--s { font-size: 12px; }
.cb-text__body--l { font-size: 18px; }
.cb-text__body--xl {
  color: var(--text, #fff);
  font-family: var(--font-display);
  font-size: clamp(22px, 3vw, 32px);
  font-weight: 800;
  line-height: 1.25;
}

/* Факты */
.cb-facts {
  margin: 0;
  display: flex;
  flex-direction: column;
}

.cb-facts__row {
  display: flex;
  justify-content: space-between;
  gap: 12px;

  padding: 9px 0;

  border-bottom: 1px dashed rgba(255, 255, 255, 0.08);
}

.cb-facts__row:last-child {
  border-bottom: 0;
}

.cb-facts dt {
  color: var(--text-muted, #8a8598);
  font-size: 13px;
}

.cb-facts dd {
  margin: 0;
  color: var(--text, #fff);
  font-size: 13px;
  font-weight: 800;
  text-align: right;
  overflow-wrap: anywhere;
}

/* Навыки */
.cb-bars {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cb-bars__head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;

  color: var(--text-dim, #d4d0de);
  font-size: 13px;
  font-weight: 700;
}

.cb-bars__head b {
  color: var(--accent-color, #a78bfa);
  font-variant-numeric: tabular-nums;
}

.cb-bars__track {
  height: 8px;
  overflow: hidden;

  background: rgba(255, 255, 255, 0.06);
  border-radius: 99px;
}

.cb-bars__track i {
  display: block;
  height: 100%;

  background: linear-gradient(90deg, color-mix(in srgb, var(--accent-color, #8b5cf6) 55%, #000), var(--accent-color, #8b5cf6));
  border-radius: inherit;
  box-shadow: 0 0 12px color-mix(in srgb, var(--accent-color, #8b5cf6) 60%, transparent);

  transform-origin: left;
  animation: cb-bar-fill 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

@keyframes cb-bar-fill {
  from { transform: scaleX(0); }
}

/* Мой путь */
.cb-timeline {
  margin: 0;
  padding: 0 0 0 18px;

  list-style: none;
  border-left: 2px solid color-mix(in srgb, var(--accent-color, #8b5cf6) 45%, transparent);
}

.cb-timeline li {
  position: relative;

  display: flex;
  flex-direction: column;
  gap: 2px;

  padding: 0 0 14px;
}

.cb-timeline li::before {
  content: "";

  position: absolute;
  top: 4px;
  left: -25px;

  width: 12px;
  height: 12px;

  background: var(--accent-color, #8b5cf6);
  border: 3px solid var(--bg-card, #15131c);
  border-radius: 50%;
}

.cb-timeline__when {
  color: var(--accent-color, #a78bfa);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.cb-timeline__text {
  color: var(--text, #fff);
  font-size: 14px;
}

/* Тиммейты */
.cb-players {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cb-players__item {
  display: flex;
  align-items: center;
  gap: 10px;

  padding: 6px;

  color: inherit;
  border-radius: 10px;
  text-decoration: none;

  transition: background 0.15s ease;
}

.cb-players__item:hover {
  background: rgba(255, 255, 255, 0.05);
}

.cb-players__avatar {
  width: 34px;
  height: 34px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  color: #fff;
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 40%, #1f1b2b);
  border-radius: 9px;

  font-style: normal;
  font-weight: 900;
}

.cb-players__item span {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.cb-players__item strong {
  color: var(--text, #fff);
  font-size: 14px;
}

.cb-players__item small {
  color: var(--text-muted, #8a8598);
  font-size: 11px;
}

/* Пустое место */
.cb-spacer {
  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-muted, #8a8598);
  font-size: 11px;
}

.cb-spacer--s { height: 12px; }
.cb-spacer--m { height: 40px; }
.cb-spacer--l { height: 96px; }

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

.cb-divider--zigzag {
  height: 10px;
  background:
      linear-gradient(135deg, var(--accent-color, #8b5cf6) 25%, transparent 25%) -5px 0 / 10px 10px repeat-x,
      linear-gradient(225deg, var(--accent-color, #8b5cf6) 25%, transparent 25%) -5px 0 / 10px 10px repeat-x;
  opacity: 0.7;
}

.cb-divider--blocks {
  height: 8px;
  background: repeating-linear-gradient(90deg, var(--accent-color, #8b5cf6) 0 8px, transparent 8px 12px);
  opacity: 0.6;
}

@media (prefers-reduced-motion: reduce) {
  .cb-bars__track i {
    animation: none;
  }
}
</style>
