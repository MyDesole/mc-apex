<script setup>
/**
 * Выбор оформления подсветки клана.
 *
 * Показывает, что уже куплено, и позволяет применить это одним нажатием:
 * лидеру не нужно искать предметы в инвентаре. Рядом — предпросмотр,
 * чтобы было видно, как подсветка будет выглядеть.
 */
import { computed, ref, watch } from 'vue'
import { shopApi } from '@/services/shop.js'
import { alert as alertDialog } from '@/utils/dialog.js'
import {
  HIGHLIGHT_COLORS,
  HIGHLIGHT_EFFECTS,
  DEFAULT_HIGHLIGHT_COLOR,
  DEFAULT_HIGHLIGHT_EFFECT,
  highlightColor,
  highlightEffect,
  stylePreview,
} from '@/data/clanHighlight.js'

const props = defineProps({
  clan: { type: Object, required: true },
  styles: {
    type: Object,
    default: () => ({ colors: [], effects: [] }),
  },
})

const emit = defineEmits(['updated'])

const saving = ref(null)
const error = ref('')

// Что сейчас применено у клана
const currentColor = computed(() => props.clan.highlight_color || DEFAULT_HIGHLIGHT_COLOR)
const currentEffect = computed(() => props.clan.highlight_effect || DEFAULT_HIGHLIGHT_EFFECT)

// Предпросмотр: показываем то, что выбрано, а не только применённое
const previewColor = ref(currentColor.value)
const previewEffect = ref(currentEffect.value)

watch(currentColor, (value) => { previewColor.value = value })
watch(currentEffect, (value) => { previewEffect.value = value })

const preview = computed(() => stylePreview(previewColor.value, previewEffect.value))

const isActive = computed(() => Boolean(props.clan.is_highlighted))

const ownedColors = computed(() => props.styles?.colors ?? [])
const ownedEffects = computed(() => props.styles?.effects ?? [])

const hasOwned = computed(() => ownedColors.value.length > 0 || ownedEffects.value.length > 0)

/** Применить купленный цвет или эффект. */
async function apply(entry) {
  if (!entry || saving.value) return

  saving.value = entry.id
  error.value = ''

  try {
    await shopApi.equip(entry.id)

    if (entry.kind === 'color') previewColor.value = entry.value
    if (entry.kind === 'effect') previewEffect.value = entry.value

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось применить оформление.'
  } finally {
    saving.value = null
  }
}

/** Вернуть базовое оформление. */
async function reset(entry) {
  if (!entry || saving.value) return

  saving.value = entry.id
  error.value = ''

  try {
    await shopApi.unequip(entry.id)

    if (entry.kind === 'color') previewColor.value = DEFAULT_HIGHLIGHT_COLOR
    if (entry.kind === 'effect') previewEffect.value = DEFAULT_HIGHLIGHT_EFFECT

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не удалось сбросить оформление.'
  } finally {
    saving.value = null
  }
}

function colorValue(key) {
  return highlightColor(key).color
}

function effectName(key) {
  return highlightEffect(key).name
}
</script>

<template>
  <section class="style-picker">
    <header class="style-picker__head">
      <h4>Оформление подсветки</h4>
      <p>
        Цвет и эффект покупаются в магазине, а применяются здесь.
        Можно менять когда угодно.
      </p>
    </header>

    <p v-if="!isActive" class="style-picker__notice">
      Подсветка не активна. Сначала купите её в магазине — оформление
      начнёт действовать сразу после этого.
    </p>

    <p v-if="error" class="style-picker__error">{{ error }}</p>

    <!-- Предпросмотр -->
    <div class="preview" :style="{ '--hl-color': preview['--hl-color'], '--hl-glow': preview['--hl-glow'] }">
      <div class="preview__card">
        <div class="preview__accent"></div>

        <div class="preview__body">
          <strong>{{ clan.name }}</strong>
          <span>[{{ clan.tag }}]</span>
        </div>
      </div>

      <div class="preview__caption">
        {{ highlightColor(previewColor).name }} · {{ effectName(previewEffect) }}
      </div>
    </div>

    <!-- Цвета -->
    <div class="style-group">
      <div class="style-group__title">Цвет</div>

      <div v-if="ownedColors.length" class="chips">
        <button
            v-for="entry in ownedColors"
            :key="entry.id"
            type="button"
            class="chip"
            :class="{ 'chip--active': entry.equipped }"
            :disabled="!!saving || !isActive"
            @click="apply(entry)"
        >
          <span class="chip__dot" :style="{ background: colorValue(entry.value) }"></span>
          {{ entry.name }}
          <span v-if="entry.equipped" class="chip__mark">✓</span>
        </button>
      </div>

      <p v-else class="style-group__empty">
        Цвета не куплены. Их можно взять в магазине.
      </p>
    </div>

    <!-- Эффекты -->
    <div class="style-group">
      <div class="style-group__title">Эффект</div>

      <div v-if="ownedEffects.length" class="chips">
        <button
            v-for="entry in ownedEffects"
            :key="entry.id"
            type="button"
            class="chip"
            :class="{ 'chip--active': entry.equipped }"
            :disabled="!!saving || !isActive"
            @click="apply(entry)"
        >
          {{ entry.name }}
          <span v-if="entry.equipped" class="chip__mark">✓</span>
        </button>
      </div>

      <p v-else class="style-group__empty">
        Эффекты не куплены. Их можно взять в магазине.
      </p>
    </div>

    <!-- Сброс -->
    <div v-if="hasOwned" class="style-picker__foot">
      <button
          v-for="entry in [...ownedColors, ...ownedEffects].filter((e) => e.equipped)"
          :key="`reset-${entry.id}`"
          type="button"
          class="reset-btn"
          :disabled="!!saving || !isActive"
          @click="reset(entry)"
      >
        Вернуть базовый {{ entry.kind === 'color' ? 'цвет' : 'эффект' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.style-picker {
  margin-bottom: 14px;
  padding: 14px 15px;
  background: rgba(255, 255, 255, .025);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.style-picker__head h4 {
  margin: 0 0 4px;
  color: var(--text);
  font-size: 13px;
  font-weight: 800;
}

.style-picker__head p {
  margin: 0 0 12px;
  color: var(--text-dim);
  font-size: 11.5px;
  line-height: 1.5;
}

.style-picker__notice {
  margin: 0 0 12px;
  padding: 8px 11px;
  color: #fcd34d;
  background: rgba(250, 204, 21, .08);
  border: 1px solid rgba(250, 204, 21, .22);
  border-radius: 9px;
  font-size: 11.5px;
  line-height: 1.5;
}

.style-picker__error {
  margin: 0 0 10px;
  color: #fca5a5;
  font-size: 11.5px;
}

/* Предпросмотр */
.preview {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.preview__card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 190px;
  padding: 10px 14px 10px 16px;
  background: var(--bg-card);
  border: 1px solid color-mix(in srgb, var(--hl-color) 40%, transparent);
  border-radius: 11px;
  box-shadow: 0 0 18px -8px var(--hl-glow);
  overflow: hidden;
}

.preview__accent {
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  background: var(--hl-color);
  border-radius: 2px;
}

.preview__body {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.preview__body strong {
  color: var(--text);
  font-size: 12.5px;
}

.preview__body span {
  color: var(--hl-color);
  font-size: 11px;
  font-weight: 700;
}

.preview__caption {
  color: var(--text-dim);
  font-size: 11.5px;
}

/* Группы */
.style-group {
  margin-bottom: 12px;
}

.style-group__title {
  margin-bottom: 7px;
  color: var(--text-dim);
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.style-group__empty {
  margin: 0;
  color: var(--text-dim);
  font-size: 11.5px;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 12px;
  color: var(--text-dim);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 9px;
  font-size: 12px;
  font-family: inherit;
  cursor: pointer;
  transition: color .16s ease, border-color .16s ease, background .16s ease;
}

.chip:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover, #343443);
}

.chip:disabled {
  opacity: .5;
  cursor: not-allowed;
}

.chip--active {
  color: var(--text);
  background: rgba(124, 58, 237, .12);
  border-color: var(--accent);
}

.chip__dot {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  box-shadow: 0 0 6px rgba(0, 0, 0, .4);
}

.chip__mark {
  color: var(--accent-light);
  font-weight: 800;
}

.style-picker__foot {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.reset-btn {
  padding: 6px 11px;
  color: var(--text-dim);
  background: transparent;
  border: 1px dashed var(--border);
  border-radius: 8px;
  font-size: 11.5px;
  font-family: inherit;
  cursor: pointer;
}

.reset-btn:hover:not(:disabled) {
  color: var(--text);
  border-color: var(--border-hover, #343443);
}

.reset-btn:disabled {
  opacity: .5;
  cursor: not-allowed;
}
</style>
