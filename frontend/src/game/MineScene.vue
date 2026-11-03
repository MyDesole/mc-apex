<script setup>
/**
 * Шахта: рамка пещеры вокруг игры. Сам забег — MinerView; здесь только
 * окружение (стены породы, фонари, крепи), чтобы он стоял в шахте, а не на
 * странице сайта.
 */
import MinerView from '@/views/games/MinerView.vue'
</script>

<template>
  <div class="mn">
    <i class="mn-lamp mn-lamp--l" aria-hidden="true" /><i class="mn-lamp mn-lamp--r" aria-hidden="true" />
    <i class="mn-beam mn-beam--l" aria-hidden="true" /><i class="mn-beam mn-beam--r" aria-hidden="true" />
    <div class="mn-inner"><MinerView /></div>
  </div>
</template>

<style scoped>
.mn {
  position: relative; min-height: 100%; padding: 0 34px;
  background:
    radial-gradient(40% 30% at 6% 8%, rgba(255, 154, 42, 0.22), transparent 70%),
    radial-gradient(40% 30% at 94% 8%, rgba(255, 154, 42, 0.22), transparent 70%),
    repeating-conic-gradient(#2f2f36 0 25%, #28282e 0 50%) 0 0 / 32px 32px;
}
.mn-inner { position: relative; z-index: 1; }
.mn-beam { position: absolute; top: 0; bottom: 0; width: 22px; background: repeating-linear-gradient(0deg, #6b4a1c 0 28px, #4a3210 28px 32px); border-inline: 4px solid #2a1c08; z-index: 2; }
.mn-beam--l { left: 4px; }
.mn-beam--r { right: 4px; }
.mn-lamp { position: absolute; top: 10px; width: 14px; height: 22px; background: #ffd45c; border: 3px solid #5c4305; box-shadow: 0 0 26px 8px rgba(255, 154, 42, 0.5); z-index: 3; animation: mn-flicker 1.4s steps(3) infinite; }
.mn-lamp--l { left: 30px; }
.mn-lamp--r { right: 30px; animation-delay: -0.7s; }
@keyframes mn-flicker { 50% { opacity: 0.8; } }
/* Игру перекрашиваем под шахту: без страничной шапки, всё из дерева и камня */
.mn :deep(*) { border-radius: 0 !important; }
.mn :deep(.mn-head) { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin: 0 0 10px; padding: 8px 12px; background: #7c4a21; border: 4px solid #4a2a10; box-shadow: inset 0 4px 0 #a8672f, 4px 4px 0 rgba(0, 0, 0, 0.35); }
.mn :deep(.mn-head p), .mn :deep(.mn-head__kicker) { display: none; }
.mn :deep(.mn-head h1) { margin: 0; font-size: 20px; color: #fff; text-shadow: 2px 2px 0 #4a2a10; }
.mn :deep(.mn-head__stats) { display: flex; gap: 6px; margin: 0; padding: 0; background: none; border: 0; }
.mn :deep(.mn-head__stats > *) { padding: 3px 9px; font-size: 11px; background: rgba(0, 0, 0, 0.45); border: 3px solid #1b0f06; }
.mn :deep(.mn-mine) { padding: 12px; background: #1d1a24 !important; border: 4px solid #1b0f06 !important; box-shadow: inset 0 0 0 4px #3a3446; }
.mn :deep(.mn-panel) { color: #fff; background: #3b2a1a !important; border: 4px solid #1b0f06 !important; box-shadow: inset 0 4px 0 #5a4026; }
.mn :deep(.mn-btn) { font-weight: 800; border: 4px solid #1b0f06 !important; box-shadow: inset 0 4px 0 rgba(255, 255, 255, 0.25), 0 4px 0 #0e0804; text-shadow: 1px 1px 0 rgba(0, 0, 0, 0.5); }
.mn :deep(.mn-btn--primary) { background: #3f9a2f !important; border-color: #1f4d17 !important; }
.mn :deep(.mn-tabs button) { border: 3px solid #1b0f06 !important; }
@media (max-width: 760px) { .mn { padding: 0 20px; } .mn-beam { width: 12px; } .mn-lamp--l { left: 20px; } .mn-lamp--r { right: 20px; } }
@media (prefers-reduced-motion: reduce) { .mn-lamp { animation: none; } }
</style>
