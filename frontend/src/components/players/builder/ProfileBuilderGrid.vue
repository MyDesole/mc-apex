<script setup>
/**
 * Сетка профиля из блоков и её редактор.
 *
 * Показ: блоки по порядку на сетке из 12 колонок; ширина блока — от
 * четверти до всей строки; на телефоне все во всю ширину. У каждого блока
 * своё оформление (фон, цвет, узор, высота, углы, шрифт, заголовок), у
 * страницы — отступы, углы, фон под блоками и анимация появления.
 *
 * Редактор (editing) — полноэкранная студия: сверху отмена/повтор,
 * компьютер/телефон и «Опубликовать»; слева рейка разделов (блоки, слои,
 * шаблоны, тема, текст, сетка, анимация); по центру живой профиль-холст,
 * где блок выбирается кликом; справа инспектор выбранного блока.
 * Сохраняет родитель (emit save { blocks, page }).
 *
 * Содержимое частей профиля (статистика, друзья, клан…) рисует родитель
 * через слот #builtin — их данные живут в PlayerCard и на странице.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import ProfileCustomBlock from '@/components/players/builder/ProfileCustomBlock.vue'
import ProfileBlockSettings from '@/components/players/builder/ProfileBlockSettings.vue'
import ProfileStudioPanel from '@/components/players/builder/ProfileStudioPanel.vue'
import StudioIcon from '@/components/players/builder/StudioIcon.vue'
import {
  BLOCK_TYPES, WIDTHS, COLORS,
  blockTitle, defaultBlocks, defaultPage, makeBlock, widthSpan, withDefaults,
} from '@/data/players/profileBlocks.js'

const props = defineProps({
  blocks: { type: Array, default: null },
  page: { type: Object, default: null },
  user: { type: Object, required: true },
  editing: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  // Свой профиль: подсказываем, если блоков не осталось
  owner: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'cancel', 'reset', 'preview-page'])

const draft = ref([])
const pageDraft = ref(defaultPage())
const openSettings = ref(null)
const studioTab = ref('add') // раздел левой панели; null — панель свёрнута
const device = ref('desktop') // 'desktop' | 'phone' — как смотреть холст
const replaying = ref(false)
const dragId = ref(null)

const STUDIO_TABS = [
  ['add', 'plus', 'Блоки'],
  ['header', 'header', 'Шапка'],
  ['layers', 'layers', 'Слои'],
  ['templates', 'template', 'Шаблоны'],
  ['theme', 'palette', 'Тема'],
  ['type', 'type', 'Текст'],
  ['layout', 'grid', 'Сетка'],
  ['motion', 'sparkle', 'Эффекты'],
]

// Шапку рисует родитель вне сетки: клик по ней в студии открывает её раздел
function onCanvasClick(event) {
  if (!live.value) return
  if (event.target.closest?.('.player-card--builder-head .profile-header')) {
    event.preventDefault()
    event.stopPropagation()
    openSettings.value = null
    studioTab.value = 'header'
  }
}

function toggleTab(id) {
  studioTab.value = studioTab.value === id ? null : id
}
const overId = ref(null)

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

// Начинаем редактирование с сохранённой раскладки (даже пустой) или с «как сейчас»
watch(
    () => props.editing,
    (on) => {
      if (on) {
        draft.value = (Array.isArray(props.blocks) ? clone(props.blocks) : defaultBlocks()).map(withDefaults)
        pageDraft.value = { ...defaultPage(), ...(props.page ?? {}) }
        openSettings.value = null
        // История объявлена ниже: при первом запуске её ещё нет
        nextTick(resetHistory)
      }
    },
    { immediate: true },
)

/* ---------- Отмена и повтор ----------
 * Снимок черновика (блоки + страница) после каждой паузы в правках.
 * Ползунок тянут — снимок один, когда отпустили.
 */

const past = ref([])
const future = ref([])
let lastSnapshot = ''
let restoring = false
let historyTimer = null

function snapshot() {
  return JSON.stringify({ blocks: draft.value, page: pageDraft.value })
}

function resetHistory() {
  past.value = []
  future.value = []
  lastSnapshot = snapshot()
}

watch([draft, pageDraft], () => {
  if (restoring || !props.editing) return
  clearTimeout(historyTimer)
  historyTimer = setTimeout(() => {
    const now = snapshot()
    if (now === lastSnapshot) return
    past.value.push(lastSnapshot)
    if (past.value.length > 80) past.value.shift()
    future.value = []
    lastSnapshot = now
  }, 350)
}, { deep: true })

function restore(state) {
  restoring = true
  const { blocks, page } = JSON.parse(state)
  draft.value = blocks
  pageDraft.value = page
  lastSnapshot = state
  if (!blocks.some((b) => b.id === openSettings.value)) openSettings.value = null
  nextTick(() => { restoring = false })
}

function undo() {
  clearTimeout(historyTimer)
  if (!past.value.length) return
  future.value.push(snapshot())
  restore(past.value.pop())
}

function redo() {
  if (!future.value.length) return
  past.value.push(snapshot())
  restore(future.value.pop())
}

const dirty = computed(() => past.value.length > 0)

/* ---------- Клавиши студии ---------- */

function onKey(event) {
  if (!props.editing) return
  const typing = event.target.closest?.('input, textarea, select, [contenteditable]')
  const mod = event.ctrlKey || event.metaKey
  const key = event.key.toLowerCase()

  if (mod && (key === 'z' || key === 'я') && !typing) {
    event.preventDefault()
    event.shiftKey ? redo() : undo()
  } else if (mod && (key === 'y' || key === 'н') && !typing) {
    event.preventDefault()
    redo()
  } else if (event.key === 'Escape') {
    openSettings.value = null
  } else if ((event.key === 'Delete' || event.key === 'Backspace') && !typing && activeBlock.value) {
    event.preventDefault()
    remove(activeBlock.value)
  }
}

/* ---------- Студия поверх сайта ---------- */

function syncBody() {
  const body = document.body
  const on = props.editing
  body.classList.toggle('pb-studio', on)
  body.classList.toggle('pb-studio--panel', on && !!studioTab.value)
  body.classList.toggle('pb-studio--inspector', on && !!activeBlock.value)
  body.classList.toggle('pb-studio--phone', on && device.value === 'phone')
  body.classList.toggle('pb-studio--guest', on && guestView.value)
  body.classList.toggle('pb-studio--header', on && studioTab.value === 'header' && !guestView.value)
}

function replay() {
  replaying.value = false
  nextTick(() => {
    replaying.value = true
    setTimeout(() => { replaying.value = false }, 1400)
  })
}

function select(id) {
  openSettings.value = id
  nextTick(() => {
    document.querySelector(`[data-block-id="${id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

/* ---------- Ширина мышью ----------
 * Тянешь правый край блока — ширина прилипает к ближайшей из WIDTHS
 * по 12-колоночной сетке холста.
 */

const resizing = ref(null) // { id, label }

function startResize(block, event) {
  const grid = gridEl.value
  const section = event.target.closest('.pb-block')
  if (!grid || !section) return

  const gap = parseFloat(getComputedStyle(grid).columnGap) || 0
  const colWidth = (grid.getBoundingClientRect().width + gap) / 12
  const left = section.getBoundingClientRect().left

  resizing.value = { id: block.id, label: WIDTHS.find((w) => w.id === block.width)?.label }

  const onMove = (e) => {
    const cols = Math.max(1, Math.round((e.clientX - left + gap) / colWidth))
    // Ближайшая допустимая ширина
    const next = WIDTHS.reduce((best, w) => (Math.abs(w.span - cols) < Math.abs(best.span - cols) ? w : best))
    if (block.width !== next.id) block.width = next.id
    resizing.value = { id: block.id, label: next.label }
  }

  const onUp = () => {
    resizing.value = null
    window.removeEventListener('pointermove', onMove)
    window.removeEventListener('pointerup', onUp)
  }

  window.addEventListener('pointermove', onMove)
  window.addEventListener('pointerup', onUp)
}

/* ---------- Заголовок прямо на холсте ---------- */

const titleEditing = ref(null)

function startTitle(block) {
  if (!blockTitle(block) && block.style.hide_title) return
  titleEditing.value = block.id
  nextTick(() => document.querySelector('.pb-title-input')?.select())
}

/* ---------- Глазами гостя ---------- */

const guestView = ref(false)

// Холст в режиме правки: в студии и не «глазами гостя»
const live = computed(() => props.editing && !guestView.value)

function reorder(from, to) {
  const [item] = draft.value.splice(from, 1)
  draft.value.splice(to, 0, item)
}

// Шапка профиля живёт у родителя: показываем ему настройки ещё до сохранения
watch(pageDraft, (page) => emit('preview-page', props.editing ? page : null), { deep: true })

const list = computed(() =>
    (props.editing ? draft.value : (props.blocks ?? []).map(withDefaults))
        .filter((b) => BLOCK_TYPES[b.type]),
)

const currentPage = computed(() => (props.editing ? pageDraft.value : { ...defaultPage(), ...(props.page ?? {}) }))

const activeBlock = computed(() => draft.value.find((b) => b.id === openSettings.value) ?? null)

function indexOf(id) {
  return draft.value.findIndex((b) => b.id === id)
}

/* ---------- Порядок ---------- */

function move(block, delta) {
  const from = indexOf(block.id)
  const to = from + delta
  if (to < 0 || to >= draft.value.length) return

  const [item] = draft.value.splice(from, 1)
  draft.value.splice(to, 0, item)
}

function onDragStart(block, event) {
  dragId.value = block.id
  event.dataTransfer.effectAllowed = 'move'
  // Firefox без данных не начинает перетаскивание
  event.dataTransfer.setData('text/plain', block.id)

  // «Призрак» при перетаскивании — весь блок, а не одна ручка
  const section = event.target.closest('.pb-block')
  if (section) event.dataTransfer.setDragImage(section, 24, 24)
}

function onDragOver(block) {
  if (dragId.value && dragId.value !== block.id) overId.value = block.id
}

function onDrop(block) {
  if (!dragId.value || dragId.value === block.id) return

  const from = indexOf(dragId.value)
  const [item] = draft.value.splice(from, 1)
  draft.value.splice(indexOf(block.id), 0, item)

  dragId.value = null
  overId.value = null
}

function onDragEnd() {
  dragId.value = null
  overId.value = null
}

/* ---------- Блоки ---------- */

function resize(block, delta) {
  const i = WIDTHS.findIndex((w) => w.id === block.width)
  const next = WIDTHS[Math.min(WIDTHS.length - 1, Math.max(0, i + delta))]
  block.width = next.id
}

function remove(block) {
  draft.value.splice(indexOf(block.id), 1)
  if (openSettings.value === block.id) openSettings.value = null
}

function duplicate(block) {
  const copy = makeBlock(block.type, clone({ width: block.width, tone: block.tone, settings: block.settings, style: block.style }))
  draft.value.splice(indexOf(block.id) + 1, 0, copy)
}

function add(type) {
  const block = makeBlock(type)
  draft.value.push(block)
  // Новый блок в конце — выбираем и докручиваем до него
  select(block.id)
}

function applyPreset(preset) {
  draft.value = preset.blocks()
  // Цвета и фон из темы оставляем: шаблон — это раскладка
  const { bg, bg_from, bg_to, accent, glass, blur } = pageDraft.value
  pageDraft.value = { ...defaultPage(), ...preset.page, bg, bg_from, bg_to, accent, glass, blur }
  openSettings.value = null
}

function shuffleLook() {
  // «Удиви меня»: случайное оформление всем блокам
  const tones = ['default', 'accent', 'glass', 'gradient', 'neon', 'paper', 'outline']
  const patterns = ['none', 'none', 'dots', 'grid', 'stripes', 'aurora', 'noise']
  const pick = (arr) => arr[Math.floor(Math.random() * arr.length)]
  const color = pick(COLORS.slice(1)).id

  for (const block of draft.value) {
    if (['divider', 'spacer'].includes(block.type)) continue
    block.tone = pick(tones)
    block.style.pattern = pick(patterns)
    block.style.color = Math.random() < 0.6 ? color : pick(COLORS).id
    block.style.hover = pick(['none', 'lift', 'glow', 'tilt'])
  }
}

/* ---------- Плотная укладка ----------
 * Обычная сетка ставит блоки рядами: короткий блок рядом с длинным
 * оставляет под собой дыру. Здесь строки сетки по 4px, и каждый блок
 * занимает столько строк, сколько в нём пикселей (+ отступ) — блоки
 * встают в ближайшее свободное место, как плитка. Высоту меряем живьём:
 * подгрузились картинки или открыли настройки — пересчитываем.
 */

const ROW = 4
const gridEl = ref(null)
const masonry = computed(() => currentPage.value.flow !== 'rows')

let resizeObserver = null
let queued = false
const phone = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia('(max-width: 760px)') : null

function packBlocks() {
  queued = false
  const grid = gridEl.value
  if (!grid) return

  const blocks = grid.querySelectorAll(':scope > .pb-block, :scope > .pb-empty')
  const active = masonry.value && !phone?.matches && !(props.editing && device.value === 'phone')

  if (!active) {
    blocks.forEach((el) => el.style.removeProperty('grid-row-end'))
    return
  }

  const gap = parseFloat(getComputedStyle(grid).columnGap) || 0

  blocks.forEach((el) => {
    const height = el.getBoundingClientRect().height
    el.style.gridRowEnd = `span ${Math.max(1, Math.ceil((height + gap) / ROW))}`
  })
}

/*
 * Пачку изменений считаем один раз. Не через requestAnimationFrame: во
 * вкладке в фоне он не срабатывает, и блоки остались бы друг на друге.
 * Высота блока от числа строк не меняется (align-self: start), так что
 * ResizeObserver не уходит в цикл.
 */
function schedulePack() {
  if (queued) return
  queued = true
  queueMicrotask(packBlocks)
}

function observeBlocks() {
  if (!resizeObserver || !gridEl.value) return
  resizeObserver.disconnect()
  gridEl.value.querySelectorAll(':scope > .pb-block, :scope > .pb-empty').forEach((el) => resizeObserver.observe(el))
  schedulePack()
}

onMounted(() => {
  if (typeof ResizeObserver !== 'undefined') resizeObserver = new ResizeObserver(schedulePack)
  phone?.addEventListener?.('change', schedulePack)
  window.addEventListener('keydown', onKey)
  document.addEventListener('click', onCanvasClick, true)
  observeBlocks()
})

onUnmounted(() => {
  resizeObserver?.disconnect()
  phone?.removeEventListener?.('change', schedulePack)
  window.removeEventListener('keydown', onKey)
  document.removeEventListener('click', onCanvasClick, true)
  document.body.classList.remove('pb-studio', 'pb-studio--panel', 'pb-studio--inspector', 'pb-studio--phone', 'pb-studio--guest', 'pb-studio--header')
})

// Блоки добавили, убрали, переставили или сменили укладку
watch(
    () => [list.value.map((b) => `${b.id}:${b.width}`).join(), masonry.value, device.value, replaying.value],
    () => nextTick(observeBlocks),
)

// Студия закрывает сайт целиком; ширина холста зависит от открытых панелей
watch(
    () => [props.editing, studioTab.value, !!openSettings.value, device.value, guestView.value],
    () => {
      syncBody()
      // Холст поменял ширину — блоки перекладываем, когда панели доедут
      setTimeout(schedulePack, 260)
    },
    { immediate: true },
)

/* ---------- Вид блока ---------- */

function blockClasses(block) {
  const s = block.style
  return [
    `pb-block--${block.type}`,
    `pb-tone--${block.tone || 'default'}`,
    `pb-align--${s.align}`,
    `pb-h--${s.height}`,
    `pb-pattern--${s.pattern}`,
    `pb-radius--${s.radius}`,
    `pb-hover--${s.hover}`,
    `pb-font--${s.font}`,
    `pb-border--${s.border || 'none'}`,
    `pb-text--${s.text || 'auto'}`,
    {
      'pb-hide-mobile': s.hide_mobile,
      'is-dragging': dragId.value === block.id,
      'is-over': overId.value === block.id,
      'is-active': openSettings.value === block.id,
    },
  ]
}

function blockStyle(block, i) {
  // Свой цвет пипеткой важнее цвета из палитры
  const hex = block.style.hex || COLORS.find((c) => c.id === block.style.color)?.hex
  const tilt = Number(block.style.tilt) || 0
  const opacity = Number(block.style.opacity ?? 100)

  return {
    '--span': widthSpan(block.width),
    '--i': i,
    ...(hex ? { '--accent-color': hex } : {}),
    // Наклон и прозрачность — только на готовом профиле, в редакторе мешают
    ...(!live.value && tilt ? { rotate: `${tilt}deg` } : {}),
    ...(opacity < 100 ? { opacity: live.value ? Math.max(0.6, opacity / 100) : opacity / 100 } : {}),
  }
}
</script>

<template>
  <div
      class="pb"
      :class="[
        `pb-gap--${currentPage.gap}`,
        `pb-page-radius--${currentPage.radius}`,
        `pb-backdrop--${currentPage.backdrop}`,
        `pb-shadow--${currentPage.shadow || 'soft'}`,
        `pb-titles--${currentPage.titles || 'default'}`,
        `pb-density--${currentPage.density || 'normal'}`,
        live ? 'pb--editing' : `pb-appear--${currentPage.appear}`,
        editing && replaying ? `pb-appear--${currentPage.appear}` : null,
        { 'pb--phone': live && device === 'phone' },
      ]"
      :style="{
        '--pb-glass': `${currentPage.glass ?? 100}%`,
        '--pb-blur': `${currentPage.blur ?? 0}px`,
      }"
  >
    <!-- Холст: блоки профиля -->
    <div ref="gridEl" class="pb-grid" :class="{ 'pb-grid--masonry': masonry }">
      <section
          v-for="(block, i) in list"
          :key="replaying ? `${block.id}-replay` : block.id"
          :data-block-id="block.id"
          class="pb-block"
          :class="blockClasses(block)"
          :style="blockStyle(block, i)"
          @click="live && (openSettings = block.id)"
          @dblclick="live && startTitle(block)"
          @dragover.prevent="editing && onDragOver(block)"
          @drop.prevent="editing && onDrop(block)"
          @dragend="onDragEnd"
      >
        <!-- Ярлык и быстрые действия: видны при наведении и у выбранного -->
        <template v-if="live">
          <span class="pb-tag">{{ BLOCK_TYPES[block.type].label }}</span>

          <div class="pb-quick" @click.stop @mousedown.stop>
            <span
                class="pb-quick__grip"
                title="Перетащи"
                draggable="true"
                @dragstart="onDragStart(block, $event)"
            ><StudioIcon name="grip" :size="15" /></span>
            <button type="button" title="Выше" @click="move(block, -1)"><StudioIcon name="up" :size="14" /></button>
            <button type="button" title="Ниже" @click="move(block, 1)"><StudioIcon name="down" :size="14" /></button>
            <i></i>
            <button type="button" title="Уже" @click="resize(block, -1)"><StudioIcon name="minus" :size="14" /></button>
            <b>{{ WIDTHS.find((w) => w.id === block.width)?.label }}</b>
            <button type="button" title="Шире" @click="resize(block, 1)"><StudioIcon name="plus" :size="14" /></button>
            <i></i>
            <button
                v-if="!BLOCK_TYPES[block.type].single"
                type="button"
                title="Копия блока"
                @click="duplicate(block)"
            ><StudioIcon name="copy" :size="14" /></button>
            <button type="button" class="is-danger" title="Убрать (Delete)" @click="remove(block)"><StudioIcon name="trash" :size="14" /></button>
          </div>

          <!-- Правый край: тянуть, чтобы поменять ширину -->
          <span
              class="pb-resize"
              :class="{ 'is-on': resizing?.id === block.id }"
              title="Тяни, чтобы поменять ширину"
              @click.stop
              @pointerdown.stop.prevent="startResize(block, $event)"
          >
            <em v-if="resizing?.id === block.id">{{ resizing.label }}</em>
          </span>
        </template>

        <!-- Содержимое -->
        <div class="pb-block__body">
          <input
              v-if="live && titleEditing === block.id"
              v-model="block.style.title"
              class="block-title pb-block__title pb-title-input"
              maxlength="60"
              :placeholder="BLOCK_TYPES[block.type].title || 'Заголовок'"
              @click.stop
              @keydown.enter.prevent="titleEditing = null"
              @keydown.esc.stop.prevent="titleEditing = null"
              @blur="titleEditing = null"
          />
          <h3 v-else-if="blockTitle(block)" class="block-title pb-block__title">
            <span v-if="block.style.icon" class="pb-block__icon">{{ block.style.icon }}</span>
            {{ blockTitle(block) }}
          </h3>
          <slot v-if="BLOCK_TYPES[block.type].group === 'profile'" name="builtin" :block="block" />
          <ProfileCustomBlock v-else :block="block" :user="user" :editing="live" />
        </div>
      </section>

      <button v-if="live" type="button" class="pb-empty pb-empty--add" @click="studioTab = 'add'">
        <StudioIcon name="plus" :size="20" />
        {{ list.length ? 'Добавить блок' : 'Профиль пуст — добавь первый блок или возьми шаблон' }}
      </button>

      <!-- Хозяин убрал все блоки: подсказка видна только ему -->
      <div v-else-if="owner && !list.length" class="pb-empty">
        Блоков нет — гости видят только шапку. Добавить блоки можно в студии профиля.
      </div>
    </div>

    <!-- Каркас студии поверх сайта -->
    <Teleport to="body">
      <div v-if="editing" class="studio">
        <!-- Верхняя полоса -->
        <header class="studio-top">
          <button type="button" class="studio-top__back" title="Выйти без сохранения" @click="emit('cancel')">
            <StudioIcon name="back" :size="18" />
          </button>
          <div class="studio-top__title">
            <strong>Студия профиля</strong>
            <small>{{ draft.length }} блоков · {{ dirty ? 'есть несохранённые правки' : 'без изменений' }}</small>
          </div>

          <div class="studio-top__center">
            <div class="studio-top__group">
              <button type="button" :disabled="!past.length" title="Отменить (Ctrl+Z)" @click="undo"><StudioIcon name="undo" /></button>
              <button type="button" :disabled="!future.length" title="Повторить (Ctrl+Y)" @click="redo"><StudioIcon name="redo" /></button>
            </div>
            <div class="studio-top__group studio-top__device">
              <button type="button" :class="{ 'is-on': device === 'desktop' }" title="Как на компьютере" @click="device = 'desktop'">
                <StudioIcon name="monitor" />
              </button>
              <button type="button" :class="{ 'is-on': device === 'phone' }" title="Как на телефоне" @click="device = 'phone'">
                <StudioIcon name="phone" />
              </button>
            </div>
            <div class="studio-top__group">
              <button type="button" title="Глазами гостя" @click="guestView = true; openSettings = null"><StudioIcon name="eye" /></button>
              <button type="button" title="Случайное оформление всем блокам" @click="shuffleLook"><StudioIcon name="dice" /></button>
              <button type="button" title="Проиграть появление" @click="replay"><StudioIcon name="motion" /></button>
              <button type="button" title="Вернуть обычный профиль" @click="emit('reset')"><StudioIcon name="reset" /></button>
            </div>
          </div>

          <div class="studio-top__actions">
            <button type="button" class="studio-btn studio-btn--ghost" @click="emit('cancel')">Отмена</button>
            <button
                type="button"
                class="studio-btn studio-btn--primary"
                :disabled="saving"
                @click="emit('save', { blocks: clone(draft), page: clone(pageDraft) })"
            >
              <StudioIcon name="check" :size="16" />
              {{ saving ? 'Публикуем…' : 'Опубликовать' }}
            </button>
          </div>
        </header>

        <!-- Рейка разделов и панель -->
        <nav class="studio-rail">
          <button
              v-for="[id, icon, label] in STUDIO_TABS"
              :key="id"
              type="button"
              :class="{ 'is-on': studioTab === id }"
              @click="toggleTab(id)"
          >
            <StudioIcon :name="icon" :size="20" />
            <span>{{ label }}</span>
          </button>
        </nav>

        <Transition name="studio-slide-left">
          <aside v-if="studioTab" class="studio-left">
            <ProfileStudioPanel
                :tab="studioTab"
                :blocks="draft"
                :page="pageDraft"
                :active-id="openSettings"
                @add="add"
                @select="select"
                @move="move"
                @remove="remove"
                @reorder="reorder"
                @preset="applyPreset"
                @replay="replay"
            />
          </aside>
        </Transition>

        <!-- Инспектор выбранного блока -->
        <Transition name="studio-slide-right">
          <aside v-if="activeBlock" :key="activeBlock.id" class="studio-right">
            <header class="studio-right__head">
              <span class="studio-right__icon">{{ BLOCK_TYPES[activeBlock.type].icon }}</span>
              <div>
                <strong>{{ BLOCK_TYPES[activeBlock.type].label }}</strong>
                <small>Правки сразу видны на холсте</small>
              </div>
              <button type="button" title="Закрыть (Esc)" @click="openSettings = null"><StudioIcon name="close" :size="16" /></button>
            </header>

            <ProfileBlockSettings :block="activeBlock" />
          </aside>
        </Transition>

        <!-- Глазами гостя: студия спрятана, одна кнопка назад -->
        <button v-if="guestView" type="button" class="studio-guest-back" @click="guestView = false">
          <StudioIcon name="back" :size="16" /> Вернуться в студию
        </button>

        <!-- Подсказка, пока ничего не выбрано -->
        <div v-if="!activeBlock && !guestView" class="studio-hint">
          <StudioIcon name="cursor" :size="15" />
          Клик — настроить · двойной клик — заголовок · тяни правый край — ширина
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
@import "./ProfileBuilderGrid.css";
@import "./ProfileStudio.css";
</style>
