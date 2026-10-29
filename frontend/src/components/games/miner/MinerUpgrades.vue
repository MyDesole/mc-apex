<script setup>
/**
 * Мастерская шахтёра: улучшения за ApexCoin. Покупка — на сервере,
 * здесь только витрина с уровнями и ценой следующего.
 */
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { UPGRADE_SPRITES } from '@/data/games/miner.js'

defineProps({
  upgrades: { type: Object, required: true },
  balance: { type: Number, default: 0 },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['buy'])
</script>

<template>
  <div class="mu">
    <article v-for="(u, key) in upgrades" :key="key" class="mu-item" :class="{ 'is-max': u.price === null }">
      <div class="mu-item__head">
        <span class="mu-item__icon"><PixelSprite :sprite="UPGRADE_SPRITES[key]" :size="24" /></span>
        <div>
          <b>{{ u.name }}</b>
          <span class="mu-pips" :aria-label="`Уровень ${u.level} из ${u.max}`">
            <i v-for="n in u.max" :key="n" :class="{ on: n <= u.level }"></i>
          </span>
        </div>
      </div>

      <p>{{ u.about }}</p>

      <button
          v-if="u.price !== null"
          type="button"
          class="mu-buy"
          :disabled="busy || balance < u.price"
          :title="balance < u.price ? `Не хватает ${u.price - balance} ApexCoin` : 'Улучшить'"
          @click="emit('buy', key)"
      >Улучшить · ₳ {{ u.price }}</button>
      <span v-else class="mu-max">Максимальный уровень</span>
    </article>
  </div>
</template>

<style scoped>
.mu {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 10px;
}

.mu-item {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 12px;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
}

.mu-item.is-max { border-color: rgba(250, 204, 21, 0.3); background: rgba(250, 204, 21, 0.04); }

.mu-item__head { display: flex; align-items: center; gap: 10px; }
.mu-item__head b { display: block; color: #fff; font-size: 14px; }

.mu-item__icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;

  display: grid;
  place-items: center;

  background: rgba(0, 0, 0, 0.3);
  border-radius: 9px;
}

.mu-item p { flex: 1; margin: 0; color: #8a8598; font-size: 11.5px; line-height: 1.45; }

.mu-pips { display: inline-flex; gap: 3px; margin-top: 4px; }
.mu-pips i { width: 10px; height: 5px; background: rgba(255, 255, 255, 0.1); border-radius: 1px; }
.mu-pips i.on { background: #facc15; box-shadow: 0 0 6px rgba(250, 204, 21, 0.5); }

.mu-buy {
  min-height: 34px;

  color: #1a1206;
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border: 0;
  border-radius: 9px;

  font: inherit;
  font-size: 12.5px;
  font-weight: 900;

  cursor: pointer;
}

.mu-buy:disabled { color: #8a8598; background: rgba(255, 255, 255, 0.06); cursor: default; }

.mu-max {
  min-height: 34px;

  display: grid;
  place-items: center;

  color: #facc15;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
</style>
