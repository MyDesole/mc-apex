<script setup>
/**
 * Знак APEX: изометрический блок в фирменных фиолетовых гранях.
 * Цвета граней берутся из --cube-top / --cube-left / --cube-right,
 * поэтому тот же знак можно перекрасить, например, под цвет тира.
 */
</script>

<template>
  <svg
      class="brand-cube"
      viewBox="0 0 24 24"
      aria-hidden="true"
  >
    <path d="M12 1.5 22 7 12 12.5 2 7z" fill="var(--cube-top, #a78bfa)" />
    <path d="M2 7l10 5.5V23L2 17.5z" fill="var(--cube-left, #7c3aed)" />
    <path d="M22 7v10.5L12 23V12.5z" fill="var(--cube-right, #4c1d95)" />
    <path d="M7 8.2 9.4 9.5 7 10.8 4.6 9.5zM15.5 4.6l2.4 1.3-2.4 1.3-2.4-1.3z" fill="rgba(255,255,255,0.28)" />
  </svg>
</template>

<style>
.brand-cube {
  display: block;
  width: 26px;
  height: 26px;
  flex-shrink: 0;
}
</style>
