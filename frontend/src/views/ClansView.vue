<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const clans = ref([])
const loading = ref(true)
const search = ref('')

let timer = null

async function load() {
  loading.value = true

  try {
    const data = await clansApi.list(
        search.value
            ? { search: search.value }
            : {}
    )

    clans.value = data.data
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  clearTimeout(timer)

  timer = setTimeout(load, 300)
})

onMounted(load)
</script>

<template>
  <div class="clans-page">

    <!-- =========================
         ШАПКА
    ========================== -->

    <div class="page-head">

      <div class="head-content">
        <div class="eyebrow">
          <span class="eyebrow-dot"></span>
          Сообщество
        </div>

        <h1>Кланы</h1>

        <p>
          Найди свой клан или создай собственный
          и собери сильнейшую команду.
        </p>
      </div>

      <RouterLink
          to="/clans/create"
          class="create-button"
      >
        <span class="create-icon">+</span>
        <span>Создать клан</span>
      </RouterLink>

    </div>

    <!-- =========================
         ПОИСК
    ========================== -->

    <div class="search-box">

      <div class="search-icon">
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-4-4" />
        </svg>
      </div>

      <input
          v-model="search"
          type="text"
          placeholder="Поиск по названию или тегу..."
      />

      <div
          v-if="search"
          class="search-clear"
          @click="search = ''"
      >
        ×
      </div>

      <div
          v-if="loading"
          class="search-loader"
      ></div>

    </div>

    <!-- =========================
         СОСТОЯНИЕ
    ========================== -->

    <div
        v-if="loading"
        class="state"
    >
      <div class="loader-ring"></div>

      <span>Загружаем кланы...</span>
    </div>

    <div
        v-else-if="!clans.length"
        class="state state-empty"
    >
      <div class="empty-icon">
        ♜
      </div>

      <strong>Кланов не найдено</strong>

      <span>
        Попробуй изменить поисковый запрос.
      </span>
    </div>

    <!-- =========================
         СПИСОК
    ========================== -->

    <div
        v-else
        class="clans-list"
    >

      <div class="list-top">
        <span>
          Найдено кланов:
          <strong>{{ clans.length }}</strong>
        </span>

        <span class="list-hint">
          Нажми на клан, чтобы открыть его профиль
        </span>
      </div>

      <RouterLink
          v-for="clan in clans"
          :key="clan.id"
          :to="`/clans/${clan.id}`"
          class="clan-card"
          :class="{ highlighted: clan.is_highlighted }"
      >

        <!-- Акцентная полоска -->
        <div
            class="card-accent"
            :style="{ background: clan.banner_color }"
        ></div>

        <!-- АВАТАР -->
        <div
            class="clan-avatar"
            :style="{
            background: clan.banner_color || '#7c3aed'
          }"
        >
          <img
              v-if="clan.avatar_url"
              :src="clan.avatar_url"
              :alt="clan.name"
              class="avatar-img"
          />

          <template v-else>
            {{ clan.tag?.charAt(0)?.toUpperCase() || 'C' }}
          </template>

          <span
              v-if="clan.is_highlighted"
              class="featured-mark"
              title="Рекомендуемый клан"
          >
            ★
          </span>
        </div>

        <!-- ИНФОРМАЦИЯ -->
        <div class="clan-info">

          <div class="clan-title">
            <span class="clan-tag">
              [{{ clan.tag }}]
            </span>

            <span class="clan-name">
              {{ clan.name }}
            </span>
          </div>

          <div class="clan-meta">

            <span class="meta-item">
              <span class="meta-icon">♟</span>
              {{ clan.members_count }} участников
            </span>

            <span class="meta-separator">·</span>

            <span class="meta-item">
              <span class="meta-icon">♛</span>
              Лидер:
              <span class="leader">
                {{ clan.leader?.username || '—' }}
              </span>
            </span>

          </div>

        </div>

        <!-- СИЛА -->
        <div class="clan-power">

          <span class="power-label">
            Сила
          </span>

          <strong class="power-value">
            {{ clan.power }}
          </strong>

        </div>

        <!-- СТРЕЛКА -->
        <div class="card-arrow">
          →
        </div>

      </RouterLink>

    </div>

  </div>
</template>

<style scoped>
/* =========================================================
   СТРАНИЦА
========================================================= */

.clans-page {
  position: relative;

  width: min(980px, calc(100% - 40px));

  margin: 42px auto 80px;
}

/* лёгкое свечение за контентом */

.clans-page::before {
  content: '';

  position: fixed;

  top: 130px;
  left: 50%;

  width: 600px;
  height: 400px;

  transform: translateX(-50%);

  background:
      radial-gradient(
          ellipse,
          rgba(124, 58, 237, 0.08),
          transparent 70%
      );

  pointer-events: none;

  z-index: -1;
}

/* =========================================================
   ШАПКА
========================================================= */

.page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 30px;

  margin-bottom: 26px;
}

.head-content {
  min-width: 0;
}

.eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;

  margin-bottom: 9px;

  color: #817b92;

  font-size: 10px;
  font-weight: 850;

  text-transform: uppercase;
  letter-spacing: 1.6px;
}

.eyebrow-dot {
  width: 6px;
  height: 6px;

  background: #8b5cf6;

  border-radius: 50%;

  box-shadow:
      0 0 9px rgba(139, 92, 246, 0.7);
}

h1 {
  margin: 0;

  color: #f8f7fb;

  font-size: clamp(32px, 4vw, 42px);
  line-height: 1;

  font-weight: 950;

  letter-spacing: -1.8px;
}

.head-content p {
  max-width: 520px;

  margin: 11px 0 0;

  color: #777284;

  font-size: 13px;
  line-height: 1.6;
}

/* =========================================================
   КНОПКА СОЗДАНИЯ
========================================================= */

.create-button {
  flex-shrink: 0;

  display: inline-flex;
  align-items: center;
  gap: 9px;

  min-height: 42px;

  padding: 0 16px;

  color: #fff;

  background:
      linear-gradient(
          135deg,
          #8b5cf6,
          #6d28d9
      );

  border: 1px solid rgba(167, 139, 250, 0.32);
  border-radius: 10px;

  font-size: 12px;
  font-weight: 800;

  text-decoration: none;

  box-shadow:
      0 9px 25px rgba(124, 58, 237, 0.18),
      inset 0 1px rgba(255, 255, 255, 0.15);

  transition:
      transform 0.2s ease,
      filter 0.2s ease,
      box-shadow 0.2s ease;
}

.create-button:hover {
  transform: translateY(-2px);

  filter: brightness(1.07);

  box-shadow:
      0 13px 32px rgba(124, 58, 237, 0.27),
      inset 0 1px rgba(255, 255, 255, 0.18);
}

.create-icon {
  width: 21px;
  height: 21px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #ddd6fe;

  background: rgba(255, 255, 255, 0.10);

  border-radius: 6px;

  font-size: 17px;
  line-height: 1;
}

/* =========================================================
   ПОИСК
========================================================= */

.search-box {
  position: relative;

  height: 50px;

  display: flex;
  align-items: center;

  margin-bottom: 22px;

  background:
      linear-gradient(
          135deg,
          rgba(24, 22, 32, 0.95),
          rgba(17, 16, 24, 0.95)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 12px;

  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.12);

  transition:
      border-color 0.2s ease,
      box-shadow 0.2s ease;
}

.search-box:focus-within {
  border-color: rgba(139, 92, 246, 0.4);

  box-shadow:
      0 10px 35px rgba(0, 0, 0, 0.18),
      0 0 0 3px rgba(124, 58, 237, 0.06);
}

.search-icon {
  width: 50px;
  height: 100%;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #625d70;
}

.search-icon svg {
  width: 18px;
  height: 18px;
}

.search-box input {
  min-width: 0;
  flex: 1;

  height: 100%;

  padding: 0 10px 0 0;

  color: #eeeaf5;

  background: transparent;

  border: 0;
  outline: 0;

  font-family: inherit;
  font-size: 13px;
}

.search-box input::placeholder {
  color: #5e596b;
}

.search-clear {
  width: 34px;
  height: 34px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-right: 6px;

  color: #777184;

  border-radius: 8px;

  font-size: 20px;

  cursor: pointer;

  transition:
      color 0.15s ease,
      background 0.15s ease;
}

.search-clear:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}

.search-loader {
  position: absolute;

  right: 14px;

  width: 15px;
  height: 15px;

  border: 2px solid rgba(139, 92, 246, 0.18);
  border-top-color: #8b5cf6;

  border-radius: 50%;

  animation: spin 0.7s linear infinite;
}

/* =========================================================
   СПИСОК
========================================================= */

.clans-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.list-top {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 0 3px 8px;

  color: #625e6d;

  font-size: 10px;
  font-weight: 650;
}

.list-top strong {
  color: #aaa4b7;
}

.list-hint {
  color: #514d5c;
}

/* =========================================================
   КАРТОЧКА КЛАНА
========================================================= */

.clan-card {
  position: relative;

  min-height: 82px;

  display: flex;
  align-items: center;

  gap: 15px;

  padding: 13px 16px 13px 18px;

  overflow: hidden;

  color: inherit;
  text-decoration: none;

  background:
      linear-gradient(
          135deg,
          rgba(24, 23, 32, 0.95),
          rgba(18, 17, 25, 0.95)
      );

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 13px;

  box-shadow:
      0 5px 20px rgba(0, 0, 0, 0.08);

  transition:
      transform 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      box-shadow 0.2s ease;
}

.clan-card:hover {
  transform: translateX(4px);

  background:
      linear-gradient(
          135deg,
          rgba(30, 28, 40, 0.98),
          rgba(19, 18, 27, 0.98)
      );

  border-color: rgba(139, 92, 246, 0.19);

  box-shadow:
      0 12px 30px rgba(0, 0, 0, 0.17),
      -3px 0 20px rgba(124, 58, 237, 0.05);
}

/* левая цветная линия */

.card-accent {
  position: absolute;

  left: 0;
  top: 14px;
  bottom: 14px;

  width: 2px;

  opacity: 0.8;

  border-radius: 0 4px 4px 0;

  box-shadow:
      0 0 10px currentColor;
}

/* =========================================================
   ОСОБЫЙ КЛАН
========================================================= */

.clan-card.highlighted {
  border-color: rgba(250, 204, 21, 0.17);

  background:
      linear-gradient(
          100deg,
          rgba(250, 204, 21, 0.055),
          rgba(23, 22, 29, 0.96) 42%
      );

  box-shadow:
      0 8px 30px rgba(0, 0, 0, 0.10),
      inset 0 0 30px rgba(250, 204, 21, 0.015);
}

.clan-card.highlighted:hover {
  border-color: rgba(250, 204, 21, 0.32);

  box-shadow:
      0 14px 35px rgba(0, 0, 0, 0.16),
      0 0 25px rgba(250, 204, 21, 0.05);
}

/* =========================================================
   АВАТАР
========================================================= */

.clan-avatar {
  position: relative;

  width: 54px;
  height: 54px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: visible;

  color: #fff;

  border-radius: 13px;

  font-size: 21px;
  font-weight: 950;

  box-shadow:
      inset 0 0 0 1px rgba(255, 255, 255, 0.10),
      0 7px 20px rgba(0, 0, 0, 0.18);

  isolation: isolate;
}

.clan-avatar::before {
  content: '';

  position: absolute;
  inset: 0;

  z-index: -1;

  border-radius: inherit;

  opacity: 0.5;

  filter: blur(9px);
}

.avatar-img {
  position: absolute;

  inset: 0;

  width: 100%;
  height: 100%;

  object-fit: cover;
  object-position: center;

  display: block;

  border-radius: inherit;
}

/* звёздочка */

.featured-mark {
  position: absolute;

  top: -7px;
  right: -7px;

  width: 20px;
  height: 20px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fde68a;

  background:
      linear-gradient(
          135deg,
          #4b3b08,
          #211b08
      );

  border: 1px solid rgba(250, 204, 21, 0.38);
  border-radius: 7px;

  font-size: 10px;

  box-shadow:
      0 4px 12px rgba(0, 0, 0, 0.3),
      0 0 12px rgba(250, 204, 21, 0.10);
}

/* =========================================================
   ИНФОРМАЦИЯ
========================================================= */

.clan-info {
  min-width: 0;
  flex: 1;
}

.clan-title {
  display: flex;
  align-items: center;

  min-width: 0;

  margin-bottom: 6px;

  gap: 6px;

  line-height: 1.2;
}

.clan-tag {
  flex-shrink: 0;

  color: #a78bfa;

  font-size: 12px;
  font-weight: 850;
}

.clan-name {
  min-width: 0;

  overflow: hidden;

  color: #eeecf3;

  font-size: 15px;
  font-weight: 800;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-meta {
  display: flex;
  align-items: center;
  gap: 6px;

  min-width: 0;

  color: #676273;

  font-size: 11px;
  font-weight: 550;
}

.meta-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  min-width: 0;
}

.meta-icon {
  color: #555060;

  font-size: 12px;
}

.meta-separator {
  color: #45414e;
}

.leader {
  max-width: 120px;

  overflow: hidden;

  color: #858091;

  text-overflow: ellipsis;
  white-space: nowrap;
}

/* =========================================================
   СИЛА
========================================================= */

.clan-power {
  min-width: 90px;

  padding: 5px 12px;

  text-align: right;

  border-left: 1px solid rgba(255, 255, 255, 0.055);
}

.power-label {
  display: block;

  margin-bottom: 2px;

  color: #555160;

  font-size: 8px;
  font-weight: 850;

  text-transform: uppercase;
  letter-spacing: 1px;
}

.power-value {
  display: block;

  color: #b9a4ff;

  font-size: 20px;
  line-height: 1;

  font-weight: 950;

  letter-spacing: -0.6px;

  text-shadow:
      0 0 20px rgba(139, 92, 246, 0.18);
}

.highlighted .power-value {
  color: #f5d76e;

  text-shadow:
      0 0 20px rgba(250, 204, 21, 0.15);
}

/* =========================================================
   СТРЕЛКА
========================================================= */

.card-arrow {
  width: 26px;

  color: #454150;

  font-size: 19px;
  text-align: center;

  transition:
      color 0.2s ease,
      transform 0.2s ease;
}

.clan-card:hover .card-arrow {
  color: #a78bfa;

  transform: translateX(3px);
}

/* =========================================================
   СОСТОЯНИЯ
========================================================= */

.state {
  min-height: 280px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  gap: 12px;

  color: #686374;

  background:
      linear-gradient(
          145deg,
          rgba(24, 23, 32, 0.8),
          rgba(16, 15, 22, 0.8)
      );

  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 14px;
}

.state-empty {
  gap: 7px;
}

.state-empty strong {
  color: #aaa5b4;

  font-size: 14px;
}

.state-empty span {
  color: #5e5969;

  font-size: 11px;
}

.empty-icon {
  width: 52px;
  height: 52px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 4px;

  color: #655d77;

  background: rgba(139, 92, 246, 0.06);

  border: 1px solid rgba(139, 92, 246, 0.09);
  border-radius: 14px;

  font-size: 23px;
}

/* =========================================================
   LOADER
========================================================= */

.loader-ring {
  width: 25px;
  height: 25px;

  border: 2px solid rgba(139, 92, 246, 0.15);
  border-top-color: #8b5cf6;

  border-radius: 50%;

  animation: spin 0.75s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================================
   АДАПТИВ
========================================================= */

@media (max-width: 700px) {
  .clans-page {
    width: min(100% - 24px, 980px);

    margin-top: 28px;
  }

  .page-head {
    align-items: flex-start;
    flex-direction: column;

    gap: 18px;
  }

  .create-button {
    width: 100%;

    justify-content: center;
  }

  .head-content p {
    font-size: 12px;
  }

  .list-hint {
    display: none;
  }

  .clan-card {
    padding: 12px 13px 12px 15px;

    gap: 12px;
  }

  .clan-avatar {
    width: 48px;
    height: 48px;

    border-radius: 11px;

    font-size: 18px;
  }

  .clan-name {
    font-size: 14px;
  }

  .clan-meta {
    font-size: 10px;
  }

  .clan-power {
    min-width: 65px;

    padding-left: 8px;
    padding-right: 3px;
  }

  .power-value {
    font-size: 17px;
  }

  .card-arrow {
    display: none;
  }
}

@media (max-width: 480px) {
  .clans-page {
    width: calc(100% - 20px);

    margin-top: 22px;
  }

  h1 {
    font-size: 30px;
  }

  .search-box {
    height: 46px;
  }

  .clan-card {
    min-height: 72px;
  }

  .clan-meta {
    display: block;
  }

  .meta-separator {
    display: none;
  }

  .meta-item:nth-child(3) {
    display: none;
  }

  .clan-power {
    min-width: 55px;
  }

  .power-label {
    font-size: 7px;
  }

  .power-value {
    font-size: 15px;
  }
}
</style>