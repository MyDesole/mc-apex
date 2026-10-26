<script setup>
/**
 * Стикер в чате: крупная пиксельная картинка с анимацией и подписью.
 */
import PixelSprite from '@/components/shop/PixelSprite.vue'

defineProps({
  sticker: { type: Object, required: true },
  size: { type: Number, default: 72 },
})
</script>

<template>
  <span class="sticker" :title="sticker.caption">
    <PixelSprite :sprite="sticker.sprite" :size="size" :anim="sticker.anim" glow="#fde047" />
    <span class="sticker__caption">{{ sticker.caption }}</span>
  </span>
</template>

<style scoped>
.sticker {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;

  padding: 6px 4px 2px;
}

.sticker :deep(.pixel-sprite) {
  filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.45));
}

.sticker__caption {
  padding: 2px 8px;

  color: #fff;
  background: rgba(0, 0, 0, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;

  font-family: var(--font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.02em;
}
</style>
