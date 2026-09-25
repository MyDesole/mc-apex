<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { homeApi } from '@/services/home.js'
import UserName from '@/components/UserName.vue'

const loading = ref(true)

// данные
const hero = ref({})
const socials = ref({})
const footer = ref({})
const statsData = ref({})
const players = ref([])
const clans = ref([])
const news = ref([])

const tierColors = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

const socialLabels = {
  social_discord: 'Discord',
  social_telegram: 'Telegram',
  social_youtube: 'YouTube',
  social_vk: 'VK',
  social_twitch: 'Twitch',
  social_twitter: 'Twitter',
}

const typeLabels = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
}

const socialsList = ref([])

onMounted(async () => {
  try {
    const data = await homeApi.index()

    hero.value = data.hero ?? {}
    socials.value = data.socials ?? {}
    footer.value = data.footer ?? {}
    statsData.value = data.stats_data ?? {}
    players.value = data.players ?? []
    clans.value = data.clans ?? []
    news.value = data.news ?? []

    socialsList.value = Object.entries(socials.value)
        .filter(([, url]) => url)
        .map(([key, url]) => ({
          key,
          url,
          label: socialLabels[key] ?? key,
        }))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="home">
    <!-- HERO -->
    <section class="hero">
      <div v-if="hero.hero_badge" class="hero-badge">
        <span class="dot" />
        {{ hero.hero_badge }}
      </div>

      <h1>
        {{ hero.hero_title || 'APEX TIERS' }}
      </h1>

      <p>{{ hero.hero_subtitle || 'Рейтинг игроков и кланов Minecraft PvP' }}</p>

      <div class="hero-actions">
        <RouterLink
            :to="hero.hero_primary_url || '/players'"
            class="hero-btn primary"
        >
          {{ hero.hero_primary_text || 'Смотреть игроков' }}
        </RouterLink>
        <RouterLink
            :to="hero.hero_secondary_url || '/clans'"
            class="hero-btn ghost"
        >
          {{ hero.hero_secondary_text || 'Все кланы' }}
        </RouterLink>
      </div>
    </section>

    <!-- СТАТИСТИКА -->
    <section v-if="!loading" class="stats-bar">
      <div class="stats-bar__item">
        <span class="stats-bar__value">{{ statsData.players ?? 0 }}</span>
        <span class="stats-bar__label">игроков</span>
      </div>
      <div class="stats-bar__divider" />
      <div class="stats-bar__item">
        <span class="stats-bar__value">{{ statsData.clans ?? 0 }}</span>
        <span class="stats-bar__label">кланов</span>
      </div>
      <div class="stats-bar__divider" />
      <div class="stats-bar__item">
        <span class="stats-bar__value">{{ statsData.tournaments ?? 0 }}</span>
        <span class="stats-bar__label">турниров</span>
      </div>
      <div class="stats-bar__divider" />
      <div class="stats-bar__item">
        <span class="stats-bar__value">{{ statsData.matches ?? 0 }}</span>
        <span class="stats-bar__label">матчей</span>
      </div>
    </section>

    <!-- ЗАГРУЗКА -->
    <div v-if="loading" class="loading">
      <div class="spinner" />
      <span>Загрузка...</span>
    </div>

    <div v-else class="tops">
      <!-- НОВОСТИ -->
      <section v-if="news.length" class="top-block">
        <header class="top-head">
          <div class="top-head__left">
            <h2>Новости</h2>
            <span class="top-count">Последние {{ news.length }}</span>
          </div>

          <RouterLink to="/news" class="top-link">
            Все новости
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </RouterLink>
        </header>

        <div class="news-grid">
          <RouterLink
              v-for="n in news"
              :key="n.id"
              :to="`/news/${n.id}`"
              class="news-card"
              :class="{ 'news-card--pinned': n.is_pinned }"
          >
            <div
                class="news-card__cover"
                :style="n.cover_url ? { backgroundImage: `url(${n.cover_url})` } : {}"
            >
              <span v-if="!n.cover_url" class="news-card__letter">
                {{ n.title.charAt(0).toUpperCase() }}
              </span>

              <div class="news-card__badges">
                <span v-if="n.is_pinned" class="news-badge news-badge--pin">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 17v5" />
                    <path d="M9 11V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v7l2 3v1H7v-1l2-3z" />
                  </svg>
                </span>
                <span
                    class="news-badge"
                    :class="`news-badge--${n.type}`"
                >
                  {{ typeLabels[n.type] }}
                </span>
              </div>
            </div>

            <div class="news-card__body">
              <h3 class="news-card__title">{{ n.title }}</h3>
              <p v-if="n.excerpt" class="news-card__excerpt">{{ n.excerpt }}</p>
              <div class="news-card__meta">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" />
                  <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
                {{ new Date(n.published_at ?? n.created_at).toLocaleDateString('ru-RU') }}
              </div>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- ТОП ИГРОКОВ -->
      <section class="top-block">
        <header class="top-head">
          <div class="top-head__left">
            <h2>Топ игроков</h2>
            <span class="top-count">Топ {{ players.length }}</span>
          </div>

          <RouterLink to="/players" class="top-link">
            Все игроки
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </RouterLink>
        </header>

        <div v-if="!players.length" class="empty">
          Пока нет игроков с рейтингом
        </div>

        <template v-else>
          <!-- ПОДИУМ ТОП-3 -->
          <div v-if="players.length >= 1" class="podium" :class="{ 'podium--full': players.length >= 3 }">
            <!-- 2 место -->
            <RouterLink
                v-if="players[1]"
                :to="`/players/${players[1].id}`"
                class="podium-card podium-card--2"
            >
              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>2</span>
              </div>

              <div class="podium-card__avatar">
                <img
                    v-if="players[1].avatar_url"
                    :src="players[1].avatar_url"
                    :alt="players[1].username"
                />
                <template v-else>
                  {{ (players[1].username || 'И').charAt(0).toUpperCase() }}
                </template>
              </div>

              <div class="podium-card__name">
                <UserName :user="players[1]" />
              </div>

              <div class="podium-card__tier" :style="{ color: tierColors[players[1].tier] }">
                {{ players[1].tier }}
              </div>

              <div class="podium-card__score">
                <span class="score-value">{{ players[1].tier_score }}</span>
                <span class="score-suffix">%</span>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">2 место</span>
              </div>
            </RouterLink>

            <!-- 1 место -->
            <RouterLink
                v-if="players[0]"
                :to="`/players/${players[0].id}`"
                class="podium-card podium-card--1"
            >
              <div class="podium-card__crown">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                  <circle cx="3" cy="7" r="1" />
                  <circle cx="21" cy="7" r="1" />
                  <circle cx="12" cy="5" r="1" />
                </svg>
              </div>

              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>1</span>
              </div>

              <div class="podium-card__avatar">
                <img
                    v-if="players[0].avatar_url"
                    :src="players[0].avatar_url"
                    :alt="players[0].username"
                />
                <template v-else>
                  {{ (players[0].username || 'И').charAt(0).toUpperCase() }}
                </template>
              </div>

              <div class="podium-card__name">
                <UserName :user="players[0]" />
              </div>

              <div class="podium-card__tier" :style="{ color: tierColors[players[0].tier] }">
                {{ players[0].tier }}
              </div>

              <div class="podium-card__score">
                <span class="score-value">{{ players[0].tier_score }}</span>
                <span class="score-suffix">%</span>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">1 место</span>
              </div>
            </RouterLink>

            <!-- 3 место -->
            <RouterLink
                v-if="players[2]"
                :to="`/players/${players[2].id}`"
                class="podium-card podium-card--3"
            >
              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>3</span>
              </div>

              <div class="podium-card__avatar">
                <img
                    v-if="players[2].avatar_url"
                    :src="players[2].avatar_url"
                    :alt="players[2].username"
                />
                <template v-else>
                  {{ (players[2].username || 'И').charAt(0).toUpperCase() }}
                </template>
              </div>

              <div class="podium-card__name">
                <UserName :user="players[2]" />
              </div>

              <div class="podium-card__tier" :style="{ color: tierColors[players[2].tier] }">
                {{ players[2].tier }}
              </div>

              <div class="podium-card__score">
                <span class="score-value">{{ players[2].tier_score }}</span>
                <span class="score-suffix">%</span>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">3 место</span>
              </div>
            </RouterLink>
          </div>

          <!-- ОСТАЛЬНЫЕ (4+) -->
          <div v-if="players.length > 3" class="players-list">
            <RouterLink
                v-for="(player, i) in players.slice(3)"
                :key="player.id"
                :to="`/players/${player.id}`"
                class="player-row"
            >
              <div class="rank">
                <span class="rank-num">#{{ i + 4 }}</span>
              </div>

              <div class="row-avatar">
                <img
                    v-if="player.avatar_url"
                    :src="player.avatar_url"
                    :alt="player.username"
                    class="row-avatar-img"
                />
                <template v-else>
                  {{ (player.username || 'И').charAt(0).toUpperCase() }}
                </template>
              </div>

              <div class="row-info">
                <div class="row-name">
                  <UserName :user="player" />
                </div>
                <div class="row-bar">
                  <div
                      class="row-bar-fill"
                      :style="{ width: Math.min(player.tier_score, 100) + '%' }"
                  />
                </div>
              </div>

              <div class="row-tier" :style="{ color: tierColors[player.tier] }">
                {{ player.tier }}
              </div>

              <div class="row-score">
                <span class="score-value">{{ player.tier_score }}</span>
                <span class="score-suffix">%</span>
              </div>
            </RouterLink>
          </div>
        </template>
      </section>

      <!-- ТОП КЛАНОВ -->
      <section class="top-block">
        <header class="top-head">
          <div class="top-head__left">
            <h2>Топ кланов</h2>
            <span class="top-count">Топ {{ clans.length }}</span>
          </div>

          <RouterLink to="/clans" class="top-link">
            Все кланы
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </RouterLink>
        </header>

        <div v-if="!clans.length" class="empty">
          Пока нет кланов
        </div>

        <template v-else>
          <!-- ПОДИУМ ТОП-3 КЛАНОВ -->
          <div v-if="clans.length >= 1" class="podium" :class="{ 'podium--full': clans.length >= 3 }">
            <!-- 2 место -->
            <RouterLink
                v-if="clans[1]"
                :to="`/clans/${clans[1].id}`"
                class="podium-card podium-card--2"
            >
              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>2</span>
              </div>

              <div
                  class="podium-card__clan-avatar"
                  :style="{ background: clans[1].banner_color || '#7c3aed' }"
              >
                <img v-if="clans[1].avatar_url" :src="clans[1].avatar_url" :alt="clans[1].name" />
                <template v-else>{{ clans[1].tag?.charAt(0) || 'C' }}</template>
              </div>

              <div class="podium-card__name">
                <span class="podium-card__tag">[{{ clans[1].tag }}]</span>
                {{ clans[1].name }}
              </div>

              <div class="podium-card__stats">
                <div class="podium-stat">
                  <span class="podium-stat__val">{{ clans[1].power }}</span>
                  <span class="podium-stat__lbl">сила</span>
                </div>
                <div class="podium-stat">
                  <span class="podium-stat__val win">{{ clans[1].wins }}</span>
                  <span class="podium-stat__lbl">побед</span>
                </div>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">2 место</span>
              </div>
            </RouterLink>

            <!-- 1 место -->
            <RouterLink
                v-if="clans[0]"
                :to="`/clans/${clans[0].id}`"
                class="podium-card podium-card--1"
            >
              <div class="podium-card__crown">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                  <circle cx="3" cy="7" r="1" />
                  <circle cx="21" cy="7" r="1" />
                  <circle cx="12" cy="5" r="1" />
                </svg>
              </div>

              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>1</span>
              </div>

              <div
                  class="podium-card__clan-avatar"
                  :style="{ background: clans[0].banner_color || '#7c3aed' }"
              >
                <img v-if="clans[0].avatar_url" :src="clans[0].avatar_url" :alt="clans[0].name" />
                <template v-else>{{ clans[0].tag?.charAt(0) || 'C' }}</template>
              </div>

              <div class="podium-card__name">
                <span class="podium-card__tag">[{{ clans[0].tag }}]</span>
                {{ clans[0].name }}
              </div>

              <div class="podium-card__stats">
                <div class="podium-stat">
                  <span class="podium-stat__val">{{ clans[0].power }}</span>
                  <span class="podium-stat__lbl">сила</span>
                </div>
                <div class="podium-stat">
                  <span class="podium-stat__val win">{{ clans[0].wins }}</span>
                  <span class="podium-stat__lbl">побед</span>
                </div>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">1 место</span>
              </div>
            </RouterLink>

            <!-- 3 место -->
            <RouterLink
                v-if="clans[2]"
                :to="`/clans/${clans[2].id}`"
                class="podium-card podium-card--3"
            >
              <div class="podium-card__glow" />
              <div class="podium-card__rank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                </svg>
                <span>3</span>
              </div>

              <div
                  class="podium-card__clan-avatar"
                  :style="{ background: clans[2].banner_color || '#7c3aed' }"
              >
                <img v-if="clans[2].avatar_url" :src="clans[2].avatar_url" :alt="clans[2].name" />
                <template v-else>{{ clans[2].tag?.charAt(0) || 'C' }}</template>
              </div>

              <div class="podium-card__name">
                <span class="podium-card__tag">[{{ clans[2].tag }}]</span>
                {{ clans[2].name }}
              </div>

              <div class="podium-card__stats">
                <div class="podium-stat">
                  <span class="podium-stat__val">{{ clans[2].power }}</span>
                  <span class="podium-stat__lbl">сила</span>
                </div>
                <div class="podium-stat">
                  <span class="podium-stat__val win">{{ clans[2].wins }}</span>
                  <span class="podium-stat__lbl">побед</span>
                </div>
              </div>

              <div class="podium-card__base">
                <span class="podium-card__place">3 место</span>
              </div>
            </RouterLink>
          </div>

          <!-- ОСТАЛЬНЫЕ (4+) -->
          <div v-if="clans.length > 3" class="clans-list">
            <RouterLink
                v-for="(clan, i) in clans.slice(3)"
                :key="clan.id"
                :to="`/clans/${clan.id}`"
                class="clan-row"
                :class="{ highlighted: clan.is_highlighted }"
            >
              <div class="rank">
                <span class="rank-num">#{{ i + 4 }}</span>
              </div>

              <div class="clan-avatar" :style="{ background: clan.banner_color }">
                <img
                    v-if="clan.avatar_url"
                    :src="clan.avatar_url"
                    :alt="clan.name"
                    class="avatar-img"
                />
                <template v-else>{{ clan.tag?.charAt(0) || 'C' }}</template>
              </div>

              <div class="clan-info">
                <div class="clan-name">
                  <span class="tag">[{{ clan.tag }}]</span>
                  {{ clan.name }}
                  <span v-if="clan.is_highlighted" class="highlight-star">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
                    </svg>
                  </span>
                </div>
                <div class="clan-meta">
                  {{ clan.members_count }} участников
                  <span class="sep">·</span>
                  Лидер: {{ clan.leader?.username }}
                </div>
              </div>

              <div class="clan-stats">
                <div class="stat">
                  <span class="stat-value power">{{ clan.power }}</span>
                  <span class="stat-label">сила</span>
                </div>
                <div class="stat">
                  <span class="stat-value win">{{ clan.wins }}</span>
                  <span class="stat-label">побед</span>
                </div>
                <div class="stat">
                  <span class="stat-value loss">{{ clan.losses }}</span>
                  <span class="stat-label">поражений</span>
                </div>
              </div>
            </RouterLink>
          </div>
        </template>
      </section>
    </div>

    <!-- ФУТЕР с соцсетями -->
    <footer v-if="socialsList.length || footer.footer_text" class="home-footer">
      <div v-if="socialsList.length" class="footer-socials">
        <a
            v-for="s in socialsList"
            :key="s.key"
            :href="s.url"
            target="_blank"
            rel="noopener"
            class="social-link"
            :class="s.key"
        >
          {{ s.label }}
        </a>
      </div>

      <div v-if="footer.footer_text" class="footer-text">
        {{ footer.footer_text }}
      </div>
    </footer>
  </div>
</template>

<style scoped>
.home {
  width: min(1100px, calc(100% - 40px));
  margin: 0 auto;
  padding: 32px 0 40px;
}

/* === HERO === */

.hero {
  position: relative;
  text-align: center;
  padding: 72px 20px 88px;
  margin-bottom: 40px;
  border-radius: 24px;
  overflow: hidden;
  background:
      radial-gradient(circle at 50% 0%, rgba(124, 58, 237, 0.18), transparent 55%),
      radial-gradient(circle at 80% 100%, rgba(6, 182, 212, 0.08), transparent 40%),
      var(--bg-card);
  border: 1px solid var(--border);
}

.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
      linear-gradient(rgba(124, 58, 237, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(124, 58, 237, 0.05) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(circle at 50% 30%, black, transparent 70%);
  -webkit-mask-image: radial-gradient(circle at 50% 30%, black, transparent 70%);
  pointer-events: none;
}

.hero-badge {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  margin-bottom: 24px;
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.25);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.hero-badge .dot {
  width: 6px;
  height: 6px;
  background: #a78bfa;
  border-radius: 50%;
  box-shadow: 0 0 8px #a78bfa;
  animation: pulse 2s infinite;
}

.hero h1 {
  position: relative;
  margin: 0 0 14px;
  font-size: 64px;
  font-weight: 900;
  letter-spacing: -3px;
  line-height: 1;
  background: linear-gradient(135deg, #e9d5ff 0%, #a78bfa 40%, #7c3aed 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  filter: drop-shadow(0 4px 30px rgba(124, 58, 237, 0.3));
}

.hero p {
  position: relative;
  margin: 0 0 32px;
  color: var(--text-dim);
  font-size: 16px;
  font-weight: 500;
}

.hero-actions {
  position: relative;
  display: flex;
  gap: 12px;
  justify-content: center;
}

.hero-btn {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 22px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 700;
  transition: all 0.2s ease;
}

.hero-btn.primary {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 6px 24px rgba(124, 58, 237, 0.35);
}

.hero-btn.primary:hover {
  background: var(--accent-light);
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(124, 58, 237, 0.45);
}

.hero-btn.ghost {
  color: var(--text);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
}

.hero-btn.ghost:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: var(--border-hover);
}

/* === STATS BAR === */

.stats-bar {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 16px;
  padding: 20px 24px;
  margin-bottom: 48px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  flex-wrap: wrap;
}

.stats-bar__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  flex: 1;
  min-width: 100px;
}

.stats-bar__value {
  font-size: 26px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -1px;
}

.stats-bar__label {
  font-size: 11px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 700;
}

.stats-bar__divider {
  width: 1px;
  height: 32px;
  background: var(--border);
}

/* === NEWS === */

.news-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.news-card {
  position: relative;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  transition: all 0.22s ease;
}

.news-card:hover {
  border-color: var(--border-hover);
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.news-card--pinned {
  border-color: rgba(250, 204, 21, 0.35);
  box-shadow: 0 0 30px rgba(250, 204, 21, 0.05);
}

.news-card__cover {
  position: relative;
  height: 130px;
  background: linear-gradient(135deg, #7c3aed, #06b6d4);
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
}

.news-card__letter {
  font-size: 42px;
  font-weight: 900;
  color: #fff;
  opacity: 0.4;
}

.news-card__badges {
  position: absolute;
  top: 8px;
  left: 8px;
  right: 8px;
  display: flex;
  justify-content: space-between;
  gap: 6px;
}

.news-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #fff;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(6px);
}

.news-badge--pin {
  background: rgba(250, 204, 21, 0.85);
  color: #000;
}

.news-badge--news { color: #a78bfa; }
.news-badge--update { color: #4ade80; }
.news-badge--event { color: #f472b6; }
.news-badge--announcement { color: #fbbf24; }

.news-card__body {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.news-card__title {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.news-card__excerpt {
  margin: 0;
  color: var(--text-dim);
  font-size: 12px;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.news-card__meta {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: auto;
  font-size: 11px;
  color: var(--text-muted);
  font-weight: 600;
}

/* === TOPS LAYOUT === */

.tops {
  display: flex;
  flex-direction: column;
  gap: 56px;
}

.top-block {
  animation: fadeIn 0.5s ease;
}

.top-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 0 4px;
}

.top-head__left {
  display: flex;
  align-items: baseline;
  gap: 12px;
}

.top-head h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.top-count {
  padding: 3px 9px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.top-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--accent-light);
  font-size: 13px;
  font-weight: 700;
  transition: all 0.2s;
}

.top-link:hover {
  color: #c4b5fd;
  gap: 10px;
}

/* === PODIUM === */

.podium {
  display: grid;
  grid-template-columns: 1fr 1.15fr 1fr;
  align-items: end;
  gap: 14px;
  padding: 24px 12px 0;
  margin-bottom: 20px;
  perspective: 1200px;
}

.podium--full {
  min-height: 340px;
}

.podium-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 24px 16px 0;
  text-decoration: none;
  color: inherit;
  border-radius: 18px 18px 14px 14px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
  border: 1px solid var(--border);
  overflow: hidden;
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
  transform-style: preserve-3d;
}

.podium-card:hover {
  transform: translateY(-4px);
  border-color: var(--border-hover);
}

.podium-card__glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.8;
  background: radial-gradient(circle at 50% 0%, currentColor 0%, transparent 60%);
  mix-blend-mode: screen;
  pointer-events: none;
}

/* Rank badge */
.podium-card__rank {
  position: relative;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: -0.3px;
  color: #fff;
}

.podium-card__rank svg {
  filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
}

/* Avatar */
.podium-card__avatar,
.podium-card__clan-avatar {
  position: relative;
  z-index: 2;
  width: 84px;
  height: 84px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 30px;
  font-weight: 900;
  color: #fff;
  overflow: hidden;
  border: 3px solid var(--bg-card);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.podium-card__clan-avatar {
  border-radius: 20px;
}

.podium-card__avatar img,
.podium-card__clan-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

/* Crown */
.podium-card__crown {
  position: absolute;
  top: -6px;
  left: 50%;
  transform: translateX(-50%);
  color: #facc15;
  filter: drop-shadow(0 4px 12px rgba(250, 204, 21, 0.6));
  z-index: 3;
  animation: crownFloat 3s ease-in-out infinite;
}

@keyframes crownFloat {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50% { transform: translateX(-50%) translateY(-4px); }
}

/* Name */
.podium-card__name {
  position: relative;
  z-index: 2;
  font-size: 15px;
  font-weight: 800;
  color: #fff;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

.podium-card__tag {
  color: var(--accent-light);
}

/* Tier */
.podium-card__tier {
  position: relative;
  z-index: 2;
  font-size: 28px;
  font-weight: 900;
  letter-spacing: -1px;
  filter: drop-shadow(0 2px 8px currentColor);
}

/* Score */
.podium-card__score {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: baseline;
  gap: 1px;
}

.podium-card__score .score-value {
  font-size: 20px;
  font-weight: 900;
  color: #fff;
}

.podium-card__score .score-suffix {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-dim);
}

/* Stats (для кланов) */
.podium-card__stats {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 20px;
}

.podium-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.podium-stat__val {
  font-size: 16px;
  font-weight: 900;
  color: #fff;
}

.podium-stat__val.win { color: #4ade80; }

.podium-stat__lbl {
  font-size: 9px;
  color: var(--text-muted);
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.5px;
}

/* Base (place) */
.podium-card__base {
  position: relative;
  z-index: 2;
  width: 100%;
  margin-top: auto;
  padding: 12px 0 10px;
  text-align: center;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  background: rgba(255, 255, 255, 0.02);
}

.podium-card__place {
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* 1 место */
.podium-card--1 {
  color: #facc15;
  padding-top: 34px;
  padding-bottom: 0;
  background:
      radial-gradient(circle at 50% -10%, rgba(250, 204, 21, 0.25), transparent 60%),
      linear-gradient(180deg, rgba(250, 204, 21, 0.06), rgba(250, 204, 21, 0.01));
  border-color: rgba(250, 204, 21, 0.45);
  box-shadow:
      0 20px 60px -20px rgba(250, 204, 21, 0.4),
      0 0 0 1px rgba(250, 204, 21, 0.1) inset;
}

.podium-card--1:hover {
  border-color: rgba(250, 204, 21, 0.7);
  box-shadow:
      0 28px 80px -20px rgba(250, 204, 21, 0.55),
      0 0 0 1px rgba(250, 204, 21, 0.2) inset;
}

.podium-card--1 .podium-card__glow {
  color: #facc15;
  opacity: 0.5;
}

.podium-card--1 .podium-card__rank {
  background: linear-gradient(135deg, #fde047, #f59e0b);
  color: #422006;
  box-shadow: 0 4px 14px rgba(250, 204, 21, 0.5);
}

.podium-card--1 .podium-card__avatar,
.podium-card--1 .podium-card__clan-avatar {
  width: 96px;
  height: 96px;
  border-color: rgba(250, 204, 21, 0.5);
  box-shadow:
      0 12px 40px rgba(250, 204, 21, 0.35),
      0 0 0 4px rgba(250, 204, 21, 0.15);
}

.podium-card--1 .podium-card__place { color: #facc15; }

/* 2 место */
.podium-card--2 {
  color: #cbd5e1;
  min-height: 290px;
  background:
      radial-gradient(circle at 50% -10%, rgba(203, 213, 225, 0.2), transparent 60%),
      linear-gradient(180deg, rgba(203, 213, 225, 0.05), rgba(203, 213, 225, 0.01));
  border-color: rgba(203, 213, 225, 0.35);
}

.podium-card--2:hover {
  border-color: rgba(203, 213, 225, 0.55);
}

.podium-card--2 .podium-card__glow {
  color: #cbd5e1;
  opacity: 0.35;
}

.podium-card--2 .podium-card__rank {
  background: linear-gradient(135deg, #e5e7eb, #9ca3af);
  color: #1e293b;
  box-shadow: 0 4px 14px rgba(203, 213, 225, 0.4);
}

.podium-card--2 .podium-card__avatar,
.podium-card--2 .podium-card__clan-avatar {
  border-color: rgba(203, 213, 225, 0.4);
  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.5),
      0 0 0 3px rgba(203, 213, 225, 0.1);
}

.podium-card--2 .podium-card__place { color: #cbd5e1; }

/* 3 место */
.podium-card--3 {
  color: #d97706;
  min-height: 270px;
  background:
      radial-gradient(circle at 50% -10%, rgba(217, 119, 6, 0.2), transparent 60%),
      linear-gradient(180deg, rgba(217, 119, 6, 0.05), rgba(217, 119, 6, 0.01));
  border-color: rgba(217, 119, 6, 0.4);
}

.podium-card--3:hover {
  border-color: rgba(217, 119, 6, 0.6);
}

.podium-card--3 .podium-card__glow {
  color: #d97706;
  opacity: 0.35;
}

.podium-card--3 .podium-card__rank {
  background: linear-gradient(135deg, #e09966, #a0522d);
  color: #2a1006;
  box-shadow: 0 4px 14px rgba(217, 119, 6, 0.4);
}

.podium-card--3 .podium-card__avatar,
.podium-card--3 .podium-card__clan-avatar {
  border-color: rgba(217, 119, 6, 0.4);
  box-shadow:
      0 10px 30px rgba(0, 0, 0, 0.5),
      0 0 0 3px rgba(217, 119, 6, 0.1);
}

.podium-card--3 .podium-card__place { color: #d97706; }

/* === PLAYERS / CLANS LIST === */

.players-list,
.clans-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-row,
.clan-row {
  position: relative;
  display: grid;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: all 0.22s ease;
  overflow: hidden;
}

.player-row::before,
.clan-row::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--accent);
  opacity: 0;
  transition: opacity 0.22s;
}

.player-row:hover::before,
.clan-row:hover::before {
  opacity: 1;
}

.player-row:hover,
.clan-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
  transform: translateX(4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
}

/* === RANK === */

.rank {
  min-width: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
}

.rank-num {
  font-size: 14px;
  color: var(--text-muted);
  font-weight: 800;
  letter-spacing: -0.5px;
}

/* === PLAYER ROW === */

.player-row {
  grid-template-columns: 44px 48px 1fr 56px 70px;
}

.row-avatar {
  position: relative;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 12px;
  color: #fff;
  font-size: 18px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.row-avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.row-info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row-name {
  font-size: 15px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row-bar {
  height: 4px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  overflow: hidden;
}

.row-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #7c3aed, #a78bfa);
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.row-tier {
  font-size: 24px;
  font-weight: 900;
  text-align: center;
  letter-spacing: -1px;
  filter: drop-shadow(0 2px 8px currentColor);
  opacity: 0.95;
}

.row-score {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 1px;
}

.score-value {
  font-size: 17px;
  font-weight: 900;
  color: var(--text);
}

.score-suffix {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-dim);
}

/* === CLAN ROW === */

.clan-row {
  grid-template-columns: 44px 52px 1fr auto;
}

.clan-avatar {
  position: relative;
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.clan-info {
  min-width: 0;
}

.clan-name {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-name .tag {
  color: var(--accent-light);
  margin-right: 2px;
}

.highlight-star {
  display: inline-flex;
  align-items: center;
  color: #facc15;
  filter: drop-shadow(0 0 6px rgba(250, 204, 21, 0.6));
}

.clan-meta {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-meta .sep {
  margin: 0 6px;
  opacity: 0.4;
}

.clan-stats {
  display: flex;
  gap: 28px;
  padding-left: 16px;
  border-left: 1px solid var(--border);
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 48px;
}

.stat-value {
  font-size: 17px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}

.stat-value.power { color: #a78bfa; }
.stat-value.win { color: #4ade80; }
.stat-value.loss { color: #f87171; }

.stat-label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 700;
  margin-top: 2px;
}

/* === FOOTER === */

.home-footer {
  margin-top: 64px;
  padding: 32px 24px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  text-align: center;
}

.footer-socials {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.social-link {
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  transform: translateY(-1px);
  color: #fff;
}

.social-link.social_discord:hover { background: #5865f2; border-color: #5865f2; }
.social-link.social_telegram:hover { background: #229ed9; border-color: #229ed9; }
.social-link.social_youtube:hover { background: #ff0000; border-color: #ff0000; }
.social-link.social_vk:hover { background: #0077ff; border-color: #0077ff; }
.social-link.social_twitch:hover { background: #9146ff; border-color: #9146ff; }
.social-link.social_twitter:hover { background: #1da1f2; border-color: #1da1f2; }

.footer-text {
  color: var(--text-muted);
  font-size: 12px;
}

/* === STATES === */

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 0;
  color: var(--text-dim);
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.empty {
  padding: 48px;
  text-align: center;
  color: var(--text-muted);
  font-size: 14px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* === АДАПТИВ === */

@media (max-width: 800px) {
  .hero h1 { font-size: 48px; }

  .podium {
    grid-template-columns: 1fr;
    padding: 0;
    gap: 10px;
    min-height: auto;
  }

  .podium-card--1,
  .podium-card--2,
  .podium-card--3 {
    min-height: 0;
    padding: 20px 16px 0;
    border-radius: 16px;
  }

  .podium-card--1 {
    padding-top: 28px;
  }

  .podium-card--1 .podium-card__avatar,
  .podium-card--1 .podium-card__clan-avatar {
    width: 80px;
    height: 80px;
  }

  .podium-card__avatar,
  .podium-card__clan-avatar {
    width: 72px;
    height: 72px;
    font-size: 26px;
  }

  /* на мобилке 1 место всегда первое */
  .podium-card--1 { order: -1; }
  .podium-card--2 { order: 0; }
  .podium-card--3 { order: 1; }

  .player-row {
    grid-template-columns: 40px 44px 1fr 48px 60px;
    gap: 12px;
    padding: 12px 16px;
  }

  .row-avatar { width: 42px; height: 42px; font-size: 16px; }

  .clan-row {
    grid-template-columns: 40px 46px 1fr auto;
    gap: 12px;
    padding: 12px 16px;
  }

  .clan-avatar { width: 44px; height: 44px; }

  .clan-stats { gap: 18px; padding-left: 12px; }

  .stat { min-width: 42px; }

  .stat-value { font-size: 15px; }
}

@media (max-width: 600px) {
  .home { width: calc(100% - 24px); padding: 20px 0 40px; }

  .hero {
    padding: 48px 16px 56px;
    margin-bottom: 32px;
    border-radius: 18px;
  }

  .hero h1 { font-size: 38px; letter-spacing: -2px; }

  .hero p { font-size: 14px; }

  .hero-actions { flex-direction: column; }

  .hero-btn { width: 100%; justify-content: center; }

  .stats-bar {
    padding: 16px;
    gap: 8px;
    margin-bottom: 32px;
  }

  .stats-bar__value { font-size: 20px; }

  .stats-bar__divider { display: none; }

  .stats-bar__item { min-width: 70px; flex: 0 0 auto; }

  .top-head h2 { font-size: 18px; }

  .top-count { display: none; }

  .news-grid {
    grid-template-columns: 1fr;
  }

  .player-row {
    grid-template-columns: 36px 40px 1fr 48px;
    gap: 10px;
    padding: 10px 14px;
  }

  .row-avatar { width: 38px; height: 38px; font-size: 14px; border-radius: 10px; }

  .row-tier { font-size: 20px; }

  .row-score { display: none; }

  .clan-row {
    grid-template-columns: 36px 40px 1fr;
    gap: 10px;
    padding: 10px 14px;
  }

  .clan-avatar { width: 40px; height: 40px; border-radius: 10px; font-size: 16px; }

  .clan-stats { display: none; }
}
</style>