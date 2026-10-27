<script setup>
/**
 * Левая панель студии профиля. Разделы:
 * add — новые блоки, layers — список блоков (порядок, выбор, удаление),
 * templates — готовые раскладки, theme — палитры, фон и цвета,
 * type — шрифт и заголовки, layout — сетка и ширина, motion — появление.
 * Настройки страницы меняет прямо в page (это черновик родителя).
 */
import { computed, ref, watch } from 'vue'
import StudioIcon from '@/components/players/builder/StudioIcon.vue'
import StudioInventory from '@/components/players/builder/StudioInventory.vue'
import {
  BLOCK_TYPES, GROUPS, HEADER_ITEMS, HEADER_ITEM_STYLE, PAGE_OPTIONS, PRESETS, THEME_PRESETS, WIDTHS, widthSpan,
} from '@/data/players/profileBlocks.js'

const props = defineProps({
  tab: { type: String, required: true },
  blocks: { type: Array, required: true },
  page: { type: Object, required: true },
  activeId: { type: String, default: null },
  // Часть шапки, на которую нажали на холсте
  focusItem: { type: String, default: null },
  myTemplates: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'add', 'select', 'move', 'remove', 'reorder', 'preset', 'replay',
  'save-template', 'apply-template', 'delete-template', 'profile', 'header-mode',
])

const templateName = ref('')

function saveTemplate() {
  emit('save-template', templateName.value.trim())
  templateName.value = ''
}

const TITLES = {
  add: ['Добавить блок', 'Нажми — блок встанет в конец профиля'],
  layers: ['Слои', 'Порядок блоков. Тяни строку, чтобы переставить'],
  templates: ['Шаблоны', 'Готовая раскладка заменит текущую — до публикации всё можно отменить'],
  theme: ['Тема', 'Палитры, фон страницы и характер блоков'],
  type: ['Текст', 'Шрифт всего профиля и вид заголовков'],
  layout: ['Сетка', 'Как блоки ложатся на страницу'],
  motion: ['Анимация', 'Как блоки появляются перед гостем. Эффекты профиля — из инвентаря'],
  inventory: ['Инвентарь', 'Купленное для профиля: нажми — наденешь, холст обновится сразу'],
  profile: ['Профиль', 'Аватар, обложка, описание, соцсети и витрина ачивок'],
  header: ['Шапка','Раскладка, фон, ник и аватар. Нажми на шапку на холсте — откроется этот раздел'],
}

const heading = computed(() => TITLES[props.tab] ?? TITLES.add)

/* ---------- Добавить ---------- */

const query = ref('')

const usedSingles = computed(() => new Set(props.blocks.filter((b) => BLOCK_TYPES[b.type]?.single).map((b) => b.type)))

const paletteGroups = computed(() => {
  const q = query.value.trim().toLowerCase()
  return GROUPS.map((group) => ({
    ...group,
    types: Object.entries(BLOCK_TYPES).filter(([, def]) =>
      def.group === group.id && (!q || `${def.label} ${def.hint}`.toLowerCase().includes(q))),
  })).filter((group) => group.types.length)
})

/* ---------- Слои ---------- */

const dragFrom = ref(null)
const dragOver = ref(null)

function onLayerDrop(index) {
  if (dragFrom.value !== null && dragFrom.value !== index) emit('reorder', dragFrom.value, index)
  dragFrom.value = null
  dragOver.value = null
}

function widthLabel(id) {
  return WIDTHS.find((w) => w.id === id)?.label ?? ''
}

/* ---------- Шаблоны ---------- */

const PREVIEW_HEIGHT = { showcase: 46, video: 40, games: 34, aspects: 30, quote: 18, divider: 4, spacer: 8 }

const presetCards = computed(() =>
  PRESETS.map((preset) => ({
    ...preset,
    map: preset.blocks().map((b) => ({ ...b, height: PREVIEW_HEIGHT[b.type] ?? 24 })),
  })),
)

/* ---------- Тема ---------- */

const activeTheme = computed(() => THEME_PRESETS.find((t) =>
  t.bg === props.page.bg && t.bg_from === props.page.bg_from && t.bg_to === props.page.bg_to,
)?.id)

function applyTheme(theme) {
  const { id, label, ...values } = theme
  Object.assign(props.page, values)
}

const BG_TYPES = PAGE_OPTIONS.bg.options

const SHADOW_PREVIEW = {
  soft: '0 6px 14px rgba(0,0,0,.35)',
  none: 'none',
  deep: '0 14px 26px rgba(0,0,0,.7)',
  neon: '0 0 0 1px var(--accent-color), 0 0 16px color-mix(in srgb, var(--accent-color) 55%, transparent)',
}

function sampleStyle(extra = {}) {
  return {
    '--pp-from': props.page.bg_from,
    '--pp-to': props.page.bg_to,
    ...extra,
  }
}

/* ---------- Части шапки ---------- */

const openItem = ref(props.focusItem)
const itemFrom = ref(null)
const itemOver = ref(null)

// Клик по части шапки на холсте раскрывает её здесь
watch(() => props.focusItem, (id) => { if (id) openItem.value = id })

function moveItem(i, delta) {
  const list = props.page.hdr_items
  const [item] = list.splice(i, 1)
  list.splice(i + delta, 0, item)
}

function dropItem(to) {
  if (itemFrom.value !== null && itemFrom.value !== to) {
    const list = props.page.hdr_items
    const [item] = list.splice(itemFrom.value, 1)
    list.splice(to, 0, item)
  }
  itemFrom.value = null
  itemOver.value = null
}

/* ---------- Сетка ---------- */

// Схемы укладки для плиток: высоты кирпичиков
const FLOW_MAP = {
  masonry: [[6, 30], [6, 18], [4, 16], [8, 26], [6, 14]],
  rows: [[6, 24], [6, 24], [4, 18], [4, 18], [4, 18]],
}

function seg(key) {
  return PAGE_OPTIONS[key].options
}
</script>

<template>
  <div class="studio-panel">
    <header class="studio-panel__head">
      <strong>{{ heading[0] }}</strong>
      <small>{{ heading[1] }}</small>
    </header>

    <div class="studio-panel__body">
      <!-- ============ Добавить ============ -->
      <template v-if="tab === 'add'">
        <label class="studio-search">
          <StudioIcon name="cursor" :size="15" />
          <input v-model="query" placeholder="Найти блок: опрос, видео, цели…" />
        </label>

        <section v-for="group in paletteGroups" :key="group.id" class="studio-group">
          <h4>{{ group.label }}</h4>
          <div class="studio-blocks">
            <button
                v-for="[type, def] in group.types"
                :key="type"
                type="button"
                class="studio-block-card"
                :disabled="def.single && usedSingles.has(type)"
                @click="emit('add', type)"
            >
              <span class="studio-block-card__icon">{{ def.icon }}</span>
              <span class="studio-block-card__text">
                <strong>{{ def.label }}</strong>
                <small>{{ def.single && usedSingles.has(type) ? 'Уже в профиле' : def.hint }}</small>
              </span>
              <StudioIcon class="studio-block-card__plus" name="plus" :size="16" />
            </button>
          </div>
        </section>

        <p v-if="!paletteGroups.length" class="studio-empty">Ничего не нашлось.</p>
      </template>

      <!-- ============ Слои ============ -->
      <template v-else-if="tab === 'layers'">
        <ol class="studio-layers">
          <li
              v-for="(block, i) in blocks"
              :key="block.id"
              class="studio-layer"
              :class="{ 'is-active': activeId === block.id, 'is-over': dragOver === i, 'is-drag': dragFrom === i }"
              draggable="true"
              @dragstart="dragFrom = i; $event.dataTransfer.setData('text/plain', block.id)"
              @dragover.prevent="dragOver = i"
              @drop.prevent="onLayerDrop(i)"
              @dragend="dragFrom = null; dragOver = null"
              @click="emit('select', block.id)"
          >
            <StudioIcon class="studio-layer__grip" name="grip" :size="16" />
            <span class="studio-layer__icon">{{ BLOCK_TYPES[block.type].icon }}</span>
            <span class="studio-layer__name">
              <strong>{{ block.style.title || BLOCK_TYPES[block.type].label }}</strong>
              <small>{{ BLOCK_TYPES[block.type].label }} · {{ widthLabel(block.width) }}</small>
            </span>
            <span class="studio-layer__actions" @click.stop>
              <button type="button" title="Выше" :disabled="i === 0" @click="emit('move', block, -1)"><StudioIcon name="up" :size="14" /></button>
              <button type="button" title="Ниже" :disabled="i === blocks.length - 1" @click="emit('move', block, 1)"><StudioIcon name="down" :size="14" /></button>
              <button type="button" class="is-danger" title="Убрать" @click="emit('remove', block)"><StudioIcon name="trash" :size="14" /></button>
            </span>
          </li>
        </ol>
        <p v-if="!blocks.length" class="studio-empty">Блоков нет — загляни в «Добавить» или «Шаблоны».</p>
      </template>

      <!-- ============ Шаблоны ============ -->
      <template v-else-if="tab === 'templates'">
        <section class="studio-group">
          <h4>Мои шаблоны</h4>
          <form class="studio-search" @submit.prevent="saveTemplate">
            <StudioIcon name="save" :size="15" />
            <input v-model="templateName" maxlength="30" placeholder="Сохранить текущий вид как…" />
            <button type="submit" class="studio-icon-btn" title="Сохранить"><StudioIcon name="plus" :size="15" /></button>
          </form>
          <div v-for="t in myTemplates" :key="t.id" class="studio-layer studio-mytpl" @click="emit('apply-template', t)">
            <StudioIcon name="template" :size="16" />
            <span class="studio-layer__name">
              <strong>{{ t.name }}</strong>
              <small>{{ t.blocks.length }} блоков</small>
            </span>
            <span class="studio-layer__actions is-always" @click.stop>
              <button type="button" class="is-danger" title="Удалить шаблон" @click="emit('delete-template', t.id)"><StudioIcon name="trash" :size="14" /></button>
            </span>
          </div>
          <p v-if="!myTemplates.length" class="studio-note">Собери вид, который нравится, и сохрани — потом вернёшься к нему одним кликом.</p>
        </section>

        <h4 class="studio-subhead">Готовые</h4>
        <button
            v-for="preset in presetCards"
            :key="preset.id"
            type="button"
            class="studio-template"
            :style="{ '--preset-color': preset.color }"
            @click="emit('preset', preset)"
        >
          <span class="studio-template__map">
            <i
                v-for="(b, n) in preset.map"
                :key="n"
                :class="`is-${b.tone}`"
                :style="{ gridColumn: `span ${widthSpan(b.width)}`, height: `${b.height}px` }"
            ></i>
          </span>
          <span class="studio-template__info">
            <strong>{{ preset.label }}</strong>
            <small>{{ preset.hint }}</small>
          </span>
        </button>
      </template>

      <!-- ============ Тема ============ -->
      <template v-else-if="tab === 'theme'">
        <section class="studio-group">
          <h4>Палитры</h4>
          <div class="studio-themes">
            <button
                v-for="theme in THEME_PRESETS"
                :key="theme.id"
                type="button"
                class="studio-theme"
                :class="{ 'is-on': activeTheme === theme.id }"
                @click="applyTheme(theme)"
            >
              <span
                  class="studio-swatch-bg"
                  :class="`pp-bg--${theme.bg}`"
                  :style="{ '--pp-from': theme.bg_from, '--pp-to': theme.bg_to }"
              ></span>
              <span>{{ theme.label }}</span>
            </button>
          </div>
        </section>

        <section class="studio-group">
          <h4>Фон страницы</h4>
          <div class="studio-tiles studio-tiles--4">
            <button
                v-for="[value, label] in BG_TYPES"
                :key="value"
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.bg === value }"
                @click="page.bg = value"
            >
              <span class="studio-swatch-bg" :class="`pp-bg--${value}`" :style="sampleStyle()">
                <em v-if="value === 'none'">Тема</em>
              </span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>

          <div v-if="page.bg !== 'none'" class="studio-colors">
            <label class="studio-color">
              <input v-model="page.bg_from" type="color" />
              <span><small>Цвет 1</small>{{ page.bg_from }}</span>
            </label>
            <label class="studio-color">
              <input v-model="page.bg_to" type="color" />
              <span><small>Цвет 2</small>{{ page.bg_to }}</span>
            </label>
            <button
                type="button"
                class="studio-icon-btn"
                title="Поменять местами"
                @click="[page.bg_from, page.bg_to] = [page.bg_to, page.bg_from]"
            ><StudioIcon name="redo" :size="15" /></button>
          </div>
        </section>

        <section class="studio-group">
          <h4>Блоки</h4>
          <label class="studio-slider">
            <span>Непрозрачность <b>{{ page.glass }}%</b></span>
            <input v-model.number="page.glass" type="range" min="20" max="100" />
          </label>
          <label class="studio-slider">
            <span>Размытие под блоками <b>{{ page.blur }} px</b></span>
            <input v-model.number="page.blur" type="range" min="0" max="24" />
          </label>

          <span class="studio-label">Тени</span>
          <div class="studio-tiles studio-tiles--4">
            <button
                v-for="[value, label] in seg('shadow')"
                :key="value"
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.shadow === value }"
                :style="sampleStyle()"
                @click="page.shadow = value"
            >
              <span class="studio-shadow"><i :style="{ boxShadow: SHADOW_PREVIEW[value] }"></i></span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>

          <span class="studio-label">Углы</span>
          <div class="studio-seg">
            <button
                v-for="[value, label] in seg('radius')"
                :key="value"
                type="button"
                :class="{ 'is-on': page.radius === value }"
                @click="page.radius = value"
            >{{ label }}</button>
          </div>

          <span class="studio-label">Подложка под сеткой</span>
          <div class="studio-seg">
            <button
                v-for="[value, label] in seg('backdrop')"
                :key="value"
                type="button"
                :class="{ 'is-on': page.backdrop === value }"
                @click="page.backdrop = value"
            >{{ label }}</button>
          </div>
        </section>
      </template>

      <!-- ============ Текст ============ -->
      <template v-else-if="tab === 'type'">
        <section class="studio-group">
          <h4>Шрифт профиля</h4>
          <div class="studio-fonts">
            <button
                v-for="[value, label] in seg('font')"
                :key="value"
                type="button"
                class="studio-font"
                :class="[`pp-font--${value}`, { 'is-on': page.font === value }]"
                @click="page.font = value"
            >
              <span class="studio-font__sample">Аа</span>
              <span class="studio-font__name">{{ label }}</span>
              <span class="studio-font__line">Бридж, клатчи и тир S</span>
            </button>
          </div>
        </section>

        <section class="studio-group">
          <h4>Заголовки блоков</h4>
          <div class="studio-titles">
            <button
                v-for="[value, label] in seg('titles')"
                :key="value"
                type="button"
                class="studio-title"
                :class="[`pb-titles--${value}`, { 'is-on': page.titles === value }]"
                :style="sampleStyle()"
                @click="page.titles = value"
            >
              <span class="pb-block__title">{{ value === 'hidden' ? 'Без заголовков' : 'Мой сквад' }}</span>
              <small>{{ label }}</small>
            </button>
          </div>
        </section>
      </template>

      <!-- ============ Сетка ============ -->
      <template v-else-if="tab === 'layout'">
        <section class="studio-group">
          <h4>Укладка</h4>
          <div class="studio-tiles studio-tiles--2">
            <button
                v-for="[value, label] in seg('flow')"
                :key="value"
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.flow === value }"
                @click="page.flow = value"
            >
              <span class="studio-flow" :class="`studio-flow--${value}`">
                <i v-for="(cell, n) in FLOW_MAP[value]" :key="n" :style="{ gridColumn: `span ${cell[0]}`, height: `${cell[1]}px` }"></i>
              </span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>
        </section>

        <section v-for="key in ['gap', 'density', 'width', 'header']" :key="key" class="studio-group">
          <h4>{{ PAGE_OPTIONS[key].label }}</h4>
          <div class="studio-seg">
            <button
                v-for="[value, label] in seg(key)"
                :key="value"
                type="button"
                :class="{ 'is-on': page[key] === value }"
                @click="page[key] = value"
            >{{ label }}</button>
          </div>
          <p v-if="key === 'width'" class="studio-note">Широкий профиль выходит за колонку сайта на больших экранах.</p>
        </section>
      </template>

      <!-- ============ Инвентарь ============ -->
      <StudioInventory v-else-if="tab === 'inventory'" />

      <!-- ============ Профиль ============ -->
      <template v-else-if="tab === 'profile'">
        <section class="studio-group">
          <h4>Данные профиля</h4>
          <p class="studio-note">Аватар и обложка, статус, цитата и описание, соцсети, любимые режимы, витрина ачивок и привязка Minecraft.</p>
          <button type="button" class="studio-wide-btn" @click="emit('profile')">
            <StudioIcon name="user" :size="16" /> Изменить данные профиля
          </button>
        </section>
      </template>

      <!-- ============ Шапка ============ -->
      <template v-else-if="tab === 'header'">
        <section class="studio-group">
          <h4>Режим</h4>
          <div class="studio-tiles studio-tiles--2">
            <button
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.hdr_mode !== 'blocks' }"
                @click="emit('header-mode', 'whole')"
            >
              <span class="studio-hdr studio-hdr--classic"><i></i><b></b><s></s></span>
              <span class="studio-tile__label">Шапка целиком</span>
            </button>
            <button
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.hdr_mode === 'blocks' }"
                @click="emit('header-mode', 'blocks')"
            >
              <span class="studio-hdr studio-hdr--split"><i></i><b></b><s></s></span>
              <span class="studio-tile__label">Разобрать на блоки</span>
            </button>
          </div>
          <p class="studio-note">
            {{ page.hdr_mode === 'blocks'
              ? 'Аватар, ник, бейджи, «о себе» и тир — теперь блоки: двигай, растягивай и оформляй их как любые другие. Сверху осталась лента с обложкой и питомцами.'
              : 'Разбери шапку — и её части станут обычными блоками сетки.' }}
          </p>
        </section>

        <section v-if="page.hdr_mode !== 'blocks'" class="studio-group">
          <h4>Раскладка</h4>
          <div class="studio-tiles studio-tiles--3">
            <button
                v-for="[value, label] in seg('hdr_layout')"
                :key="value"
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.hdr_layout === value }"
                @click="page.hdr_layout = value"
            >
              <span class="studio-hdr" :class="`studio-hdr--${value}`"><i></i><b></b><s></s></span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>
        </section>

        <section v-if="page.hdr_mode !== 'blocks'" class="studio-group">
          <h4>Элементы шапки</h4>
          <p class="studio-note">Тяни строку, чтобы переставить. Нажми на элемент в шапке на холсте — он раскроется здесь.</p>
          <ol class="studio-layers">
            <li
                v-for="(item, i) in page.hdr_items"
                :key="item.id"
                class="studio-hitem"
                :class="{ 'is-open': openItem === item.id, 'is-off': item.hidden, 'is-over': itemOver === i }"
                draggable="true"
                @dragstart="itemFrom = i"
                @dragover.prevent="itemOver = i"
                @drop.prevent="dropItem(i)"
                @dragend="itemFrom = null; itemOver = null"
            >
              <div class="studio-layer" @click="openItem = openItem === item.id ? null : item.id">
                <StudioIcon class="studio-layer__grip" name="grip" :size="16" />
                <span class="studio-layer__name"><strong>{{ HEADER_ITEMS[item.id] }}</strong></span>
                <span class="studio-layer__actions is-always" @click.stop>
                  <button type="button" :title="item.hidden ? 'Показать' : 'Спрятать'" @click="item.hidden = !item.hidden">
                    <StudioIcon :name="item.hidden ? 'eye-off' : 'eye'" :size="14" />
                  </button>
                  <button type="button" title="Выше" :disabled="i === 0" @click="moveItem(i, -1)"><StudioIcon name="up" :size="14" /></button>
                  <button type="button" title="Ниже" :disabled="i === page.hdr_items.length - 1" @click="moveItem(i, 1)"><StudioIcon name="down" :size="14" /></button>
                </span>
              </div>
              <div v-if="openItem === item.id" class="studio-hitem__body">
                <template v-for="(def, key) in HEADER_ITEM_STYLE" :key="key">
                  <span class="studio-label">{{ def.label }}</span>
                  <div class="studio-seg" :class="{ 'studio-seg--wrap': def.options.length > 4 }">
                    <button
                        v-for="[value, label] in def.options"
                        :key="value"
                        type="button"
                        :class="{ 'is-on': item[key] === value }"
                        @click="item[key] = value"
                    >{{ label }}</button>
                  </div>
                </template>
                <p v-if="item.id === 'name'" class="studio-note">Цвет и эффект ника — из инвентаря.</p>
              </div>
            </li>
          </ol>
        </section>

        <section class="studio-group">
          <h4>Фон шапки</h4>
          <div class="studio-seg studio-seg--wrap">
            <button
                v-for="[value, label] in seg('hdr_bg')"
                :key="value"
                type="button"
                :class="{ 'is-on': page.hdr_bg === value }"
                @click="page.hdr_bg = value"
            >{{ label }}</button>
          </div>
          <span class="studio-label">Высота</span>
          <div class="studio-seg">
            <button
                v-for="[value, label] in seg('hdr_height')"
                :key="value"
                type="button"
                :class="{ 'is-on': page.hdr_height === value }"
                @click="page.hdr_height = value"
            >{{ label }}</button>
          </div>
        </section>

        <section class="studio-group">
          <h4>Аватар</h4>
          <div class="studio-tiles studio-tiles--4">
            <button
                v-for="[value, label] in seg('hdr_avatar')"
                :key="value"
                type="button"
                class="studio-tile"
                :class="{ 'is-on': page.hdr_avatar === value }"
                @click="page.hdr_avatar = value"
            >
              <span class="studio-ava" :class="`studio-ava--${value}`"><i></i></span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>
          <span class="studio-label">Размер аватара</span>
          <div class="studio-seg">
            <button
                v-for="[value, label] in seg('hdr_avatar_size')"
                :key="value"
                type="button"
                :class="{ 'is-on': page.hdr_avatar_size === value }"
                @click="page.hdr_avatar_size = value"
            >{{ label }}</button>
          </div>
        </section>

        <section class="studio-group">
          <h4>Вид</h4>
          <label class="studio-switch">
            <input type="checkbox" :checked="page.header === 'compact'" @change="page.header = $event.target.checked ? 'compact' : 'full'" />
            <span></span>
            Компактная шапка (без больших плашек)
          </label>
        </section>
      </template>

      <!-- ============ Анимация ============ -->
      <template v-else-if="tab === 'motion'">

        <section class="studio-group">
          <h4>Появление блоков</h4>
          <div class="studio-tiles studio-tiles--2">
            <button
                v-for="[value, label] in seg('appear')"
                :key="value"
                type="button"
                class="studio-tile studio-motion"
                :class="[`studio-motion--${value}`, { 'is-on': page.appear === value }]"
                @click="page.appear = value; emit('replay')"
            >
              <span class="studio-motion__stage"><i></i><i></i><i></i></span>
              <span class="studio-tile__label">{{ label }}</span>
            </button>
          </div>
          <button type="button" class="studio-wide-btn" @click="emit('replay')">
            <StudioIcon name="motion" :size="16" /> Проиграть на холсте
          </button>
        </section>
      </template>
    </div>
  </div>
</template>
