<script setup>
/**
 * Сетка профиля из блоков и её редактор.
 *
 * Показ: блоки по порядку на сетке из 12 колонок; ширина блока — треть,
 * половина, две трети или вся строка; на телефоне все во всю ширину.
 *
 * Редактор (editing): блоки перетаскиваются мышью или стрелками, ширина
 * меняется кнопками, у каждого блока — оформление, настройки и удаление;
 * «Добавить блок» открывает палитру. Сохраняет родитель (emit save).
 *
 * Содержимое готовых блоков (статистика, друзья…) рисует родитель через
 * слот #builtin — их данные живут в PlayerCard.
 */
import { computed, ref, watch } from 'vue'
import ProfileCustomBlock from '@/components/players/builder/ProfileCustomBlock.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import {
  BLOCK_TYPES, WIDTHS, TONES, STICKER_SPRITES,
  defaultBlocks, makeBlock, widthSpan,
} from '@/data/players/profileBlocks.js'

const props = defineProps({
  blocks: { type: Array, default: null },
  user: { type: Object, required: true },
  editing: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'cancel', 'reset'])

const draft = ref([])
const openSettings = ref(null)
const showPalette = ref(false)
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
        draft.value = clone(props.blocks?.length ? props.blocks : defaultBlocks())
        openSettings.value = null
      }
    },
    { immediate: true },
)

const list = computed(() => (props.editing ? draft.value : (props.blocks ?? [])))

const usedBuiltins = computed(() => new Set(draft.value.filter((b) => BLOCK_TYPES[b.type]?.builtin).map((b) => b.type)))

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

/* ---------- Ширина, оформление, удаление ---------- */

function resize(block, delta) {
  const i = WIDTHS.findIndex((w) => w.id === block.width)
  const next = WIDTHS[Math.min(WIDTHS.length - 1, Math.max(0, i + delta))]
  block.width = next.id
}

function cycleTone(block) {
  const i = TONES.findIndex((t) => t.id === block.tone)
  block.tone = TONES[(i + 1) % TONES.length].id
}

function remove(block) {
  draft.value.splice(indexOf(block.id), 1)
  if (openSettings.value === block.id) openSettings.value = null
}

function add(type) {
  const block = makeBlock(type)
  draft.value.push(block)
  showPalette.value = false

  // Новый блок с настройками — сразу открываем их
  if (Object.keys(block.settings).length) openSettings.value = block.id
}

function toneLabel(block) {
  return TONES.find((t) => t.id === block.tone)?.label ?? 'Обычный'
}

/* ---------- Ссылки ---------- */

function addLink(block) {
  block.settings.items ??= []
  if (block.settings.items.length < 8) block.settings.items.push({ label: '', url: 'https://' })
}

/** Для поля даты: ISO → значение datetime-local и обратно. */
function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function setDate(block, value) {
  block.settings.date = value ? new Date(value).toISOString() : ''
}
</script>

<template>
  <div class="pb" :class="{ 'pb--editing': editing }">
    <!-- Панель конструктора -->
    <div v-if="editing" class="pb-toolbar">
      <div class="pb-toolbar__title">
        <strong>Конструктор профиля</strong>
        <span>Перетаскивай блоки, меняй ширину, добавляй новые</span>
      </div>

      <div class="pb-toolbar__actions">
        <button type="button" class="pb-btn pb-btn--add" @click="showPalette = true">＋ Блок</button>
        <button type="button" class="pb-btn pb-btn--ghost" title="Вернуть обычный профиль" @click="emit('reset')">Сбросить</button>
        <button type="button" class="pb-btn pb-btn--ghost" @click="emit('cancel')">Отмена</button>
        <button type="button" class="pb-btn pb-btn--save" :disabled="saving" @click="emit('save', clone(draft))">
          {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </button>
      </div>
    </div>

    <!-- Сетка -->
    <div class="pb-grid">
      <section
          v-for="block in list"
          :key="block.id"
          class="pb-block"
          :class="[
            `pb-block--${block.type}`,
            `pb-tone--${block.tone || 'default'}`,
            { 'is-dragging': dragId === block.id, 'is-over': overId === block.id },
          ]"
          :style="{ '--span': widthSpan(block.width) }"
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
          <span class="pb-controls__name">{{ BLOCK_TYPES[block.type]?.label }}</span>

          <div class="pb-controls__group">
            <button type="button" title="Выше" @click="move(block, -1)">↑</button>
            <button type="button" title="Ниже" @click="move(block, 1)">↓</button>
          </div>

          <div class="pb-controls__group">
            <button type="button" title="Уже" @click="resize(block, -1)">−</button>
            <span class="pb-controls__width">{{ WIDTHS.find((w) => w.id === block.width)?.label }}</span>
            <button type="button" title="Шире" @click="resize(block, 1)">+</button>
          </div>

          <button type="button" class="pb-controls__tone" title="Оформление блока" @click="cycleTone(block)">
            ◐ {{ toneLabel(block) }}
          </button>

          <button
              v-if="Object.keys(block.settings || {}).length"
              type="button"
              :class="{ 'is-on': openSettings === block.id }"
              title="Настройки"
              @click="openSettings = openSettings === block.id ? null : block.id"
          >⚙</button>

          <button type="button" class="pb-controls__remove" title="Убрать блок" @click="remove(block)">✕</button>
        </div>

        <!-- Настройки блока -->
        <div v-if="editing && openSettings === block.id" class="pb-settings" @mousedown.stop>
          <template v-if="block.type === 'text'">
            <input v-model="block.settings.title" maxlength="80" placeholder="Заголовок" />
            <textarea v-model="block.settings.body" maxlength="2000" rows="4" placeholder="Текст блока"></textarea>
          </template>

          <template v-else-if="block.type === 'quote'">
            <textarea v-model="block.settings.text" maxlength="300" rows="3" placeholder="Цитата"></textarea>
            <input v-model="block.settings.author" maxlength="60" placeholder="Автор (необязательно)" />
          </template>

          <template v-else-if="block.type === 'links'">
            <div v-for="(item, i) in block.settings.items" :key="i" class="pb-settings__row">
              <input v-model="item.label" maxlength="40" placeholder="Надпись" />
              <input v-model="item.url" maxlength="300" placeholder="https://…" />
              <button type="button" @click="block.settings.items.splice(i, 1)">✕</button>
            </div>
            <button
                v-if="(block.settings.items || []).length < 8"
                type="button"
                class="pb-settings__add"
                @click="addLink(block)"
            >＋ Кнопка</button>
          </template>

          <template v-else-if="block.type === 'now'">
            <div class="pb-settings__row">
              <input v-model="block.settings.emoji" maxlength="8" class="pb-settings__emoji" placeholder="🎮" />
              <input v-model="block.settings.text" maxlength="120" placeholder="Играю в бридж с друзьями" />
            </div>
          </template>

          <template v-else-if="block.type === 'countdown'">
            <input v-model="block.settings.title" maxlength="80" placeholder="До чего отсчёт? (Турнир, стрим…)" />
            <input
                type="datetime-local"
                :value="toLocalInput(block.settings.date)"
                @input="setDate(block, $event.target.value)"
            />
          </template>

          <template v-else-if="block.type === 'video'">
            <input v-model="block.settings.url" maxlength="300" placeholder="https://youtube.com/watch?v=…" />
          </template>

          <template v-else-if="block.type === 'guestbook'">
            <input v-model="block.settings.title" maxlength="60" placeholder="Заголовок книги" />
          </template>

          <template v-else-if="block.type === 'sticker'">
            <div class="pb-settings__sprites">
              <button
                  v-for="sprite in STICKER_SPRITES"
                  :key="sprite"
                  type="button"
                  :class="{ 'is-on': block.settings.sprite === sprite }"
                  @click="block.settings.sprite = sprite"
              >
                <PixelSprite :sprite="sprite" :size="22" />
              </button>
            </div>
            <input v-model="block.settings.caption" maxlength="40" placeholder="Подпись" />
          </template>

          <template v-else-if="block.type === 'divider'">
            <div class="pb-settings__row">
              <button
                  v-for="style in [['line', 'Линия'], ['dots', 'Точки'], ['glow', 'Неон']]"
                  :key="style[0]"
                  type="button"
                  class="pb-settings__chip"
                  :class="{ 'is-on': block.settings.style === style[0] }"
                  @click="block.settings.style = style[0]"
              >{{ style[1] }}</button>
            </div>
          </template>
        </div>

        <!-- Содержимое -->
        <div class="pb-block__body">
          <slot v-if="BLOCK_TYPES[block.type]?.builtin" name="builtin" :block="block" />
          <ProfileCustomBlock v-else :block="block" :user="user" :editing="editing" />
        </div>
      </section>

      <div v-if="editing && !list.length" class="pb-empty">
        Профиль пуст — добавь первый блок кнопкой «＋ Блок».
      </div>
    </div>

    <!-- Палитра блоков -->
    <Teleport to="body">
      <div v-if="showPalette" class="pb-palette" @click.self="showPalette = false">
        <div class="pb-palette__card">
          <div class="pb-palette__head">
            <strong>Добавить блок</strong>
            <button type="button" @click="showPalette = false">✕</button>
          </div>

          <div class="pb-palette__grid">
            <button
                v-for="(def, type) in BLOCK_TYPES"
                :key="type"
                type="button"
                class="pb-palette__item"
                :disabled="def.builtin && usedBuiltins.has(type)"
                @click="add(type)"
            >
              <span class="pb-palette__icon">{{ def.icon }}</span>
              <span class="pb-palette__text">
                <strong>{{ def.label }}</strong>
                <small>{{ def.builtin && usedBuiltins.has(type) ? 'Уже в профиле' : def.hint }}</small>
              </span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
@import "./ProfileBuilderGrid.css";
</style>
