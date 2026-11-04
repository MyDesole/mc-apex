<script setup>
/**
 * Доска заданий: четыре суточных и три недельных задания, бонус за все
 * суточные. Награда — ApexCoin и Жетоны удачи; забирается кнопкой, на
 * экране «Награда». Рубли заданиями не выдаются.
 */
import { onMounted, ref } from 'vue'
import { api } from '@/services/core/api.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import CountdownText from '@/components/core/CountdownText.vue'
import RewardReveal from '@/game/RewardReveal.vue'

const board = ref(null)
const error = ref('')
const busy = ref(false)
const reveal = ref(null)

async function load() {
  try {
    board.value = await api.get('/quests')
    error.value = ''
  } catch (e) {
    error.value = e.message || 'Не удалось открыть доску заданий.'
  }
}

async function claim(url) {
  busy.value = true
  error.value = ''

  try {
    const result = await api.post(url)
    board.value = result.board
    reveal.value = [
      { sprite: 'gi-medal-gold', label: 'ApexCoin', amount: result.reward.coins },
      { sprite: 'gi-star-gold', label: 'Жетоны удачи', amount: result.reward.tokens },
    ]
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

const pct = (q) => Math.round((q.progress / q.target) * 100)

onMounted(load)
</script>

<template>
  <div class="g-scene qs">
    <p v-if="error" class="g-msg g-msg--err">{{ error }}</p>

    <template v-if="board">
      <div class="qs-top">
        <span class="g-chip g-chip--gold"><PixelSprite sprite="gi-star-gold" :size="18" /> Жетоны удачи: <b>{{ board.tokens }}</b></span>
        <span class="g-hint">Жетоны пригодятся в сундуках и на колесе удачи. Рубли заданиями не выдаются.</span>
      </div>

      <section class="qs-block g-wood">
        <h3 class="g-title">Задания дня <small>сброс через <CountdownText :to="board.resets.daily" /></small></h3>
        <article v-for="q in board.daily" :key="q.key" class="qs-row g-stone" :class="{ 'is-done': q.done, 'is-claimed': q.claimed }">
          <PixelSprite :sprite="q.sprite" :size="40" />
          <div class="qs-row__body">
            <b>{{ q.title }}</b>
            <small>{{ q.text }}</small>
            <span class="g-bar" :style="{ '--v': pct(q) + '%', '--c': q.done ? '#4ade80' : '#60a5fa' }"><i /></span>
            <em>{{ q.progress }} / {{ q.target }}</em>
          </div>
          <div class="qs-row__reward">
            <span>{{ q.coins.toLocaleString('ru-RU') }} ₳</span>
            <span>{{ q.tokens }} жет.</span>
          </div>
          <button type="button" class="g-btn g-btn--sm" :class="q.done && !q.claimed ? 'g-btn--go' : ''" :disabled="busy || !q.done || q.claimed" @click="claim(`/quests/${q.key}/claim`)">
            {{ q.claimed ? 'Получено' : q.done ? 'Забрать' : 'В процессе' }}
          </button>
        </article>

        <article class="qs-bonus g-stone" :class="{ 'is-ready': board.bonus.ready && !board.bonus.claimed }">
          <PixelSprite sprite="gi-crown-gold" :size="40" />
          <div class="qs-row__body">
            <b>Бонус дня</b>
            <small>Забери награды за все задания дня</small>
          </div>
          <div class="qs-row__reward">
            <span>{{ board.bonus.coins.toLocaleString('ru-RU') }} ₳</span>
            <span>{{ board.bonus.tokens }} жет.</span>
          </div>
          <button type="button" class="g-btn g-btn--sm" :class="board.bonus.ready && !board.bonus.claimed ? 'g-btn--gold' : ''" :disabled="busy || !board.bonus.ready || board.bonus.claimed" @click="claim('/quests/bonus')">
            {{ board.bonus.claimed ? 'Получено' : 'Забрать' }}
          </button>
        </article>
      </section>

      <section class="qs-block g-dark">
        <h3 class="g-title">Задания недели <small>сброс через <CountdownText :to="board.resets.weekly" /></small></h3>
        <article v-for="q in board.weekly" :key="q.key" class="qs-row g-stone" :class="{ 'is-done': q.done, 'is-claimed': q.claimed }">
          <PixelSprite :sprite="q.sprite" :size="40" />
          <div class="qs-row__body">
            <b>{{ q.title }}</b>
            <small>{{ q.text }}</small>
            <span class="g-bar" :style="{ '--v': pct(q) + '%', '--c': q.done ? '#4ade80' : '#c084fc' }"><i /></span>
            <em>{{ q.progress }} / {{ q.target }}</em>
          </div>
          <div class="qs-row__reward">
            <span>{{ q.coins.toLocaleString('ru-RU') }} ₳</span>
            <span>{{ q.tokens }} жет.</span>
          </div>
          <button type="button" class="g-btn g-btn--sm" :class="q.done && !q.claimed ? 'g-btn--go' : ''" :disabled="busy || !q.done || q.claimed" @click="claim(`/quests/${q.key}/claim`)">
            {{ q.claimed ? 'Получено' : q.done ? 'Забрать' : 'В процессе' }}
          </button>
        </article>
      </section>
    </template>

    <RewardReveal v-if="reveal" :rewards="reveal" title="Награда за задание" @close="reveal = null" />
  </div>
</template>

<style scoped>
.qs-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.qs-block { margin-bottom: 12px; padding: 12px 14px; }
.qs-block h3 small { margin-left: 8px; font-size: 11px; color: #ffe9a8; }
.qs-row, .qs-bonus { display: grid; grid-template-columns: auto 1fr auto auto; align-items: center; gap: 12px; margin-top: 8px; padding: 8px 12px; }
.qs-row__body { display: grid; gap: 3px; min-width: 0; font-size: 13px; }
.qs-row__body small { font-size: 11px; color: #c9b9e8; }
.qs-row__body em { font-size: 11px; font-style: normal; color: #e8dcc0; }
.qs-row__body .g-bar { height: 8px; }
.qs-row__reward { display: grid; gap: 2px; text-align: right; font-size: 12px; font-weight: 800; color: #ffd45c; white-space: nowrap; }
.qs-row.is-done { box-shadow: inset 0 0 0 3px #2f6b2f; }
.qs-row.is-claimed { opacity: 0.55; }
.qs-bonus.is-ready { box-shadow: inset 0 0 0 3px #ffd45c; }
@media (max-width: 640px) {
  .qs-row, .qs-bonus { grid-template-columns: auto 1fr; }
  .qs-row__reward { text-align: left; }
}
</style>
