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
  // Спокойный оттенок темы, без ярких градиентов — как остальные плашки сайта
  return t ? `linear-gradient(90deg, ${t.from}2e, ${t.to}12)` : 'rgba(255, 255, 255, 0.03)'
})

const border = computed(() => {
  const t = style.value?.theme

  return t ? `${t.from}88` : 'var(--border)'
})
</script>

<template>
  <div v-if="visible" class="cstyle" :class="{ 'cstyle--compact': compact }" :style="{ background: gradient, borderColor: border }">
    <span v-if="style.emblem" class="cstyle__emblem" :title="style.emblem.title">{{ style.emblem.glyph }}</span>
    <span v-if="style.emblem && compact && !style.motto" class="cstyle__label">{{ style.emblem.title }}</span>
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

/* В списке — компактная «плашка» по размеру содержимого, а не полоса на всю ширину */
.cstyle--compact {
  display: inline-flex;
  width: fit-content;
  max-width: 100%;
  margin: 2px 0;
  padding: 3px 10px;
  gap: 6px;
  font-size: 12px;
  border-radius: 4px;
}

.cstyle__label {
  font-size: 12px;
  font-weight: 700;
  color: #e6e6f4;
}

.cstyle__emblem {
  font-size: 20px;
  line-height: 1;
}

.cstyle--compact .cstyle__emblem {
  font-size: 14px;
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
