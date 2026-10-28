<script setup>
/**
 * Карточка лота в сетке аукциона: питомец крупно с уровнем, черты,
 * таймер и цена. Клик — открыть лот (ставки, история, чат).
 */
import { computed } from 'vue'
import AppIcon from '@/components/core/AppIcon.vue'
import ShopItemPreview from '@/components/shop/ShopItemPreview.vue'
import { PET_RARITY } from '@/data/players/petTraits.js'

const props = defineProps({
  lot: { type: Object, required: true },
  now: { type: Number, required: true },
})

defineEmits(['open'])

const traits = computed(() => props.lot.pet?.pet ?? null)
const rarity = computed(() => PET_RARITY[traits.value?.rarity] ?? { label: 'Питомец', color: '#a8a3b8' })

const msLeft = computed(() => new Date(props.lot.ends_at).getTime() - props.now)

const timer = computed(() => {
  const ms = msLeft.value
  if (ms <= 0) return 'завершается'
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин` : `${m}:${String(s).padStart(2, '0')}`
})

const price = computed(() => Number(props.lot.current_price || props.lot.start_price || 0).toLocaleString('ru-RU'))
</script>

<template>
  <button
      type="button"
      class="lc"
      :class="{ 'is-leading': lot.leading, 'is-mine': lot.mine }"
      :style="{ '--rar': rarity.color }"
      @click="$emit('open', lot)"
  >
    <div class="lc__stage">
      <ShopItemPreview v-if="lot.pet" :item="lot.pet" class="lc__preview" />

      <span class="lc__level" title="Уровень питомца">
        <small>УР</small>{{ lot.level ?? traits?.level ?? 1 }}
      </span>
      <span class="lc__timer" :class="{ 'is-soon': msLeft < 10 * 60000 }">
        <AppIcon icon="bolt" :size="11" /> {{ timer }}
      </span>
      <span v-if="lot.mine" class="lc__flag">Твой лот</span>
      <span v-else-if="lot.leading" class="lc__flag lc__flag--lead">Лидируешь</span>
    </div>

    <div class="lc__body">
      <div class="lc__name">
        <strong>{{ traits?.name || lot.pet?.name }}</strong>
        <em v-if="traits?.serial">#{{ traits.serial }}</em>
      </div>
      <span class="lc__rarity">{{ rarity.label }}</span>

      <ul v-if="traits" class="lc__traits">
        <li>{{ traits.model?.name }}</li>
        <li>{{ traits.backdrop?.name }}</li>
        <li>{{ traits.accessory?.name }}</li>
      </ul>
    </div>

    <div class="lc__foot">
      <div class="lc__price">
        <span>{{ lot.current_price ? 'Ставка' : 'Старт' }}</span>
        <b>₳ {{ price }}</b>
      </div>
      <div class="lc__meta">
        <span v-if="lot.arena" title="Рейтинг арены"><AppIcon icon="sword" :size="12" /> {{ lot.arena.rating }}</span>
        <span title="Ставок"><AppIcon icon="coin" :size="12" /> {{ lot.bids_count }}</span>
        <span title="Сообщений в чате"><AppIcon icon="chat" :size="12" /> {{ lot.messages_count ?? 0 }}</span>
      </div>
    </div>
  </button>
</template>

<style scoped>
.lc {
  display: flex;
  flex-direction: column;

  padding: 0;
  overflow: hidden;

  color: inherit;
  background: var(--bg-card, #15131c);
  border: 1px solid color-mix(in srgb, var(--rar) 28%, rgba(255, 255, 255, 0.06));
  border-radius: 18px;

  font: inherit;
  text-align: left;

  cursor: pointer;
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.lc:hover {
  border-color: color-mix(in srgb, var(--rar) 70%, transparent);
  box-shadow: 0 16px 36px -20px var(--rar);
  transform: translateY(-3px);
}

.lc.is-leading { border-color: #22c55e; }

.lc__stage {
  position: relative;
  height: 150px;
  background: radial-gradient(circle at 50% 70%, color-mix(in srgb, var(--rar) 22%, transparent), transparent 70%);
}

.lc__preview { height: 100%; }

.lc__level {
  position: absolute;
  left: 10px;
  bottom: 10px;

  display: inline-flex;
  align-items: baseline;
  gap: 3px;

  padding: 4px 9px;

  color: #fff;
  background: rgba(10, 8, 16, 0.72);
  border: 1px solid color-mix(in srgb, var(--rar) 55%, transparent);
  border-radius: 10px;

  font-size: 16px;
  font-weight: 900;
  line-height: 1;
}

.lc__level small { color: var(--rar); font-size: 9px; letter-spacing: 0.08em; }

/* Внизу справа: сверху справа превью рисует свой номер экземпляра */
.lc__timer {
  position: absolute;
  right: 10px;
  bottom: 10px;

  display: inline-flex;
  align-items: center;
  gap: 4px;

  padding: 3px 8px;

  color: #e9e5f5;
  background: rgba(10, 8, 16, 0.7);
  border-radius: 999px;

  font-size: 11.5px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.lc__timer.is-soon { color: #fff; background: #dc2626; }

.lc__flag {
  position: absolute;
  top: 10px;
  left: 10px;

  padding: 3px 8px;

  color: #e9e5f5;
  background: rgba(10, 8, 16, 0.7);
  border-radius: 999px;

  font-size: 10.5px;
  font-weight: 800;
}

.lc__flag--lead { color: #052e16; background: #4ade80; }

.lc__body {
  display: flex;
  flex-direction: column;
  gap: 4px;

  padding: 12px 14px 0;
}

.lc__name { display: flex; align-items: baseline; gap: 6px; min-width: 0; }
.lc__name strong { overflow: hidden; color: #fff; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
.lc__name em { flex-shrink: 0; color: #a8a3b8; font-size: 12px; font-style: normal; font-weight: 700; }

.lc__rarity {
  color: var(--rar);
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.lc__traits {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;

  margin: 4px 0 0;
  padding: 0;

  list-style: none;
}

.lc__traits li {
  padding: 2px 7px;

  color: #c9c4d8;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 6px;

  font-size: 11px;
  font-weight: 600;
}

.lc__foot {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;

  margin-top: auto;
  padding: 12px 14px 14px;
}

.lc__price { display: flex; flex-direction: column; }
.lc__price span { color: #8a8598; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.lc__price b { color: #fde68a; font-size: 19px; font-weight: 900; }

.lc__meta { display: flex; gap: 10px; color: #8a8598; font-size: 12px; font-weight: 700; }
.lc__meta span { display: inline-flex; align-items: center; gap: 4px; }
</style>
