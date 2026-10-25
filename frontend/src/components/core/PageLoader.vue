<script setup>
/**
 * Загрузка: подпрыгивающий блок-логотип, как на главной.
 * compact — для вкладок и диалогов, без большого отступа.
 */
import BrandCube from '@/components/core/BrandCube.vue'

defineProps({
  text: { type: String, default: 'Загружаем…' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div class="page-loader" :class="{ 'page-loader--compact': compact }" role="status" aria-live="polite">
    <BrandCube class="page-loader__cube" />
    <span>{{ text }}</span>
  </div>
</template>

<style scoped>
.page-loader {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;

  padding: 80px 0;

  color: var(--text-dim);
}

.page-loader--compact {
  padding: 24px 0;
}

.page-loader__cube {
  width: 28px;
  height: 28px;

  animation: loader-hop 0.9s ease-in-out infinite;
}

@keyframes loader-hop {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

@media (prefers-reduced-motion: reduce) {
  .page-loader__cube { animation: none; }
}
</style>
