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
import { STICKER_PACKS } from '@/data/shop/stickers.js'
import { HIGHLIGHT_COLORS, HIGHLIGHT_EFFECTS, highlightColor } from '@/data/clan/clanHighlight.js'
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

// Подсветка клана: цвет — из цветовых стилей, эффект — из эффектов
const clanLook = computed(() => {
  const isColor = HIGHLIGHT_COLORS[value.value]
  const look = highlightColor(isColor ? value.value : 'violet')

  return {
    color: look.color,
    glow: look.glow,
    effect: HIGHLIGHT_EFFECTS[value.value] ? value.value : (isColor ? 'glow' : 'frame'),
  }
})

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

    <!-- Эффект профиля без слоя (свечение, огонь, лёд…): тот же вид на мини-шапке -->
    <div
        v-else-if="item.type === 'profile_effect'"
        class="preview__header preview__header--fx"
        :class="`preview-fx--${value}`"
        :style="{ '--fx': color }"
    >
      <span class="preview__header-fx" />
      <span class="preview__header-name">{{ auth.user?.username || 'Игрок' }}</span>
    </div>

    <!-- Подсветка клана и её оформление: строчка в списке кланов -->
    <div
        v-else-if="item.type === 'clan_highlight' || item.type === 'clan_highlight_style'"
        class="preview__clan"
    >
      <span class="preview__clan-row preview__clan-row--dim"><i /><b /></span>
      <span
          class="preview__clan-row preview__clan-row--lit"
          :class="`hl-fx--${clanLook.effect}`"
          :style="{ '--hl-color': clanLook.color, '--hl-glow': clanLook.glow }"
      >
        <i>{{ (auth.user?.clan_tag || 'APX').slice(0, 3) }}</i>
        <span>Твой клан</span>
      </span>
      <span class="preview__clan-row preview__clan-row--dim"><i /><b /></span>
    </div>

    <!-- Приоритет тир-теста: твоя заявка прыгает в начало очереди -->
    <div v-else-if="item.type === 'tier_priority'" class="preview__queue">
      <span class="preview__queue-row preview__queue-row--me">⚡ Ты</span>
      <span class="preview__queue-row">Игрок 1</span>
      <span class="preview__queue-row">Игрок 2</span>
    </div>

    <!-- Монеты -->
    <div v-else-if="item.type === 'coin_bundle'" class="preview__coins">
      <span v-for="n in 5" :key="n" class="preview__coin" :style="{ '--n': n }">₳</span>
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

    <!-- Значок у ника: ник со значком -->
    <div v-else-if="item.type === 'name_icon'" class="preview__title">
      <span class="preview__title-name preview__icon-name">
        {{ previewName }}
        <PixelSprite
            v-if="item.metadata?.sprite"
            :sprite="item.metadata.sprite"
            :anim="item.metadata.anim"
            :glow="color"
            :size="24"
        />
      </span>
    </div>

    <!-- Стикерпак: веер из трёх стикеров, главный по центру -->
    <div v-else-if="item.type === 'sticker_pack'" class="preview__stickers">
      <span
          v-for="(id, i) in (item.metadata?.stickers || []).slice(0, 3)"
          :key="id"
          class="preview__sticker"
          :class="`preview__sticker--${i}`"
      >
        <PixelSprite
            :sprite="(STICKER_PACKS[value]?.[id] || { sprite: 'creeper' }).sprite"
            :anim="(STICKER_PACKS[value]?.[id] || {}).anim"
            :size="i === 0 ? 46 : 32"
        />
      </span>
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

.preview__icon-name {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

/* Веер: главный стикер по центру, два — позади под углом */
.preview__stickers {
  position: relative;

  width: 120px;
  max-width: 100%;
  height: 80px;
}

.preview__sticker {
  position: absolute;
  bottom: 8px;
  left: 50%;

  display: flex;

  filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.45));
}

.preview__sticker--0 {
  z-index: 2;
  transform: translateX(-50%);
  animation: preview-sticker-bob 2.4s ease-in-out infinite;
}

.preview__sticker--1 {
  transform: translateX(-120%) rotate(-14deg);
  opacity: 0.85;
}

.preview__sticker--2 {
  transform: translateX(20%) rotate(14deg);
  opacity: 0.85;
}

@keyframes preview-sticker-bob {
  50% { translate: 0 -5px; }
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

/* ---------- Эффект профиля без слоя: как в шапке профиля ---------- */

.preview__header--fx {
  overflow: hidden;
  background: #110f18;
}

.preview__header-fx {
  position: absolute;
  inset: 0;

  pointer-events: none;
}

.preview-fx--glow .preview__header-fx {
  background:
      radial-gradient(ellipse at 72% 38%, color-mix(in srgb, var(--fx) 55%, transparent), transparent 55%),
      radial-gradient(ellipse at 20% 75%, color-mix(in srgb, var(--fx) 30%, transparent), transparent 55%);
  filter: blur(14px);
}

.preview-fx--pulse .preview__header-fx {
  background: radial-gradient(ellipse at 70% 35%, color-mix(in srgb, var(--fx) 65%, transparent), transparent 55%);
  filter: blur(14px);
  animation: pfx-pulse 2.4s ease-in-out infinite;
}

.preview-fx--gradient .preview__header-fx {
  inset: -50%;
  background: conic-gradient(from 0deg, #8b5cf6, #ec4899, #f59e0b, #22c55e, #06b6d4, #8b5cf6);
  filter: blur(24px);
  opacity: 0.45;
  animation: pfx-spin 8s linear infinite;
}

.preview-fx--fire .preview__header-fx {
  inset: 25% -10% -30%;
  background:
      radial-gradient(ellipse at 50% 100%, rgba(250, 204, 21, 0.75), rgba(249, 115, 22, 0.55) 25%, rgba(239, 68, 68, 0.3) 45%, transparent 72%);
  filter: blur(10px);
  transform-origin: 50% 100%;
  animation: pfx-fire 1.2s ease-in-out infinite alternate;
}

.preview-fx--ice .preview__header-fx {
  background:
      radial-gradient(ellipse at 75% 20%, rgba(165, 243, 252, 0.6), transparent 35%),
      radial-gradient(ellipse at 25% 75%, rgba(6, 182, 212, 0.45), transparent 50%);
  filter: blur(10px);
  animation: pfx-pulse 4.5s ease-in-out infinite;
}

.preview-fx--legendary .preview__header-fx {
  inset: -40%;
  background:
      radial-gradient(ellipse at 70% 30%, rgba(250, 204, 21, 0.6), transparent 30%),
      radial-gradient(ellipse at 30% 70%, rgba(249, 115, 22, 0.45), transparent 38%),
      conic-gradient(from 0deg, rgba(250, 204, 21, 0.1), rgba(249, 115, 22, 0.25), rgba(250, 204, 21, 0.1));
  filter: blur(14px);
  animation: pfx-spin 10s linear infinite;
}

.preview-fx--warp .preview__header-fx {
  background: repeating-radial-gradient(circle at 70% 40%, color-mix(in srgb, var(--fx) 40%, transparent) 0 4px, transparent 4px 16px);
  animation: pfx-warp 3s linear infinite;
  opacity: 0.7;
}

.preview-fx--glitch .preview__header-fx {
  background:
      linear-gradient(90deg, rgba(239, 68, 68, 0.35), transparent 30%, rgba(6, 182, 212, 0.35) 70%, transparent),
      repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.05) 0 2px, transparent 2px 5px);
  animation: pfx-glitch 1.4s steps(2) infinite;
}

.preview-fx--glitch .preview__header-name {
  animation: pfx-glitch-text 1.4s steps(2) infinite;
}

/* Неизвестный эффект без слоя — мягкое свечение цветом предмета */
.preview__header--fx:not([class*='preview-fx--glow'], [class*='preview-fx--pulse'], [class*='preview-fx--gradient'], [class*='preview-fx--fire'], [class*='preview-fx--ice'], [class*='preview-fx--legendary'], [class*='preview-fx--warp'], [class*='preview-fx--glitch']) .preview__header-fx {
  background: radial-gradient(ellipse at 70% 35%, color-mix(in srgb, var(--fx) 55%, transparent), transparent 55%);
  filter: blur(14px);
}

@keyframes pfx-pulse { 50% { opacity: 0.45; transform: scale(1.08); } }
@keyframes pfx-spin { to { transform: rotate(360deg); } }
@keyframes pfx-fire { from { transform: scaleY(0.92); } to { transform: scaleY(1.1); } }
@keyframes pfx-warp { to { transform: scale(1.3); opacity: 0.3; } }
@keyframes pfx-glitch { 50% { transform: translateX(3px); } }
@keyframes pfx-glitch-text { 50% { transform: translateX(-2px); text-shadow: 2px 0 #ef4444, -2px 0 #06b6d4; } }

/* ---------- Подсветка клана: строка в списке кланов ---------- */

.preview__clan {
  width: 86%;

  display: flex;
  flex-direction: column;
  gap: 6px;
}

.preview__clan-row {
  position: relative;
  overflow: hidden;

  display: flex;
  align-items: center;
  gap: 8px;

  height: 24px;
  padding: 0 8px;

  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 7px;
}

.preview__clan-row--dim i {
  width: 14px;
  height: 14px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.preview__clan-row--dim b {
  width: 50%;
  height: 6px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.preview__clan-row--lit {
  height: 32px;

  color: #fff;
  background: color-mix(in srgb, var(--hl-color) 14%, #15131c);
  border: 1.5px solid var(--hl-color);

  font-size: 12px;
  font-weight: 800;
}

.preview__clan-row--lit i {
  padding: 1px 5px;

  color: #0d0b14;
  background: var(--hl-color);
  border-radius: 4px;

  font-size: 9px;
  font-style: normal;
  font-weight: 900;
}

.preview__clan-row--lit span {
  position: relative;
  z-index: 1;
}

.hl-fx--glow { box-shadow: 0 0 14px var(--hl-glow); }
.hl-fx--pulse { animation: hl-pulse 1.8s ease-in-out infinite; }
.hl-fx--neon { box-shadow: 0 0 8px var(--hl-color), inset 0 0 8px var(--hl-glow); animation: hl-flicker 3s steps(1) infinite; }
.hl-fx--ice { background: linear-gradient(135deg, rgba(165, 243, 252, 0.25), #15131c); border-color: #a5f3fc; box-shadow: 0 0 12px rgba(165, 243, 252, 0.4); }

.hl-fx--gradient,
.hl-fx--legendary,
.hl-fx--holo,
.hl-fx--aurora,
.hl-fx--galaxy,
.hl-fx--lava,
.hl-fx--fire {
  border-color: transparent;
  background:
      linear-gradient(#15131c, #15131c) padding-box,
      var(--hl-border, linear-gradient(90deg, #8b5cf6, #ec4899, #f59e0b, #8b5cf6)) border-box;
  background-size: 100% 100%, 300% 100%;
  animation: hl-slide 3s linear infinite;
}

.hl-fx--legendary { --hl-border: linear-gradient(90deg, #facc15, #f97316, #fde047, #facc15); box-shadow: 0 0 14px rgba(250, 204, 21, 0.4); }
.hl-fx--holo { --hl-border: linear-gradient(90deg, #f0abfc, #67e8f9, #fde68a, #a7f3d0, #f0abfc); }
.hl-fx--aurora { --hl-border: linear-gradient(90deg, #22c55e, #06b6d4, #8b5cf6, #22c55e); }
.hl-fx--galaxy { --hl-border: linear-gradient(90deg, #6366f1, #a855f7, #ec4899, #6366f1); }
.hl-fx--lava,
.hl-fx--fire { --hl-border: linear-gradient(90deg, #ef4444, #f97316, #facc15, #ef4444); box-shadow: 0 4px 14px rgba(249, 115, 22, 0.35); }

.hl-fx--storm { animation: hl-storm 2.6s infinite; }
.hl-fx--matrix { background: repeating-linear-gradient(90deg, rgba(34, 197, 94, 0.18) 0 2px, transparent 2px 6px), #07110a; border-color: #22c55e; }
.hl-fx--glitch { animation: pfx-glitch 1.2s steps(2) infinite; box-shadow: 2px 0 rgba(239, 68, 68, 0.6), -2px 0 rgba(6, 182, 212, 0.6); }

.hl-fx--shine::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(110deg, transparent 35%, rgba(255, 255, 255, 0.45) 50%, transparent 65%);
  transform: translateX(-100%);
  animation: hl-shine 2.4s ease-in-out infinite;
}

@keyframes hl-pulse { 50% { box-shadow: 0 0 18px var(--hl-glow); } }
@keyframes hl-flicker { 0%, 100% { opacity: 1; } 92% { opacity: 0.6; } 94% { opacity: 1; } }
@keyframes hl-slide { to { background-position: 0 0, 300% 0; } }
@keyframes hl-storm { 0%, 88%, 100% { box-shadow: none; } 90%, 94% { box-shadow: 0 0 18px rgba(186, 230, 253, 0.8); } }
@keyframes hl-shine { 60%, 100% { transform: translateX(100%); } }

/* ---------- Приоритет тир-теста: очередь ---------- */

.preview__queue {
  width: 70%;

  display: flex;
  flex-direction: column;
  gap: 5px;
}

.preview__queue-row {
  padding: 5px 10px;

  color: #a8a3b8;
  background: rgba(255, 255, 255, 0.04);
  border-radius: 7px;

  font-size: 11px;
  font-weight: 700;
}

.preview__queue-row--me {
  color: #1c1917;
  background: linear-gradient(90deg, #fde047, #f59e0b);
  box-shadow: 0 0 14px rgba(250, 204, 21, 0.45);

  font-weight: 900;

  animation: preview-queue-jump 2.6s ease-in-out infinite;
}

@keyframes preview-queue-jump {
  0%, 15% { transform: translateY(58px); opacity: 0.6; }
  40%, 100% { transform: translateY(0); opacity: 1; }
}

/* ---------- Монеты ---------- */

.preview__coins {
  position: relative;
  width: 90px;
  height: 80px;
}

.preview__coin {
  position: absolute;
  left: calc(10px + (var(--n) - 1) * 14px);
  bottom: calc(6px + (var(--n) - 1) * 6px);

  width: 30px;
  height: 30px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #78350f;
  background: radial-gradient(circle at 35% 30%, #fef08a, #facc15 55%, #ca8a04);
  border-radius: 50%;
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.4), inset 0 -2px 0 rgba(0, 0, 0, 0.15);

  font-size: 14px;
  font-weight: 900;

  animation: preview-coin 2s ease-in-out infinite;
  animation-delay: calc(var(--n) * 0.15s);
}

@keyframes preview-coin {
  50% { translate: 0 -5px; }
}

@media (prefers-reduced-motion: reduce) {
  .preview *,
  .preview *::after {
    animation: none !important;
  }
}
</style>
