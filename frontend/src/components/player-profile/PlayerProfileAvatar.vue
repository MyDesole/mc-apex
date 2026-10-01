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
.avatar-wrap {
  --frame-width: 3px;

  position: relative;
  flex-shrink: 0;
  width: var(--avatar-size);
  height: var(--avatar-size);
}

/* =========================================
   CUSTOM FRAME
   ========================================= */

.avatar-frame {
  position: absolute;
  inset: 0;

  z-index: 0;

  border-radius: 17px;

  pointer-events: none;

  /* небольшое свечение рамки */
  filter:
      drop-shadow(0 0 5px color-mix(
          in srgb,
          var(--frame-color, #fff) 35%,
          transparent
      ));
}

/*
 * Внутренний слой создаёт отверстие,
 * чтобы frame был именно рамкой, а не просто
 * фоном за аватаром.
 */
.avatar-frame__inner {
  position: absolute;
  inset: var(--frame-width);

  border-radius: 14px;

  background: var(--bg-card);

  pointer-events: none;
}

/* =========================================
   AVATAR
   ========================================= */

.avatar {
  position: absolute;

  inset: var(--frame-width);

  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: center;

  width: auto;
  height: auto;

  border-radius: 13px;

  font-size: calc(var(--avatar-size) * 0.39);
  font-weight: 800;

  color: #fff;

  overflow: hidden;

  box-shadow:
      0 6px 24px rgba(0, 0, 0, 0.4);
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

/* =========================================
   DEFAULT
   ========================================= */

.avatar-wrap:not(.avatar-wrap--framed) .avatar {
  inset: 0;
  border-radius: 14px;
}

/* =========================================
   FRAME EFFECT
   ========================================= */

.avatar-wrap--framed::after {
  content: '';

  position: absolute;
  inset: -3px;

  z-index: 2;

  border-radius: 19px;

  pointer-events: none;

  box-shadow:
      0 0 0 1px rgba(255, 255, 255, 0.08),
      0 0 18px color-mix(
          in srgb,
          var(--avatar-accent, #7c3aed) 20%,
          transparent
      );
}
</style>