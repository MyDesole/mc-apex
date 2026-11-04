<script setup>
import { maintenance } from '@/services/core/maintenance.js'
</script>

<template>
  <Teleport to="body">
    <div v-if="maintenance" class="maint" role="alert" aria-live="assertive">
      <div class="maint__card">
        <div class="maint__blocks" aria-hidden="true">
          <span /><span /><span />
        </div>

        <h2 class="maint__title">Идёт обновление</h2>

        <p class="maint__text">
          Подождите около 2 минут. Страница откроется сама, как только всё будет готово.
        </p>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.maint {
  position: fixed;
  inset: 0;
  z-index: 100000;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 16px;

  background: rgba(10, 9, 14, 0.92);
  backdrop-filter: blur(6px);
}

.maint__card {
  width: 100%;
  max-width: 420px;

  padding: 32px 28px;

  text-align: center;

  background: var(--bg-card, #16141d);
  border: 1px solid var(--border, #2a2733);
  border-radius: var(--radius, 8px);
}

.maint__blocks {
  display: flex;
  justify-content: center;
  gap: 8px;

  margin-bottom: 20px;
}

.maint__blocks span {
  width: 14px;
  height: 14px;

  background: var(--accent, #a78bfa);

  animation: maint-hop 1s ease-in-out infinite;
}

.maint__blocks span:nth-child(2) {
  animation-delay: 0.15s;
}

.maint__blocks span:nth-child(3) {
  animation-delay: 0.3s;
}

.maint__title {
  margin: 0 0 10px;

  color: #fff;

  font-family: var(--font-display, inherit);
  font-size: 22px;
}

.maint__text {
  margin: 0;

  color: var(--text-dim, #a8a3b5);

  font-size: 15px;
  line-height: 1.5;
}

@keyframes maint-hop {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-10px); }
}

@media (prefers-reduced-motion: reduce) {
  .maint__blocks span {
    animation: none;
  }
}
</style>
