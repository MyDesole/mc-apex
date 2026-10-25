<script setup>
/**
 * Пиксельная иконка 16×16.
 *
 * Пиксели одного цвета в строке склеиваются в один прямоугольник —
 * так в SVG выходит десяток элементов, а не двести пятьдесят шесть.
 * Неизвестный ключ (например, старое эмодзи) рисуется звездой.
 */
import { computed } from 'vue'
import { FIXED_COLORS, PIXEL_ICONS } from '@/data/achievements/pixelIcons.js'

const props = defineProps({
  icon: { type: String, default: 'star' },
  // Основной цвет иконки — цвет ачивки
  color: { type: String, default: '#a78bfa' },
  size: { type: Number, default: 32 },
  // Закрытая ачивка: силуэт без цвета
  muted: { type: Boolean, default: false },
})

function mix(hex, target, amount) {
  const value = /^#?([0-9a-f]{6})$/i.exec(hex || '')?.[1] ?? 'a78bfa'
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(value.slice(i, i + 2), 16))
  const t = target === 'white' ? 255 : 0
  const blend = (c) => Math.round(c + (t - c) * amount)

  return `rgb(${blend(r)}, ${blend(g)}, ${blend(b)})`
}

const palette = computed(() => {
  if (props.muted) {
    // Силуэт: контур остаётся, всё остальное — оттенки серого
    return {
      o: '#0d0c11', m: '#3a3546', l: '#4a4458', d: '#2c2836', w: '#575069',
      r: '#3a3546', b: '#3a3546', B: '#2c2836', s: '#4a4458', S: '#2c2836',
      k: '#4a4458', K: '#3a3546', u: '#2c2836',
    }
  }

  return {
    ...FIXED_COLORS,
    m: props.color,
    l: mix(props.color, 'white', 0.35),
    w: mix(props.color, 'white', 0.75),
    d: mix(props.color, 'black', 0.35),
  }
})

const rects = computed(() => {
  const rows = PIXEL_ICONS[props.icon] ?? PIXEL_ICONS.star
  const out = []

  rows.forEach((row, y) => {
    let x = 0

    while (x < row.length) {
      const ch = row[x]
      let end = x

      while (end + 1 < row.length && row[end + 1] === ch) end++

      if (ch !== '.') {
        out.push({ x, y, w: end - x + 1, fill: palette.value[ch] ?? palette.value.m })
      }

      x = end + 1
    }
  })

  return out
})
</script>

<template>
  <svg
      class="pixel-icon"
      :width="size"
      :height="size"
      viewBox="0 0 16 16"
      shape-rendering="crispEdges"
      aria-hidden="true"
  >
    <rect
        v-for="(r, index) in rects"
        :key="index"
        :x="r.x"
        :y="r.y"
        :width="r.w"
        height="1"
        :fill="r.fill"
    />
  </svg>
</template>

<style scoped>
.pixel-icon {
  display: block;
  flex-shrink: 0;
}
</style>
