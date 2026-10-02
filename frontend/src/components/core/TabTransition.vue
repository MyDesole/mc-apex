<script setup>
/**
 * Обёртка для плавной смены содержимого вкладок и табов.
 *
 * Классы переходов лежат в assets/transitions.css и подключены
 * глобально, поэтому импортировать их не нужно.
 *
 *   <TabTransition :active="tab" name="tab-fade">
 *     <div v-if="tab === 'a'">…</div>
 *     <div v-else-if="tab === 'b'">…</div>
 *   </TabTransition>
 *
 * mode="out-in" обязателен: без него уходящий и приходящий блоки
 * накладываются друг на друга, и переход выглядит рывком.
 *
 * Содержимое сменяется по ключу, поэтому цепочка v-if / v-else-if
 * должна быть обёрнута в один элемент — иначе Vue считает её одним
 * узлом и переход не сработает.
 */
defineProps({
  /** Значение активной вкладки: смена ключа запускает переход. */
  active: { type: [String, Number], required: true },

  /** Имя перехода: tab-fade, slide-x, fade-scale. */
  name: { type: String, default: 'tab-fade' },

  /** Тег обёртки. */
  tag: { type: String, default: 'div' },
})
</script>

<template>
  <Transition :name="name" mode="out-in">
    <component :is="tag" :key="active" class="tab-transition">
      <slot />
    </component>
  </Transition>
</template>

<style scoped>
/*
 * Обёртка не должна влиять на раскладку: она лишь держит ключ,
 * по которому Vue понимает, что содержимое сменилось.
 */
.tab-transition {
  display: contents;
}
</style>
