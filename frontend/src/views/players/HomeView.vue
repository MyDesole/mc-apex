<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { homeApi } from '@/services/players/home.js'
import UserName from '@/components/players/UserName.vue'
import { userLink, clanLink } from '@/utils/links.js'

const loading = ref(true)

const hero = ref({})
const socials = ref({})
const footer = ref({})
const statsData = ref({})
const players = ref([])
const clans = ref([])
const news = ref([])

const tierColors = {
  S: '#facc15',
  A: '#fb923c',
  B: '#a78bfa',
  C: '#22d3ee',
  D: '#4ade80',
  E: '#94a3b8',
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

const topPlayers = computed(() => players.value.slice(0, 3))
const restPlayers = computed(() => players.value.slice(3))

const topClans = computed(() => clans.value.slice(0, 3))
const restClans = computed(() => clans.value.slice(3))

function playerScore(player) {
  return Number(player?.tier_score ?? 0)
}

function playerTierColor(player) {
  return tierColors[player?.tier] || '#a78bfa'
}

function clanColor(clan) {
  return clan?.banner_color || '#7c3aed'
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function playerInitial(player) {
  return (player?.username || 'И').charAt(0).toUpperCase()
}

function clanInitial(clan) {
  return clan?.tag?.charAt(0)?.toUpperCase() || 'C'
}

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
  } catch (error) {
    console.error('Failed to load home page:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="home">
    <!-- Decorative background -->
    <div class="home-bg" aria-hidden="true">
      <div class="home-bg__orb home-bg__orb--one" />
      <div class="home-bg__orb home-bg__orb--two" />
      <div class="home-bg__orb home-bg__orb--three" />
      <div class="home-bg__grid" />
      <div class="home-bg__noise" />
    </div>

    <main class="home-content">
      <!-- =========================================================
           HERO
      ========================================================== -->
      <section class="hero">
        <div class="hero__aurora" />
        <div class="hero__ring hero__ring--one" />
        <div class="hero__ring hero__ring--two" />

        <div class="hero__content">
          <div v-if="hero.hero_badge" class="hero-badge">
            <span class="hero-badge__pulse" />
            <span>{{ hero.hero_badge }}</span>
            <span class="hero-badge__line" />
            <span class="hero-badge__live">LIVE</span>
          </div>

          <div class="hero__eyebrow">
            <span />
            MINECRAFT PVP RANKING
            <span />
          </div>

          <h1>
            <span class="hero__title-main">
              {{ hero.hero_title || 'APEX' }}
            </span>
            <span class="hero__title-sub">
              TIERS
            </span>
          </h1>

          <p class="hero__subtitle">
            {{ hero.hero_subtitle || 'Рейтинг игроков и кланов Minecraft PvP' }}
          </p>

          <div class="hero-actions">
            <RouterLink
                :to="hero.hero_primary_url || '/players'"
                class="hero-btn hero-btn--primary"
            >
              <span class="hero-btn__shine" />

              <svg
                  width="17"
                  height="17"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M12 3v18M3 12h18"
                    stroke-linecap="round"
                />
              </svg>

              <span>
                {{ hero.hero_primary_text || 'Смотреть игроков' }}
              </span>

              <svg
                  class="hero-btn__arrow"
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
              >
                <path
                    d="M5 12h14M13 5l7 7-7 7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </RouterLink>

            <RouterLink
                :to="hero.hero_secondary_url || '/clans'"
                class="hero-btn hero-btn--ghost"
            >
              <svg
                  width="17"
                  height="17"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path
                    d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
                />
                <path
                    d="M9 12l2 2 4-4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>

              <span>
                {{ hero.hero_secondary_text || 'Все кланы' }}
              </span>
            </RouterLink>
          </div>
        </div>

        <div class="hero__side hero__side--left">
          <span>01</span>
          <i />
          <span>RANK</span>
        </div>

        <div class="hero__side hero__side--right">
          <span>EST.</span>
          <i />
          <span>2025</span>
        </div>

        <div class="hero__bottom">
          <div>
            <span class="hero__bottom-dot" />
            SYSTEM ONLINE
          </div>

          <div class="hero__bottom-center">
            APEX // GLOBAL
          </div>

          <div>
            RANKED SEASON
            <span class="hero__bottom-accent">01</span>
          </div>
        </div>
      </section>

      <!-- =========================================================
           STATS
      ========================================================== -->
      <section v-if="!loading" class="stats">
        <div class="stats__heading">
          <span class="stats__heading-line" />
          <span>NETWORK OVERVIEW</span>
          <span class="stats__heading-line" />
        </div>

        <div class="stats__grid">
          <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--purple">
              <svg
                  width="19"
                  height="19"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path
                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>

            <div class="stat-card__content">
              <strong>{{ statsData.players ?? 0 }}</strong>
              <span>Игроков</span>
            </div>

            <div class="stat-card__number">01</div>
          </div>

          <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--cyan">
              <svg
                  width="19"
                  height="19"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path
                    d="M16 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1"
                />
                <circle cx="9" cy="7" r="4" />
                <path d="M16 11h6M19 8v6" />
              </svg>
            </div>

            <div class="stat-card__content">
              <strong>{{ statsData.clans ?? 0 }}</strong>
              <span>Кланов</span>
            </div>

            <div class="stat-card__number">02</div>
          </div>

          <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--gold">
              <svg
                  width="19"
                  height="19"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M8 21h8" />
                <path d="M12 17v4" />
                <path
                    d="M6 4h12v5a6 6 0 0 1-12 0V4z"
                />
                <path d="M6 6H3v2a4 4 0 0 0 4 4" />
                <path d="M18 6h3v2a4 4 0 0 1-4 4" />
              </svg>
            </div>

            <div class="stat-card__content">
              <strong>{{ statsData.tournaments ?? 0 }}</strong>
              <span>Турниров</span>
            </div>

            <div class="stat-card__number">03</div>
          </div>

          <div class="stat-card">
            <div class="stat-card__icon stat-card__icon--green">
              <svg
                  width="19"
                  height="19"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M6 4l6 4 6-4" />
                <path d="M6 20l6-4 6 4" />
                <path d="M12 8v8" />
              </svg>
            </div>

            <div class="stat-card__content">
              <strong>{{ statsData.matches ?? 0 }}</strong>
              <span>Матчей</span>
            </div>

            <div class="stat-card__number">04</div>
          </div>
        </div>
      </section>

      <!-- =========================================================
           LOADING
      ========================================================== -->
      <div v-if="loading" class="loading">
        <div class="loading__ring">
          <span />
        </div>

        <div class="loading__text">
          <strong>INITIALIZING</strong>
          <span>Загрузка рейтинговой системы...</span>
        </div>
      </div>

      <div v-else class="sections">
        <!-- =======================================================
             NEWS
        ======================================================== -->
        <section v-if="news.length" class="section">
          <header class="section-head">
            <div class="section-head__title">
              <div class="section-head__mark section-head__mark--news">
                <svg
                    width="17"
                    height="17"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                  <path
                      d="M4 5h16v14H4z"
                      stroke-linejoin="round"
                  />
                  <path
                      d="M8 9h8M8 13h5"
                      stroke-linecap="round"
                  />
                </svg>
              </div>

              <div>
                <div class="section-head__eyebrow">
                  LATEST INTEL
                </div>

                <h2>Новости</h2>
              </div>
            </div>

            <RouterLink to="/news" class="section-link">
              <span>Все новости</span>

              <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M5 12h14M13 5l7 7-7 7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </RouterLink>
          </header>

          <div class="news-grid">
            <RouterLink
                v-for="(item, index) in news"
                :key="item.id"
                :to="`/news/${item.id}`"
                class="news-card"
                :class="{
                'news-card--featured': index === 0,
                'news-card--pinned': item.is_pinned,
              }"
            >
              <div
                  class="news-card__cover"
                  :style="
                  item.cover_url
                    ? { backgroundImage: `url(${item.cover_url})` }
                    : {}
                "
              >
                <div class="news-card__cover-shade" />

                <div
                    v-if="!item.cover_url"
                    class="news-card__fallback"
                >
                  <span>{{ item.title?.charAt(0)?.toUpperCase() }}</span>
                  <div />
                </div>

                <div class="news-card__top">
                  <span
                      v-if="item.is_pinned"
                      class="news-card__pin"
                  >
                    <svg
                        width="11"
                        height="11"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                      <path d="M12 17v5" />
                      <path
                          d="M9 11V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v7l2 3v1H7v-1l2-3z"
                      />
                    </svg>

                    PINNED
                  </span>

                  <span
                      class="news-card__type"
                      :class="`news-card__type--${item.type}`"
                  >
                    {{ typeLabels[item.type] || 'Новость' }}
                  </span>
                </div>

                <div class="news-card__index">
                  {{ String(index + 1).padStart(2, '0') }}
                </div>
              </div>

              <div class="news-card__body">
                <div class="news-card__meta">
                  <span>
                    {{ formatDate(item.published_at ?? item.created_at) }}
                  </span>

                  <i />
                  <span>APEX NEWS</span>
                </div>

                <h3>{{ item.title }}</h3>

                <p v-if="item.excerpt">
                  {{ item.excerpt }}
                </p>

                <div class="news-card__read">
                  <span>Читать</span>

                  <svg
                      width="14"
                      height="14"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                  >
                    <path
                        d="M5 12h14M13 5l7 7-7 7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                  </svg>
                </div>
              </div>
            </RouterLink>
          </div>
        </section>

        <!-- =======================================================
             PLAYERS
        ======================================================== -->
        <section class="section">
          <header class="section-head">
            <div class="section-head__title">
              <div class="section-head__mark section-head__mark--player">
                <svg
                    width="17"
                    height="17"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                  <circle cx="12" cy="8" r="4" />
                  <path
                      d="M4 21a8 8 0 0 1 16 0"
                      stroke-linecap="round"
                  />
                </svg>
              </div>

              <div>
                <div class="section-head__eyebrow">
                  GLOBAL RANKING
                </div>

                <h2>Топ игроков</h2>
              </div>

              <span class="section-count">
                TOP {{ players.length }}
              </span>
            </div>

            <RouterLink to="/players" class="section-link">
              <span>Все игроки</span>

              <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M5 12h14M13 5l7 7-7 7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </RouterLink>
          </header>

          <div v-if="!players.length" class="empty">
            <div class="empty__icon">Ø</div>
            <strong>Рейтинг пуст</strong>
            <span>Пока нет игроков с рейтингом</span>
          </div>

          <template v-else>
            <!-- PLAYER PODIUM -->
            <div class="podium podium--players">
              <!-- SECOND -->
              <RouterLink
                  v-if="topPlayers[1]"
                  :to="userLink(topPlayers[1])"
                  class="podium-card podium-card--second"
              >
                <div class="podium-card__ambient" />

                <div class="podium-rank">
                  <span>02</span>
                </div>

                <div class="podium-avatar">
                  <img
                      v-if="topPlayers[1].avatar_url"
                      :src="topPlayers[1].avatar_url"
                      :alt="topPlayers[1].username"
                  />

                  <span v-else>
                    {{ playerInitial(topPlayers[1]) }}
                  </span>
                </div>

                <div class="podium-name">
                  <UserName :user="topPlayers[1]" />
                </div>

                <div
                    class="podium-tier"
                    :style="{
                    color: playerTierColor(topPlayers[1]),
                  }"
                >
                  {{ topPlayers[1].tier }}
                </div>

                <div class="podium-score">
                  <strong>{{ playerScore(topPlayers[1]) }}</strong>
                  <span>%</span>
                </div>

                <div class="podium-base">
                  <span>2ND PLACE</span>
                </div>
              </RouterLink>

              <!-- FIRST -->
              <RouterLink
                  v-if="topPlayers[0]"
                  :to="userLink(topPlayers[0])"
                  class="podium-card podium-card--first"
              >
                <div class="podium-card__stars">
                  <i />
                  <i />
                  <i />
                  <i />
                </div>

                <div class="podium-crown">
                  <svg
                      width="30"
                      height="30"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                  >
                    <path
                        d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"
                    />
                    <circle cx="3" cy="7" r="1" />
                    <circle cx="21" cy="7" r="1" />
                    <circle cx="12" cy="5" r="1" />
                  </svg>
                </div>

                <div class="podium-card__ambient" />

                <div class="podium-rank">
                  <span>01</span>
                </div>

                <div class="podium-avatar">
                  <div class="podium-avatar__halo" />

                  <img
                      v-if="topPlayers[0].avatar_url"
                      :src="topPlayers[0].avatar_url"
                      :alt="topPlayers[0].username"
                  />

                  <span v-else>
                    {{ playerInitial(topPlayers[0]) }}
                  </span>
                </div>

                <div class="podium-name">
                  <UserName :user="topPlayers[0]" />
                </div>

                <div
                    class="podium-tier"
                    :style="{
                    color: playerTierColor(topPlayers[0]),
                  }"
                >
                  {{ topPlayers[0].tier }}
                </div>

                <div class="podium-score">
                  <strong>{{ playerScore(topPlayers[0]) }}</strong>
                  <span>%</span>
                </div>

                <div class="podium-base">
                  <span>APEX CHAMPION</span>
                </div>
              </RouterLink>

              <!-- THIRD -->
              <RouterLink
                  v-if="topPlayers[2]"
                  :to="userLink(topPlayers[2])"
                  class="podium-card podium-card--third"
              >
                <div class="podium-card__ambient" />

                <div class="podium-rank">
                  <span>03</span>
                </div>

                <div class="podium-avatar">
                  <img
                      v-if="topPlayers[2].avatar_url"
                      :src="topPlayers[2].avatar_url"
                      :alt="topPlayers[2].username"
                  />

                  <span v-else>
                    {{ playerInitial(topPlayers[2]) }}
                  </span>
                </div>

                <div class="podium-name">
                  <UserName :user="topPlayers[2]" />
                </div>

                <div
                    class="podium-tier"
                    :style="{
                    color: playerTierColor(topPlayers[2]),
                  }"
                >
                  {{ topPlayers[2].tier }}
                </div>

                <div class="podium-score">
                  <strong>{{ playerScore(topPlayers[2]) }}</strong>
                  <span>%</span>
                </div>

                <div class="podium-base">
                  <span>3RD PLACE</span>
                </div>
              </RouterLink>
            </div>

            <!-- REST PLAYERS -->
            <div
                v-if="restPlayers.length"
                class="rank-list"
            >
              <RouterLink
                  v-for="(player, index) in restPlayers"
                  :key="player.id"
                  :to="userLink(player)"
                  class="rank-row"
              >
                <div class="rank-row__position">
                  {{ String(index + 4).padStart(2, '0') }}
                </div>

                <div class="rank-row__avatar">
                  <img
                      v-if="player.avatar_url"
                      :src="player.avatar_url"
                      :alt="player.username"
                  />

                  <span v-else>
                    {{ playerInitial(player) }}
                  </span>
                </div>

                <div class="rank-row__identity">
                  <strong>
                    <UserName :user="player" />
                  </strong>

                  <div class="rank-row__progress">
                    <span
                        :style="{
                        width: `${Math.min(playerScore(player), 100)}%`,
                        background: playerTierColor(player),
                      }"
                    />
                  </div>
                </div>

                <div
                    class="rank-row__tier"
                    :style="{
                    color: playerTierColor(player),
                  }"
                >
                  {{ player.tier }}
                </div>

                <div class="rank-row__score">
                  <strong>{{ playerScore(player) }}</strong>
                  <span>%</span>
                </div>

                <svg
                    class="rank-row__arrow"
                    width="15"
                    height="15"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                  <path
                      d="M5 12h14M13 5l7 7-7 7"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  />
                </svg>
              </RouterLink>
            </div>
          </template>
        </section>

        <!-- =======================================================
             CLANS
        ======================================================== -->
        <section class="section section--clans">
          <header class="section-head">
            <div class="section-head__title">
              <div class="section-head__mark section-head__mark--clan">
                <svg
                    width="17"
                    height="17"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                  <path
                      d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"
                  />
                  <path
                      d="M9 12l2 2 4-4"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  />
                </svg>
              </div>

              <div>
                <div class="section-head__eyebrow">
                  CLAN WARFARE
                </div>

                <h2>Топ кланов</h2>
              </div>

              <span class="section-count">
                TOP {{ clans.length }}
              </span>
            </div>

            <RouterLink to="/clans" class="section-link">
              <span>Все кланы</span>

              <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M5 12h14M13 5l7 7-7 7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </RouterLink>
          </header>

          <div v-if="!clans.length" class="empty">
            <div class="empty__icon">Ø</div>
            <strong>Кланов пока нет</strong>
            <span>Здесь появится рейтинг кланов</span>
          </div>

          <template v-else>
            <!-- CLAN PODIUM -->
            <div class="podium podium--clans">
              <!-- SECOND -->
              <RouterLink
                  v-if="topClans[1]"
                  :to="clanLink(topClans[1])"
                  class="clan-podium clan-podium--second"
                  :style="{
                  '--clan-color': clanColor(topClans[1]),
                }"
              >
                <div class="clan-podium__glow" />

                <div class="clan-podium__rank">
                  02
                </div>

                <div class="clan-podium__avatar">
                  <img
                      v-if="topClans[1].avatar_url"
                      :src="topClans[1].avatar_url"
                      :alt="topClans[1].name"
                  />

                  <span v-else>
                    {{ clanInitial(topClans[1]) }}
                  </span>
                </div>

                <div class="clan-podium__tag">
                  [{{ topClans[1].tag }}]
                </div>

                <h3>{{ topClans[1].name }}</h3>

                <div class="clan-podium__stats">
                  <div>
                    <strong>{{ topClans[1].power }}</strong>
                    <span>СИЛА</span>
                  </div>

                  <div>
                    <strong>{{ topClans[1].wins }}</strong>
                    <span>ПОБЕДЫ</span>
                  </div>
                </div>

                <div class="clan-podium__base">
                  2ND PLACE
                </div>
              </RouterLink>

              <!-- FIRST -->
              <RouterLink
                  v-if="topClans[0]"
                  :to="clanLink(topClans[0])"
                  class="clan-podium clan-podium--first"
                  :style="{
                  '--clan-color': clanColor(topClans[0]),
                }"
              >
                <div class="clan-podium__crown">
                  <svg
                      width="29"
                      height="29"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.5"
                  >
                    <path
                        d="M3 7l4 5 5-7 5 7 4-5v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"
                    />
                  </svg>
                </div>

                <div class="clan-podium__glow" />

                <div class="clan-podium__rank">
                  01
                </div>

                <div class="clan-podium__avatar">
                  <div class="clan-podium__avatar-ring" />

                  <img
                      v-if="topClans[0].avatar_url"
                      :src="topClans[0].avatar_url"
                      :alt="topClans[0].name"
                  />

                  <span v-else>
                    {{ clanInitial(topClans[0]) }}
                  </span>
                </div>

                <div class="clan-podium__tag">
                  [{{ topClans[0].tag }}]
                </div>

                <h3>{{ topClans[0].name }}</h3>

                <div class="clan-podium__stats">
                  <div>
                    <strong>{{ topClans[0].power }}</strong>
                    <span>POWER</span>
                  </div>

                  <div>
                    <strong>{{ topClans[0].wins }}</strong>
                    <span>WINS</span>
                  </div>
                </div>

                <div class="clan-podium__base">
                  APEX CLAN
                </div>
              </RouterLink>

              <!-- THIRD -->
              <RouterLink
                  v-if="topClans[2]"
                  :to="clanLink(topClans[2])"
                  class="clan-podium clan-podium--third"
                  :style="{
                  '--clan-color': clanColor(topClans[2]),
                }"
              >
                <div class="clan-podium__glow" />

                <div class="clan-podium__rank">
                  03
                </div>

                <div class="clan-podium__avatar">
                  <img
                      v-if="topClans[2].avatar_url"
                      :src="topClans[2].avatar_url"
                      :alt="topClans[2].name"
                  />

                  <span v-else>
                    {{ clanInitial(topClans[2]) }}
                  </span>
                </div>

                <div class="clan-podium__tag">
                  [{{ topClans[2].tag }}]
                </div>

                <h3>{{ topClans[2].name }}</h3>

                <div class="clan-podium__stats">
                  <div>
                    <strong>{{ topClans[2].power }}</strong>
                    <span>POWER</span>
                  </div>

                  <div>
                    <strong>{{ topClans[2].wins }}</strong>
                    <span>WINS</span>
                  </div>
                </div>

                <div class="clan-podium__base">
                  3RD PLACE
                </div>
              </RouterLink>
            </div>

            <!-- REST CLANS -->
            <div
                v-if="restClans.length"
                class="clans-list"
            >
              <RouterLink
                  v-for="(clan, index) in restClans"
                  :key="clan.id"
                  :to="clanLink(clan)"
                  class="clan-row"
                  :class="{
                  'clan-row--highlighted': clan.is_highlighted,
                }"
                  :style="{
                  '--row-clan-color': clanColor(clan),
                }"
              >
                <div class="clan-row__position">
                  {{ String(index + 4).padStart(2, '0') }}
                </div>

                <div class="clan-row__avatar">
                  <img
                      v-if="clan.avatar_url"
                      :src="clan.avatar_url"
                      :alt="clan.name"
                  />

                  <span v-else>
                    {{ clanInitial(clan) }}
                  </span>
                </div>

                <div class="clan-row__identity">
                  <div class="clan-row__name">
                    <span>[{{ clan.tag }}]</span>
                    {{ clan.name }}

                    <svg
                        v-if="clan.is_highlighted"
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                    >
                      <path
                          d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z"
                      />
                    </svg>
                  </div>

                  <div class="clan-row__meta">
                    <span>
                      {{ clan.members_count ?? 0 }} участников
                    </span>

                    <i />

                    <span>
                      Лидер:
                      {{ clan.leader?.username || '—' }}
                    </span>
                  </div>
                </div>

                <div class="clan-row__metric">
                  <strong>{{ clan.power ?? 0 }}</strong>
                  <span>POWER</span>
                </div>

                <div class="clan-row__metric clan-row__metric--win">
                  <strong>{{ clan.wins ?? 0 }}</strong>
                  <span>WINS</span>
                </div>

                <div class="clan-row__metric clan-row__metric--loss">
                  <strong>{{ clan.losses ?? 0 }}</strong>
                  <span>LOSS</span>
                </div>

                <svg
                    class="clan-row__arrow"
                    width="15"
                    height="15"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                  <path
                      d="M5 12h14M13 5l7 7-7 7"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  />
                </svg>
              </RouterLink>
            </div>
          </template>
        </section>
      </div>

      <!-- =========================================================
           FOOTER
      ========================================================== -->
      <footer
          v-if="socialsList.length || footer.footer_text"
          class="home-footer"
      >
        <div class="home-footer__top">
          <div class="home-footer__brand">
            <span class="home-footer__logo">
              A
            </span>

            <div>
              <strong>APEX TIERS</strong>
              <span>MINECRAFT PVP RANKING</span>
            </div>
          </div>

          <div
              v-if="socialsList.length"
              class="footer-socials"
          >
            <a
                v-for="social in socialsList"
                :key="social.key"
                :href="social.url"
                target="_blank"
                rel="noopener noreferrer"
                class="social-link"
                :class="social.key"
            >
              <span>{{ social.label }}</span>

              <svg
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M7 17L17 7M7 7h10v10"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
              </svg>
            </a>
          </div>
        </div>

        <div class="home-footer__bottom">
          <span>
            {{ footer.footer_text || 'APEX TIERS — Minecraft PvP Ranking' }}
          </span>

          <span>
            © {{ new Date().getFullYear() }} APEX TIERS
          </span>
        </div>
      </footer>
    </main>
  </div>
</template>

<style scoped>
@import "@/views/players/HomeView.css";
</style>
