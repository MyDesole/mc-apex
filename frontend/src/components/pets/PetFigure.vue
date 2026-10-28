<script setup>
/**
 * Питомец целиком: спрайт в окрасе экземпляра, сияние фона и аксессуар
 * на своём месте. Для страницы «Питомцы» — карточки, арена, топ.
 *
 * trick — показать трюк (имя анимации из petTricks.css).
 */
import { computed } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import PetAccessory from '@/components/players/PetAccessory.vue'
import { PIXEL_SPRITES, spriteRatio } from '@/data/shop/pixelSprites.js'
import { accessoryPlacement, petBackdrop, petModelStyle } from '@/data/players/petTraits.js'

const props = defineProps({
  species: { type: String, default: null },
  traits: { type: Object, default: null },
  size: { type: Number, default: 48 },
  backdrop: { type: Boolean, default: true },
  trick: { type: String, default: null },
  flip: { type: Boolean, default: false },
})

const sprite = computed(() => (PIXEL_SPRITES[props.species] ? props.species : 'paw'))
const width = computed(() => Math.round(props.size * Math.min(1.4, spriteRatio(sprite.value))))
const aura = computed(() => (props.backdrop ? petBackdrop(props.traits) : null))
const acc = computed(() => {
  const id = props.traits?.accessory?.id
  return id && id !== 'none' ? accessoryPlacement(sprite.value, id, props.size, PIXEL_SPRITES[sprite.value]?.rows) : null
})
</script>

<template>
  <span
      class="pf"
      :class="[{ 'pf--flip': flip }, trick ? `pf-trick--${trick}` : null]"
      :style="{ width: `${width}px`, height: `${size}px` }"
  >
    <span v-if="aura" class="pf__aura" :style="{ background: aura }"></span>
    <span class="pf__body">
      <span class="pf__skin" :style="petModelStyle(traits)"><PixelSprite :sprite="sprite" :size="size" /></span>
      <span v-if="acc" class="pf__acc" :style="{ left: `${acc.left}px`, top: `${acc.top}px` }">
        <PetAccessory :id="acc.art" :width="acc.width" />
      </span>
    </span>
  </span>
</template>

<style scoped>
.pf {
  position: relative;
  display: inline-block;
  flex-shrink: 0;
}

.pf__aura {
  position: absolute;
  inset: -45%;
  z-index: 0;
  border-radius: 50%;
  opacity: 0.85;
  pointer-events: none;
}

.pf__body {
  position: relative;
  z-index: 1;
  display: block;
  width: 100%;
  height: 100%;
}

.pf--flip .pf__body { transform: scaleX(-1); }

.pf__skin { display: block; line-height: 0; }

.pf__acc {
  position: absolute;
  z-index: 2;
  line-height: 0;
  pointer-events: none;
}
</style>
