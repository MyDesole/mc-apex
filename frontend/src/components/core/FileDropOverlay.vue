<script setup>
/**
 * Подсветка области сброса файлов: рамка поверх формы, пока над ней
 * тащат файл. Родитель должен быть position: relative.
 */
defineProps({
  hint: { type: String, default: '' },
})
</script>

<template>
  <div class="file-drop" aria-hidden="true">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <path d="M17 8l-5-5-5 5" />
      <path d="M12 3v12" />
    </svg>
    <strong>Отпусти, чтобы приложить</strong>
    <span v-if="hint">{{ hint }}</span>
  </div>
</template>

<style scoped>
.file-drop {
  position: absolute;
  inset: 0;
  z-index: 40;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;

  color: #ddd6fe;
  text-align: center;

  background: rgba(18, 12, 30, 0.86);
  border: 2px dashed rgba(167, 139, 250, 0.75);
  border-radius: 8px;

  pointer-events: none;
}

.file-drop strong {
  font-size: 16px;
}

.file-drop span {
  font-size: 12px;
  opacity: 0.7;
}
</style>
