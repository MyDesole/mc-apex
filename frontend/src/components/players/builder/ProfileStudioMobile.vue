<script setup>
/**
 * Студия профиля для телефона — упрощённая.
 *
 * Полноэкранная студия с панелями и холстом на телефоне неудобна, тут —
 * четыре вкладки под палец: «Блоки» (порядок, добавить, убрать, текст),
 * «Вид» (готовые палитры и шаблоны), «Инвентарь» (надеть и примерить),
 * «Профиль» (данные профиля). Тонкая настройка — на компьютере.
 * «Посмотреть» прячет студию и показывает профиль как есть.
 */
import { computed, ref } from 'vue'
import ProfileBlockSettings from '@/components/players/builder/ProfileBlockSettings.vue'
import StudioIcon from '@/components/players/builder/StudioIcon.vue'
import StudioInventory from '@/components/players/builder/StudioInventory.vue'
import { BLOCK_TYPES, GROUPS, PRESETS, THEME_PRESETS, WIDTHS } from '@/data/players/profileBlocks.js'

const props = defineProps({
  blocks: { type: Array, required: true },
  page: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
})

const emit = defineEmits(['add', 'move', 'remove', 'preset', 'publish', 'cancel', 'profile', 'preview'])

const TABS = [
  ['blocks', 'layers', 'Блоки'],
  ['look', 'palette', 'Вид'],
  ['inventory', 'bag', 'Инвентарь'],
  ['profile', 'user', 'Профиль'],
]

const tab = ref('blocks')
const openId = ref(null)
const adding = ref(false)

const usedSingles = computed(() => new Set(props.blocks.filter((b) => BLOCK_TYPES[b.type]?.single).map((b) => b.type)))

const addGroups = computed(() => GROUPS.map((group) => ({
  ...group,
  types: Object.entries(BLOCK_TYPES).filter(([type, def]) => def.group === group.id && !(def.single && usedSingles.value.has(type))),
})).filter((group) => group.types.length))

function add(type) {
  emit('add', type)
  adding.value = false
}

function widthLabel(id) {
  return WIDTHS.find((w) => w.id === id)?.label ?? ''
}

const activeTheme = computed(() => THEME_PRESETS.find((t) =>
  t.bg === props.page.bg && t.bg_from === props.page.bg_from && t.bg_to === props.page.bg_to,
)?.id)

function applyTheme(theme) {
  const { id, label, ...values } = theme
  Object.assign(props.page, values)
}

function resetTheme() {
  props.page.bg = 'none'
}
</script>

<template>
  <div class="ms">
    <header class="ms-top">
      <button type="button" class="ms-top__btn" @click="emit('cancel')">Отмена</button>
      <div class="ms-top__title">
        <strong>Студия</strong>
        <small>{{ dirty ? 'есть правки' : 'без изменений' }}</small>
      </div>
      <button type="button" class="ms-top__btn ms-top__btn--primary" :disabled="saving" @click="emit('publish')">
        {{ saving ? '…' : 'Опубликовать' }}
      </button>
    </header>

    <main class="ms-body">
      <!-- ============ Блоки ============ -->
      <template v-if="tab === 'blocks'">
        <button type="button" class="ms-add" @click="adding = true">
          <StudioIcon name="plus" :size="18" /> Добавить блок
        </button>

        <ol class="ms-list">
          <li v-for="(block, i) in blocks" :key="block.id" class="ms-item" :class="{ 'is-open': openId === block.id }">
            <div class="ms-item__row" @click="openId = openId === block.id ? null : block.id">
              <span class="ms-item__icon">{{ BLOCK_TYPES[block.type]?.icon }}</span>
              <span class="ms-item__name">
                <strong>{{ block.style?.title || BLOCK_TYPES[block.type]?.label }}</strong>
                <small>{{ BLOCK_TYPES[block.type]?.label }} · {{ widthLabel(block.width) }}</small>
              </span>
              <span class="ms-item__actions" @click.stop>
                <button type="button" :disabled="i === 0" aria-label="Выше" @click="emit('move', block, -1)"><StudioIcon name="up" :size="16" /></button>
                <button type="button" :disabled="i === blocks.length - 1" aria-label="Ниже" @click="emit('move', block, 1)"><StudioIcon name="down" :size="16" /></button>
              </span>
            </div>

            <div v-if="openId === block.id" class="ms-item__body">
              <ProfileBlockSettings :block="block" simple />
              <button type="button" class="ms-remove" @click="emit('remove', block); openId = null">
                <StudioIcon name="trash" :size="15" /> Убрать блок
              </button>
            </div>
          </li>
        </ol>

        <p v-if="!blocks.length" class="ms-note">Блоков нет — добавь первый или возьми шаблон во вкладке «Вид».</p>
      </template>

      <!-- ============ Вид ============ -->
      <template v-else-if="tab === 'look'">
        <section class="ms-section">
          <h3>Палитра</h3>
          <div class="ms-themes">
            <button type="button" class="ms-theme" :class="{ 'is-on': page.bg === 'none' }" @click="resetTheme">
              <span class="studio-swatch-bg"><em>Как в теме</em></span>
              <span>Без фона</span>
            </button>
            <button
                v-for="theme in THEME_PRESETS"
                :key="theme.id"
                type="button"
                class="ms-theme"
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

        <section class="ms-section">
          <h3>Шаблон раскладки</h3>
          <p class="ms-note">Заменит блоки и их порядок. До «Опубликовать» всё можно отменить.</p>
          <button
              v-for="preset in PRESETS"
              :key="preset.id"
              type="button"
              class="ms-preset"
              :style="{ '--preset-color': preset.color }"
              @click="emit('preset', preset)"
          >
            <strong>{{ preset.label }}</strong>
            <small>{{ preset.hint }}</small>
          </button>
        </section>
      </template>

      <!-- ============ Инвентарь ============ -->
      <StudioInventory v-else-if="tab === 'inventory'" />

      <!-- ============ Профиль ============ -->
      <section v-else class="ms-section">
        <h3>Данные профиля</h3>
        <p class="ms-note">Аватар и обложка, статус, описание, соцсети, любимые режимы и витрина ачивок.</p>
        <button type="button" class="ms-add" @click="emit('profile')">
          <StudioIcon name="user" :size="18" /> Изменить данные профиля
        </button>
        <p class="ms-note">Тонкая настройка блоков, шапки, шрифтов и анимаций — в студии на компьютере.</p>
      </section>
    </main>

    <button type="button" class="ms-preview" @click="emit('preview')">
      <StudioIcon name="eye" :size="16" /> Посмотреть
    </button>

    <nav class="ms-tabs">
      <button
          v-for="[id, icon, label] in TABS"
          :key="id"
          type="button"
          :class="{ 'is-on': tab === id }"
          @click="tab = id"
      >
        <StudioIcon :name="icon" :size="20" />
        <span>{{ label }}</span>
      </button>
    </nav>

    <!-- Выбор нового блока -->
    <Transition name="ms-sheet">
      <div v-if="adding" class="ms-sheet" @click.self="adding = false">
        <div class="ms-sheet__card">
          <header>
            <strong>Добавить блок</strong>
            <button type="button" aria-label="Закрыть" @click="adding = false"><StudioIcon name="close" :size="18" /></button>
          </header>
          <div class="ms-sheet__body">
            <section v-for="group in addGroups" :key="group.id">
              <h4>{{ group.label }}</h4>
              <button v-for="[type, def] in group.types" :key="type" type="button" class="ms-type" @click="add(type)">
                <span class="ms-item__icon">{{ def.icon }}</span>
                <span class="ms-item__name">
                  <strong>{{ def.label }}</strong>
                  <small>{{ def.hint }}</small>
                </span>
              </button>
            </section>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style>
.ms {
  --ms-accent: #8b5cf6;

  position: fixed;
  inset: 0;
  z-index: 1000;

  display: flex;
  flex-direction: column;

  color: #ece9f5;
  background: #0c0b12;

  font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
}

.ms button { font-family: inherit; }

.ms-top {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 10px;

  padding: calc(env(safe-area-inset-top) + 10px) 12px 10px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.ms-top__title {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ms-top__title strong { font-size: 15px; }
.ms-top__title small { color: #8d88a0; font-size: 11px; }

.ms-top__btn {
  min-height: 36px;
  padding: 0 12px;

  color: #c9c4d8;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;

  font-size: 13px;
  font-weight: 700;
}

.ms-top__btn--primary {
  color: #fff;
  background: linear-gradient(135deg, #a78bfa, #7c3aed);
  border-color: transparent;
}

.ms-top__btn:disabled { opacity: 0.6; }

.ms-body {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;

  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 14px 12px 110px;
}

.ms-add {
  min-height: 46px;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  color: #fff;
  background: color-mix(in srgb, var(--ms-accent) 22%, transparent);
  border: 1px dashed color-mix(in srgb, var(--ms-accent) 60%, transparent);
  border-radius: 14px;

  font-size: 14px;
  font-weight: 800;
}

.ms-list {
  margin: 0;
  padding: 0;
  list-style: none;

  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ms-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
}

.ms-item.is-open { border-color: color-mix(in srgb, var(--ms-accent) 55%, transparent); }

.ms-item__row,
.ms-type {
  width: 100%;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 10px 10px 10px 12px;

  color: inherit;
  background: none;
  border: 0;

  text-align: left;
}

.ms-item__icon {
  width: 36px;
  height: 36px;

  display: grid;
  place-items: center;
  flex-shrink: 0;

  color: #d8ccff;
  background: color-mix(in srgb, var(--ms-accent) 18%, transparent);
  border-radius: 10px;

  font-family: "Segoe UI Symbol", "Noto Sans Symbols 2", "DejaVu Sans", sans-serif;
  font-variant-emoji: text;
}

.ms-item__name {
  flex: 1;
  min-width: 0;

  display: flex;
  flex-direction: column;
}

.ms-item__name strong,
.ms-item__name small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-item__name strong { font-size: 14px; }
.ms-item__name small { color: #8d88a0; font-size: 11.5px; }

.ms-item__actions {
  display: flex;
  gap: 4px;
}

.ms-item__actions button {
  width: 38px;
  height: 38px;

  display: grid;
  place-items: center;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.05);
  border: 0;
  border-radius: 10px;
}

.ms-item__actions button:disabled { opacity: 0.25; }

.ms-item__body {
  display: flex;
  flex-direction: column;
  gap: 12px;

  padding: 4px 12px 12px;
}

.ms-item__body .pb-settings {
  padding: 0;
  overflow: visible;
}

.ms-remove {
  min-height: 40px;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  color: #fca5a5;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 10px;

  font-size: 13px;
  font-weight: 700;
}

.ms-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ms-section h3 {
  margin: 6px 0 0;

  color: #8d88a0;

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.ms-note {
  margin: 0;
  color: #8d88a0;
  font-size: 12.5px;
  line-height: 1.45;
}

.ms-themes {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.ms-theme {
  display: flex;
  flex-direction: column;
  gap: 6px;

  padding: 5px 5px 8px;

  color: #d6d2e2;
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;

  font-size: 12px;
  font-weight: 700;
}

.ms-theme.is-on { border-color: var(--ms-accent); box-shadow: 0 0 0 1px var(--ms-accent); }
.ms-theme .studio-swatch-bg { height: 56px; }

.ms-preset {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;

  padding: 12px 14px;

  color: inherit;
  background:
      radial-gradient(ellipse at 0 0, color-mix(in srgb, var(--preset-color, #8b5cf6) 22%, transparent), transparent 60%),
      rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;

  text-align: left;
}

.ms-preset strong { font-size: 14px; }
.ms-preset small { color: #8d88a0; font-size: 12px; }

.ms-preview {
  position: fixed;
  right: 14px;
  bottom: calc(env(safe-area-inset-bottom) + 78px);

  display: flex;
  align-items: center;
  gap: 6px;

  padding: 10px 16px;

  color: #fff;
  background: rgba(30, 27, 44, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 999px;
  box-shadow: 0 10px 26px rgba(0, 0, 0, 0.5);

  font-size: 13px;
  font-weight: 700;
}

.ms-tabs {
  display: grid;
  grid-template-columns: repeat(4, 1fr);

  padding: 6px 6px calc(env(safe-area-inset-bottom) + 6px);

  background: #0c0b12;
  border-top: 1px solid rgba(255, 255, 255, 0.07);
}

.ms-tabs button {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;

  padding: 6px 0;

  color: #8d88a0;
  background: none;
  border: 0;
  border-radius: 12px;

  font-size: 11px;
  font-weight: 700;
}

.ms-tabs button.is-on {
  color: #fff;
  background: color-mix(in srgb, var(--ms-accent) 18%, transparent);
}

/* Шторка выбора блока */
.ms-sheet {
  position: fixed;
  inset: 0;
  z-index: 2;

  display: flex;
  align-items: flex-end;

  background: rgba(0, 0, 0, 0.6);
}

.ms-sheet__card {
  width: 100%;
  max-height: 82vh;

  display: flex;
  flex-direction: column;

  background: #111019;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px 20px 0 0;
}

.ms-sheet__card header {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 14px 14px 10px;
}

.ms-sheet__card header strong { font-size: 16px; }

.ms-sheet__card header button {
  width: 36px;
  height: 36px;

  display: grid;
  place-items: center;

  color: #c9c4d8;
  background: rgba(255, 255, 255, 0.06);
  border: 0;
  border-radius: 10px;
}

.ms-sheet__body {
  overflow-y: auto;
  padding: 0 10px calc(env(safe-area-inset-bottom) + 16px);
}

.ms-sheet__body h4 {
  margin: 12px 4px 6px;

  color: #8d88a0;

  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.ms-type {
  border-radius: 12px;
}

.ms-type:active { background: rgba(255, 255, 255, 0.05); }

.ms-sheet-enter-active,
.ms-sheet-leave-active { transition: opacity 0.2s ease; }

.ms-sheet-enter-active .ms-sheet__card,
.ms-sheet-leave-active .ms-sheet__card { transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); }

.ms-sheet-enter-from,
.ms-sheet-leave-to { opacity: 0; }

.ms-sheet-enter-from .ms-sheet__card,
.ms-sheet-leave-to .ms-sheet__card { transform: translateY(40px); }
</style>
