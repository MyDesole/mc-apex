<script setup>
import { computed } from 'vue'
import { AVATAR_FRAMES } from '@/data/players/profileCustomization.js'

const props = defineProps({
  user: { type: Object, required: true },
  accent: { type: String, default: '#7c3aed' },
  size: { type: Number, default: 72 },
})

const frame = computed(() =>
    AVATAR_FRAMES.find(f => f.id === props.user.avatar_frame) ?? AVATAR_FRAMES[0]
)

const hasFrame = computed(() => frame.value?.id !== 'default')

const frameStyle = computed(() => {
  if (!frame.value) return {}

  if (frame.value.gradient) {
    return {
      background: frame.value.gradient,
    }
  }

  return {
    background: frame.value.color,
  }
})

const letter = computed(() =>
    (props.user.username || 'И').charAt(0).toUpperCase()
)
</script>

<template>
  <div
      class="avatar-wrap"
      :class="{ 'avatar-wrap--framed': hasFrame }"
      :style="{ '--avatar-size': `${size}px` }"
  >
    <!-- CUSTOM FRAME -->
    <div
        v-if="hasFrame"
        class="avatar-frame"
        :style="frameStyle"
    >
      <div class="avatar-frame__inner" />
    </div>

    <!-- AVATAR -->
    <div
        class="avatar"
        :style="{ background: accent }"
    >
      <img
          v-if="user.avatar_url"
          :src="user.avatar_url"
          :alt="user.username"
          class="avatar__img"
      />

      <template v-else>
        {{ letter }}
      </template>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileAvatar.css";
</style>
