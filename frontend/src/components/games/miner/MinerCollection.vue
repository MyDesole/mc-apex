<script setup>
/** Коллекция артефактов: найденные цветные, остальные — силуэты. */
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { ARTIFACT_SPRITES } from '@/data/games/miner.js'

const TIERS = { 1: 'Пещеры', 2: 'Сланец', 3: 'Незер', 4: 'Энд' }

defineProps({
  artifacts: { type: Object, required: true },
})
</script>

<template>
  <div class="mc">
    <div class="mc-grid">
      <div
          v-for="a in artifacts.items"
          :key="a.id"
          class="mc-slot"
          :class="{ 'is-found': a.count > 0 }"
          :title="a.count > 0 ? `${a.name} · найдено ${a.count}` : `??? · попадается с яруса «${TIERS[a.tier]}»`"
      >
        <PixelSprite :sprite="ARTIFACT_SPRITES[a.id]" :size="24" :anim="a.count > 0 ? 'glow' : null" glow="rgba(250,204,21,.6)" />
        <span v-if="a.count > 1" class="mc-count">×{{ a.count }}</span>
      </div>
    </div>
    <p class="mc-bonus">
      Бонус к добыче: <b>+{{ Math.round(artifacts.bonus * 100) }}%</b>
      <span>+1% за каждый разный, вся коллекция — ещё +2%</span>
    </p>
  </div>
</template>

<style scoped>
.mc { display: flex; flex-direction: column; gap: 10px; }

.mc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }

.mc-slot {
  position: relative;

  aspect-ratio: 1;

  display: grid;
  place-items: center;

  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
}

.mc-slot:not(.is-found) :deep(.pixel-sprite) { filter: brightness(0) invert(1) opacity(0.14); }
.mc-slot.is-found { border-color: rgba(250, 204, 21, 0.35); background: rgba(250, 204, 21, 0.06); }

.mc-count {
  position: absolute;
  right: 3px;
  bottom: 2px;

  color: #fde68a;
  font-size: 10px;
  font-weight: 900;
}

.mc-bonus { margin: 0; color: #d6d2e2; font-size: 12px; }
.mc-bonus b { color: #86efac; }
.mc-bonus span { display: block; margin-top: 2px; color: #8a8598; font-size: 11px; }
</style>
