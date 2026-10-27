<script setup>
/**
 * Иконки студии профиля: линейные, 24×24, цвет — currentColor.
 */
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: Number, default: 18 },
})

const ICONS = {
  plus: 'M12 5v14 M5 12h14',
  minus: 'M5 12h14',
  layers: 'M12 3l9 5-9 5-9-5 9-5z M3 13l9 5 9-5 M3 17.5l9 5 9-5',
  template: 'M4 4h16v6H4z M4 14h7v6H4z M15 14h5v6h-5z',
  palette: 'M12 3a9 9 0 1 0 0 18c1.4 0 2-1 2-2 0-1.4-1-1.6-1-3 0-1 .8-2 2-2h2.5A3.5 3.5 0 0 0 21 10.5C21 6.4 17 3 12 3z M7.5 11.5h.01 M10 7.5h.01 M14.5 7.5h.01',
  type: 'M5 6V4h14v2 M12 4v16 M9 20h6',
  grid: 'M4 4h7v7H4z M13 4h7v7h-7z M4 13h7v7H4z M13 13h7v7h-7z',
  motion: 'M5 12h3 M3 8h5 M4 16h4 M14 5l6 7-6 7 M10 5l6 7-6 7',
  undo: 'M9 14L4 9l5-5 M4 9h10a6 6 0 0 1 0 12h-3',
  redo: 'M15 14l5-5-5-5 M20 9H10a6 6 0 0 0 0 12h3',
  monitor: 'M3 4h18v12H3z M8 20h8 M12 16v4',
  phone: 'M7 2.5h10v19H7z M11 18.5h2',
  dice: 'M4 4h16v16H4z M8.5 8.5h.01 M15.5 8.5h.01 M12 12h.01 M8.5 15.5h.01 M15.5 15.5h.01',
  reset: 'M4 12a8 8 0 1 0 2.3-5.6 M4 4v4h4',
  back: 'M15 18l-6-6 6-6',
  up: 'M12 19V5 M6 11l6-6 6 6',
  down: 'M12 5v14 M6 13l6 6 6-6',
  copy: 'M9 9h11v11H9z M5 15H4V4h11v1',
  trash: 'M4 7h16 M9 7V4h6v3 M6.5 7l1 14h9l1-14',
  grip: 'M9 6h.01 M15 6h.01 M9 12h.01 M15 12h.01 M9 18h.01 M15 18h.01',
  close: 'M6 6l12 12 M18 6L6 18',
  check: 'M5 12.5l4.5 4.5L19 7',
  sliders: 'M4 6h10 M18 6h2 M16 4v4 M4 12h4 M12 12h8 M10 10v4 M4 18h12 M20 18h0 M18 16v4',
  cursor: 'M5 3l6 16 2.5-6.5L20 10z',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
  'eye-off': 'M3 3l18 18 M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4 M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 5.4-1.6 M9.9 9.9a3 3 0 0 0 4.2 4.2',
  user: 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8z M4 21a8 8 0 0 1 16 0',
  bag: 'M5 8h14l-1 13H6z M9 8V6a3 3 0 0 1 6 0v2',
  brush: 'M14 4l6 6-8 8-6-6z M6 12l-3 7 7-3',
  save: 'M5 3h11l3 3v15H5z M8 3v6h8V3 M8 14h8v7H8z',
  header:'M3 4h18v8H3z M7 8h.01 M10 8h7 M3 16h18 M3 20h11',
  sparkle: 'M12 3v4 M12 17v4 M3 12h4 M17 12h4 M6 6l2.5 2.5 M15.5 15.5L18 18 M6 18l2.5-2.5 M15.5 8.5L18 6',
  wand: 'M4 20L15 9 M14 4v2 M19 9h2 M17.5 5.5l1.5-1.5 M11 4.5l.5 1.5 M18 12l1.5.5 M15 9l-1.5-1.5',
}

const paths = computed(() => (ICONS[props.name] ?? '').split(/\s(?=M)/))
</script>

<template>
  <svg
      :width="size"
      :height="size"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
  >
    <path v-for="(d, i) in paths" :key="i" :d="d" />
  </svg>
</template>
