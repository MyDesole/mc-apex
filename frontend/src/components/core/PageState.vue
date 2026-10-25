<script setup>
/**
 * Состояние страницы вместо пустого места: «не найдено», «ошибка», «пусто».
 *
 * Раньше при ошибке загрузки страница клана или турнира просто
 * ничего не показывала.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import PixelIcon from '@/components/achievements/PixelIcon.vue'

const props = defineProps({
  kind: { type: String, default: 'empty' }, // notFound | error | empty
  title: { type: String, default: null },
  text: { type: String, default: null },
  // Кнопка: { to, label } — ссылка; без to — событие action
  action: { type: Object, default: null },
})

const emit = defineEmits(['action'])

const DEFAULTS = {
  notFound: { icon: 'flag', code: '404', title: 'Здесь ничего нет', text: 'Страница не найдена — возможно, её удалили или ссылка с опечаткой.', color: '#a78bfa' },
  error: { icon: 'tnt', code: null, title: 'Что-то пошло не так', text: 'Не удалось загрузить данные. Попробуйте обновить страницу.', color: '#ef4444' },
  empty: { icon: 'chest', code: null, title: 'Пока пусто', text: null, color: '#a78bfa' },
}

const preset = computed(() => DEFAULTS[props.kind] ?? DEFAULTS.empty)
</script>

<template>
  <section class="page-state" :class="`page-state--${kind}`" role="status">
    <span v-if="preset.code" class="page-state__code">{{ preset.code }}</span>

    <PixelIcon :icon="preset.icon" :color="preset.color" :size="kind === 'empty' ? 40 : 56" />

    <h2 class="page-state__title">{{ title || preset.title }}</h2>
    <p v-if="text || preset.text" class="page-state__text">{{ text || preset.text }}</p>

    <RouterLink v-if="action?.to" :to="action.to" class="btn btn-primary">{{ action.label }}</RouterLink>
    <button v-else-if="action" type="button" class="btn btn-secondary" @click="emit('action')">{{ action.label }}</button>

    <slot />
  </section>
</template>

<style scoped>
.page-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;

  width: min(560px, calc(100% - 32px));
  margin: 0 auto;
  padding: 72px 0;

  text-align: center;
}

.page-state--empty {
  padding: 40px 0;
}

.page-state__code {
  color: #fff;

  font-family: var(--font-display);
  font-size: clamp(56px, 12vw, 96px);
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.04em;
}

.page-state__title {
  margin: 4px 0 0;

  color: #fff;

  font-size: 22px;
}

.page-state--empty .page-state__title {
  font-family: var(--font-body);
  font-size: 17px;
  letter-spacing: 0;
}

.page-state__text {
  margin: 0;

  color: var(--text-dim);
}

.page-state .btn {
  margin-top: 8px;
}
</style>
