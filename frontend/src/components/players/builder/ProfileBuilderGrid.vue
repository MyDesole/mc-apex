<script setup>
/**
 * Сетка профиля из блоков и её редактор.
 *
 * Показ: блоки по порядку на сетке из 12 колонок; ширина блока — от
 * четверти до всей строки; на телефоне все во всю ширину. У каждого блока
 * своё оформление (фон, цвет, узор, высота, углы, шрифт, заголовок), у
 * страницы — отступы, углы, фон под блоками и анимация появления.
 *
 * Редактор (editing): блоки перетаскиваются мышью или стрелками, ширина
 * меняется кнопками, блок можно скопировать, настроить и убрать; «＋ Блок»
 * открывает палитру, «Шаблоны» — готовые раскладки, «Страница» — общие
 * настройки. Сохраняет родитель (emit save { blocks, page }).
 *
 * Содержимое частей профиля (статистика, друзья, клан…) рисует родитель
 * через слот #builtin — их данные живут в PlayerCard и на странице.
 */
import { computed, ref, watch } from 'vue'
import ProfileCustomBlock from '@/components/players/builder/ProfileCustomBlock.vue'
import ProfileBlockSettings from '@/components/players/builder/ProfileBlockSettings.vue'
import {
  BLOCK_TYPES, GROUPS, WIDTHS, COLORS, PAGE_OPTIONS, PRESETS,
  blockTitle, defaultBlocks, defaultPage, makeBlock, widthSpan, withDefaults,
} from '@/data/players/profileBlocks.js'

const props = defineProps({
  blocks: { type: Array, default: null },
  page: { type: Object, default: null },
  user: { type: Object, required: true },
  editing: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'cancel', 'reset', 'preview-page'])

const draft = ref([])
const pageDraft = ref(defaultPage())
const openSettings = ref(null)
const panel = ref(null) // 'blocks' | 'presets' | 'page'
const dragId = ref(null)
const overId = ref(null)

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

// Начинаем редактирование с сохранённой раскладки или с «как сейчас»
watch(
    () => props.editing,
    (on) => {
      if (on) {
        draft.value = (props.blocks?.length ? clone(props.blocks) : defaultBlocks()).map(withDefaults)
        pageDraft.value = { ...defaultPage(), ...(props.page ?? {}) }
        openSettings.value = null
      }
    },
    { immediate: true },
)

// Шапка профиля живёт у родителя: показываем ему настройки ещё до сохранения
watch(pageDraft, (page) => emit('preview-page', props.editing ? page : null), { deep: true })

const list = computed(() =>
    (props.editing ? draft.value : (props.blocks ?? []).map(withDefaults))
        .filter((b) => BLOCK_TYPES[b.type]),
)

const currentPage = computed(() => (props.editing ? pageDraft.value : { ...defaultPage(), ...(props.page ?? {}) }))

const activeBlock = computed(() => draft.value.find((b) => b.id === openSettings.value) ?? null)

const usedSingles = computed(() => new Set(draft.value.filter((b) => BLOCK_TYPES[b.type]?.single).map((b) => b.type)))

const paletteGroups = computed(() =>
    GROUPS.map((group) => ({
      ...group,
      types: Object.entries(BLOCK_TYPES).filter(([, def]) => def.group === group.id),
    })),
)

// Высота блока в мини-макете шаблона: крупные части повыше
const PREVIEW_HEIGHT = { showcase: 46, video: 40, games: 34, aspects: 30, quote: 18, divider: 4, spacer: 8 }

const presetCards = computed(() =>
    PRESETS.map((preset) => ({
      ...preset,
      map: preset.blocks().map((b) => ({ ...b, height: PREVIEW_HEIGHT[b.type] ?? 24 })),
      tags: [
        PAGE_OPTIONS.gap.options.find(([v]) => v === preset.page.gap)?.[1],
        PAGE_OPTIONS.appear.options.find(([v]) => v === preset.page.appear)?.[1],
        preset.page.header === 'compact' ? 'Компактная шапка' : null,
      ].filter(Boolean),
    })),
)

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
  panel.value = null
  openSettings.value = block.id

  // Новый блок в конце — докручиваем до него
  requestAnimationFrame(() => {
    document.querySelector(`[data-block-id="${block.id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

function applyPreset(preset) {
  draft.value = preset.blocks()
  pageDraft.value = { ...defaultPage(), ...preset.page }
  openSettings.value = null
  panel.value = null
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
    {
      'pb-hide-mobile': s.hide_mobile,
      'is-dragging': dragId.value === block.id,
      'is-over': overId.value === block.id,
      'is-active': openSettings.value === block.id,
    },
  ]
}

function blockStyle(block, i) {
  const hex = COLORS.find((c) => c.id === block.style.color)?.hex
  return {
    '--span': widthSpan(block.width),
    '--i': i,
    ...(hex ? { '--accent-color': hex } : {}),
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
        editing ? 'pb--editing' : `pb-appear--${currentPage.appear}`,
      ]"
  >
    <!-- Панель конструктора -->
    <div v-if="editing" class="pb-toolbar">
      <div class="pb-toolbar__title">
        <strong>Конструктор профиля</strong>
        <span>{{ draft.length }} блоков · тяни за ⠿, ⚙ — всё оформление блока</span>
      </div>

      <div class="pb-toolbar__actions">
        <button type="button" class="pb-btn pb-btn--add" @click="panel = 'blocks'">＋ Блок</button>
        <button type="button" class="pb-btn" @click="panel = 'presets'">Шаблоны</button>
        <button type="button" class="pb-btn" :class="{ 'is-on': panel === 'page' }" @click="panel = panel === 'page' ? null : 'page'">Страница</button>
        <button type="button" class="pb-btn" title="Случайное оформление всем блокам" @click="shuffleLook">🎲</button>
        <button type="button" class="pb-btn pb-btn--ghost" title="Вернуть обычный профиль" @click="emit('reset')">Сбросить</button>
        <button type="button" class="pb-btn pb-btn--ghost" @click="emit('cancel')">Отмена</button>
        <button type="button" class="pb-btn pb-btn--save" :disabled="saving" @click="emit('save', { blocks: clone(draft), page: clone(pageDraft) })">
          {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </button>
      </div>

      <!-- Настройки всей страницы -->
      <div v-if="panel === 'page'" class="pb-page">
        <div v-for="(def, key) in PAGE_OPTIONS" :key="key" class="pb-option">
          <span class="pb-option__label">{{ def.label }}</span>
          <div class="pb-option__chips">
            <button
                v-for="[value, label] in def.options"
                :key="value"
                type="button"
                class="pb-chip"
                :class="{ 'is-on': pageDraft[key] === value }"
                @click="pageDraft[key] = value"
            >{{ label }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Сетка -->
    <div class="pb-grid">
      <section
          v-for="(block, i) in list"
          :key="block.id"
          :data-block-id="block.id"
          class="pb-block"
          :class="blockClasses(block)"
          :style="blockStyle(block, i)"
          @dragover.prevent="editing && onDragOver(block)"
          @drop.prevent="editing && onDrop(block)"
          @dragend="onDragEnd"
      >
        <!-- Управление блоком -->
        <div v-if="editing" class="pb-controls" @mousedown.stop>
          <!-- Тянуть только за ручку: иначе в полях настроек не выделить текст -->
          <span
              class="pb-controls__handle"
              title="Перетащи"
              draggable="true"
              @dragstart="onDragStart(block, $event)"
          >⠿</span>
          <span class="pb-controls__name">{{ BLOCK_TYPES[block.type].icon }} {{ BLOCK_TYPES[block.type].label }}</span>

          <div class="pb-controls__group">
            <button type="button" title="Выше" @click="move(block, -1)">↑</button>
            <button type="button" title="Ниже" @click="move(block, 1)">↓</button>
          </div>

          <div class="pb-controls__group">
            <button type="button" title="Уже" @click="resize(block, -1)">−</button>
            <span class="pb-controls__width">{{ WIDTHS.find((w) => w.id === block.width)?.label }}</span>
            <button type="button" title="Шире" @click="resize(block, 1)">+</button>
          </div>

          <button
              v-if="!BLOCK_TYPES[block.type].single"
              type="button"
              title="Копия блока"
              @click="duplicate(block)"
          >⧉</button>

          <button
              type="button"
              :class="{ 'is-on': openSettings === block.id }"
              title="Содержимое и оформление"
              @click="openSettings = openSettings === block.id ? null : block.id"
          >⚙</button>

          <button type="button" class="pb-controls__remove" title="Убрать блок" @click="remove(block)">✕</button>
        </div>

        <!-- Содержимое -->
        <div class="pb-block__body">
          <h3 v-if="blockTitle(block)" class="block-title pb-block__title">{{ blockTitle(block) }}</h3>
          <slot v-if="BLOCK_TYPES[block.type].group === 'profile'" name="builtin" :block="block" />
          <ProfileCustomBlock v-else :block="block" :user="user" :editing="editing" />
        </div>
      </section>

      <div v-if="editing && !list.length" class="pb-empty">
        Профиль пуст — добавь первый блок кнопкой «＋ Блок» или возьми шаблон.
      </div>
    </div>

    <!-- Настройки блока: боковая панель, блок при этом виден целиком -->
    <Teleport to="body">
      <Transition name="pb-drawer">
        <aside v-if="editing && activeBlock" :key="activeBlock.id" class="pb-drawer">
          <header class="pb-drawer__head">
            <span class="pb-drawer__icon">{{ BLOCK_TYPES[activeBlock.type].icon }}</span>
            <div>
              <strong>{{ BLOCK_TYPES[activeBlock.type].label }}</strong>
              <small>Изменения сразу видны в профиле</small>
            </div>
            <button type="button" title="Закрыть" @click="openSettings = null">✕</button>
          </header>

          <ProfileBlockSettings :block="activeBlock" />
        </aside>
      </Transition>
    </Teleport>

    <!-- Палитра блоков и шаблоны -->
    <Teleport to="body">
      <div v-if="panel === 'blocks' || panel === 'presets'" class="pb-palette" @click.self="panel = null">
        <div class="pb-palette__card">
          <div class="pb-palette__head">
            <strong>{{ panel === 'blocks' ? 'Добавить блок' : 'Шаблоны профиля' }}</strong>
            <button type="button" @click="panel = null">✕</button>
          </div>

          <template v-if="panel === 'blocks'">
            <section v-for="group in paletteGroups" :key="group.id" class="pb-palette__group">
              <h4>{{ group.label }}</h4>
              <div class="pb-palette__grid">
                <button
                    v-for="[type, def] in group.types"
                    :key="type"
                    type="button"
                    class="pb-palette__item"
                    :disabled="def.single && usedSingles.has(type)"
                    @click="add(type)"
                >
                  <span class="pb-palette__icon">{{ def.icon }}</span>
                  <span class="pb-palette__text">
                    <strong>{{ def.label }}</strong>
                    <small>{{ def.single && usedSingles.has(type) ? 'Уже в профиле' : def.hint }}</small>
                  </span>
                </button>
              </div>
            </section>
          </template>

          <template v-else>
            <p class="pb-palette__note">Шаблон заменит раскладку и настройки страницы. До «Сохранить» всё можно отменить.</p>
            <div class="pb-presets">
              <button
                  v-for="preset in presetCards"
                  :key="preset.id"
                  type="button"
                  class="pb-preset"
                  :style="{ '--preset-color': preset.color }"
                  @click="applyPreset(preset)"
              >
                <!-- Мини-макет: шапка профиля и блоки шаблона -->
                <span class="pb-preset__preview">
                  <span class="pb-preset__header" :class="{ 'is-compact': preset.page.header === 'compact' }">
                    <i></i><b></b>
                  </span>
                  <span class="pb-preset__grid" :class="`pb-preset__grid--${preset.page.gap || 'normal'}`">
                    <i
                        v-for="(b, n) in preset.map"
                        :key="n"
                        :class="`is-${b.tone}`"
                        :style="{ gridColumn: `span ${widthSpan(b.width)}`, height: `${b.height}px` }"
                    ><em>{{ BLOCK_TYPES[b.type].icon }}</em></i>
                  </span>
                </span>

                <span class="pb-preset__info">
                  <strong>{{ preset.label }}</strong>
                  <small>{{ preset.hint }}</small>
                  <span class="pb-preset__tags">
                    <em>{{ preset.map.length }} блоков</em>
                    <em v-for="tag in preset.tags" :key="tag">{{ tag }}</em>
                  </span>
                </span>

                <span class="pb-preset__apply">Применить →</span>
              </button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
@import "./ProfileBuilderGrid.css";
</style>
