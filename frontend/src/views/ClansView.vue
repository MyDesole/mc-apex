<template>
  <section class="clans-page">
    <!-- ============================================================
         HEADER
         ============================================================ -->

    <div class="page-head">
      <div>
        <div class="eyebrow">COMMUNITY</div>

        <h1>Кланы</h1>

        <p>
          Найди свою команду и присоединяйся к игре.
        </p>
      </div>

      <button
          class="refresh-btn"
          :disabled="loading"
          @click="loadClans"
      >
        <span :class="{ spinning: loading }">↻</span>
        Обновить
      </button>
    </div>

    <!-- ============================================================
         ERROR
         ============================================================ -->

    <div
        v-if="error"
        class="state error-state"
    >
      <div class="state-icon">!</div>

      <div>
        <strong>Не удалось загрузить кланы</strong>
        <span>{{ error }}</span>
      </div>
    </div>

    <!-- ============================================================
         LOADING
         ============================================================ -->

    <div
        v-else-if="loading"
        class="clans-list"
    >
      <div
          v-for="i in 6"
          :key="i"
          class="clan-row skeleton-row"
      >
        <div class="skeleton avatar"></div>

        <div class="skeleton-content">
          <div class="skeleton line large"></div>
          <div class="skeleton line small"></div>
        </div>

        <div class="skeleton-stat"></div>
        <div class="skeleton-stat"></div>
      </div>
    </div>

    <!-- ============================================================
         CLANS
         ============================================================ -->

    <div
        v-else-if="clans.length"
        class="clans-list"
    >
      <article
          v-for="clan in clans"
          :key="clan.id"
          class="clan-row"
          :class="rowClasses(clan)"
          :style="rowStyle(clan)"
          @click="openClan(clan)"
      >
        <!-- ========================================================
             EFFECT
             ======================================================== -->

        <div
            v-if="
            isHighlighted(clan) &&
            effectiveClanEffect(clan) !== 'none'
          "
            class="clan-effect"
            aria-hidden="true"
        >
          <div class="clan-effect-bg"></div>
          <div class="clan-effect-glow"></div>
          <div class="clan-effect-shimmer"></div>
        </div>

        <!-- ========================================================
             AVATAR
             ======================================================== -->

        <div class="clan-avatar-wrap">
          <div class="clan-avatar">
            <img
                v-if="clan.avatar_url"
                :src="clan.avatar_url"
                :alt="clan.name"
            />

            <span v-else>
              {{ clan.name?.charAt(0)?.toUpperCase() || '?' }}
            </span>
          </div>

          <div
              v-if="isHighlighted(clan)"
              class="avatar-aura"
          ></div>
        </div>

        <!-- ========================================================
             INFO
             ======================================================== -->

        <div class="clan-main">
          <div class="clan-title">
            <h2>
              {{ clan.name }}
            </h2>

            <span
                v-if="clan.tag"
                class="clan-tag"
            >
              [{{ clan.tag }}]
            </span>

            <span
                v-if="isMine(clan)"
                class="my-badge"
            >
              Мой
            </span>
          </div>

          <p class="clan-description">
            {{ clan.description || 'Описание отсутствует' }}
          </p>

          <div class="clan-meta">
            <span>
              <i></i>
              {{ clan.is_open ? 'Открытый набор' : 'Закрытый набор' }}
            </span>

            <span>
              {{ clan.members_count || 0 }}/{{ clan.max_members || 0 }}
              участников
            </span>

            <span v-if="clan.leader">
              Лидер: {{ clan.leader.username }}
            </span>
          </div>
        </div>

        <!-- ========================================================
             POWER
             ======================================================== -->

        <div class="clan-stat">
          <strong>
            {{ clan.power || 0 }}
          </strong>

          <span>
            Power
          </span>
        </div>

        <!-- ========================================================
             W / L
             ======================================================== -->

        <div class="clan-stat record">
          <strong>
            <b>
              {{ clan.wins || 0 }}
            </b>

            <em>/</em>

            <small>
              {{ clan.losses || 0 }}
            </small>
          </strong>

          <span>
            W / L
          </span>
        </div>

        <!-- ========================================================
             EFFECT NAME
             ======================================================== -->

        <div
            v-if="isHighlighted(clan)"
            class="effect-label"
        >
          {{ effectLabel(effectiveClanEffect(clan)) }}
        </div>
      </article>
    </div>

    <!-- ============================================================
         EMPTY
         ============================================================ -->

    <div
        v-else
        class="state empty-state"
    >
      <div class="state-icon">⌁</div>

      <strong>
        Кланов пока нет
      </strong>

      <span>
        Попробуй обновить страницу позже.
      </span>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clansApi } from '@/services/clans.js'

const router = useRouter()

const clans = ref([])
const loading = ref(false)
const error = ref('')

/*
 * ============================================================
 * ЭФФЕКТЫ ПОДСВЕТКИ
 * ============================================================
 *
 * Ключи совпадают с effect_value предметов магазина
 * и с App\Support\ClanHighlight на сервере.
 */

const EFFECT_LABELS = {
  // Базовая подсветка без свечения
  frame: 'Рамка',
  glow: 'Glow',
  pulse: 'Pulse',
  gradient: 'Gradient',
  fire: 'Fire',
  ice: 'Ice',
  aurora: 'Aurora',
  legendary: 'Legendary',
}

/** Цвета подсветки — те же ключи, что продаются в магазине. */
const HIGHLIGHT_COLORS = {
  gold: '#facc15',
  crimson: '#ef4444',
  cyan: '#06b6d4',
  violet: '#8b5cf6',
  emerald: '#22c55e',
  rose: '#ec4899',
}

/*
 * ============================================================
 * LOAD
 * ============================================================
 */

const loadClans = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await clansApi.list()

    /*
     * НЕ МЕНЯТЬ.
     *
     * В твоём API response.data уже является массивом кланов.
     */
    clans.value = response?.data || []
  } catch (err) {
    console.error('Failed to load clans:', err)

    error.value =
        err?.response?.data?.message ||
        err?.message ||
        'Произошла ошибка при загрузке.'
  } finally {
    loading.value = false
  }
}

onMounted(loadClans)

/*
 * ============================================================
 * NAVIGATION
 * ============================================================
 */

const openClan = (clan) => {
  if (!clan?.id) return

  // Свой клан ведём во вкладку «Мой клан»: там форум, ресурсы,
  // участники и выход, которых нет на публичной странице
  if (isMine(clan)) {
    router.push('/my-clan')

    return
  }

  router.push(`/clans/${clan.id}`)
}

/*
 * ============================================================
 * CLAN HELPERS
 * ============================================================
 */

/**
 * Подсветка активна.
 *
 * Сервер отдаёт is_highlighted уже с учётом срока, но подстрахуемся:
 * если пришла дата окончания и она в прошлом — подсветки нет.
 */
const isHighlighted = (clan) => {
  if (!clan?.is_highlighted) {
    return false
  }

  if (clan.highlight_until && new Date(clan.highlight_until) <= new Date()) {
    return false
  }

  return true
}

/**
 * Свой ли это клан.
 *
 * Сервер помечает свой клан полем my_clan_id, поэтому сравнивать
 * нужно его с id клана, а не id клана с самим собой.
 */
const isMine = (clan) => {
  if (!clan || clan.my_clan_id === null || clan.my_clan_id === undefined) {
    return false
  }

  return Number(clan.my_clan_id) === Number(clan.id)
}

const effectiveClanEffect = (clan) => {
  if (!isHighlighted(clan)) {
    return 'none'
  }

  // Эффект куплен в магазине; без покупки работает базовая рамка
  const effect = clan?.highlight_effect

  return EFFECT_LABELS[effect] ? effect : 'frame'
}

const effectiveClanColor = (clan) => {
  // Цвет тоже покупается; иначе берём цвет клана
  return (
      HIGHLIGHT_COLORS[clan?.highlight_color] ||
      clan?.banner_color ||
      '#8b5cf6'
  )
}

const effectLabel = (effect) => EFFECT_LABELS[effect] || effect

const rowClasses = (clan) => {
  const effect = effectiveClanEffect(clan)

  return {
    highlighted: isHighlighted(clan),

    [`effect-${effect}`]:
    isHighlighted(clan) &&
    effect !== 'none',
  }
}

const rowStyle = (clan) => {
  if (!isHighlighted(clan)) {
    return {}
  }

  return {
    '--clan-color': effectiveClanColor(clan),
  }
}
</script>

<style scoped>

/* ============================================================
   PAGE
   ============================================================ */

.clans-page {
  position: relative;

  /*
   * Главное ограничение ширины.
   *
   * Страница НЕ должна растягиваться на весь 1920px.
   */
  width: min(100%, 1200px);
  max-width: 1200px;

  /*
   * Центрируем содержимое.
   */
  margin: 0 auto;

  min-width: 0;

  color: var(--text);
}

/* ============================================================
   HEADER
   ============================================================ */

.page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 24px;

  margin-bottom: 20px;
}

.eyebrow {
  margin-bottom: 6px;

  color: var(--accent-light);

  font-size: 10px;
  font-weight: 800;

  letter-spacing: .18em;
  text-transform: uppercase;
}

.page-head h1 {
  margin: 0;

  font-size: 30px;
  line-height: 1.1;
  font-weight: 800;

  letter-spacing: -.025em;
}

.page-head p {
  margin: 7px 0 0;

  color: var(--text-dim);

  font-size: 14px;
}

/* ============================================================
   REFRESH
   ============================================================ */

.refresh-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 8px;

  min-height: 38px;
  padding: 0 14px;

  border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px;

  background: rgba(255,255,255,.035);
  color: var(--text);

  font: inherit;
  font-size: 13px;
  font-weight: 700;

  cursor: pointer;

  transition:
      background .18s ease,
      border-color .18s ease,
      transform .18s ease,
      opacity .18s ease;
}

.refresh-btn:hover:not(:disabled) {
  background: rgba(255,255,255,.06);

  border-color:
      rgba(255,255,255,.13);

  transform: translateY(-1px);
}

.refresh-btn:disabled {
  cursor: default;
  opacity: .55;
}

.refresh-btn > span {
  display: inline-flex;

  font-size: 19px;
  line-height: 1;
}

.spinning {
  animation: refresh-spin .8s linear infinite;
}

@keyframes refresh-spin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================
   DEBUG
   ============================================================ */

/* ============================================================
   LIST
   ============================================================ */

.clans-list {
  display: flex;
  flex-direction: column;

  width: 100%;
  min-width: 0;

  gap: 8px;
}

/* ============================================================
   CLAN ROW
   ============================================================ */

.clan-row {
  position: relative;

  isolation: isolate;

  display: grid;

  grid-template-columns:
    56px
    minmax(0, 1fr)
    90px
    82px;

  align-items: center;

  gap: 16px;

  width: 100%;
  min-width: 0;

  min-height: 88px;

  padding: 14px 16px;

  box-sizing: border-box;

  overflow: hidden;

  border: 1px solid rgba(255,255,255,.065);
  border-radius: 14px;

  background:
      linear-gradient(
          135deg,
          rgba(255,255,255,.045),
          rgba(255,255,255,.018)
      );

  box-shadow:
      0 8px 30px rgba(0,0,0,.12);

  cursor: pointer;

  transition:
      transform .18s ease,
      border-color .18s ease,
      background .18s ease,
      box-shadow .18s ease;
}

.clan-row:hover {
  transform: translateY(-1px);

  border-color:
      rgba(255,255,255,.105);

  background:
      linear-gradient(
          135deg,
          rgba(255,255,255,.055),
          rgba(255,255,255,.025)
      );

  box-shadow:
      0 12px 34px rgba(0,0,0,.18);
}

.clan-row:active {
  transform: translateY(0);
}

/* ============================================================
   EFFECT LAYER
   ============================================================ */

.clan-effect {
  position: absolute;

  inset: 0;

  width: 100%;
  height: 100%;

  overflow: hidden;

  pointer-events: none;

  border-radius: inherit;

  /*
   * ВАЖНО:
   * Эффект находится внутри самой строки.
   */
  z-index: 0;
}

.clan-effect-bg,
.clan-effect-glow,
.clan-effect-shimmer {
  position: absolute;

  inset: 0;

  width: 100%;
  height: 100%;

  pointer-events: none;
}

.clan-effect-bg {
  opacity: 0;
}

.clan-effect-glow {
  opacity: 0;
}

.clan-effect-shimmer {
  opacity: 0;
}

/* ============================================================
   CONTENT ABOVE EFFECT
   ============================================================ */

.clan-avatar-wrap,
.clan-main,
.clan-stat,
.effect-label {
  position: relative;
  z-index: 1;
}

/* ============================================================
   AVATAR
   ============================================================ */

.clan-avatar-wrap {
  position: relative;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 56px;
  height: 56px;
}

.clan-avatar {
  position: relative;

  z-index: 2;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 52px;
  height: 52px;

  overflow: hidden;

  border: 1px solid rgba(255,255,255,.09);
  border-radius: 13px;

  background:
      linear-gradient(
          135deg,
          rgba(124,58,237,.22),
          rgba(6,182,212,.10)
      );

  color: var(--text);

  font-size: 18px;
  font-weight: 800;

  box-shadow:
      0 5px 18px rgba(0,0,0,.22);
}

.clan-avatar img {
  display: block;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.avatar-aura {
  position: absolute;

  inset: -5px;

  z-index: 1;

  border-radius: 16px;

  background:
      radial-gradient(
          circle,
          color-mix(
              in srgb,
              var(--clan-color) 20%,
              transparent
          ),
          transparent 68%
      );

  filter: blur(5px);

  opacity: .8;

  pointer-events: none;
}

/* ============================================================
   MAIN
   ============================================================ */

.clan-main {
  min-width: 0;
}

.clan-title {
  display: flex;
  align-items: center;

  flex-wrap: wrap;

  gap: 7px;

  min-width: 0;
}

.clan-title h2 {
  min-width: 0;
  max-width: 100%;

  margin: 0;

  overflow: hidden;

  color: var(--text);

  font-size: 15px;
  line-height: 1.25;
  font-weight: 800;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-tag {
  flex: 0 0 auto;

  color: var(--text-muted);

  font-size: 11px;
  font-weight: 700;
}

.my-badge {
  flex: 0 0 auto;

  padding: 3px 7px;

  border: 1px solid rgba(124,58,237,.28);
  border-radius: 6px;

  background: rgba(124,58,237,.12);
  color: var(--accent-light);

  font-size: 9px;
  font-weight: 900;

  letter-spacing: .04em;
  text-transform: uppercase;
}

.clan-description {
  margin: 5px 0 7px;

  overflow: hidden;

  color: var(--text-dim);

  font-size: 12px;
  line-height: 1.45;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-meta {
  display: flex;
  align-items: center;

  flex-wrap: wrap;

  gap: 10px;

  color: var(--text-muted);

  font-size: 10px;
}

.clan-meta span {
  display: inline-flex;
  align-items: center;

  gap: 5px;
}

.clan-meta i {
  display: block;

  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow:
      0 0 7px rgba(34,197,94,.45);
}

/* ============================================================
   STATS
   ============================================================ */

.clan-stat {
  display: flex;
  flex-direction: column;

  align-items: flex-end;
  justify-content: center;

  min-width: 0;
}

.clan-stat strong {
  display: block;

  color: var(--text);

  font-size: 16px;
  line-height: 1.1;
  font-weight: 800;
}

.clan-stat > span {
  margin-top: 4px;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 700;

  letter-spacing: .08em;
  text-transform: uppercase;
}

/* ============================================================
   W / L
   ============================================================ */

.clan-stat.record strong {
  display: flex;
  align-items: center;

  gap: 4px;
}

.clan-stat.record b {
  color: #4ade80;
  font-size: 14px;
}

.clan-stat.record em {
  color: var(--text-muted);

  font-size: 11px;
  font-style: normal;
}

.clan-stat.record small {
  color: #f87171;

  font-size: 14px;
  font-weight: 800;
}

/* ============================================================
   EFFECT LABEL
   ============================================================ */

.effect-label {
  position: absolute;

  top: 8px;
  right: 10px;

  padding: 3px 6px;

  border-radius: 5px;

  background: rgba(0,0,0,.22);

  color: color-mix(
      in srgb,
      var(--clan-color) 75%,
      var(--text)
  );

  font-size: 7px;
  font-weight: 900;

  letter-spacing: .1em;
  text-transform: uppercase;

  opacity: .65;

  pointer-events: none;
}

/* ============================================================
   STATES
   ============================================================ */

.state {
  display: flex;
  flex-direction: column;

  align-items: center;
  justify-content: center;

  min-height: 220px;

  padding: 32px;

  border: 1px solid rgba(255,255,255,.06);
  border-radius: 14px;

  background: rgba(255,255,255,.018);

  text-align: center;
}

.state-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 42px;
  height: 42px;

  margin-bottom: 12px;

  border-radius: 12px;

  background: rgba(124,58,237,.10);

  color: var(--accent-light);

  font-size: 20px;
  font-weight: 800;
}

.state strong {
  color: var(--text);

  font-size: 14px;
  font-weight: 800;
}

.state span {
  margin-top: 5px;

  color: var(--text-muted);

  font-size: 12px;
}

.error-state {
  flex-direction: row;
  justify-content: flex-start;

  min-height: 80px;

  text-align: left;
}

.error-state .state-icon {
  flex: 0 0 auto;

  margin: 0;

  background: rgba(239,68,68,.10);
  color: #f87171;
}

.error-state > div:last-child {
  display: flex;
  flex-direction: column;
}

/* ============================================================
   SKELETON
   ============================================================ */

.skeleton-row {
  cursor: default;
  pointer-events: none;
}

.skeleton {
  background:
      linear-gradient(
          90deg,
          rgba(255,255,255,.045),
          rgba(255,255,255,.085),
          rgba(255,255,255,.045)
      );

  background-size: 200% 100%;

  animation:
      skeleton-loading 1.5s ease-in-out infinite;
}

.skeleton.avatar {
  width: 52px;
  height: 52px;

  border-radius: 13px;
}

.skeleton-content {
  display: flex;
  flex-direction: column;

  gap: 9px;

  min-width: 0;
}

.skeleton.line {
  height: 10px;

  border-radius: 5px;
}

.skeleton.line.large {
  width: min(240px, 70%);
}

.skeleton.line.small {
  width: min(150px, 45%);
}

.skeleton-stat {
  width: 42px;
  height: 25px;

  justify-self: end;

  border-radius: 6px;
}

@keyframes skeleton-loading {
  0% {
    background-position: 100% 0;
  }

  100% {
    background-position: -100% 0;
  }
}

/* ============================================================
   EFFECTS
   ============================================================ */

/*
 * Базовая подсветка: клан просто подсвечен своим цветом,
 * без свечения и анимаций. Свечение — отдельный покупаемый эффект.
 */
.effect-frame {
  border-color:
      color-mix(
          in srgb,
          var(--clan-color) 26%,
          rgba(255,255,255,.06)
      );

  box-shadow:
      inset 0 0 24px
      color-mix(
          in srgb,
          var(--clan-color) 6%,
          transparent
      );
}

.effect-frame .clan-effect-bg {
  opacity: 1;

  background:
      linear-gradient(
          100deg,
          color-mix(
              in srgb,
              var(--clan-color) 9%,
              transparent
          ),
          transparent 45%
      );
}

.effect-glow {
  border-color:
      color-mix(
          in srgb,
          var(--clan-color) 30%,
          rgba(255,255,255,.06)
      );

  box-shadow:
      inset 0 0 35px
      color-mix(
          in srgb,
          var(--clan-color) 8%,
          transparent
      ),
      0 0 18px
      color-mix(
          in srgb,
          var(--clan-color) 7%,
          transparent
      );
}

.effect-glow .clan-effect-bg {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 55% 100% at 0% 50%,
          color-mix(
              in srgb,
              var(--clan-color) 18%,
              transparent
          ),
          transparent 70%
      );
}

.effect-glow .clan-effect-glow {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 70% 90% at 0% 50%,
          color-mix(
              in srgb,
              var(--clan-color) 10%,
              transparent
          ),
          transparent 68%
      );

  filter: blur(18px);
}

/* ============================================================
   PULSE
   ============================================================ */

.effect-pulse {
  border-color:
      color-mix(
          in srgb,
          var(--clan-color) 35%,
          rgba(255,255,255,.06)
      );

  animation:
      clan-pulse-border 2.8s ease-in-out infinite;
}

.effect-pulse .clan-effect-bg {
  opacity: .8;

  background:
      radial-gradient(
          ellipse 60% 100% at 5% 50%,
          color-mix(
              in srgb,
              var(--clan-color) 17%,
              transparent
          ),
          transparent 70%
      );
}

.effect-pulse .clan-effect-glow {
  opacity: .7;

  background:
      radial-gradient(
          ellipse 65% 100% at 0% 50%,
          color-mix(
              in srgb,
              var(--clan-color) 14%,
              transparent
          ),
          transparent 70%
      );

  filter: blur(18px);

  animation:
      clan-pulse-glow 2.8s ease-in-out infinite;
}

/* ============================================================
   GRADIENT
   ============================================================ */

.effect-gradient {
  border-color:
      color-mix(
          in srgb,
          var(--clan-color) 30%,
          rgba(255,255,255,.06)
      );
}

.effect-gradient .clan-effect-bg {
  opacity: 1;

  background:
      linear-gradient(
          120deg,
          color-mix(
              in srgb,
              var(--clan-color) 8%,
              transparent
          ),
          transparent 38%,
          color-mix(
              in srgb,
              var(--clan-color) 13%,
              transparent
          ),
          transparent 72%,
          color-mix(
              in srgb,
              var(--clan-color) 7%,
              transparent
          )
      );

  background-size: 200% 100%;

  animation:
      clan-gradient 7s ease-in-out infinite;
}

.effect-gradient .clan-effect-glow {
  opacity: .45;

  background:
      radial-gradient(
          ellipse 50% 100% at 10% 50%,
          color-mix(
              in srgb,
              var(--clan-color) 13%,
              transparent
          ),
          transparent 72%
      );
}

/* ============================================================
   FIRE
   ============================================================ */

.effect-fire {
  border-color: rgba(249,115,22,.28);

  box-shadow:
      inset 0 0 35px rgba(239,68,68,.055),
      0 0 18px rgba(249,115,22,.05);
}

.effect-fire .clan-effect-bg {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 55% 90% at 8% 100%,
          rgba(239,68,68,.18),
          transparent 70%
      ),
      radial-gradient(
          ellipse 45% 80% at 45% 100%,
          rgba(249,115,22,.10),
          transparent 72%
      );
}

.effect-fire .clan-effect-glow {
  opacity: .7;

  background:
      radial-gradient(
          ellipse 45% 80% at 15% 100%,
          rgba(239,68,68,.16),
          transparent 72%
      );

  filter: blur(16px);

  animation:
      clan-fire 2.5s ease-in-out infinite;
}

/* ============================================================
   ICE
   ============================================================ */

.effect-ice {
  border-color: rgba(6,182,212,.28);

  box-shadow:
      inset 0 0 35px rgba(6,182,212,.05),
      0 0 18px rgba(6,182,212,.04);
}

.effect-ice .clan-effect-bg {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 55% 90% at 5% 20%,
          rgba(6,182,212,.17),
          transparent 70%
      ),
      radial-gradient(
          ellipse 50% 90% at 90% 80%,
          rgba(165,243,252,.08),
          transparent 72%
      );
}

.effect-ice .clan-effect-glow {
  opacity: .6;

  background:
      radial-gradient(
          ellipse 45% 90% at 10% 35%,
          rgba(6,182,212,.14),
          transparent 70%
      );

  filter: blur(16px);

  animation:
      clan-ice 4s ease-in-out infinite;
}

/* ============================================================
   AURORA
   ============================================================ */

.effect-aurora {
  border-color: rgba(139,92,246,.30);

  box-shadow:
      inset 0 0 40px rgba(124,58,237,.06),
      0 0 20px rgba(124,58,237,.05);
}

.effect-aurora .clan-effect-bg {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 45% 90% at 5% 25%,
          rgba(124,58,237,.20),
          transparent 72%
      ),
      radial-gradient(
          ellipse 50% 80% at 50% 100%,
          rgba(6,182,212,.14),
          transparent 72%
      ),
      radial-gradient(
          ellipse 45% 80% at 95% 20%,
          rgba(236,72,153,.10),
          transparent 72%
      );

  animation:
      clan-aurora 7s ease-in-out infinite alternate;
}

.effect-aurora .clan-effect-glow {
  opacity: .65;

  background:
      linear-gradient(
          110deg,
          rgba(124,58,237,.09),
          rgba(6,182,212,.05),
          rgba(236,72,153,.07)
      );

  filter: blur(18px);

  animation:
      clan-aurora-glow 6s ease-in-out infinite alternate;
}

/* ============================================================
   LEGENDARY
   ============================================================ */

.effect-legendary {
  border-color: rgba(250,204,21,.34);

  box-shadow:
      inset 0 0 40px rgba(250,204,21,.055),
      0 0 20px rgba(250,204,21,.06);
}

.effect-legendary .clan-effect-bg {
  opacity: 1;

  background:
      radial-gradient(
          ellipse 45% 90% at 5% 50%,
          rgba(250,204,21,.18),
          transparent 70%
      ),
      radial-gradient(
          ellipse 40% 80% at 50% 100%,
          rgba(249,115,22,.11),
          transparent 72%
      ),
      radial-gradient(
          ellipse 35% 70% at 95% 20%,
          rgba(250,204,21,.08),
          transparent 72%
      );
}

.effect-legendary .clan-effect-glow {
  opacity: .65;

  background:
      radial-gradient(
          ellipse 45% 90% at 10% 50%,
          rgba(250,204,21,.14),
          transparent 70%
      );

  filter: blur(16px);

  animation:
      clan-legendary 3.5s ease-in-out infinite;
}

/* ============================================================
   ANIMATIONS
   ============================================================ */

@keyframes clan-pulse-border {
  0%,
  100% {
    border-color:
        color-mix(
            in srgb,
            var(--clan-color) 22%,
            rgba(255,255,255,.06)
        );
  }

  50% {
    border-color:
        color-mix(
            in srgb,
            var(--clan-color) 42%,
            rgba(255,255,255,.06)
        );
  }
}

@keyframes clan-pulse-glow {
  0%,
  100% {
    opacity: .35;
    transform: scale(.98);
  }

  50% {
    opacity: .75;
    transform: scale(1.02);
  }
}

@keyframes clan-gradient {
  0% {
    background-position: 100% 0;
  }

  50% {
    background-position: 0% 0;
  }

  100% {
    background-position: -100% 0;
  }
}

@keyframes clan-fire {
  0%,
  100% {
    opacity: .35;
    transform: translateY(2px);
  }

  50% {
    opacity: .75;
    transform: translateY(-2px);
  }
}

@keyframes clan-ice {
  0%,
  100% {
    opacity: .35;
    transform: translateX(-2px);
  }

  50% {
    opacity: .7;
    transform: translateX(3px);
  }
}

@keyframes clan-aurora {
  0% {
    transform: translateX(-2%) scale(1);
  }

  50% {
    transform: translateX(2%) scale(1.03);
  }

  100% {
    transform: translateX(-1%) scale(1);
  }
}

@keyframes clan-aurora-glow {
  0% {
    opacity: .3;
    transform: translateX(-2%);
  }

  100% {
    opacity: .7;
    transform: translateX(3%);
  }
}

@keyframes clan-legendary {
  0%,
  100% {
    opacity: .3;
    transform: scale(.99);
  }

  50% {
    opacity: .75;
    transform: scale(1.01);
  }
}

/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width: 760px) {
  .page-head {
    align-items: flex-start;
  }

  .clan-row {
    grid-template-columns:
      48px
      minmax(0, 1fr)
      auto;

    gap: 12px;

    min-height: 76px;

    padding: 12px;
  }

  .clan-avatar-wrap {
    width: 48px;
    height: 48px;
  }

  .clan-avatar {
    width: 46px;
    height: 46px;
  }

  .clan-stat.record {
    display: none;
  }

  .clan-description {
    max-width: 100%;
  }
}

@media (max-width: 560px) {
  .page-head {
    flex-direction: column;

    gap: 12px;
  }

  .page-head h1 {
    font-size: 25px;
  }

  .refresh-btn {
    width: 100%;
  }

  .clan-row {
    grid-template-columns:
      44px
      minmax(0, 1fr);
  }

  .clan-avatar-wrap {
    width: 44px;
    height: 44px;
  }

  .clan-avatar {
    width: 42px;
    height: 42px;
  }

  .clan-stat {
    display: none;
  }

  .clan-meta {
    gap: 6px 10px;
  }

  .effect-label {
    display: none;
  }
}

@media (max-width: 420px) {
  .page-head p {
    font-size: 12px;
  }

  .clan-row {
    gap: 10px;

    padding: 10px;
  }

  .clan-title h2 {
    font-size: 14px;
  }

  .clan-description {
    font-size: 11px;
  }

  .clan-meta {
    font-size: 9px;
  }

  .clan-meta span:last-child {
    display: none;
  }
}

</style>

