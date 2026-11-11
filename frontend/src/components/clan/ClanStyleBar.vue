<script setup>
/**
 * Оформление клана одной строкой: эмблема, девиз и «кого ищем».
 * Подкрашивается цветовой темой клана. Если клан ничего не настроил — не рисуется.
 */
import { computed } from 'vue'

const props = defineProps({
  clan: { type: Object, required: true },
  // compact — для карточек в списке: без текста набора
  compact: { type: Boolean, default: false },
})

const style = computed(() => props.clan?.style ?? null)

const visible = computed(() => Boolean(style.value?.emblem || style.value?.motto || style.value?.theme))

const gradient = computed(() => {
  const t = style.value?.theme
  return t ? `linear-gradient(90deg, ${t.from}55, ${t.to}22)` : 'rgba(255, 255, 255, 0.05)'
})

const border = computed(() => style.value?.theme?.from ?? 'rgba(255, 255, 255, 0.15)')
</script>

<template>
  <div v-if="visible" class="cstyle" :class="{ 'cstyle--compact': compact }" :style="{ background: gradient, borderColor: border }">
    <span v-if="style.emblem" class="cstyle__emblem" :title="style.emblem.title">{{ style.emblem.glyph }}</span>
    <span v-if="style.motto" class="cstyle__motto">«{{ style.motto }}»</span>
    <span v-if="!compact && style.recruit_text" class="cstyle__recruit">
      🔎 {{ style.recruit_text }}
      <template v-if="style.min_rating > 0"> · от {{ style.min_rating }} очков рейтинга</template>
    </span>
  </div>
</template>

<style scoped>
.cstyle {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 12px;
  padding: 7px 12px;
  border: 1px solid;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.35;
}

.cstyle--compact {
  padding: 4px 9px;
  font-size: 12px;
}

.cstyle__emblem {
  font-size: 20px;
  line-height: 1;
}

.cstyle--compact .cstyle__emblem {
  font-size: 15px;
}

.cstyle__motto {
  font-style: italic;
  color: #f4f4ff;
}

.cstyle__recruit {
  color: #c9c9e0;
  font-size: 13px;
}
</style>
