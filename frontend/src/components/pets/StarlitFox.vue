<script setup>
import { useId } from 'vue'
defineProps({ size: { type:Number, default:52 }, state: { type:String, default:'idle' } })
const id=useId()
</script>

<template>
  <svg class="starlit-fox" :class="`starlit-fox--${state}`" :width="size * 1.3" :height="size" viewBox="0 0 156 120" aria-hidden="true">
    <defs>
      <linearGradient :id="`${id}-fur`" x1="0" y1="0" x2=".8" y2="1"><stop stop-color="#fcfcff"/><stop offset=".48" stop-color="#d3daed"/><stop offset="1" stop-color="#8e9abc"/></linearGradient>
      <linearGradient :id="`${id}-tail`" x1="0" y1="1" x2="1" y2="0"><stop stop-color="#69779e"/><stop offset=".4" stop-color="#b1a4ea"/><stop offset=".72" stop-color="#b4d8f1"/><stop offset="1" stop-color="#f5fbff"/></linearGradient>
      <linearGradient :id="`${id}-ear`" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#8a91bf"/><stop offset="1" stop-color="#d8c7ee"/></linearGradient>
    </defs>
    <g class="starlit-fox__tail">
      <path d="M91 94C122 97 137 79 128 58C123 46 133 32 145 26C153 60 157 85 139 102C126 115 99 113 86 104Z" :fill="`url(#${id}-tail)`" stroke="#9eaccb" stroke-width="1.2"/>
      <path d="M145 26C148 41 151 56 148 67L140 61L136 68L132 56C126 45 136 31 145 26Z" fill="#edf8ff"/>
      <path d="M118 88L122 80L126 88L122 95Z" fill="#f6f2ff"/>
      <circle cx="134" cy="79" r="1.8" fill="#f5fbff"/>
    </g>
    <path d="M50 74C62 61 90 65 101 81C108 92 101 106 87 108H49C36 105 37 87 50 74Z" :fill="`url(#${id}-fur)`" stroke="#8f9cb9" stroke-width="1.2"/>
    <path d="M56 78C66 81 71 91 66 104H47C42 97 43 88 47 84Z" fill="#f5f7ff"/>
    <g class="starlit-fox__paw starlit-fox__paw--back"><path d="M85 96L94 96L96 110Q91 116 82 111Z" fill="#aab7d2" stroke="#8f9cb9"/></g>
    <g class="starlit-fox__paw starlit-fox__paw--front"><path d="M51 94L63 96L63 110Q57 115 48 110Z" fill="#f3f7ff" stroke="#a5b2cd"/><path d="M52 109H58" stroke="#bac8df" stroke-linecap="round"/></g>
    <g class="starlit-fox__head">
      <g class="starlit-fox__ear starlit-fox__ear--left"><path d="M33 49Q14 32 21 8Q43 12 51 33Z" :fill="`url(#${id}-fur)`" stroke="#99a5c3" stroke-width="1.3"/><path d="M29 36Q22 23 26 16Q40 20 43 33Z" :fill="`url(#${id}-ear)`"/></g>
      <g class="starlit-fox__ear starlit-fox__ear--right"><path d="M67 31Q76 10 97 8Q103 34 83 49Z" :fill="`url(#${id}-fur)`" stroke="#99a5c3" stroke-width="1.3"/><path d="M76 34Q81 20 93 16Q96 30 84 39Z" :fill="`url(#${id}-ear)`"/></g>
      <path d="M29 41Q56 20 83 39L96 55L87 55L95 64L84 64L88 72Q69 91 56 92Q38 87 23 72L29 63L19 64L27 54L20 55Z" :fill="`url(#${id}-fur)`" stroke="#96a3c0" stroke-width="1.1"/>
      <path d="M27 59Q39 56 56 73Q74 55 89 59Q80 82 56 88Q32 81 27 59Z" fill="#fafbff"/>
      <g v-if="state !== 'sleep'" class="starlit-fox__eyes">
        <ellipse cx="39" cy="58" rx="5" ry="6.5" fill="#343c62"/><ellipse cx="76" cy="58" rx="5" ry="6.5" fill="#343c62"/>
        <circle cx="40.5" cy="56" r="1.7" fill="white"/><circle cx="77.5" cy="56" r="1.7" fill="white"/>
      </g>
      <g v-else fill="none" stroke="#596381" stroke-width="2.3" stroke-linecap="round"><path d="M34 59Q39 63 44 59M71 59Q76 63 81 59"/></g>
      <path d="M51 73Q57 70 62 73L57 78Z" fill="#4d5778"/>
      <path d="M52 82Q57 85 63 81" fill="none" stroke="#8b92b2" stroke-width="1.4" stroke-linecap="round"/>
      <ellipse cx="31" cy="68" rx="5" ry="2.5" fill="#d6b8d9" opacity=".5"/><ellipse cx="84" cy="68" rx="5" ry="2.5" fill="#d6b8d9" opacity=".5"/>
      <path d="M57 39L60 44L57 49L54 44Z" fill="#99dcf0" stroke="#f8ffff" stroke-width=".7"/>
    </g>
    <g class="starlit-fox__stars" fill="#d6eeff"><path d="M111 24L113 29L118 31L113 33L111 38L109 33L104 31L109 29Z"/><circle cx="104" cy="51" r="1.5"/><circle cx="125" cy="16" r="1.3"/></g>
  </svg>
</template>

<style scoped>
.starlit-fox { display:block; overflow:visible; image-rendering:auto; }
.starlit-fox__tail { transform-origin:96px 98px; animation:aster-tail 4s ease-in-out infinite; }
.starlit-fox__ear--left { transform-origin:42px 41px; animation:aster-ear 7s ease-in-out infinite; }
.starlit-fox__ear--right { transform-origin:78px 41px; animation:aster-ear 7s ease-in-out -2s infinite; }
.starlit-fox__eyes { transform-origin:58px 58px; animation:aster-blink 5.6s infinite; }
.starlit-fox__stars { animation:aster-stars 3.6s ease-in-out infinite alternate; }
.starlit-fox--walk .starlit-fox__paw, .starlit-fox--run .starlit-fox__paw { transform-origin:60px 94px; animation:aster-step .45s ease-in-out infinite alternate; }
.starlit-fox--walk .starlit-fox__paw--back, .starlit-fox--run .starlit-fox__paw--back { animation-delay:-.45s; }
.starlit-fox--run .starlit-fox__paw { animation-duration:.22s; }
.starlit-fox--eat .starlit-fox__head { transform-origin:58px 80px; animation:aster-nibble .5s ease-in-out infinite alternate; }
.starlit-fox--sleep .starlit-fox__tail { animation-duration:8s; }
.starlit-fox--sleep .starlit-fox__stars { opacity:.25; animation:none; }
@keyframes aster-tail { 0%,100% { transform:rotate(-5deg); } 50% { transform:rotate(8deg); } }
@keyframes aster-ear { 0%,82%,100% { transform:rotate(0); } 87%,94% { transform:rotate(-9deg); } 90%,97% { transform:rotate(4deg); } }
@keyframes aster-blink { 0%,43%,47%,90%,94%,100% { transform:scaleY(1); } 45%,92% { transform:scaleY(.08); } }
@keyframes aster-stars { from { opacity:.3; transform:translateY(1px); } to { opacity:.95; transform:translateY(-2px); } }
@keyframes aster-step { from { transform:rotate(-10deg); } to { transform:rotate(10deg); } }
@keyframes aster-nibble { to { transform:rotate(7deg) translateY(2px); } }
@media(prefers-reduced-motion:reduce) { .starlit-fox * { animation:none !important; } }
</style>
