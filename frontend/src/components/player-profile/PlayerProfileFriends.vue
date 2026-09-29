<script setup>
import { computed } from 'vue'
import {tierColor} from "@/composables/useTier.js";

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
          :to="`/players/${f.id}`"
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
.friends {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: linear-gradient(180deg, #171a21 0%, #10131a 100%);
  border: 1px solid var(--border);
  border-radius: 16px;
}

.friends__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.friends__title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: #c7d5e0;
  text-transform: uppercase;
  letter-spacing: 1.2px;
}

.friends__count {
  font-size: 11px;
  color: #4a5568;
  font-weight: 700;
}

.friends__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

/* === TILE === */

.friend-tile {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: 6px;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  text-decoration: none;
  color: #fff;
  font-size: 16px;
  font-weight: 900;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.friend-tile:hover {
  transform: translateY(-2px) scale(1.06);
  box-shadow: 0 8px 20px rgba(124, 58, 237, 0.5);
  z-index: 5;
}

.friend-tile__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
  display: block;
}

.friend-tile__letter {
  position: relative;
}

.friend-tile__tier {
  position: absolute;
  right: -3px;
  bottom: -3px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #171a21;
  z-index: 2;
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .friends {
    padding: 12px;
  }

  .friends__grid {
    grid-template-columns: repeat(5, 1fr);
    gap: 5px;
  }

  .friend-tile {
    font-size: 14px;
  }

  .friend-tile__tier {
    width: 10px;
    height: 10px;
    right: -2px;
    bottom: -2px;
  }
}
</style>