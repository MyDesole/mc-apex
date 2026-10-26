<script setup>
/**
 * Карточка набора косметики: обложка в цветах темы с крупной
 * пиксельной картинкой, состав набора, цена со скидкой.
 */
import { computed } from 'vue'
import AppIcon from '@/components/core/AppIcon.vue'
import PixelSprite from '@/components/shop/PixelSprite.vue'
import { bundleTheme } from '@/data/shop/bundleThemes.js'
import { TYPE_LABELS, formatCoins } from '@/data/wallet/economy.js'
import { frameById } from '@/data/players/profileCustomization.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AvatarFrameDecor from '@/components/players/AvatarFrameDecor.vue'

const props = defineProps({
  bundle: { type: Object, required: true },
  canBuy: { type: Boolean, default: true },
})

const emit = defineEmits(['buy'])

const auth = useAuthStore()

const info = computed(() => props.bundle.bundle ?? { items: [], value: 0, price_for_you: props.bundle.price })
const look = computed(() => bundleTheme(info.value.theme || props.bundle.metadata?.theme))
const frameLook = computed(() => (look.value.frame ? frameById(look.value.frame) : null))
const initial = computed(() => (auth.user?.username || 'A').charAt(0).toUpperCase())

const discount = computed(() => {
  const value = info.value.value
  return value > 0 ? Math.round((1 - props.bundle.price / value) * 100) : 0
})

// Часть набора уже есть — цена ниже базовой
const partlyOwned = computed(() => !props.bundle.owned && info.value.price_for_you < props.bundle.price)
</script>

<template>
  <article
      class="bundle"
      :class="{ 'bundle--light': look.light, 'bundle--owned': bundle.owned }"
      :style="{ '--glow': look.glow, '--accent': look.accent }"
  >
    <div class="bundle__cover" :style="{ background: look.bg }">
      <span class="bundle__rays"></span>
      <!-- Премиум-набор: аватар в живой рамке набора; остальные — пиксель-арт -->
      <span v-if="look.frame" class="bundle__avatar" :style="{ background: frameLook?.gradient || frameLook?.color }">
        <AvatarFrameDecor :frame="look.frame" :size="70" />
        <span class="bundle__avatar-inner">{{ initial }}</span>
      </span>
      <span v-else class="bundle__hero">
        <PixelSprite :sprite="look.hero" :size="64" anim="bounce" />
      </span>

      <span v-if="info.mood" class="bundle__mood">{{ info.mood }}</span>
      <span v-if="discount > 0" class="bundle__discount">−{{ discount }}%</span>
    </div>

    <div class="bundle__body">
      <h3 class="bundle__name">{{ String(bundle.name || '').replace(/^Набор\s+/, '').replace(/[«»]/g, '') }}</h3>
      <p class="bundle__desc">{{ bundle.description }}</p>

      <ul class="bundle__items">
        <li v-for="part in info.items" :key="part.id" :class="{ 'is-owned': part.owned }">
          <span class="bundle__item-icon" :style="{ color: part.color || look.glow }">
            <PixelSprite v-if="part.metadata?.sprite" :sprite="part.metadata.sprite" :size="16" />
            <AppIcon v-else :icon="part.icon" :size="14" />
          </span>
          <span class="bundle__item-name">
            {{ part.name }}
            <small>{{ TYPE_LABELS[part.type] || part.type }}</small>
          </span>
          <em v-if="part.exclusive" class="bundle__exclusive">эксклюзив</em>
          <AppIcon v-if="part.owned" icon="check" :size="14" class="bundle__check" />
        </li>
      </ul>
    </div>

    <div class="bundle__foot">
      <div class="bundle__price">
        <s v-if="info.value > info.price_for_you">₳ {{ formatCoins(info.value) }}</s>
        <strong v-if="!bundle.owned">₳ {{ formatCoins(info.price_for_you) }}</strong>
        <span v-if="partlyOwned" class="bundle__note">с учётом того, что уже есть</span>
      </div>

      <span v-if="bundle.owned" class="bundle__owned">
        <AppIcon icon="check" :size="15" />
        Всё уже есть
      </span>
      <button
          v-else
          type="button"
          class="bundle__buy"
          :disabled="!canBuy"
          @click="emit('buy')"
      >
        {{ canBuy ? 'Купить набор' : 'Не хватает' }}
      </button>
    </div>
  </article>
</template>

<style scoped>
.bundle {
  position: relative;
  overflow: hidden;

  display: flex;
  flex-direction: column;

  background: #15131c;
  border: 1px solid color-mix(in srgb, var(--glow) 35%, transparent);
  border-radius: 16px;

  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.bundle:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 40px color-mix(in srgb, var(--glow) 22%, transparent);
}

.bundle__cover {
  position: relative;
  overflow: hidden;

  height: 150px;

  display: flex;
  align-items: center;
  justify-content: center;
}

.bundle__rays {
  position: absolute;
  inset: -60%;

  background: repeating-conic-gradient(rgba(255, 255, 255, 0.08) 0 8deg, transparent 8deg 20deg);
  mask-image: radial-gradient(circle, #000 15%, transparent 55%);

  animation: rays 18s linear infinite;
}

@keyframes rays {
  to { transform: rotate(360deg); }
}

.bundle__hero {
  position: relative;
  filter: drop-shadow(0 8px 14px rgba(0, 0, 0, 0.45)) drop-shadow(0 0 18px color-mix(in srgb, var(--glow) 60%, transparent));
}

.bundle__avatar {
  position: relative;

  width: 70px;
  height: 70px;
  padding: 4px;

  border-radius: 50%;
  box-shadow: 0 0 24px color-mix(in srgb, var(--glow) 55%, transparent);
}

.bundle__avatar-inner {
  width: 100%;
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: #1a1726;
  border-radius: 50%;

  font-size: 24px;
  font-weight: 900;
}

.bundle__mood,
.bundle__discount {
  position: absolute;
  top: 12px;

  padding: 4px 9px;

  border-radius: 999px;

  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.bundle__mood {
  left: 12px;

  color: #fff;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(4px);
}

.bundle__discount {
  right: 12px;

  color: #052e16;
  background: #4ade80;
}

.bundle__body {
  flex: 1;

  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 16px 16px 8px;
}

.bundle__name {
  margin: 0;
  color: #fff;
  font-size: 18px;
  font-weight: 900;
}

.bundle__desc {
  margin: 0;
  color: #a8a3b8;
  font-size: 13px;
  line-height: 1.5;
}

.bundle__items {
  margin: 4px 0 0;
  padding: 0;

  display: flex;
  flex-direction: column;
  gap: 4px;

  list-style: none;
}

.bundle__items li {
  display: flex;
  align-items: center;
  gap: 9px;

  padding: 5px 8px;

  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
}

.bundle__items li.is-owned {
  opacity: 0.55;
}

.bundle__item-icon {
  width: 24px;
  height: 24px;

  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  background: rgba(0, 0, 0, 0.3);
  border-radius: 6px;
}

.bundle__item-name {
  min-width: 0;
  flex: 1;

  display: flex;
  flex-direction: column;

  overflow: hidden;

  color: #e6e3ee;

  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bundle__item-name small {
  color: #7d788c;
  font-size: 11px;
  font-weight: 500;
}

.bundle__exclusive {
  flex-shrink: 0;

  padding: 2px 6px;

  color: #fde68a;
  background: rgba(250, 204, 21, 0.12);
  border: 1px solid rgba(250, 204, 21, 0.3);
  border-radius: 5px;

  font-size: 10px;
  font-style: normal;
  font-weight: 800;
  text-transform: uppercase;
}

.bundle__check {
  color: #4ade80;
}

.bundle__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  padding: 12px 16px 16px;
}

.bundle__price {
  display: flex;
  flex-direction: column;
}

.bundle__price s {
  color: #7d788c;
  font-size: 12px;
}

.bundle__price strong {
  color: #fde68a;
  font-size: 20px;
  font-weight: 900;
}

.bundle__note {
  color: #86efac;
  font-size: 11px;
}

.bundle__buy {
  min-height: 42px;
  padding: 0 18px;

  color: #fff;
  background: linear-gradient(135deg, color-mix(in srgb, var(--glow) 85%, #fff), color-mix(in srgb, var(--glow) 70%, #000));
  border: 0;
  border-radius: 11px;
  box-shadow: 0 8px 20px color-mix(in srgb, var(--glow) 35%, transparent);

  font-size: 14px;
  font-weight: 900;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);

  cursor: pointer;
}

.bundle__buy:disabled {
  opacity: 0.5;
  cursor: default;
}

.bundle__owned {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  color: #4ade80;
  font-size: 13px;
  font-weight: 800;
}

@media (prefers-reduced-motion: reduce) {
  .bundle__rays {
    animation: none;
  }
}
</style>
