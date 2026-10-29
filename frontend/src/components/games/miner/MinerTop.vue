<script setup>
/** Топ шахтёров недели по лучшему забегу и призы тройке. */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useNow } from '@/composables/core/useNow.js'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  top: { type: Object, required: true },
})

const now = useNow()

const endsIn = computed(() => {
  const ms = new Date(props.top.ends_at).getTime() - now.value
  if (!(ms > 0)) return 'подводим итоги'
  const days = Math.floor(ms / 86400000)
  const hours = Math.floor((ms % 86400000) / 3600000)
  return days > 0 ? `${days} д ${hours} ч` : `${hours} ч ${Math.floor((ms % 3600000) / 60000)} мин`
})
</script>

<template>
  <div class="mt">
    <div class="mt-prizes">
      <span v-for="(p, place) in top.prizes" :key="place" class="mt-prize" :class="`mt-prize--${place}`">
        <b>{{ place }} место · ₳ {{ p.coins }}</b>
        <span>+ {{ p.case }}</span>
      </span>
    </div>

    <ol v-if="top.rows?.length" class="mt-list">
      <li v-for="r in top.rows" :key="r.place" :class="`mt-row--${r.place}`">
        <span class="mt-place">{{ r.place }}</span>
        <RouterLink v-if="r.user" :to="`/user/${encodeURIComponent(r.user.username)}`" class="mt-user">
          <img v-if="r.user.avatar_url" :src="r.user.avatar_url" alt="" loading="lazy" />
          <span>{{ r.user.username }}</span>
        </RouterLink>
        <span v-else class="mt-user">—</span>
        <b>₳ {{ r.coins }}</b>
      </li>
    </ol>
    <p v-else class="mt-empty">На этой неделе ещё никто не спускался. Будь первым.</p>

    <div class="mt-foot">
      <span><AppIcon icon="clock" :size="12" /> До итогов: {{ endsIn }}</span>
      <span v-if="top.me?.place">Ты {{ top.me.place }}-й · ₳ {{ top.me.best }}</span>
    </div>
  </div>
</template>

<style scoped>
.mt { display: flex; flex-direction: column; gap: 10px; }

.mt-prizes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 5px; }

.mt-prize {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;

  padding: 7px 4px;

  color: #a8a3b8;
  background: rgba(255, 255, 255, 0.04);
  border-radius: 9px;

  font-size: 10px;
  font-weight: 700;
  line-height: 1.25;
  text-align: center;
}

.mt-prize b { color: #fde68a; font-size: 12.5px; }
.mt-prize--1 { background: rgba(250, 204, 21, 0.1); }

.mt-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 3px; }

.mt-list li {
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;

  padding: 5px 8px;

  border-radius: 8px;
  font-size: 12.5px;
}

.mt-list li:nth-child(odd) { background: rgba(255, 255, 255, 0.03); }
.mt-list b { color: #fde68a; font-size: 12px; }

.mt-place { color: #8a8598; font-weight: 900; text-align: center; }
.mt-row--1 .mt-place { color: #facc15; }
.mt-row--2 .mt-place { color: #e2e8f0; }
.mt-row--3 .mt-place { color: #f59e0b; }

.mt-user { display: inline-flex; align-items: center; gap: 6px; min-width: 0; color: #e6e3ee; text-decoration: none; }
.mt-user span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mt-user img { width: 20px; height: 20px; border-radius: 4px; image-rendering: pixelated; }
.mt-user:hover span { color: #fff; text-decoration: underline; }

.mt-empty { margin: 0; color: #8a8598; font-size: 12px; }

.mt-foot { display: flex; justify-content: space-between; gap: 8px; color: #8a8598; font-size: 11px; font-weight: 700; }
.mt-foot span { display: inline-flex; align-items: center; gap: 4px; }
</style>
