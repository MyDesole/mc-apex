<script setup>
/**
 * «Сейчас на APEX» — живая лента событий сайта.
 *
 * Тиры, ачивки, клановые войны, победы в турнирах, новые кланы и
 * привязки Minecraft. Новые события приезжают по сокету и на пару
 * секунд подсвечиваются.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { useActivityFeed } from '@/composables/activity/useActivityFeed.js'
import PixelIcon from '@/components/achievements/PixelIcon.vue'
import { tierColors } from '@/data/players/profileCustomization.js'
import { userLink, clanLink } from '@/utils/links.js'

const props = defineProps({
  limit: { type: Number, default: 8 },
})

const { items, loading, loadingMore, hasMore, live, fresh, loadMore } = useActivityFeed({
  limit: props.limit,
})

const MODES = {
  pvp: 'PvP',
  bedwars: 'BedWars',
}

/* Относительное время пересчитывается раз в полминуты */
const now = ref(Date.now())
let clock = null

onMounted(() => {
  clock = setInterval(() => {
    now.value = Date.now()
  }, 30000)
})

onUnmounted(() => clearInterval(clock))

function ago(iso) {
  if (!iso) return ''

  const seconds = Math.max(0, Math.round((now.value - new Date(iso).getTime()) / 1000))

  if (seconds < 45) return 'только что'

  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} мин назад`

  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours} ч назад`

  const days = Math.round(hours / 24)
  if (days < 7) return `${days} дн назад`

  return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function tierColor(tier) {
  return tierColors[tier] || '#a78bfa'
}

function clanSide(war, side) {
  const clan = war.payload?.[side]
  const score = war.payload?.[`${side}_score`]

  return {
    clan,
    score,
    winner: clan && war.payload?.winner_clan_id === clan.id,
  }
}
</script>

<template>
  <section class="feed">
    <header class="feed__head">
      <h2 class="feed__title">Сейчас на APEX</h2>

      <span
          class="feed__status"
          :class="{ 'feed__status--live': live }"
          :title="live ? 'Новые события появятся здесь сами' : 'Нет соединения: обновите страницу, чтобы увидеть новое'"
      >
        <span class="feed__status-dot" />
        {{ live ? 'В эфире' : 'Не в сети' }}
      </span>
    </header>

    <div v-if="loading" class="feed__skeleton">
      <span v-for="n in 4" :key="n" />
    </div>

    <p v-else-if="!items.length" class="feed__empty">
      Здесь появятся новые тиры, ачивки, клановые войны и победы в турнирах.
    </p>

    <TransitionGroup
        v-else
        tag="ol"
        name="feed-item"
        class="feed__list"
    >
      <li
          v-for="item in items"
          :key="item.id"
          class="feed-item"
          :class="[`feed-item--${item.type.replace('.', '-')}`, { 'feed-item--fresh': fresh.has(item.id) }]"
      >
        <!-- Иконка события -->
        <span class="feed-item__icon" aria-hidden="true">
          <span
              v-if="item.type === 'tier.assigned'"
              class="tier-block feed-item__tier"
              :style="{ '--tier': tierColor(item.payload.tier) }"
          >{{ item.payload.tier }}</span>

          <PixelIcon
              v-else-if="item.type === 'achievement.earned'"
              :icon="item.payload.achievement?.icon"
              :color="item.payload.achievement?.color || '#facc15'"
              :size="22"
          />

          <svg v-else-if="item.type === 'clan_war.finished'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M14.5 17.5 3 6V3h3l11.5 11.5M13 19l6-6M16 16l4 4M19 21l2-2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>

          <svg v-else-if="item.type === 'tournament.won'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M8 21h8M12 17v4M6 4h12v5a6 6 0 0 1-12 0V4zM6 6H3v2a4 4 0 0 0 4 4M18 6h3v2a4 4 0 0 1-4 4" stroke-linecap="round" />
          </svg>

          <svg v-else-if="item.type === 'clan.created'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M5 21V4M5 4h11l-2 4 2 4H5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>

          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" stroke-linecap="round" />
          </svg>
        </span>

        <!-- Текст события -->
        <p class="feed-item__text">
          <template v-if="item.type === 'clan_war.finished'">
            <span class="feed-item__muted">Война кланов:</span>
            <template v-for="(side, index) in [clanSide(item, 'challenger'), clanSide(item, 'opponent')]" :key="index">
              <RouterLink
                  v-if="side.clan"
                  :to="clanLink(side.clan)"
                  class="feed-item__clan"
                  :class="{ 'feed-item__clan--winner': side.winner }"
                  :style="{ '--clan': side.clan.color || 'var(--accent-light)' }"
              >[{{ side.clan.tag }}] {{ side.clan.name }}</RouterLink>
              <strong v-if="index === 0" class="feed-item__score">
                {{ item.payload.challenger_score }} : {{ item.payload.opponent_score }}
              </strong>
            </template>
          </template>

          <template v-else>
            <RouterLink
                v-if="item.actor"
                :to="userLink(item.actor)"
                class="feed-item__actor"
            >
              <span
                  v-if="item.actor.clan_tag"
                  class="feed-item__tag"
                  :style="item.actor.clan_color ? { color: item.actor.clan_color } : {}"
              >[{{ item.actor.clan_tag }}]</span>
              {{ item.actor.username }}
            </RouterLink>
            <span v-else class="feed-item__muted">Игрок</span>

            <template v-if="item.type === 'tier.assigned'">
              получил тир <strong :style="{ color: tierColor(item.payload.tier) }">{{ item.payload.tier }}</strong>
              в {{ MODES[item.payload.mode] || item.payload.mode }}
              <span class="feed-item__muted">· {{ item.payload.score }}%</span>
            </template>

            <template v-else-if="item.type === 'achievement.earned'">
              открыл ачивку <strong>«{{ item.payload.achievement?.name }}»</strong>
            </template>

            <template v-else-if="item.type === 'tournament.won'">
              выиграл турнир
              <RouterLink
                  v-if="item.payload.tournament"
                  :to="`/tournaments/${item.payload.tournament.id}`"
                  class="feed-item__link"
              >«{{ item.payload.tournament.name }}»</RouterLink>
            </template>

            <template v-else-if="item.type === 'clan.created'">
              основал клан
              <RouterLink
                  v-if="item.payload.clan"
                  :to="clanLink(item.payload.clan)"
                  class="feed-item__clan"
                  :style="{ '--clan': item.payload.clan.color || 'var(--accent-light)' }"
              >[{{ item.payload.clan.tag }}] {{ item.payload.clan.name }}</RouterLink>
            </template>

            <template v-else-if="item.type === 'minecraft.linked'">
              привязал Minecraft-аккаунт
              <strong class="feed-item__nick">{{ item.payload.nickname }}</strong>
            </template>
          </template>
        </p>

        <time
            class="feed-item__time"
            :datetime="item.created_at"
            :title="new Date(item.created_at).toLocaleString('ru-RU')"
        >{{ ago(item.created_at) }}</time>
      </li>
    </TransitionGroup>

    <button
        v-if="!loading && items.length && hasMore"
        type="button"
        class="feed__more"
        :disabled="loadingMore"
        @click="loadMore"
    >
      {{ loadingMore ? 'Загружаем…' : 'Показать ещё' }}
    </button>
  </section>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.feed__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  padding: 16px 18px;

  border-bottom: 1px solid var(--border);
}

.feed__title {
  margin: 0;

  color: #fff;

  font-size: 18px;
}

.feed__status {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  color: var(--text-muted);

  font-size: 13px;
  font-weight: 600;
}

.feed__status-dot {
  position: relative;

  width: 8px;
  height: 8px;

  background: var(--text-muted);
  border-radius: 50%;
}

.feed__status--live {
  color: #4ade80;
}

.feed__status--live .feed__status-dot {
  background: #4ade80;
}

.feed__status--live .feed__status-dot::after {
  content: '';

  position: absolute;
  inset: 0;

  border: 2px solid #4ade80;
  border-radius: 50%;

  animation: feed-ping 1.8s ease-out infinite;
}

@keyframes feed-ping {
  from { transform: scale(1); opacity: 0.9; }
  to { transform: scale(2.6); opacity: 0; }
}

/* ---------- Список ---------- */

.feed__list {
  position: relative;

  max-height: 432px;
  margin: 0;
  padding: 0;

  overflow-y: auto;

  list-style: none;
}

.feed-item {
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;

  padding: 11px 18px;

  border-bottom: 1px solid var(--border);

  transition: background 0.6s ease;
}

.feed-item:last-child {
  border-bottom: 0;
}

.feed-item--fresh {
  background: var(--accent-soft);
}

.feed-item__icon {
  width: 32px;
  height: 32px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;
}

.feed-item__icon svg {
  width: 17px;
  height: 17px;
}

.feed-item--tier-assigned .feed-item__icon {
  background: none;
  border: 0;
}

.feed-item__tier {
  width: 32px;
  height: 32px;
}

.feed-item__glyph {
  font-size: 16px;
  line-height: 1;
}

.feed-item--clan_war-finished .feed-item__icon {
  color: #f87171;
}

.feed-item--tournament-won .feed-item__icon {
  color: var(--gold);
}

.feed-item--clan-created .feed-item__icon {
  color: var(--accent-light);
}

.feed-item--minecraft-linked .feed-item__icon {
  color: #4ade80;
}

.feed-item__text {
  margin: 0;

  color: var(--text-dim);

  font-size: 14px;
  line-height: 1.45;
}

.feed-item__text strong {
  color: var(--text);
  font-weight: 600;
}

.feed-item__actor {
  color: var(--text);
  font-weight: 600;
}

.feed-item__actor:hover,
.feed-item__link:hover,
.feed-item__clan:hover {
  text-decoration: underline;
}

.feed-item__tag {
  margin-right: 2px;
  color: var(--accent-light);
}

.feed-item__link {
  color: var(--text);
  font-weight: 600;
}

.feed-item__clan {
  color: var(--clan);
  font-weight: 600;
}

.feed-item__clan--winner::after {
  content: ' ✓';
  color: #4ade80;
}

.feed-item__score {
  margin: 0 6px;
  font-variant-numeric: tabular-nums;
}

.feed-item__nick {
  font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace;
  font-size: 13px;
}

.feed-item__muted {
  color: var(--text-muted);
}

.feed-item__time {
  color: var(--text-muted);

  font-size: 12px;
  white-space: nowrap;
}

/* ---------- Пусто, загрузка, ещё ---------- */

.feed__empty {
  margin: 0;
  padding: 28px 18px;

  color: var(--text-muted);

  font-size: 14px;
  text-align: center;
}

.feed__skeleton {
  display: flex;
  flex-direction: column;
  gap: 10px;

  padding: 16px 18px;
}

.feed__skeleton span {
  height: 32px;

  /* Шиммер загрузки: это смысловой градиент, а не декоративное «стекло» */
  background: linear-gradient(90deg, var(--bg) 0%, var(--bg-card-hover) 50%, var(--bg) 100%);
  background-size: 200% 100%;
  border-radius: 4px;

  animation: feed-shimmer 1.2s linear infinite;
}

@keyframes feed-shimmer {
  from { background-position: 200% 0; }
  to { background-position: -200% 0; }
}

.feed__more {
  padding: 12px;

  color: var(--accent-light);
  background: transparent;
  border-top: 1px solid var(--border);

  font-size: 14px;
  font-weight: 600;

  cursor: pointer;
}

.feed__more:hover:not(:disabled) {
  background: var(--bg-card-hover);
}

/* ---------- Появление нового события ---------- */

.feed-item-enter-active {
  transition:
      opacity 0.35s ease,
      transform 0.35s ease;
}

.feed-item-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.feed-item-move {
  transition: transform 0.35s ease;
}

@media (prefers-reduced-motion: reduce) {
  .feed__status--live .feed__status-dot::after,
  .feed__skeleton span {
    animation: none;
  }

  .feed-item-enter-active,
  .feed-item-move {
    transition: none;
  }
}

@media (max-width: 600px) {
  .feed-item {
    grid-template-columns: 32px minmax(0, 1fr);
    padding: 11px 14px;
  }

  .feed-item__time {
    grid-column: 2;
    margin-top: -6px;
  }
}
</style>
