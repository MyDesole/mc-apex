<script setup>
/**
 * Звание бриджера.
 *
 * Цвет всегда берётся из ответа сервера.
 * Компонент не знает ничего о конкретных рангах и не содержит
 * локальной карты цветов.
 */
defineProps({
  rank: {
    type: Object,
    default: null,
  },

  size: {
    type: String,
    default: 'md',
  },

  emptyLabel: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <span
      v-if="rank"
      class="bridge-rank"
      :class="`bridge-rank--${size}`"
      :style="{ '--rank-color': rank.color }"
  >
    <span class="bridge-rank__dot" />

    <span class="bridge-rank__label">
      {{ rank.label }}
    </span>
  </span>

  <span
      v-else-if="emptyLabel"
      class="bridge-rank bridge-rank--empty"
      :class="`bridge-rank--${size}`"
  >
    {{ emptyLabel }}
  </span>
</template>

<style scoped>
@import "@/components/bridge/BridgeRankBadge.css";
</style>