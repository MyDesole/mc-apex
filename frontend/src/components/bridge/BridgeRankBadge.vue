<script setup>
/**
 * Звание бриджера: в бридж-профиле это главный ранг.
 *
 * Отдельный компонент, потому что показывается и в профиле, и в панели
 * тестера, и в списке бриджеров.
 */
defineProps({
  rank: { type: Object, default: null },
  size: { type: String, default: 'md' },
  emptyLabel: { type: String, default: '' },
})
</script>

<template>
  <span
      v-if="rank"
      class="bridge-rank"
      :class="`bridge-rank--${size}`"
      :style="{ '--rank-color': rank.color || '#8b5cf6' }"
  >
    <span class="bridge-rank__dot" />
    {{ rank.label }}
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
