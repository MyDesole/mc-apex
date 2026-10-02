<script setup>
/**
 * Выбор оформления подсветки клана.
 *
 * Показывает, что уже куплено, и позволяет применить это одним нажатием:
 * лидеру не нужно искать предметы в инвентаре. Рядом — предпросмотр,
 * чтобы было видно, как подсветка будет выглядеть.
 */
import { computed, ref, watch } from 'vue'
import { shopApi } from '@/services/shop/shop.js'
import { alert as alertDialog } from '@/utils/dialog.js'
import {
  HIGHLIGHT_COLORS,
  HIGHLIGHT_EFFECTS,
  DEFAULT_HIGHLIGHT_COLOR,
  DEFAULT_HIGHLIGHT_EFFECT,
  highlightColor,
  highlightEffect,
  stylePreview,
} from '@/data/clan/clanHighlight.js'

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
@import "@/components/clan/dialogs/ClanHighlightStylePicker.css";
</style>
