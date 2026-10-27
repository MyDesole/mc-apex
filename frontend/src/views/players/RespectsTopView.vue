<script setup>
/**
 * Топ уважаемых недели: у кого больше всего респектов за 7 дней.
 * Тройка — на пьедестале, остальные — списком.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'
import UserName from '@/components/players/UserName.vue'
import { userLink } from '@/utils/links.js'

const players = ref([])
const loading = ref(true)

const podium = computed(() => {
  const [first, second, third] = players.value
  // Второе слева, первое по центру, третье справа
  return [second, first, third].map((p, i) => p && { ...p, place: [2, 1, 3][i] }).filter(Boolean)
})

const rest = computed(() => players.value.slice(3))

onMounted(async () => {
  try {
    players.value = (await api.get('/respects/top')).players ?? []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rt">
    <header class="rt-hero">
      <span class="rt-hero__fire">🔥</span>
      <h1>Самые уважаемые недели</h1>
      <p>Респект можно дать любому игроку раз в день — кнопкой в шапке его профиля. Здесь те, кого уважают больше всех за последние 7 дней.</p>
    </header>

    <p v-if="loading" class="rt-empty">Считаем респекты…</p>
    <p v-else-if="!players.length" class="rt-empty">На этой неделе ещё никто не получил респект. Зайди в профиль друга и стань первым!</p>

    <template v-else>
      <section class="rt-podium">
        <RouterLink
            v-for="p in podium"
            :key="p.id"
            :to="userLink(p)"
            class="rt-place"
            :class="`rt-place--${p.place}`"
        >
          <span class="rt-place__crown">{{ p.place === 1 ? '👑' : p.place === 2 ? '🥈' : '🥉' }}</span>
          <span class="rt-place__avatar">
            <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
            <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
          </span>
          <UserName :user="p" class="rt-place__name" />
          <span class="rt-place__count">🔥 {{ p.respects }}</span>
          <span class="rt-place__stand">{{ p.place }}</span>
        </RouterLink>
      </section>

      <ol v-if="rest.length" class="rt-list" start="4">
        <li v-for="(p, i) in rest" :key="p.id">
          <RouterLink :to="userLink(p)" class="rt-row">
            <span class="rt-row__pos">{{ i + 4 }}</span>
            <span class="rt-row__avatar">
              <img v-if="p.avatar_url" :src="p.avatar_url" alt="" />
              <template v-else>{{ p.username.charAt(0).toUpperCase() }}</template>
            </span>
            <UserName :user="p" />
            <span class="rt-row__count">🔥 {{ p.respects }}</span>
          </RouterLink>
        </li>
      </ol>
    </template>
  </div>
</template>

<style scoped>
.rt {
  width: min(860px, calc(100% - 32px));
  margin: 24px auto 60px;

  display: flex;
  flex-direction: column;
  gap: 22px;
}

.rt-hero {
  position: relative;
  overflow: hidden;

  padding: 30px;

  background:
      radial-gradient(circle at 85% 20%, rgba(249, 115, 22, 0.35), transparent 50%),
      linear-gradient(135deg, #2a1208, #0e0a12);
  border: 1px solid rgba(251, 146, 60, 0.35);
  border-radius: 24px;

  text-align: center;
}

.rt-hero__fire {
  display: inline-block;
  font-size: 46px;
  animation: rt-flicker 1.8s ease-in-out infinite;
}

@keyframes rt-flicker {
  50% { transform: scale(1.1) rotate(-5deg); }
}

.rt-hero h1 {
  margin: 6px 0 8px;

  color: #fff;

  font-family: var(--font-display);
  font-size: clamp(28px, 5vw, 44px);
}

.rt-hero p {
  max-width: 560px;
  margin: 0 auto;

  color: #d6c3b6;

  font-size: 14px;
  line-height: 1.5;
}

.rt-empty {
  padding: 40px 16px;

  color: #8a8598;
  border: 2px dashed rgba(255, 255, 255, 0.1);
  border-radius: 18px;

  text-align: center;
}

/* Пьедестал */
.rt-podium {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: end;
  gap: 12px;
}

.rt-place {
  position: relative;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;

  padding: 18px 10px 0;

  color: inherit;
  text-decoration: none;

  transition: transform 0.2s ease;
}

.rt-place:hover {
  transform: translateY(-4px);
}

.rt-place__crown {
  font-size: 26px;
}

.rt-place__avatar {
  width: 70px;
  height: 70px;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: linear-gradient(135deg, #7c3aed, #4c1d95);
  border: 3px solid var(--medal);
  border-radius: 22px;
  box-shadow: 0 0 24px color-mix(in srgb, var(--medal) 50%, transparent);

  font-size: 26px;
  font-weight: 900;
}

.rt-place--1 .rt-place__avatar {
  width: 90px;
  height: 90px;
}

.rt-place__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rt-place__name {
  max-width: 100%;
  overflow: hidden;

  color: #fff;

  font-size: 14px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rt-place__count {
  color: #fdba74;
  font-weight: 900;
}

.rt-place__stand {
  width: 100%;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-top: 6px;

  color: rgba(0, 0, 0, 0.55);
  background: linear-gradient(180deg, var(--medal), color-mix(in srgb, var(--medal) 55%, #000));
  border-radius: 14px 14px 0 0;

  font-family: var(--font-display);
  font-size: 34px;
  font-weight: 900;
}

/* Место — своя колонка: первый всегда по центру, даже если он один */
.rt-place--1 { --medal: #fbbf24; grid-column: 2; grid-row: 1; }
.rt-place--2 { --medal: #cbd5e1; grid-column: 1; grid-row: 1; }
.rt-place--3 { --medal: #d97706; grid-column: 3; grid-row: 1; }

.rt-place--1 .rt-place__stand { height: 110px; }
.rt-place--2 .rt-place__stand { height: 80px; }
.rt-place--3 .rt-place__stand { height: 60px; }

/* Остальные */
.rt-list {
  margin: 0;
  padding: 0;

  display: flex;
  flex-direction: column;
  gap: 6px;

  list-style: none;
}

.rt-row {
  display: flex;
  align-items: center;
  gap: 12px;

  padding: 10px 14px;

  color: #fff;
  background: var(--bg-card, #15131c);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 14px;
  text-decoration: none;

  font-weight: 800;

  transition: border-color 0.15s ease, transform 0.15s ease;
}

.rt-row:hover {
  border-color: rgba(251, 146, 60, 0.5);
  transform: translateX(3px);
}

.rt-row__pos {
  width: 26px;
  color: #8a8598;
  font-family: var(--font-display);
}

.rt-row__avatar {
  width: 36px;
  height: 36px;
  overflow: hidden;

  display: flex;
  align-items: center;
  justify-content: center;

  background: linear-gradient(135deg, #7c3aed, #4c1d95);
  border-radius: 11px;
}

.rt-row__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rt-row__count {
  margin-left: auto;
  color: #fdba74;
}

@media (max-width: 560px) {
  .rt-podium {
    gap: 6px;
  }

  .rt-place__avatar {
    width: 52px;
    height: 52px;
  }

  .rt-place--1 .rt-place__avatar {
    width: 64px;
    height: 64px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .rt-hero__fire {
    animation: none;
  }
}
</style>
