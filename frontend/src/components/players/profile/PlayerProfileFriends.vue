<script setup>
import { computed } from 'vue'
import {tierColor} from "@/composables/players/useTier.js";
import { userLink } from '@/utils/links.js'

const props = defineProps({
  friends: { type: Array, default: () => [] },
})
const emit = defineEmits(['enter', 'leave'])

const list = computed(() => props.friends)
</script>

<template>
  <aside v-if="list.length" class="friends">
    <header class="friends__head">
      <h3 class="friends__title">Друзья</h3>
      <span class="friends__count">{{ list.length }}</span>
    </header>

    <div class="friends__grid">
      <RouterLink
          v-for="f in list"
          :key="f.id"
          :to="userLink(f)"
          class="friend-tile"
          @mouseenter="emit('enter', f, $event)"
          @mouseleave="emit('leave')"
      >
        <img
            v-if="f.avatar_url"
            :src="f.avatar_url"
            :alt="f.username"
            class="friend-tile__img"
        />
        <span v-else class="friend-tile__letter">
          {{ (f.username || 'И').charAt(0).toUpperCase() }}
        </span>

        <span
            class="friend-tile__tier"
            :style="{ background: tierColor(f.tier) }"
        />
      </RouterLink>
    </div>
  </aside>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileFriends.css";
</style>
