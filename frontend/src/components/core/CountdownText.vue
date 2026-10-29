<script setup>
/**
 * Обратный отсчёт до времени `to`: «2 ч 15 мин» или «4:07».
 * Тикает сам по общим часам — родителю не нужно перерисовываться
 * каждую секунду. Когда время вышло, показывает `done`.
 */
import { computed } from 'vue'
import { useNow } from '@/composables/core/useNow.js'

const props = defineProps({
  to: { type: [String, Number], required: true },
  done: { type: String, default: '' },
})

const now = useNow()

const text = computed(() => {
  const ms = new Date(props.to).getTime() - now.value
  if (ms <= 0) return props.done
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const s = Math.floor((ms % 60000) / 1000)
  return h > 0 ? `${h} ч ${m} мин` : `${m}:${String(s).padStart(2, '0')}`
})
</script>

<template>{{ text }}</template>
