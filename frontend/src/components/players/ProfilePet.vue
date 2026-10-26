<script setup>
/**
 * Питомец профиля (магазин, тип profile_pet): пиксельный зверёк живёт
 * в шапке профиля. Сам решает, что делать: гуляет, стоит, засыпает.
 * Наведи мышь — подпрыгнет и что-нибудь скажет, кликни — сердечки.
 *
 * Слой заполняет родителя и не ловит клики, кроме самого питомца.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { profilePet } from '@/data/players/flair.js'
import PixelSprite from '@/components/shop/PixelSprite.vue'

const props = defineProps({
  pet: { type: String, default: null },
  size: { type: Number, default: 38 },
})

const data = computed(() => profilePet(props.pet))

const x = ref(12)          // позиция в процентах ширины
const y = ref(55)          // для летающих: высота полёта в процентах
const facingLeft = ref(false)
const state = ref('idle')  // idle | walk | sleep
const walkMs = ref(0)
const speech = ref('')
const jumping = ref(false)
const hearts = ref([])

let timer = null
let speechTimer = null
let heartId = 0

function rand(min, max) {
  return min + Math.random() * (max - min)
}

/** Следующее действие: чаще гуляет, иногда стоит или спит. */
function nextAction() {
  const roll = Math.random()

  if (roll < 0.6) {
    const target = rand(4, 86)
    facingLeft.value = target < x.value
    walkMs.value = Math.abs(target - x.value) * 70 + 400
    state.value = 'walk'
    x.value = target
    if (data.value?.fly) y.value = rand(20, 65)
    timer = setTimeout(() => { state.value = 'idle'; schedule() }, walkMs.value)
    return
  }

  if (roll < 0.82 || data.value?.fly) {
    state.value = 'idle'
    schedule(rand(1500, 3500))
    return
  }

  state.value = 'sleep'
  timer = setTimeout(() => { state.value = 'idle'; schedule() }, rand(5000, 8000))
}

function schedule(delay = rand(900, 2200)) {
  clearTimeout(timer)
  timer = setTimeout(nextAction, delay)
}

/** Наведение: просыпается, подпрыгивает и говорит. */
function onHover() {
  if (!data.value) return

  if (state.value === 'sleep') state.value = 'idle'

  jumping.value = false
  requestAnimationFrame(() => { jumping.value = true })

  const phrases = data.value.phrases
  speech.value = phrases[Math.floor(Math.random() * phrases.length)]

  clearTimeout(speechTimer)
  speechTimer = setTimeout(() => { speech.value = '' }, 2400)
}

/** Клик: россыпь сердечек. */
function onClick() {
  const burst = Array.from({ length: 5 }, () => ({
    id: ++heartId,
    dx: `${rand(-30, 30)}px`,
    delay: `${rand(0, 0.15)}s`,
  }))

  hearts.value.push(...burst)
  setTimeout(() => {
    hearts.value = hearts.value.filter((h) => !burst.includes(h))
  }, 1200)

  onHover()
}

onMounted(() => {
  if (!data.value) return

  x.value = rand(8, 70)
  schedule(800)
})

onUnmounted(() => {
  clearTimeout(timer)
  clearTimeout(speechTimer)
})
</script>

<template>
  <div v-if="data" class="pet-zone" aria-hidden="true">
    <div
        class="pet"
        :class="[`pet--${state}`, { 'pet--fly': data.fly, 'pet--jump': jumping }]"
        :style="{
          left: `${x}%`,
          top: data.fly ? `${y}%` : 'auto',
          transitionDuration: `${state === 'walk' ? walkMs : 0}ms`,
        }"
        @mouseenter="onHover"
        @click="onClick"
        @animationend="jumping = false"
    >
      <Transition name="pet-speech">
        <span v-if="speech" class="pet__speech">{{ speech }}</span>
      </Transition>

      <span v-if="state === 'sleep'" class="pet__zzz">
        <i>z</i><i>z</i><i>Z</i>
      </span>

      <span v-for="heart in hearts" :key="heart.id" class="pet__heart" :style="{ '--dx': heart.dx, animationDelay: heart.delay }">❤</span>

      <span class="pet__body" :class="{ 'is-left': facingLeft }">
        <PixelSprite :sprite="data.sprite" :size="size" />
      </span>
      <span class="pet__shadow"></span>
    </div>
  </div>
</template>

<style scoped>
.pet-zone {
  position: absolute;
  inset: 0;
  z-index: 15;

  overflow: hidden;
  border-radius: inherit;

  pointer-events: none;
}

.pet {
  position: absolute;
  bottom: 6px;

  display: flex;
  flex-direction: column;
  align-items: center;

  transform: translateX(-50%);
  transition-property: left, top;
  transition-timing-function: linear;

  pointer-events: auto;
  cursor: pointer;
}

.pet__body {
  display: block;
  filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.45));
  image-rendering: pixelated;
}

.pet__body.is-left {
  transform: scaleX(-1);
}

/* Тень под лапками: на прыжке и в полёте меньше */
.pet__shadow {
  width: 70%;
  height: 5px;
  margin-top: 2px;

  background: radial-gradient(ellipse, rgba(0, 0, 0, 0.45), transparent 70%);
}

.pet--fly .pet__shadow {
  display: none;
}

/* Походка: покачивание; полёт — плавные волны */
.pet--walk .pet__body {
  animation: pet-walk 0.36s ease-in-out infinite alternate;
}

.pet--walk .pet__body.is-left {
  animation-name: pet-walk-left;
}

.pet--idle .pet__body {
  animation: pet-breathe 2.4s ease-in-out infinite;
}

.pet--fly .pet__body {
  animation: pet-hover 1.6s ease-in-out infinite;
}

.pet--sleep .pet__body {
  animation: pet-sleep 3s ease-in-out infinite;
  filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.45)) brightness(0.85);
}

.pet--jump {
  animation: pet-jump 0.55s cubic-bezier(0.3, 0, 0.3, 1);
}

@keyframes pet-walk {
  from { transform: translateY(0) rotate(-4deg); }
  to { transform: translateY(-3px) rotate(4deg); }
}

@keyframes pet-walk-left {
  from { transform: scaleX(-1) translateY(0) rotate(-4deg); }
  to { transform: scaleX(-1) translateY(-3px) rotate(4deg); }
}

@keyframes pet-breathe {
  0%, 100% { scale: 1 1; }
  50% { scale: 1.04 0.96; }
}

@keyframes pet-hover {
  0%, 100% { translate: 0 0; }
  50% { translate: 0 -6px; }
}

@keyframes pet-sleep {
  0%, 100% { scale: 1 0.92; }
  50% { scale: 1.03 0.88; }
}

@keyframes pet-jump {
  0%, 100% { translate: -50% 0; }
  40% { translate: -50% -22px; }
  70% { translate: -50% -8px; }
}

/* Реплика: облачко над головой */
.pet__speech {
  position: absolute;
  bottom: calc(100% + 6px);

  padding: 5px 9px;

  color: #1c1917;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);

  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
}

.pet__speech::after {
  content: '';

  position: absolute;
  left: 50%;
  bottom: -5px;

  border: 5px solid transparent;
  border-top-color: #fff;
  border-bottom: 0;
  transform: translateX(-50%);
}

.pet-speech-enter-active,
.pet-speech-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.pet-speech-enter-from,
.pet-speech-leave-to {
  opacity: 0;
  transform: translateY(4px) scale(0.9);
}

/* Сон: буквы Z всплывают */
.pet__zzz {
  position: absolute;
  bottom: 85%;
  left: 70%;

  color: #c4b5fd;
  font-size: 11px;
  font-weight: 900;
}

.pet__zzz i {
  position: absolute;
  font-style: normal;
  opacity: 0;
  animation: pet-z 2.4s ease-out infinite;
}

.pet__zzz i:nth-child(2) { animation-delay: 0.8s; }
.pet__zzz i:nth-child(3) { animation-delay: 1.6s; font-size: 14px; }

@keyframes pet-z {
  0% { opacity: 0; transform: translate(0, 0); }
  20% { opacity: 1; }
  100% { opacity: 0; transform: translate(10px, -22px); }
}

/* Сердечки по клику */
.pet__heart {
  position: absolute;
  bottom: 60%;

  color: #fb7185;
  font-size: 13px;
  text-shadow: 0 0 6px rgba(251, 113, 133, 0.7);

  animation: pet-heart 1s ease-out forwards;
}

@keyframes pet-heart {
  0% { opacity: 1; transform: translate(0, 0) scale(0.6); }
  100% { opacity: 0; transform: translate(var(--dx), -40px) scale(1.2); }
}

@media (prefers-reduced-motion: reduce) {
  .pet,
  .pet * {
    animation: none !important;
    transition: none !important;
  }
}
</style>
