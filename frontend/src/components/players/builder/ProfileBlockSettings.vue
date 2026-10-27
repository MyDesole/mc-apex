<script setup>
/**
 * Панель ⚙ блока в конструкторе: сверху содержимое (у своих блоков),
 * ниже — оформление: фон, цвет, заголовок, выравнивание, высота, узор,
 * углы, наведение, шрифт и показ на телефоне. Меняет блок на месте.
 */
import { ref } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import TeammatePicker from '@/components/players/builder/TeammatePicker.vue'
import { BLOCK_TYPES, COLORS, STICKER_SPRITES, STYLE_OPTIONS, TONES } from '@/data/players/profileBlocks.js'

const props = defineProps({
  block: { type: Object, required: true },
})

const LIMITS = { facts: 10, bars: 8, timeline: 10 }

const NEW_ROW = {
  facts: () => ({ label: '', value: '' }),
  bars: () => ({ label: '', value: 50 }),
  timeline: () => ({ when: '', text: '' }),
}

const def = BLOCK_TYPES[props.block.type] ?? { group: 'profile', title: null }
const hasContent = def.group !== 'profile' && props.block.type !== 'guestbook'

// Блок из старой раскладки мог прийти без оформления
props.block.settings ??= {}
props.block.style ??= {}
const tab = ref(hasContent ? 'content' : 'look')

function addRow() {
  const items = (props.block.settings.items ??= [])
  if (items.length < LIMITS[props.block.type]) items.push(NEW_ROW[props.block.type]())
}

/** Для поля даты: ISO → значение datetime-local и обратно. */
function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function setDate(value) {
  props.block.settings.date = value ? new Date(value).toISOString() : ''
}
</script>

<template>
  <div class="pb-settings">
    <div v-if="hasContent" class="pb-settings__tabs">
      <button type="button" :class="{ 'is-on': tab === 'content' }" @click="tab = 'content'">Содержимое</button>
      <button type="button" :class="{ 'is-on': tab === 'look' }" @click="tab = 'look'">Оформление</button>
    </div>

    <!-- ================= Содержимое ================= -->
    <template v-if="tab === 'content'">
      <template v-if="block.type === 'text'">
        <textarea v-model="block.settings.body" maxlength="2000" rows="5" placeholder="Текст блока"></textarea>
        <div class="pb-option">
          <span class="pb-option__label">Размер текста</span>
          <div class="pb-option__chips">
            <button
                v-for="[value, label] in [['s', 'Мелкий'], ['m', 'Обычный'], ['l', 'Крупный'], ['xl', 'Огромный']]"
                :key="value"
                type="button"
                class="pb-chip"
                :class="{ 'is-on': block.settings.size === value }"
                @click="block.settings.size = value"
            >{{ label }}</button>
          </div>
        </div>
      </template>

      <template v-else-if="block.type === 'quote'">
        <textarea v-model="block.settings.text" maxlength="300" rows="3" placeholder="Цитата"></textarea>
        <input v-model="block.settings.author" maxlength="60" placeholder="Автор (необязательно)" />
      </template>

      <TeammatePicker v-else-if="block.type === 'players'" :block="block" />

      <template v-else-if="block.type in LIMITS">
        <div v-for="(item, i) in block.settings.items" :key="i" class="pb-settings__row">
          <template v-if="block.type === 'facts'">
            <input v-model="item.label" maxlength="30" placeholder="Что" class="pb-settings__short" />
            <input v-model="item.value" maxlength="80" placeholder="Значение" />
          </template>
          <template v-else-if="block.type === 'bars'">
            <input v-model="item.label" maxlength="30" placeholder="Навык" />
            <input v-model.number="item.value" type="range" min="0" max="100" class="pb-settings__range" />
            <span class="pb-settings__value">{{ item.value }}</span>
          </template>
          <template v-else-if="block.type === 'timeline'">
            <input v-model="item.when" maxlength="20" placeholder="Когда" class="pb-settings__short" />
            <input v-model="item.text" maxlength="120" placeholder="Что случилось" />
          </template>
          <button type="button" title="Убрать строку" @click="block.settings.items.splice(i, 1)">✕</button>
        </div>
        <button
            v-if="(block.settings.items || []).length < LIMITS[block.type]"
            type="button"
            class="pb-settings__add"
            @click="addRow"
        >＋ Строка</button>
      </template>

      <template v-else-if="block.type === 'countdown'">
        <input v-model="block.settings.title" maxlength="80" placeholder="До чего отсчёт? (Турнир, стрим…)" />
        <input
            type="datetime-local"
            :value="toLocalInput(block.settings.date)"
            @input="setDate($event.target.value)"
        />
      </template>

      <template v-else-if="block.type === 'video'">
        <input v-model="block.settings.url" maxlength="300" placeholder="https://youtube.com/watch?v=…" />
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
        <div class="pb-option__chips">
          <button
              v-for="[value, label] in [['line', 'Линия'], ['dots', 'Точки'], ['glow', 'Неон'], ['zigzag', 'Зигзаг'], ['blocks', 'Блоки']]"
              :key="value"
              type="button"
              class="pb-chip"
              :class="{ 'is-on': block.settings.style === value }"
              @click="block.settings.style = value"
          >{{ label }}</button>
        </div>
      </template>

      <template v-else-if="block.type === 'spacer'">
        <div class="pb-option__chips">
          <button
              v-for="[value, label] in [['s', 'Маленький'], ['m', 'Средний'], ['l', 'Большой']]"
              :key="value"
              type="button"
              class="pb-chip"
              :class="{ 'is-on': block.settings.size === value }"
              @click="block.settings.size = value"
          >{{ label }}</button>
        </div>
      </template>
    </template>

    <!-- ================= Оформление ================= -->
    <template v-else>
      <div class="pb-option">
        <span class="pb-option__label">Заголовок</span>
        <div class="pb-settings__row">
          <input v-model="block.style.title" maxlength="60" :placeholder="def.title || 'Без заголовка'" />
          <label class="pb-check">
            <input v-model="block.style.hide_title" type="checkbox" />
            Скрыть
          </label>
        </div>
      </div>

      <div class="pb-option">
        <span class="pb-option__label">Фон</span>
        <div class="pb-option__chips">
          <button
              v-for="tone in TONES"
              :key="tone.id"
              type="button"
              class="pb-chip"
              :class="{ 'is-on': block.tone === tone.id }"
              @click="block.tone = tone.id"
          >{{ tone.label }}</button>
        </div>
      </div>

      <div class="pb-option">
        <span class="pb-option__label">Цвет</span>
        <div class="pb-option__chips">
          <button
              v-for="color in COLORS"
              :key="color.id"
              type="button"
              class="pb-swatch"
              :class="{ 'is-on': block.style.color === color.id, 'pb-swatch--auto': !color.hex }"
              :style="color.hex ? { background: color.hex } : null"
              :title="color.label || color.id"
              @click="block.style.color = color.id"
          ></button>
        </div>
      </div>

      <div v-for="(option, key) in STYLE_OPTIONS" :key="key" class="pb-option">
        <span class="pb-option__label">{{ option.label }}</span>
        <div class="pb-option__chips">
          <button
              v-for="[value, label] in option.options"
              :key="value"
              type="button"
              class="pb-chip"
              :class="{ 'is-on': block.style[key] === value }"
              @click="block.style[key] = value"
          >{{ label }}</button>
        </div>
      </div>

      <label class="pb-check">
        <input v-model="block.style.hide_mobile" type="checkbox" />
        Не показывать на телефоне
      </label>
    </template>
  </div>
</template>
