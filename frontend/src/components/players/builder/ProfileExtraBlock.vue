<script setup>
/**
 * Новые свои блоки профиля: сетап, кнопки-ссылки, «Сейчас слушаю»,
 * вопрос-ответ, бегущая строка, расписание, баннер с призывом и прогресс
 * до цели. Данные уже очищены сервером (ProfileBlocksService).
 */
import { computed, ref } from 'vue'
import { youtubeId } from '@/data/players/profileBlocks.js'

const props = defineProps({
  block: { type: Object, required: true },
  editing: { type: Boolean, default: false },
})

const s = computed(() => props.block.settings || {})
const items = computed(() => s.value.items || [])

/* ---------- Иконки ---------- */

const ICONS = {
  mouse: 'M12 3a6 6 0 0 0-6 6v6a6 6 0 0 0 12 0V9a6 6 0 0 0-6-6z M12 7v4',
  keyboard: 'M2 6h20v12H2z M6 10h.01 M10 10h.01 M14 10h.01 M18 10h.01 M7 14h10',
  headset: 'M4 15v-3a8 8 0 0 1 16 0v3 M4 15h3v5H4z M17 15h3v5h-3z',
  monitor: 'M3 4h18v12H3z M8 20h8 M12 16v4',
  mousepad: 'M3 6h18v12H3z M14 10a2 2 0 0 1 4 0v2a2 2 0 0 1-4 0z',
  pc: 'M6 2h12v20H6z M9 6h6 M9 10h6 M12 17h.01',
  dpi: 'M12 3v4 M12 17v4 M3 12h4 M17 12h4 M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
  sens: 'M4 18a8 8 0 1 1 16 0 M12 18l4-6',
  fps: 'M13 2L4 14h7l-1 8 9-12h-7z',
  other: 'M12 5v14 M5 12h14',
  link: 'M10 14a4 4 0 0 0 6 0l3-3a4 4 0 0 0-6-6l-1 1 M14 10a4 4 0 0 0-6 0l-3 3a4 4 0 0 0 6 6l1-1',
  discord: 'M8 7c-2 .4-3.3 1-4 1.6C2.5 12 2 15 2.2 18c1.6 1.3 3.3 2 5 2.3l1-1.8 M16 7c2 .4 3.3 1 4 1.6 1.5 3.4 2 6.4 1.8 9.4-1.6 1.3-3.3 2-5 2.3l-1-1.8 M7 17c3 1.4 7 1.4 10 0 M9 13h.01 M15 13h.01',
  youtube: 'M2.5 8a3 3 0 0 1 2.6-2.6C8 5 16 5 18.9 5.4A3 3 0 0 1 21.5 8c.3 2.6.3 5.4 0 8a3 3 0 0 1-2.6 2.6c-2.9.4-10.9.4-13.8 0A3 3 0 0 1 2.5 16c-.3-2.6-.3-5.4 0-8z M10 9l5 3-5 3z',
  twitch: 'M4 3h16v11l-5 5h-4l-3 3v-3H4z M11 8v4 M16 8v4',
  telegram: 'M21 4L3 11l6 2 2 6 3-4 5 4z M9 13l8-6',
  tiktok: 'M14 3v12a4 4 0 1 1-4-4 M14 3c.5 2.5 2.5 4.5 5 5',
  vk: 'M3 7h3c1 4 3 6 5 7V7h3v4c1.5-.3 3-2 4-4h3c-.7 2.6-2.6 4.6-4 5.5 1.5.8 3.3 2.6 4 4.5h-3.3c-.8-1.7-2.2-3-3.7-3.3V17h-1C7 17 4 13 3 7z',
  star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
  heart: 'M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z',
  game: 'M6 9h12a4 4 0 0 1 4 4v1a3 3 0 0 1-5.4 1.8L15 14H9l-1.6 1.8A3 3 0 0 1 2 14v-1a4 4 0 0 1 4-4z M7 11v3 M5.5 12.5h3 M16 12h.01 M18 13.5h.01',
  music: 'M9 18V5l12-2v13 M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0z M21 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
}

function paths(name) {
  return (ICONS[name] ?? ICONS.other).split(/\s(?=M)/)
}

const SETUP_LABELS = {
  mouse: 'Мышь', keyboard: 'Клавиатура', headset: 'Наушники', monitor: 'Монитор', mousepad: 'Коврик',
  pc: 'ПК', dpi: 'DPI', sens: 'Сенса', fps: 'FPS', other: 'Ещё',
}

/* ---------- Музыка ---------- */

const music = computed(() => {
  const url = s.value.url || ''
  const spotify = url.match(/open\.spotify\.com\/(track|album|playlist|episode)\/([A-Za-z0-9]+)/)
  if (spotify) return { kind: 'spotify', embed: `https://open.spotify.com/embed/${spotify[1]}/${spotify[2]}?theme=0` }
  const yt = youtubeId(url)
  if (yt) return { kind: 'youtube', embed: `https://www.youtube-nocookie.com/embed/${yt}` }
  return url ? { kind: 'link' } : null
})

const musicService = computed(() => {
  const url = s.value.url || ''
  if (url.includes('yandex')) return 'Яндекс Музыка'
  if (url.includes('soundcloud')) return 'SoundCloud'
  if (url.includes('vk.com')) return 'VK Музыка'
  return 'Слушать'
})

/* ---------- Вопрос-ответ ---------- */

const open = ref(0)

/* ---------- Расписание ---------- */

const DAYS = [['mon', 'Пн'], ['tue', 'Вт'], ['wed', 'Ср'], ['thu', 'Чт'], ['fri', 'Пт'], ['sat', 'Сб'], ['sun', 'Вс']]
const todayKey = DAYS[(new Date().getDay() + 6) % 7][0]

const scheduleDays = computed(() => DAYS
    .map(([id, label]) => ({ id, label, items: items.value.filter((i) => i.day === id) }))
    .filter((d) => d.items.length))

/* ---------- Прогресс ---------- */

const progress = computed(() => {
  const target = Math.max(1, Number(s.value.target) || 1)
  const current = Math.max(0, Number(s.value.current) || 0)
  return { current, target, percent: Math.min(100, Math.round((current / target) * 100)) }
})

function fmt(n) {
  return Number(n).toLocaleString('ru-RU')
}
</script>

<template>
  <!-- Сетап -->
  <ul v-if="block.type === 'setup'" class="xb-setup">
    <li v-for="(item, i) in items" :key="i">
      <span class="xb-setup__icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path v-for="(d, n) in paths(item.kind)" :key="n" :d="d" />
        </svg>
      </span>
      <small>{{ SETUP_LABELS[item.kind] }}</small>
      <strong>{{ item.value }}</strong>
    </li>
    <li v-if="!items.length && editing" class="xb-empty">Добавь девайсы в настройках блока</li>
  </ul>

  <!-- Кнопки-ссылки -->
  <div v-else-if="block.type === 'links'" class="xb-links" :class="`xb-links--${s.style || 'solid'}`">
    <a
        v-for="(item, i) in items"
        :key="i"
        :href="item.url"
        target="_blank"
        rel="noopener noreferrer nofollow"
        :style="item.color ? { '--link-color': item.color } : null"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path v-for="(d, n) in paths(item.icon)" :key="n" :d="d" />
      </svg>
      <span>{{ item.label || item.url.replace(/^https?:\/\//, '') }}</span>
    </a>
    <p v-if="!items.length && editing" class="xb-empty">Добавь ссылки в настройках блока</p>
  </div>

  <!-- Сейчас слушаю -->
  <div v-else-if="block.type === 'music'" class="xb-music">
    <div class="xb-music__head">
      <span class="xb-music__disc">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path v-for="(d, n) in paths('music')" :key="n" :d="d" />
        </svg>
      </span>
      <div>
        <small>Сейчас слушаю</small>
        <strong>{{ s.title || 'Без названия' }}</strong>
        <span v-if="s.artist">{{ s.artist }}</span>
      </div>
      <span class="xb-music__eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
    </div>
    <iframe
        v-if="music && music.kind !== 'link' && !editing"
        class="xb-music__embed"
        :class="`xb-music__embed--${music.kind}`"
        :src="music.embed"
        loading="lazy"
        allow="encrypted-media"
        title="Трек"
    ></iframe>
    <a v-else-if="music?.kind === 'link'" class="xb-music__open" :href="s.url" target="_blank" rel="noopener noreferrer nofollow">{{ musicService }} →</a>
    <p v-else-if="editing && !music" class="xb-empty">Вставь ссылку на трек: Spotify, YouTube, Яндекс Музыка…</p>
  </div>

  <!-- Вопрос — ответ -->
  <div v-else-if="block.type === 'faq'" class="xb-faq">
    <div v-for="(item, i) in items" :key="i" class="xb-faq__item" :class="{ 'is-open': open === i }">
      <button type="button" @click="open = open === i ? -1 : i">
        <span>{{ item.q }}</span>
        <i aria-hidden="true"></i>
      </button>
      <p v-show="open === i">{{ item.a }}</p>
    </div>
    <p v-if="!items.length && editing" class="xb-empty">Добавь вопросы в настройках блока</p>
  </div>

  <!-- Бегущая строка -->
  <div
      v-else-if="block.type === 'marquee'"
      class="xb-marquee"
      :class="[`xb-marquee--${s.speed || 'normal'}`, `xb-marquee--${s.direction || 'left'}`, `xb-marquee--${s.size || 'm'}`]"
  >
    <div class="xb-marquee__track">
      <span v-for="n in 4" :key="n">{{ s.text || 'Бегущая строка' }}<b aria-hidden="true">✦</b></span>
    </div>
  </div>

  <!-- Расписание -->
  <div v-else-if="block.type === 'schedule'" class="xb-schedule">
    <div v-for="day in scheduleDays" :key="day.id" class="xb-schedule__day" :class="{ 'is-today': day.id === todayKey }">
      <b>{{ day.label }}</b>
      <ul>
        <li v-for="(item, i) in day.items" :key="i">
          <time v-if="item.time">{{ item.time }}</time>
          <span>{{ item.text }}</span>
        </li>
      </ul>
    </div>
    <p v-if="!scheduleDays.length && editing" class="xb-empty">Добавь дни в настройках блока</p>
  </div>

  <!-- Баннер с призывом -->
  <div v-else-if="block.type === 'banner'" class="xb-banner">
    <div>
      <strong>{{ s.title || 'Заголовок баннера' }}</strong>
      <p v-if="s.text">{{ s.text }}</p>
    </div>
    <a
        v-if="s.button && s.url"
        class="xb-banner__btn"
        :href="s.url"
        target="_blank"
        rel="noopener noreferrer nofollow"
    >{{ s.button }} →</a>
    <span v-else-if="s.button" class="xb-banner__btn">{{ s.button }}</span>
  </div>

  <!-- Прогресс до цели -->
  <div v-else-if="block.type === 'progress'" class="xb-progress">
    <div class="xb-progress__head">
      <strong>{{ s.title || 'Моя цель' }}</strong>
      <b>{{ progress.percent }}%</b>
    </div>
    <div class="xb-progress__track"><i :style="{ width: `${progress.percent}%` }"></i></div>
    <small>{{ fmt(progress.current) }} / {{ fmt(progress.target) }} {{ s.unit }}</small>
  </div>
</template>

<style scoped>
.xb-empty {
  margin: 0;
  padding: 18px;
  color: var(--text-muted, #8a8598);
  border: 1px dashed rgba(255, 255, 255, 0.14);
  border-radius: 10px;
  font-size: 13px;
  text-align: center;
  list-style: none;
}

/* ---------- Сетап ---------- */

.xb-setup {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.xb-setup li:not(.xb-empty) {
  display: grid;
  grid-template-columns: 34px 1fr;
  grid-template-areas: "icon label" "icon value";
  align-items: center;
  column-gap: 10px;
  padding: 10px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
}

.xb-setup__icon {
  grid-area: icon;
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  color: color-mix(in srgb, var(--accent-color, #8b5cf6) 70%, #fff);
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 18%, transparent);
  border-radius: 9px;
}

.xb-setup small { grid-area: label; color: var(--text-muted, #8a8598); font-size: 11px; }

.xb-setup strong {
  grid-area: value;
  overflow: hidden;
  font-size: 13.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---------- Ссылки ---------- */

.xb-links {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.xb-links a {
  --link-color: var(--accent-color, #8b5cf6);

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;

  min-height: 44px;
  padding: 0 16px;

  color: #fff;
  border-radius: 12px;

  font-weight: 800;
  text-decoration: none;

  transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
}

.xb-links a:hover { transform: translateY(-2px); filter: brightness(1.1); }

.xb-links--solid a {
  background: var(--link-color);
  box-shadow: 0 8px 20px color-mix(in srgb, var(--link-color) 35%, transparent);
}

.xb-links--outline a {
  color: color-mix(in srgb, var(--link-color) 60%, #fff);
  border: 2px solid var(--link-color);
}

.xb-links--glass a {
  background: color-mix(in srgb, var(--link-color) 18%, rgba(255, 255, 255, 0.04));
  border: 1px solid color-mix(in srgb, var(--link-color) 40%, transparent);
  backdrop-filter: blur(8px);
}

/* ---------- Музыка ---------- */

.xb-music { display: flex; flex-direction: column; gap: 12px; }

.xb-music__head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.xb-music__head > div { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.xb-music__head small { color: var(--text-muted, #8a8598); font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; }
.xb-music__head strong { font-size: 15px; }
.xb-music__head span { color: var(--text-dim, #c4c0d0); font-size: 13px; }

.xb-music__disc {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: #fff;
  background: conic-gradient(from 0deg, var(--accent-color, #8b5cf6), #111 40%, var(--accent-color, #8b5cf6) 70%, #111);
  border-radius: 50%;
  animation: xb-spin 6s linear infinite;
}

.xb-music__eq { display: flex; align-items: flex-end; gap: 3px; height: 18px; }

.xb-music__eq i {
  width: 3px;
  background: var(--accent-color, #8b5cf6);
  border-radius: 2px;
  animation: xb-eq 0.9s ease-in-out infinite alternate;
}

.xb-music__eq i:nth-child(2) { animation-delay: 0.2s; }
.xb-music__eq i:nth-child(3) { animation-delay: 0.4s; }
.xb-music__eq i:nth-child(4) { animation-delay: 0.1s; }

.xb-music__embed { width: 100%; border: 0; border-radius: 12px; }
.xb-music__embed--spotify { height: 152px; }
.xb-music__embed--youtube { aspect-ratio: 16 / 9; }

.xb-music__open {
  align-self: flex-start;
  padding: 8px 14px;
  color: #fff;
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 30%, transparent);
  border-radius: 999px;
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
}

@keyframes xb-spin { to { transform: rotate(360deg); } }
@keyframes xb-eq { from { height: 4px; } to { height: 18px; } }

/* ---------- Вопрос-ответ ---------- */

.xb-faq { display: flex; flex-direction: column; gap: 6px; }

.xb-faq__item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
}

.xb-faq__item.is-open { border-color: color-mix(in srgb, var(--accent-color, #8b5cf6) 45%, transparent); }

.xb-faq button {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  color: inherit;
  background: none;
  border: 0;
  font: inherit;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.xb-faq button i {
  width: 10px;
  height: 10px;
  flex-shrink: 0;
  border-right: 2px solid currentColor;
  border-bottom: 2px solid currentColor;
  transform: rotate(45deg) translateY(-3px);
  transition: transform 0.2s ease;
}

.xb-faq__item.is-open button i { transform: rotate(-135deg); }

.xb-faq p {
  margin: 0;
  padding: 0 14px 14px;
  color: var(--text-dim, #c4c0d0);
  line-height: 1.5;
  white-space: pre-line;
}

/* ---------- Бегущая строка ---------- */

.xb-marquee {
  overflow: hidden;
  mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
}

.xb-marquee__track {
  display: flex;
  width: max-content;
  animation: xb-marquee 18s linear infinite;
}

.xb-marquee__track span {
  display: inline-flex;
  align-items: center;
  gap: 24px;
  padding-right: 24px;
  font-weight: 900;
  white-space: nowrap;
}

.xb-marquee__track b {
  color: var(--accent-color, #8b5cf6);
  font-variant-emoji: text;
}

.xb-marquee--m .xb-marquee__track span { font-size: 18px; }
.xb-marquee--l .xb-marquee__track span { font-size: 28px; }
.xb-marquee--xl .xb-marquee__track span { font-size: 44px; letter-spacing: -0.02em; }

.xb-marquee--slow .xb-marquee__track { animation-duration: 32s; }
.xb-marquee--fast .xb-marquee__track { animation-duration: 9s; }
.xb-marquee--right .xb-marquee__track { animation-direction: reverse; }

@keyframes xb-marquee { to { transform: translateX(-50%); } }

/* ---------- Расписание ---------- */

.xb-schedule { display: flex; flex-direction: column; gap: 6px; }

.xb-schedule__day {
  display: grid;
  grid-template-columns: 38px 1fr;
  align-items: start;
  gap: 10px;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 10px;
}

.xb-schedule__day.is-today {
  background: color-mix(in srgb, var(--accent-color, #8b5cf6) 16%, transparent);
  box-shadow: inset 3px 0 0 var(--accent-color, #8b5cf6);
}

.xb-schedule__day b {
  color: color-mix(in srgb, var(--accent-color, #8b5cf6) 60%, #fff);
  font-size: 13px;
  text-transform: uppercase;
}

.xb-schedule ul { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 4px; }
.xb-schedule li { display: flex; gap: 10px; font-size: 13.5px; }
.xb-schedule time { color: var(--text-muted, #8a8598); font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; }

/* ---------- Баннер ---------- */

.xb-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding: 6px 4px;
}

.xb-banner strong { display: block; font-size: 22px; font-weight: 900; line-height: 1.15; }
.xb-banner p { margin: 6px 0 0; color: var(--text-dim, #c4c0d0); }

.xb-banner__btn {
  padding: 12px 20px;
  color: #fff;
  background: var(--accent-color, #8b5cf6);
  border-radius: 12px;
  box-shadow: 0 10px 24px color-mix(in srgb, var(--accent-color, #8b5cf6) 40%, transparent);
  font-weight: 900;
  text-decoration: none;
  white-space: nowrap;
  transition: transform 0.15s ease;
}

.xb-banner__btn:hover { transform: translateY(-2px); }

/* ---------- Прогресс ---------- */

.xb-progress { display: flex; flex-direction: column; gap: 10px; }

.xb-progress__head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.xb-progress__head strong { font-size: 15px; }
.xb-progress__head b { color: color-mix(in srgb, var(--accent-color, #8b5cf6) 60%, #fff); font-size: 26px; font-weight: 900; }

.xb-progress__track {
  height: 12px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.07);
  border-radius: 999px;
}

.xb-progress__track i {
  display: block;
  height: 100%;
  background:
      linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent) 0 0 / 40% 100% no-repeat,
      linear-gradient(90deg, color-mix(in srgb, var(--accent-color, #8b5cf6) 60%, #000), var(--accent-color, #8b5cf6));
  border-radius: inherit;
  animation: xb-shine 2.4s ease-in-out infinite;
  transition: width 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.xb-progress small { color: var(--text-muted, #8a8598); font-size: 12px; }

@keyframes xb-shine {
  from { background-position: -40% 0, 0 0; }
  to { background-position: 140% 0, 0 0; }
}

@media (prefers-reduced-motion: reduce) {
  .xb-music__disc, .xb-music__eq i, .xb-marquee__track, .xb-progress__track i { animation: none; }
}
</style>
