<script setup>
/** Три задания шахтёра на сегодня: прогресс и кнопка «Забрать». */
import AppIcon from '@/components/core/AppIcon.vue'

defineProps({
  quests: { type: Array, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['claim'])
</script>

<template>
  <ul class="mq">
    <li v-for="q in quests" :key="q.id" class="mq-item" :class="{ 'is-done': q.claimed }">
      <div class="mq-item__head">
        <span>{{ q.text }}</span>
        <b>₳ {{ q.reward }}</b>
      </div>

      <div class="mq-item__foot">
        <i class="mq-bar"><em :style="{ width: `${(q.progress / q.goal) * 100}%` }"></em></i>
        <span v-if="q.claimed" class="mq-claimed"><AppIcon icon="check" :size="13" /> Получено</span>
        <button
            v-else-if="q.progress >= q.goal"
            type="button"
            class="mq-claim"
            :disabled="busy"
            @click="emit('claim', q.id)"
        >Забрать</button>
        <span v-else class="mq-count">{{ q.progress }} / {{ q.goal }}</span>
      </div>
    </li>
  </ul>
</template>

<style scoped>
.mq { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }

.mq-item {
  display: flex;
  flex-direction: column;
  gap: 7px;

  padding: 9px 10px;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
}

.mq-item.is-done { opacity: 0.6; }

.mq-item__head { display: flex; justify-content: space-between; gap: 8px; font-size: 12.5px; color: #d6d2e2; }
.mq-item__head b { color: #fde68a; white-space: nowrap; }

.mq-item__foot { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 10px; }

.mq-bar { height: 6px; overflow: hidden; background: rgba(255, 255, 255, 0.07); border-radius: 99px; }
.mq-bar em { display: block; height: 100%; max-width: 100%; background: linear-gradient(90deg, #22c55e, #86efac); border-radius: inherit; }

.mq-count { color: #8a8598; font-size: 11px; font-weight: 800; }
.mq-claimed { display: inline-flex; align-items: center; gap: 4px; color: #86efac; font-size: 11px; font-weight: 800; }

.mq-claim {
  min-height: 26px;
  padding: 0 10px;

  color: #052e16;
  background: linear-gradient(135deg, #86efac, #22c55e);
  border: 0;
  border-radius: 7px;

  font: inherit;
  font-size: 11.5px;
  font-weight: 900;

  cursor: pointer;
  animation: mq-ready 1.6s ease-in-out infinite;
}

.mq-claim:disabled { opacity: 0.6; animation: none; }

@keyframes mq-ready {
  0%, 100% { box-shadow: 0 0 0 rgba(34, 197, 94, 0); }
  50% { box-shadow: 0 0 12px rgba(34, 197, 94, 0.6); }
}

@media (prefers-reduced-motion: reduce) {
  .mq-claim { animation: none; }
}
</style>
