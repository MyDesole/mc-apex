<script setup>
import { computed } from 'vue'
import { AVATAR_FRAMES } from '@/data/profileCustomization'

const props = defineProps({
  user: { type: Object, required: true },
  accent: { type: String, default: '#7c3aed' },
  size: { type: Number, default: 72 },
})

const frame = computed(() =>
    AVATAR_FRAMES.find(f => f.id === props.user.avatar_frame) ?? AVATAR_FRAMES[0]
)

const hasFrame = computed(() => frame.value.id !== 'default')

const frameStyle = computed(() => {
  if (!frame.value) return {}
  if (frame.value.gradient) return { background: frame.value.gradient }
  return { background: frame.value.color }
})

const letter = computed(() =>
    (props.user.username || 'И').charAt(0).toUpperCase()
)
</script>

<template>
  <div
      class="avatar-wrap"
      :class="{ 'avatar-wrap--framed': hasFrame }"
      :style="{ '--avatar-size': size + 'px' }"
  >
    <div v-if="hasFrame" class="avatar-ring" :style="frameStyle" />

    <div class="avatar" :style="{ background: accent }">
      <img
          v-if="user.avatar_url"
          :src="user.avatar_url"
          :alt="user.username"
          class="avatar__img"
      />
      <template v-else>{{ letter }}</template>
    </div>
  </div>
</template>

<style scoped>
.avatar-wrap {
  position: relative;
  flex-shrink: 0;
  width: var(--avatar-size);
  height: var(--avatar-size);
}

.avatar-wrap--framed {
  padding: 3px;
  border-radius: 16px;
}

.avatar-ring {
  position: absolute;
  inset: 0;
  border-radius: 16px;
  z-index: 0;
  pointer-events: none;
}

.avatar {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  font-size: calc(var(--avatar-size) * 0.39);
  font-weight: 800;
  color: #fff;
  overflow: hidden;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4);
}

.avatar-wrap--framed .avatar {
  border: 2px solid var(--bg-card);
}

.avatar__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}
</style>