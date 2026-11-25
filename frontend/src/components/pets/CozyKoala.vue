<script setup>
import { useId } from 'vue'
defineProps({ size:{type:Number,default:48}, state:{type:String,default:'idle'} })
const id=useId()
</script>

<template>
  <svg class="cozy-koala" :class="`cozy-koala--${state}`" :width="size * 1.15" :height="size" viewBox="0 0 138 120" aria-hidden="true">
    <defs>
      <radialGradient :id="`${id}-fur`" cx="35%" cy="20%" r="85%"><stop stop-color="#e9edf1"/><stop offset=".6" stop-color="#b3becb"/><stop offset="1" stop-color="#8998ac"/></radialGradient>
      <radialGradient :id="`${id}-ear`" cx="45%" cy="30%" r="80%"><stop stop-color="#f3dce2"/><stop offset="1" stop-color="#c7aebd"/></radialGradient>
      <linearGradient :id="`${id}-leaf`" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#b3d7c5"/><stop offset="1" stop-color="#669a8b"/></linearGradient>
    </defs>
    <path d="M46 74Q69 61 91 74Q110 99 90 112H48Q28 99 46 74Z" :fill="`url(#${id}-fur)`" stroke="#8798ac" stroke-width="1.1"/>
    <ellipse cx="69" cy="94" rx="19" ry="17" fill="#e2e7ed"/>
    <g class="cozy-koala__feet"><ellipse cx="43" cy="108" rx="13" ry="8" fill="#a6b4c5" stroke="#899bb0"/><ellipse cx="95" cy="108" rx="13" ry="8" fill="#a6b4c5" stroke="#899bb0"/><path d="M37 107L38 111M42 108L43 112M96 108L95 112M101 107L100 111" stroke="#dfe7ee" stroke-width="1.7" stroke-linecap="round"/></g>
    <g class="cozy-koala__head">
      <g class="cozy-koala__ear cozy-koala__ear--left"><path d="M42 21C28 3 8 7 7 27Q4 46 27 50L46 43Z" :fill="`url(#${id}-fur)`" stroke="#99a7b9"/><path d="M32 22Q13 10 14 30Q14 41 31 42Z" :fill="`url(#${id}-ear)`"/><path d="M13 15L19 12M7 26L13 24M11 39L17 38" stroke="#edf0f4" stroke-width="2.3" stroke-linecap="round"/></g>
      <g class="cozy-koala__ear cozy-koala__ear--right"><path d="M96 21C110 3 130 7 131 27Q134 46 111 50L92 43Z" :fill="`url(#${id}-fur)`" stroke="#99a7b9"/><path d="M106 22Q125 10 124 30Q124 41 107 42Z" :fill="`url(#${id}-ear)`"/><path d="M125 15L119 12M131 26L125 24M127 39L121 38" stroke="#edf0f4" stroke-width="2.3" stroke-linecap="round"/></g>
      <path d="M29 35Q32 17 51 16L56 10L63 16L71 10L78 17Q103 17 110 41Q123 75 91 84Q69 94 45 84Q16 78 27 49Z" :fill="`url(#${id}-fur)`" stroke="#93a2b7" stroke-width="1.1"/>
      <path d="M36 68Q50 64 61 70Q69 73 78 69Q92 65 104 69Q96 85 69 87Q44 83 36 68Z" fill="#e6ebf0"/>
      <g v-if="state !== 'sleep'" class="cozy-koala__eyes"><ellipse cx="46" cy="53" rx="5" ry="6.5" fill="#384557"/><ellipse cx="92" cy="53" rx="5" ry="6.5" fill="#384557"/><circle cx="47.3" cy="50.8" r="1.6" fill="#fff"/><circle cx="93.3" cy="50.8" r="1.6" fill="#fff"/></g>
      <g v-else fill="none" stroke="#59697b" stroke-width="2.1" stroke-linecap="round"><path d="M40 54Q46 59 52 54M86 54Q92 59 98 54"/></g>
      <path d="M57 50Q59 42 69 43Q81 43 82 54L79 66Q69 73 59 65Z" fill="#4c596a" stroke="#3e4b5c"/>
      <ellipse cx="65" cy="49" rx="4.5" ry="2" fill="#798696" opacity=".8"/>
      <path d="M64 77Q69 80 75 76" fill="none" stroke="#718196" stroke-width="1.5" stroke-linecap="round"/>
      <ellipse cx="35" cy="65" rx="6" ry="3" fill="#d2afc1" opacity=".6"/><ellipse cx="105" cy="65" rx="6" ry="3" fill="#d2afc1" opacity=".6"/>
    </g>
    <g class="cozy-koala__branch"><path d="M73 115Q84 89 98 72" fill="none" stroke="#8b9d82" stroke-width="2" stroke-linecap="round"/><path d="M88 91Q82 72 99 68Q106 84 88 91Z" :fill="`url(#${id}-leaf)`" stroke="#83a58e"/><path d="M83 101Q69 84 77 79Q94 85 83 101Z" :fill="`url(#${id}-leaf)`"/><path d="M90 89L98 73" stroke="#d1e2d5" stroke-width=".8"/></g>
    <g class="cozy-koala__arms"><path d="M42 87Q52 79 65 88Q69 97 59 99L45 97Z" :fill="`url(#${id}-fur)`" stroke="#97a7bb"/><path d="M99 88Q93 80 82 88Q78 97 85 100L97 98Z" :fill="`url(#${id}-fur)`" stroke="#97a7bb"/><path d="M55 89L54 94M60 90L59 95M88 90L89 95" stroke="#e5ebf2" stroke-width="1.4" stroke-linecap="round"/></g>
  </svg>
</template>

<style scoped>
.cozy-koala { display:block; overflow:visible; image-rendering:auto; }
.cozy-koala__eyes { transform-origin:69px 53px; animation:koala-blink 6s infinite; }
.cozy-koala__ear--left { transform-origin:36px 35px; animation:koala-ear 7s ease-in-out infinite; }
.cozy-koala__ear--right { transform-origin:101px 35px; animation:koala-ear 7s ease-in-out -2.2s infinite reverse; }
.cozy-koala__branch { transform-origin:76px 112px; animation:koala-leaf 5s ease-in-out infinite; }
.cozy-koala--walk .cozy-koala__feet, .cozy-koala--run .cozy-koala__feet { transform-origin:69px 105px; animation:koala-step .6s ease-in-out infinite alternate; }
.cozy-koala--eat .cozy-koala__head { transform-origin:69px 78px; animation:koala-nibble .6s ease-in-out infinite alternate; }
.cozy-koala--eat .cozy-koala__branch { animation:koala-leaf 1s ease-in-out infinite; }
.cozy-koala--sleep .cozy-koala__branch { animation-duration:10s; }
@keyframes koala-blink { 0%,42%,46%,100% { transform:scaleY(1); } 44% { transform:scaleY(.08); } }
@keyframes koala-ear { 0%,80%,100% { transform:rotate(0); } 86%,94% { transform:rotate(-6deg); } 90% { transform:rotate(3deg); } }
@keyframes koala-leaf { 0%,100% { transform:rotate(-3deg); } 50% { transform:rotate(4deg); } }
@keyframes koala-step { from { transform:rotate(-5deg); } to { transform:rotate(5deg); } }
@keyframes koala-nibble { to { transform:translateY(2px) rotate(4deg); } }
@media(prefers-reduced-motion:reduce) { .cozy-koala * { animation:none !important; } }
</style>
