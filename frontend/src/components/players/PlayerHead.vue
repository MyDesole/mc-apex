<script setup>
/**
 * Голова игрока: лицо скина Minecraft, если ник привязан, иначе
 * аватарка сайта, иначе первая буква ника.
 */
import { onMounted, ref, watch } from 'vue'
import { loadSkin, renderSkinHead } from '@/utils/minecraftSkin.js'

const props = defineProps({
  user: { type: Object, required: true },
  size: { type: Number, default: 34 },
})

const skinHead = ref('')

async function load() {
  skinHead.value = ''

  // Без привязанного ника сервер вернёт 404 — не дёргаем его зря
  if (!props.user?.minecraft_username) return

  const skin = await loadSkin(props.user.id)

  if (skin) {
    skinHead.value = renderSkinHead(skin.image, 64).toDataURL()
  }
}

onMounted(load)
watch(() => props.user?.id, load)
</script>

<template>
  <span
      class="player-head"
      :class="{ 'player-head--skin': skinHead }"
      :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.42)}px` }"
  >
    <img v-if="skinHead" :src="skinHead" :alt="user.username" />
    <img v-else-if="user.avatar_url" :src="user.avatar_url" :alt="user.username" />
    <template v-else>{{ user.username?.charAt(0).toUpperCase() || '?' }}</template>
  </span>
</template>

<style scoped>
.player-head {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;

  color: #fff;
  background: linear-gradient(135deg, #3b3550, #1d1a27);
  border-radius: 6px;

  font-weight: 800;
}

.player-head img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Пиксели скина не размываем */
.player-head--skin img {
  image-rendering: pixelated;
}
</style>
