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
import { frameById, layerEffect } from '@/data/players/profileCustomization.js'
import ProfileEffectLayer from '@/components/players/ProfileEffectLayer.vue'
import AvatarFrameDecor from '@/components/players/AvatarFrameDecor.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { bundleTheme } from '@/data/shop/bundleThemes.js'
import '@/components/shop/badgeStyles.css'
import ProfileEntrance from '@/components/players/ProfileEntrance.vue'
import CursorTrail from '@/components/players/CursorTrail.vue'
import ProfilePet from '@/components/players/ProfilePet.vue'
import ProfileTitle from '@/components/players/ProfileTitle.vue'
import { cardTheme } from '@/data/shop/cosmetics.js'
import { profileLayout } from '@/data/players/profileLayouts.js'
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

// Макет профиля: мини-страница с расстановкой и цветами макета
const layout = computed(() => (props.item.type === 'profile_layout' ? profileLayout(value.value) : null))

const hasLayer = computed(() => !!layerEffect(value.value))

const bundleLook = computed(() => bundleTheme(props.item.bundle?.theme || props.item.metadata?.theme))

const initial = computed(() => (auth.user?.username || 'A').charAt(0).toUpperCase())

const previewName = computed(() => auth.user?.username || 'ApexPlayer')

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
      <AvatarFrameDecor :frame="value" :size="80" />
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

    <!-- Макет профиля: миниатюра страницы -->
    <div
        v-else-if="layout"
        class="preview__page"
        :class="`preview__page--${layout.arrangement}`"
        :style="{
          '--lp-page': layout.preview.page,
          '--lp-card': layout.preview.card,
          '--lp-side': layout.preview.side,
          '--lp-accent': layout.preview.accent,
          '--lp-line': layout.preview.line,
        }"
    >
      <span class="preview__page-main">
        <span class="preview__page-head">
          <span class="preview__page-avatar" />
          <span class="preview__page-name" />
        </span>
        <span class="preview__page-row" />
        <span class="preview__page-row preview__page-row--short" />
      </span>
      <span class="preview__page-side">
        <span class="preview__page-chip" />
        <span class="preview__page-chip" />
        <span class="preview__page-chip" />
      </span>
    </div>

    <!-- Эффект профиля: живой слой на мини-шапке -->
    <div
        v-else-if="item.type === 'profile_effect' && hasLayer"
        class="preview__header"
    >
      <ProfileEffectLayer :effect="value" :density="0.6" />
      <span class="preview__header-name">{{ auth.user?.username || 'Игрок' }}</span>
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
        :class="[
          { 'preview__badge--sprite': item.metadata?.sprite },
          item.metadata?.style ? `badge-fx--${item.metadata.style}` : null,
        ]"
        :style="{ '--badge': color }"
    >
      <PixelSprite
          v-if="item.metadata?.sprite"
          :sprite="item.metadata.sprite"
          :size="22"
          :anim="item.metadata.anim"
          :glow="color"
      />
      <AppIcon v-else :icon="item.icon" :size="15" />
      {{ item.name.replace(/^Бейдж\s+/, '').replace(/[«»]/g, '') }}
    </span>

    <!-- Эффект ника: ник игрока крупно -->
    <div v-else-if="item.type === 'name_effect'" class="preview__name">
      <span class="name-fx" :class="`name-fx--${value}`">
        <template v-if="value === 'wave'">
          <span
              v-for="(char, i) in [...previewName]"
              :key="i"
              class="name-fx__char"
              :style="{ '--i': i }"
          >{{ char }}</span>
        </template>
        <template v-else>{{ previewName }}</template>
      </span>
    </div>

    <!-- Появление профиля: мини-профиль, интро по кругу -->
    <div v-else-if="item.type === 'profile_entrance'" class="preview__mini-profile">
      <span class="preview__mini-avatar">{{ initial }}</span>
      <span class="preview__mini-lines"><i></i><i></i></span>
      <ProfileEntrance :entrance="value" loop />
    </div>

    <!-- След курсора: курсор сам рисует восьмёрку -->
    <div v-else-if="item.type === 'cursor_trail'" class="preview__trail">
      <CursorTrail :trail="value" auto />
    </div>

    <!-- Питомец: живёт прямо в превью -->
    <div v-else-if="item.type === 'profile_pet'" class="preview__pet">
      <ProfilePet :pet="value" :size="34" />
    </div>

    <!-- Оформление сообщений: пара пузырей чата -->
    <div v-else-if="item.type === 'chat_bubble'" class="preview__chat">
      <span class="preview__bubble preview__bubble--other">Го на КВ?</span>
      <span class="preview__bubble msg__bubble bubble-fx" :class="`bubble-fx--${value}`">Уже бегу 🔥</span>
    </div>

    <!-- Титул под ником -->
    <div v-else-if="item.type === 'profile_title'" class="preview__title">
      <span class="preview__title-name">{{ previewName }}</span>
      <ProfileTitle :title="value" />
    </div>

    <!-- Набор: тематический фон и состав пиксельными значками -->
    <div
        v-else-if="item.type === 'bundle'"
        class="preview__bundle"
        :style="{ background: bundleLook.bg, '--glow': bundleLook.glow }"
    >
      <span
          v-for="part in (item.bundle?.items || []).slice(0, 5)"
          :key="part.id"
          class="preview__bundle-part"
          :style="{ '--part': part.color || color }"
      >
        <PixelSprite v-if="part.metadata?.sprite" :sprite="part.metadata.sprite" :size="20" />
        <AppIcon v-else :icon="part.icon" :size="16" />
      </span>
    </div>

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
  position: relative;

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

/* ---------- Макет профиля ---------- */

.preview--profile_layout {
  background: none;
}

.preview__page {
  width: 100%;
  height: 100%;
  padding: 12px 16px;

  display: grid;
  grid-template-columns: 1fr 34%;
  gap: 8px;

  background: var(--lp-page);
}

.preview__page--left {
  grid-template-columns: 34% 1fr;
}

.preview__page--left .preview__page-side {
  order: -1;
}

.preview__page--top,
.preview__page--bottom {
  grid-template-columns: 1fr;
  grid-template-rows: 1fr auto;
}

.preview__page--top .preview__page-side {
  order: -1;
}

.preview__page--top .preview__page-side,
.preview__page--bottom .preview__page-side {
  flex-direction: row;
  padding: 5px;
}

.preview__page-main,
.preview__page-side {
  display: flex;
  flex-direction: column;
  gap: 6px;

  padding: 7px;

  border: 1px solid color-mix(in srgb, var(--lp-accent) 55%, transparent);
  border-radius: 4px;
}

.preview__page-main {
  background: var(--lp-card);
}

.preview__page-side {
  background: var(--lp-side);
}

.preview__page-head {
  display: flex;
  align-items: center;
  gap: 6px;
}

.preview__page-avatar {
  width: 18px;
  height: 18px;
  flex: 0 0 auto;

  background: var(--lp-accent);
  border-radius: 3px;
}

.preview__page-name,
.preview__page-row {
  height: 5px;
  flex: 1;

  background: var(--lp-line);
  border-radius: 2px;
  opacity: 0.85;
}

/* Строки текста — тонкие полоски, а не растянутые по высоте блоки */
.preview__page-row {
  flex: 0 0 5px;
}

.preview__page-row--short {
  max-width: 60%;
  opacity: 0.5;
}

.preview__page-chip {
  flex: 1;
  min-height: 9px;

  background: color-mix(in srgb, var(--lp-accent) 40%, transparent);
  border-radius: 2px;
}

/* ---------- Эффект профиля ---------- */

.preview--profile_effect {
  background: #121019;
}

.preview__header {
  position: relative;

  width: 100%;
  height: 100%;

  display: flex;
  align-items: flex-end;

  padding: 14px;
}

.preview__header-name {
  position: relative;
  z-index: 5;

  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
  color: #fff;
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

.preview__badge--sprite {
  padding: 5px 12px 5px 8px;
}

/* ---------- Эффект ника ---------- */

.preview__name {
  max-width: 100%;
  padding: 0 12px;

  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 800;
  white-space: nowrap;
}

/* ---------- Появление профиля ---------- */

.preview__mini-profile {
  position: relative;
  overflow: hidden;

  width: 82%;
  height: 96px;

  display: flex;
  align-items: center;
  gap: 12px;

  padding: 0 14px;

  background: linear-gradient(135deg, #1d1a28, #14121c);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
}

.preview__mini-avatar {
  width: 44px;
  height: 44px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  color: #fff;
  background: #7c3aed;
  border-radius: 50%;

  font-weight: 900;
}

.preview__mini-lines {
  flex: 1;

  display: flex;
  flex-direction: column;
  gap: 8px;
}

.preview__mini-lines i {
  height: 8px;

  background: rgba(255, 255, 255, 0.14);
  border-radius: 4px;
}

.preview__mini-lines i:last-child {
  width: 60%;
}

/* ---------- След курсора ---------- */

.preview__trail {
  position: relative;

  width: 100%;
  height: 100%;
}

/* ---------- Питомец ---------- */

.preview__pet {
  position: relative;

  width: 100%;
  height: 100%;

  background: linear-gradient(transparent 70%, rgba(255, 255, 255, 0.04));
}

/* ---------- Сообщения ---------- */

.preview__chat {
  width: 86%;

  display: flex;
  flex-direction: column;
  gap: 8px;
}

.preview__bubble {
  max-width: 80%;
  padding: 8px 12px;

  color: #e6e3ee;
  background: #2a2638;
  border: 1px solid transparent;
  border-radius: 14px 14px 14px 4px;

  font-size: 13px;
  font-weight: 600;
}

.preview__bubble:not(.preview__bubble--other) {
  align-self: flex-end;
  border-radius: 14px 14px 4px 14px;
}

/* ---------- Титул ---------- */

.preview__title {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.preview__title-name {
  color: #fff;

  font-family: var(--font-display);
  font-size: 18px;
  font-weight: 800;
}

/* ---------- Набор ---------- */

.preview__bundle {
  width: 100%;
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;

  padding: 14px;
}

.preview__bundle-part {
  width: 36px;
  height: 36px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--part);
  background: rgba(10, 8, 14, 0.55);
  border: 1px solid color-mix(in srgb, var(--part) 50%, transparent);
  border-radius: 8px;
  box-shadow: 0 0 12px color-mix(in srgb, var(--glow) 40%, transparent);

  animation: bundle-bob 2.4s ease-in-out infinite;
}

.preview__bundle-part:nth-child(2) { animation-delay: -0.4s; }
.preview__bundle-part:nth-child(3) { animation-delay: -0.8s; }
.preview__bundle-part:nth-child(4) { animation-delay: -1.2s; }
.preview__bundle-part:nth-child(5) { animation-delay: -1.6s; }

@keyframes bundle-bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
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
