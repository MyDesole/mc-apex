<script setup>
/**
 * Панель ⚙ блока в конструкторе: сверху содержимое (у своих блоков),
 * ниже — оформление: фон, цвет, заголовок, выравнивание, высота, узор,
 * углы, наведение, шрифт и показ на телефоне. Меняет блок на месте.
 */
import { computed, ref } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import StudioIcon from '@/components/players/builder/StudioIcon.vue'
import TeammatePicker from '@/components/players/builder/TeammatePicker.vue'
import { BLOCK_TYPES, COLORS, STICKER_SPRITES, STYLE_OPTIONS, TONES } from '@/data/players/profileBlocks.js'

const props = defineProps({
  block: { type: Object, required: true },
})

const LIMITS = { facts: 10, bars: 8, timeline: 10 }

// Оформление по разделам, а не одной стеной кнопок
const LOOK_GROUPS = [
  { label: 'Форма', keys: ['radius', 'height', 'align'] },
  { label: 'Текст', keys: ['font', 'text'] },
  { label: 'Эффекты', keys: ['pattern', 'border', 'hover'], sliders: true },
]

// Готовые стили блока: фон, рамка, узор, наведение, углы и шрифт разом
const LOOKS = [
  { id: 'clean', label: 'Чистый', tone: 'default', style: { border: 'none', pattern: 'none', hover: 'lift', radius: 'inherit', font: 'default', text: 'auto' } },
  { id: 'neon', label: 'Неон', tone: 'neon', style: { border: 'glow', pattern: 'none', hover: 'glow', radius: 'round', font: 'display', text: 'accent' } },
  { id: 'glass', label: 'Стекло', tone: 'glass', style: { border: 'shine', pattern: 'none', hover: 'lift', radius: 'round', font: 'default', text: 'light' } },
  { id: 'holo', label: 'Голограмма', tone: 'gradient', style: { border: 'rainbow', pattern: 'aurora', hover: 'tilt', radius: 'round', font: 'display', text: 'light' } },
  { id: 'paper', label: 'Бумага', tone: 'paper', style: { border: 'none', pattern: 'none', hover: 'lift', radius: 'sharp', font: 'serif', text: 'auto' } },
  { id: 'terminal', label: 'Терминал', tone: 'outline', style: { border: 'none', pattern: 'grid', hover: 'none', radius: 'sharp', font: 'mono', text: 'accent' } },
  { id: 'pulse', label: 'Пульс', tone: 'accent', style: { border: 'pulse', pattern: 'dots', hover: 'glow', radius: 'round', font: 'default', text: 'auto' } },
  { id: 'bare', label: 'Без рамки', tone: 'transparent', style: { border: 'none', pattern: 'none', hover: 'none', radius: 'inherit', font: 'default', text: 'auto' } },
]

function applyLook(look) {
  props.block.tone = look.tone
  Object.assign(props.block.style, look.style)
}

// Плитки фона красим цветом блока
const accentVar = computed(() => {
  const hex = props.block.style.hex || COLORS.find((c) => c.id === props.block.style.color)?.hex
  return hex ? { '--accent-color': hex } : null
})

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
if (props.block.type === 'poll') props.block.settings.options ??= ['', '']
if (props.block.type === 'goals') props.block.settings.items ??= []
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

      <template v-else-if="block.type === 'poll'">
        <input v-model="block.settings.question" maxlength="120" placeholder="Вопрос" />
        <div v-for="(_, i) in block.settings.options" :key="i" class="pb-settings__row">
          <input v-model="block.settings.options[i]" maxlength="60" :placeholder="`Вариант ${i + 1}`" />
          <button v-if="block.settings.options.length > 2" type="button" @click="block.settings.options.splice(i, 1)">✕</button>
        </div>
        <button
            v-if="block.settings.options.length < 4"
            type="button"
            class="pb-settings__add"
            @click="block.settings.options.push('')"
        >＋ Вариант</button>
        <p class="pb-settings__hint">Голоса привязаны к номеру варианта: если поменять варианты местами, голоса останутся на месте.</p>
      </template>

      <template v-else-if="block.type === 'goals'">
        <div v-for="(item, i) in block.settings.items" :key="i" class="pb-settings__row">
          <label class="pb-check"><input v-model="item.done" type="checkbox" /></label>
          <input v-model="item.text" maxlength="80" placeholder="Цель" />
          <button type="button" @click="block.settings.items.splice(i, 1)">✕</button>
        </div>
        <button
            v-if="block.settings.items.length < 10"
            type="button"
            class="pb-settings__add"
            @click="block.settings.items.push({ text: '', done: false })"
        >＋ Цель</button>
      </template>

      <template v-else-if="block.type === 'counter'">
        <div class="pb-settings__row">
          <input v-model="block.settings.emoji" maxlength="4" class="pb-settings__emoji" placeholder="🏆" />
          <input v-model="block.settings.value" maxlength="12" placeholder="1000" />
        </div>
        <input v-model="block.settings.label" maxlength="40" placeholder="Подпись: побед, часов, рекорд…" />
      </template>

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
      <section class="studio-group">
        <h4>Стиль в один клик</h4>
        <div class="studio-tiles studio-tiles--4">
          <button
              v-for="look in LOOKS"
              :key="look.id"
              type="button"
              class="studio-tile"
              :style="accentVar"
              @click="applyLook(look)"
          >
            <span class="studio-tone" :class="[`pb-tone--${look.tone}`, `pb-border--${look.style.border}`]"><i></i><i></i></span>
            <span class="studio-tile__label">{{ look.label }}</span>
          </button>
        </div>
      </section>

      <section class="studio-group">
        <h4>Заголовок</h4>
        <div class="pb-settings__row">
          <input v-model="block.style.icon" maxlength="4" class="pb-settings__emoji" placeholder="★" title="Значок перед заголовком" />
          <input v-model="block.style.title" maxlength="60" :placeholder="def.title || 'Без заголовка'" />
        </div>
        <label class="studio-switch">
          <input v-model="block.style.hide_title" type="checkbox" />
          <span></span>
          Скрыть заголовок
        </label>
      </section>

      <section class="studio-group">
        <h4>Фон</h4>
        <div class="studio-tiles studio-tiles--4">
          <button
              v-for="tone in TONES"
              :key="tone.id"
              type="button"
              class="studio-tile"
              :class="{ 'is-on': block.tone === tone.id }"
              :style="accentVar"
              @click="block.tone = tone.id"
          >
            <span class="studio-tone" :class="`pb-tone--${tone.id}`"><i></i><i></i></span>
            <span class="studio-tile__label">{{ tone.label }}</span>
          </button>
        </div>
      </section>

      <section class="studio-group">
        <h4>Цвет</h4>
        <div class="studio-accent">
          <button
              v-for="color in COLORS"
              :key="color.id"
              type="button"
              class="studio-dot"
              :class="{ 'is-on': !block.style.hex && block.style.color === color.id, 'studio-dot--auto': !color.hex }"
              :style="color.hex ? { background: color.hex } : null"
              :title="color.label || color.id"
              @click="block.style.color = color.id; block.style.hex = ''"
          ></button>
          <label class="studio-dot studio-dot--picker" :class="{ 'is-on': block.style.hex }" :style="block.style.hex ? { background: block.style.hex } : null" title="Любой цвет">
            <input type="color" :value="block.style.hex || '#8b5cf6'" @input="block.style.hex = $event.target.value" />
            <StudioIcon v-if="!block.style.hex" name="plus" :size="14" />
          </label>
        </div>
      </section>

      <section v-for="group in LOOK_GROUPS" :key="group.label" class="studio-group">
        <h4>{{ group.label }}</h4>
        <template v-for="key in group.keys" :key="key">
          <span class="studio-label">{{ STYLE_OPTIONS[key].label }}</span>
          <div class="studio-seg" :class="{ 'studio-seg--wrap': STYLE_OPTIONS[key].options.length > 4 }">
            <button
                v-for="[value, label] in STYLE_OPTIONS[key].options"
                :key="value"
                type="button"
                :class="{ 'is-on': block.style[key] === value }"
                @click="block.style[key] = value"
            >{{ label }}</button>
          </div>
        </template>

        <template v-if="group.sliders">
          <label class="studio-slider">
            <span>Наклон <b>{{ block.style.tilt || 0 }}°</b></span>
            <input v-model.number="block.style.tilt" type="range" min="-4" max="4" step="1" />
          </label>
          <label class="studio-slider">
            <span>Прозрачность <b>{{ block.style.opacity ?? 100 }}%</b></span>
            <input v-model.number="block.style.opacity" type="range" min="30" max="100" step="5" />
          </label>
          <p class="studio-note">Наклон виден гостям, в студии блоки стоят ровно.</p>
        </template>
      </section>

      <section class="studio-group">
        <h4>Телефон</h4>
        <label class="studio-switch">
          <input v-model="block.style.hide_mobile" type="checkbox" />
          <span></span>
          Не показывать на телефоне
        </label>
      </section>
    </template>
  </div>
</template>
