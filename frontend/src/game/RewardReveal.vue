<script setup>
/**
 * Экран «Награда»: затемнение, фейерверк из пикселей и плашки с добычей.
 * Закрывается кликом или клавишей Esc. Без эмодзи: иконки — PixelSprite.
 */
import { onBeforeUnmount, onMounted } from 'vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'

defineProps({
  // [{ sprite, label, amount }]
  rewards: { type: Array, required: true },
  title: { type: String, default: 'Награда' },
})
const emit = defineEmits(['close'])

const COLORS = ['#ffd45c', '#ff7a3a', '#7dd3fc', '#f0abfc', '#86efac', '#fff']
const sparks = Array.from({ length: 36 }, (_, i) => {
  const angle = (i / 36) * Math.PI * 2
  const dist = 90 + (i % 5) * 34

  return {
    dx: Math.round(Math.cos(angle) * dist),
    dy: Math.round(Math.sin(angle) * dist),
    color: COLORS[i % COLORS.length],
    delay: (i % 6) * 0.08,
  }
})

const onKey = (event) => event.key === 'Escape' && emit('close')

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="rv" role="dialog" aria-label="Награда" @click="emit('close')">
    <div class="rv__burst" aria-hidden="true">
      <i v-for="(s, i) in sparks" :key="i" class="rv__spark" :style="{ '--dx': s.dx + 'px', '--dy': s.dy + 'px', '--c': s.color, animationDelay: s.delay + 's' }" />
    </div>

    <div class="rv__card g-wood" @click.stop>
      <h2 class="rv__title">{{ title }}</h2>
      <div class="rv__items">
        <div v-for="(r, i) in rewards" :key="i" class="rv__item" :style="{ animationDelay: 0.25 + i * 0.15 + 's' }">
          <PixelSprite :sprite="r.sprite" :size="56" anim="bounce" />
          <b>+{{ Number(r.amount).toLocaleString('ru-RU') }}</b>
          <small>{{ r.label }}</small>
        </div>
      </div>
      <button type="button" class="g-btn g-btn--go" @click="emit('close')">Забрать</button>
    </div>
  </div>
</template>

<style scoped>
.rv { position: fixed; inset: 0; z-index: 200; display: grid; place-items: center; background: rgba(6, 4, 2, 0.78); animation: rv-fade 0.2s; }
.rv__burst { position: absolute; left: 50%; top: 42%; width: 0; height: 0; pointer-events: none; }
.rv__spark { position: absolute; width: 8px; height: 8px; background: var(--c); box-shadow: 0 0 10px 2px var(--c); opacity: 0; animation: rv-spark 1.1s steps(9) infinite; }
.rv__card { position: relative; display: flex; flex-direction: column; align-items: center; gap: 14px; min-width: 280px; max-width: 92vw; padding: 20px 26px; animation: rv-pop 0.35s steps(5); }
.rv__title { margin: 0; font-size: 22px; color: #ffe9a8; text-shadow: 3px 3px 0 #4a2a10; letter-spacing: 1px; }
.rv__items { display: flex; flex-wrap: wrap; gap: 14px; justify-content: center; }
.rv__item { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 96px; padding: 10px; background: rgba(0, 0, 0, 0.35); border: 3px solid #4a2a10; opacity: 0; animation: rv-item 0.35s steps(5) forwards; }
.rv__item b { font-size: 18px; color: #ffd45c; text-shadow: 2px 2px 0 #000; }
.rv__item small { font-size: 11px; color: #e8dcc0; text-align: center; }
@keyframes rv-fade { from { opacity: 0; } }
@keyframes rv-pop { from { transform: scale(0.6); } }
@keyframes rv-item { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
@keyframes rv-spark { 0% { opacity: 1; transform: translate(0, 0); } 100% { opacity: 0; transform: translate(var(--dx), var(--dy)); } }
@media (prefers-reduced-motion: reduce) {
  .rv__spark { display: none; }
  .rv, .rv__card, .rv__item { animation-duration: 0.01s; }
}
</style>
