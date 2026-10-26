<script setup>
/**
 * Титул под ником (магазин, тип profile_title): текст в оформлении
 * эффектов ника. «Волна» — каждая буква прыгает отдельно.
 */
import { computed } from 'vue'
import { profileTitle } from '@/data/players/flair.js'

const props = defineProps({
  title: { type: String, default: null },
})

const data = computed(() => profileTitle(props.title))
</script>

<template>
  <span v-if="data" class="profile-title">
    <span class="profile-title__ornament">✦</span>
    <span class="name-fx" :class="data.style !== 'plain' ? `name-fx--${data.style}` : 'profile-title__plain'">
      <template v-if="data.style === 'wave'">
        <span
            v-for="(char, i) in [...data.text]"
            :key="i"
            class="name-fx__char"
            :style="{ '--i': i }"
        >{{ char }}</span>
      </template>
      <template v-else>{{ data.text }}</template>
    </span>
    <span class="profile-title__ornament">✦</span>
  </span>
</template>

<style scoped>
.profile-title {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.profile-title__ornament {
  color: rgba(255, 255, 255, 0.35);
  font-size: 9px;
}

.profile-title__plain {
  color: #a8a3b8;
}
</style>
