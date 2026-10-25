<script setup>
/**
 * Превью товара магазина: как он будет выглядеть, а не просто иконка.
 *
 * Рамка — на голове из скина игрока (или на букве ника), фон 3D-скина —
 * мини-сцена, тема карточки — уменьшенная карточка, цвет — образец,
 * бейдж — как в профиле. Для остального (приоритет, подсветка клана) —
 * крупная иконка.
 */
import { computed, onMounted, ref } from 'vue'

import AppIcon from '@/components/core/AppIcon.vue'
import { frameById } from '@/data/players/profileCustomization.js'
import { cardTheme } from '@/data/shop/cosmetics.js'
import { loadSkin, renderSkinHead } from '@/utils/minecraftSkin.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  item: { type: Object, required: true },
})

const auth = useAuthStore()

const head = ref(null)

const value = computed(() => props.item.effect_value)
const color = computed(() => props.item.color || '#a78bfa')

const frame = computed(() => (props.item.type === 'avatar_frame' ? frameById(value.value) : null))

const frameStyle = computed(() => {
  const f = frame.value

  if (!f) return { '--preview-frame': color.value }

  return { '--preview-frame': f.gradient || f.color || color.value }
})

const theme = computed(() => (props.item.type === 'card_theme' ? cardTheme(value.value) : null))

const initial = computed(() => (auth.user?.username || 'A').charAt(0).toUpperCase())

onMounted(async () => {
  // Голова из скина нужна только рамкам и фонам — остальным не грузим
  if (!['avatar_frame', 'skin_backdrop'].includes(props.item.type) || !auth.user?.id) return

  const skin = await loadSkin(auth.user.id)

  if (skin) head.value = renderSkinHead(skin.image, 64).toDataURL()
})
</script>

<template>
  <div class="preview" :class="`preview--${item.type}`">
    <!-- Рамка аватара -->
    <div v-if="item.type === 'avatar_frame'" class="preview__frame" :style="frameStyle">
      <span class="preview__avatar">
        <img v-if="head" :src="head" alt="" />
        <span v-else>{{ initial }}</span>
      </span>
    </div>

    <!-- Фон 3D-скина -->
    <div
        v-else-if="item.type === 'skin_backdrop'"
        class="preview__scene mc-backdrop"
        :class="`mc-backdrop--${value}`"
    >
      <span class="preview__scene-head">
        <img v-if="head" :src="head" alt="" />
      </span>
    </div>

    <!-- Тема карточки игрока -->
    <div
        v-else-if="theme"
        class="preview__card"
        :style="{
          '--card-bg': theme.bg,
          '--card-panel': theme.panel,
          '--card-border': theme.border,
          '--card-accent': theme.accent || '#a78bfa',
        }"
    >
      <span class="preview__card-stripe" />
      <span class="preview__card-tier" />
      <span class="preview__card-figure" />
      <span class="preview__card-line" />
      <span class="preview__card-line preview__card-line--short" />
    </div>

    <!-- Акцентный цвет -->
    <div v-else-if="item.type === 'accent_color'" class="preview__swatch">
      <span :style="{ background: value || color }" />
      <span :style="{ background: value || color, opacity: 0.55 }" />
      <span :style="{ background: value || color, opacity: 0.25 }" />
    </div>

    <!-- Бейдж -->
    <span
        v-else-if="item.type === 'badge'"
        class="preview__badge"
        :style="{ '--badge': color }"
    >
      <AppIcon :icon="item.icon" :size="15" />
      {{ item.name.replace(/^Бейдж\s+/, '').replace(/[«»]/g, '') }}
    </span>

    <!-- Всё остальное — крупная иконка -->
    <span v-else class="preview__icon" :style="{ color }">
      <AppIcon :icon="item.icon" :size="34" />
    </span>
  </div>
</template>

<style scoped>
.preview {
  height: 132px;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  background:
      conic-gradient(#1b1825 25%, #1e1a28 0 50%, #1b1825 0 75%, #1e1a28 0)
      0 0 / 24px 24px;
  border-bottom: 1px solid var(--border);
}

/* ---------- Рамка ---------- */

.preview__frame {
  padding: 4px;

  background: var(--preview-frame);
  border-radius: 8px;
}

.preview__avatar {
  width: 72px;
  height: 72px;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #fff;
  background: var(--accent);
  border-radius: 5px;

  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 700;
}

.preview__avatar img {
  width: 100%;
  height: 100%;
  image-rendering: pixelated;
}

/* ---------- Сцена ---------- */

.preview--skin_backdrop {
  background: none;
}

.preview__scene {
  --cell: 24px;

  width: 100%;
  height: 100%;

  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.preview__scene-head {
  position: relative;
  z-index: 1;

  width: 40px;
  height: 40px;
  margin-bottom: 26%;

  background: rgba(0, 0, 0, 0.25);
  border-radius: 3px;
}

.preview__scene-head img {
  width: 100%;
  height: 100%;
  image-rendering: pixelated;
}

/* ---------- Карточка ---------- */

.preview__card {
  position: relative;

  width: 74px;
  height: 104px;

  overflow: hidden;

  background: var(--card-bg);
  border: 2px solid var(--card-border);
  border-radius: 6px;
}

.preview__card-stripe {
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: var(--card-accent);
}

.preview__card-tier {
  position: absolute;
  top: 14px;
  left: 8px;

  width: 18px;
  height: 18px;

  background: var(--card-accent);
  border-radius: 3px;
}

.preview__card-figure {
  position: absolute;
  top: 10px;
  right: 7px;

  width: 30px;
  height: 52px;

  background: var(--card-panel);
  border-radius: 3px;
}

.preview__card-line {
  position: absolute;
  left: 8px;
  right: 8px;
  bottom: 18px;

  height: 4px;

  background: var(--card-accent);
  border-radius: 2px;
  opacity: 0.8;
}

.preview__card-line--short {
  bottom: 10px;
  right: 30px;
  opacity: 0.5;
}

/* ---------- Цвет ---------- */

.preview__swatch {
  display: flex;
  gap: 6px;
}

.preview__swatch span {
  width: 34px;
  height: 34px;

  border-radius: 6px;

  box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.25);
}

/* ---------- Бейдж ---------- */

.preview__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 6px 12px;

  color: var(--badge);
  background: color-mix(in srgb, var(--badge) 14%, #16141c);
  border: 1px solid color-mix(in srgb, var(--badge) 45%, transparent);
  border-radius: 4px;

  font-size: 13px;
  font-weight: 700;
}

/* ---------- Иконка ---------- */

.preview__icon {
  width: 64px;
  height: 64px;

  display: flex;
  align-items: center;
  justify-content: center;

  background: #16141c;
  border: 1px solid var(--border);
  border-radius: 8px;
}
</style>
