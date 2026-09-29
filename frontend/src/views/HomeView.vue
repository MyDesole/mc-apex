<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { homeApi } from '@/services/home.js'
import UserName from '@/components/UserName.vue'

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
                  :to="`/players/${topPlayers[1].id}`"
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
                  :to="`/players/${topPlayers[0].id}`"
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
                  :to="`/players/${topPlayers[2].id}`"
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
                  :to="`/players/${player.id}`"
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
                  :to="`/clans/${topClans[1].id}`"
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
                  :to="`/clans/${topClans[0].id}`"
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
                  :to="`/clans/${topClans[2].id}`"
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
                  :to="`/clans/${clan.id}`"
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
/* ================================================================
   BASE
================================================================ */

.home {
  --page-bg: #07070b;
  --card: rgba(16, 16, 23, 0.78);
  --card-solid: #101017;
  --card-hover: rgba(24, 24, 34, 0.94);

  --line: rgba(255, 255, 255, 0.075);
  --line-strong: rgba(255, 255, 255, 0.13);

  --text: #f7f7fb;
  --text-soft: #b7b7c8;
  --text-dim: #77778b;

  --purple: #8b5cf6;
  --purple-light: #b794ff;
  --cyan: #22d3ee;
  --gold: #facc15;
  --green: #4ade80;
  --red: #f87171;

  position: relative;
  min-height: 100vh;
  color: var(--text);
  background: var(--page-bg);
  overflow: hidden;
}

.home *,
.home *::before,
.home *::after {
  box-sizing: border-box;
}

.home a {
  color: inherit;
  text-decoration: none;
}

/* ================================================================
   BACKGROUND
================================================================ */

.home-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
  background:
      radial-gradient(
          circle at 50% -10%,
          rgba(124, 58, 237, 0.13),
          transparent 34%
      ),
      radial-gradient(
          circle at 100% 55%,
          rgba(6, 182, 212, 0.045),
          transparent 30%
      ),
      #07070b;
}

.home-bg__orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(100px);
  opacity: 0.12;
}

.home-bg__orb--one {
  width: 500px;
  height: 500px;
  top: -250px;
  left: 15%;
  background: #7c3aed;
}

.home-bg__orb--two {
  width: 420px;
  height: 420px;
  top: 38%;
  right: -260px;
  background: #0891b2;
}

.home-bg__orb--three {
  width: 380px;
  height: 380px;
  bottom: -240px;
  left: -180px;
  background: #6d28d9;
}

.home-bg__grid {
  position: absolute;
  inset: 0;
  opacity: 0.25;
  background-image:
      linear-gradient(
          rgba(255, 255, 255, 0.025) 1px,
          transparent 1px
      ),
      linear-gradient(
          90deg,
          rgba(255, 255, 255, 0.025) 1px,
          transparent 1px
      );
  background-size: 48px 48px;
  mask-image: linear-gradient(
      to bottom,
      black,
      transparent 75%
  );
  -webkit-mask-image: linear-gradient(
      to bottom,
      black,
      transparent 75%
  );
}

.home-bg__noise {
  position: absolute;
  inset: 0;
  opacity: 0.025;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 160 160' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.8'/%3E%3C/svg%3E");
}

.home-content {
  position: relative;
  z-index: 1;
  width: min(1160px, calc(100% - 40px));
  margin: 0 auto;
  padding: 34px 0 60px;
}

/* ================================================================
   HERO
================================================================ */

.hero {
  position: relative;
  min-height: 530px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 28px;
  padding: 90px 30px 82px;
  border: 1px solid var(--line);
  border-radius: 28px;
  overflow: hidden;
  background:
      linear-gradient(
          180deg,
          rgba(18, 17, 28, 0.9),
          rgba(8, 8, 13, 0.97)
      );
  box-shadow:
      0 40px 100px rgba(0, 0, 0, 0.38),
      inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
      linear-gradient(
          115deg,
          transparent 0 25%,
          rgba(139, 92, 246, 0.035) 25.2%,
          transparent 25.5% 70%,
          rgba(34, 211, 238, 0.025) 70.2%,
          transparent 70.5%
      );
  pointer-events: none;
}

.hero::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 160px;
  background: linear-gradient(
      to top,
      rgba(124, 58, 237, 0.07),
      transparent
  );
  pointer-events: none;
}

.hero__content {
  position: relative;
  z-index: 5;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.hero__aurora {
  position: absolute;
  width: 620px;
  height: 620px;
  top: -390px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 50%;
  background: radial-gradient(
      circle,
      rgba(139, 92, 246, 0.32),
      rgba(124, 58, 237, 0.08) 35%,
      transparent 68%
  );
  filter: blur(15px);
  pointer-events: none;
}

.hero__ring {
  position: absolute;
  border: 1px solid rgba(167, 139, 250, 0.07);
  border-radius: 50%;
  left: 50%;
  top: 50%;
  pointer-events: none;
}

.hero__ring--one {
  width: 520px;
  height: 520px;
  transform: translate(-50%, -50%);
}

.hero__ring--two {
  width: 760px;
  height: 760px;
  transform: translate(-50%, -50%);
  border-color: rgba(34, 211, 238, 0.035);
}

.hero__eyebrow {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  color: #85859a;
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 3px;
}

.hero__eyebrow span {
  width: 26px;
  height: 1px;
  background: rgba(167, 139, 250, 0.5);
}

.hero h1 {
  display: flex;
  flex-direction: column;
  margin: 0;
  line-height: 0.82;
  letter-spacing: -7px;
  text-transform: uppercase;
}

.hero__title-main {
  font-size: clamp(70px, 10vw, 125px);
  font-weight: 1000;
  color: #fff;
  text-shadow:
      0 10px 50px rgba(255, 255, 255, 0.06);
}

.hero__title-sub {
  margin-left: 30px;
  font-size: clamp(42px, 6vw, 76px);
  font-weight: 1000;
  letter-spacing: 12px;
  background: linear-gradient(
      100deg,
      #8b5cf6,
      #c4b5fd 45%,
      #7c3aed
  );
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  filter: drop-shadow(
      0 10px 30px rgba(124, 58, 237, 0.3)
  );
}

.hero__subtitle {
  max-width: 500px;
  margin: 28px 0 30px;
  color: var(--text-soft);
  font-size: 14px;
  line-height: 1.6;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 7px 12px;
  border: 1px solid rgba(139, 92, 246, 0.22);
  border-radius: 999px;
  color: #c4b5fd;
  background: rgba(124, 58, 237, 0.08);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.hero-badge__pulse {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #a78bfa;
  box-shadow: 0 0 12px #8b5cf6;
  animation: heroPulse 2s infinite;
}

.hero-badge__line {
  width: 1px;
  height: 12px;
  background: rgba(255, 255, 255, 0.15);
}

.hero-badge__live {
  color: #4ade80;
  font-size: 8px;
  letter-spacing: 1.5px;
}

.hero-actions {
  display: flex;
  gap: 10px;
}

.hero-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 900;
  transition:
      transform 0.25s ease,
      border-color 0.25s ease,
      box-shadow 0.25s ease,
      background 0.25s ease;
  overflow: hidden;
}

.hero-btn--primary {
  color: #fff;
  background: linear-gradient(
      135deg,
      #8b5cf6,
      #6d28d9
  );
  box-shadow:
      0 12px 30px rgba(124, 58, 237, 0.28),
      inset 0 1px rgba(255, 255, 255, 0.18);
}

.hero-btn--primary:hover {
  transform: translateY(-3px);
  box-shadow:
      0 18px 38px rgba(124, 58, 237, 0.4),
      inset 0 1px rgba(255, 255, 255, 0.22);
}

.hero-btn--ghost {
  color: #ddddea;
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid var(--line-strong);
}

.hero-btn--ghost:hover {
  transform: translateY(-3px);
  background: rgba(255, 255, 255, 0.06);
  border-color: rgba(167, 139, 250, 0.3);
}

.hero-btn__shine {
  position: absolute;
  width: 100px;
  height: 160px;
  top: -60px;
  left: -140px;
  transform: rotate(25deg);
  background: rgba(255, 255, 255, 0.16);
  filter: blur(12px);
  animation: buttonShine 4s infinite;
}

.hero-btn__arrow {
  opacity: 0.65;
}

.hero__side {
  position: absolute;
  z-index: 4;
  top: 50%;
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(255, 255, 255, 0.2);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 2px;
}

.hero__side i {
  width: 24px;
  height: 1px;
  background: rgba(255, 255, 255, 0.15);
}

.hero__side--left {
  left: 20px;
  transform: rotate(-90deg) translateX(-50%);
  transform-origin: left center;
}

.hero__side--right {
  right: 20px;
  transform: rotate(90deg) translateX(50%);
  transform-origin: right center;
}

.hero__bottom {
  position: absolute;
  z-index: 6;
  bottom: 17px;
  left: 24px;
  right: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #5e5e70;
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 1.5px;
}

.hero__bottom > div {
  display: flex;
  align-items: center;
  gap: 7px;
}

.hero__bottom-center {
  color: #6e6e81;
}

.hero__bottom-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #4ade80;
  box-shadow: 0 0 9px rgba(74, 222, 128, 0.8);
}

.hero__bottom-accent {
  color: #a78bfa;
}

/* ================================================================
   STATS
================================================================ */

.stats {
  margin-bottom: 62px;
}

.stats__heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  padding: 0 5px;
  color: #555568;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 2px;
}

.stats__heading-line {
  width: 20px;
  height: 1px;
  background: #3b3b4b;
}

.stats__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.stat-card {
  position: relative;
  min-height: 92px;
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 17px;
  border: 1px solid var(--line);
  border-radius: 15px;
  overflow: hidden;
  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.045),
          rgba(255, 255, 255, 0.012)
      );
  backdrop-filter: blur(12px);
  transition:
      transform 0.25s ease,
      border-color 0.25s ease,
      background 0.25s ease;
}

.stat-card:hover {
  transform: translateY(-3px);
  border-color: var(--line-strong);
  background: rgba(255, 255, 255, 0.05);
}

.stat-card::after {
  content: '';
  position: absolute;
  width: 80px;
  height: 80px;
  right: -40px;
  bottom: -45px;
  border-radius: 50%;
  background: rgba(139, 92, 246, 0.08);
  filter: blur(20px);
}

.stat-card__icon {
  position: relative;
  z-index: 1;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
}

.stat-card__icon--purple {
  color: #b794ff;
  background: rgba(139, 92, 246, 0.12);
  border: 1px solid rgba(139, 92, 246, 0.15);
}

.stat-card__icon--cyan {
  color: #67e8f9;
  background: rgba(34, 211, 238, 0.09);
  border: 1px solid rgba(34, 211, 238, 0.13);
}

.stat-card__icon--gold {
  color: #fde68a;
  background: rgba(250, 204, 21, 0.08);
  border: 1px solid rgba(250, 204, 21, 0.13);
}

.stat-card__icon--green {
  color: #86efac;
  background: rgba(74, 222, 128, 0.08);
  border: 1px solid rgba(74, 222, 128, 0.13);
}

.stat-card__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-card__content strong {
  font-size: 22px;
  font-weight: 950;
  letter-spacing: -1px;
}

.stat-card__content span {
  color: var(--text-dim);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.stat-card__number {
  position: absolute;
  right: 12px;
  top: 8px;
  color: rgba(255, 255, 255, 0.035);
  font-size: 30px;
  font-weight: 1000;
}

/* ================================================================
   SECTION HEAD
================================================================ */

.sections {
  display: flex;
  flex-direction: column;
  gap: 72px;
}

.section {
  min-width: 0;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 19px;
}

.section-head__title {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.section-head__mark {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
}

.section-head__mark--news {
  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.11);
  border: 1px solid rgba(139, 92, 246, 0.17);
}

.section-head__mark--player {
  color: #a5b4fc;
  background: rgba(99, 102, 241, 0.11);
  border: 1px solid rgba(99, 102, 241, 0.17);
}

.section-head__mark--clan {
  color: #67e8f9;
  background: rgba(34, 211, 238, 0.08);
  border: 1px solid rgba(34, 211, 238, 0.15);
}

.section-head__eyebrow {
  margin-bottom: 2px;
  color: #656579;
  font-size: 7px;
  font-weight: 900;
  letter-spacing: 2px;
}

.section-head h2 {
  margin: 0;
  font-size: 21px;
  font-weight: 900;
  letter-spacing: -0.7px;
}

.section-count {
  padding: 5px 9px;
  border: 1px solid var(--line);
  border-radius: 999px;
  color: #77778a;
  background: rgba(255, 255, 255, 0.025);
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1px;
}

.section-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 11px;
  color: #a78bfa;
  border: 1px solid transparent;
  border-radius: 9px;
  font-size: 10px;
  font-weight: 800;
  transition:
      background 0.2s ease,
      border-color 0.2s ease,
      gap 0.2s ease;
}

.section-link:hover {
  gap: 10px;
  background: rgba(139, 92, 246, 0.06);
  border-color: rgba(139, 92, 246, 0.12);
}

/* ================================================================
   NEWS
================================================================ */

.news-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.news-card {
  position: relative;
  min-width: 0;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--line);
  border-radius: 17px;
  overflow: hidden;
  background: rgba(14, 14, 20, 0.82);
  transition:
      transform 0.3s ease,
      border-color 0.3s ease,
      box-shadow 0.3s ease;
}

.news-card:hover {
  transform: translateY(-5px);
  border-color: rgba(167, 139, 250, 0.25);
  box-shadow:
      0 20px 50px rgba(0, 0, 0, 0.32),
      0 0 40px rgba(124, 58, 237, 0.055);
}

.news-card--featured {
  grid-column: span 2;
}

.news-card--pinned {
  border-color: rgba(250, 204, 21, 0.18);
}

.news-card__cover {
  position: relative;
  height: 165px;
  background:
      radial-gradient(
          circle at 70% 20%,
          rgba(139, 92, 246, 0.6),
          transparent 45%
      ),
      linear-gradient(
          135deg,
          #1e1640,
          #101827
      );
  background-size: cover;
  background-position: center;
  overflow: hidden;
}

.news-card--featured .news-card__cover {
  height: 220px;
}

.news-card__cover::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
      linear-gradient(
          120deg,
          rgba(124, 58, 237, 0.18),
          transparent 35%
      ),
      linear-gradient(
          to top,
          rgba(6, 6, 10, 0.78),
          transparent 55%
      );
}

.news-card__cover-shade {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
      radial-gradient(
          circle at 20% 20%,
          rgba(255, 255, 255, 0.08),
          transparent 20%
      );
}

.news-card__fallback {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.news-card__fallback span {
  position: relative;
  z-index: 1;
  color: rgba(255, 255, 255, 0.12);
  font-size: 90px;
  font-weight: 1000;
}

.news-card__fallback div {
  position: absolute;
  width: 180px;
  height: 180px;
  border: 1px solid rgba(167, 139, 250, 0.12);
  border-radius: 50%;
  box-shadow:
      0 0 80px rgba(124, 58, 237, 0.18),
      inset 0 0 50px rgba(124, 58, 237, 0.05);
}

.news-card__top {
  position: absolute;
  z-index: 4;
  top: 11px;
  left: 11px;
  right: 11px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}

.news-card__pin,
.news-card__type {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 7px;
  background: rgba(6, 6, 10, 0.58);
  backdrop-filter: blur(10px);
  font-size: 7px;
  font-weight: 950;
  letter-spacing: 1px;
}

.news-card__pin {
  color: #fde68a;
}

.news-card__type {
  color: #c4b5fd;
}

.news-card__type--update {
  color: #86efac;
}

.news-card__type--event {
  color: #f9a8d4;
}

.news-card__type--announcement {
  color: #fde68a;
}

.news-card__index {
  position: absolute;
  z-index: 3;
  right: 13px;
  bottom: 10px;
  color: rgba(255, 255, 255, 0.16);
  font-size: 30px;
  font-weight: 1000;
  letter-spacing: -2px;
}

.news-card__body {
  min-height: 142px;
  display: flex;
  flex-direction: column;
  padding: 14px 16px 15px;
}

.news-card__meta {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 8px;
  color: #656577;
  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.news-card__meta i {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #7c3aed;
}

.news-card h3 {
  margin: 0;
  color: #f4f4f8;
  font-size: 15px;
  font-weight: 850;
  line-height: 1.3;
  letter-spacing: -0.2px;
}

.news-card--featured h3 {
  font-size: 18px;
}

.news-card p {
  display: -webkit-box;
  margin: 7px 0 0;
  overflow: hidden;
  color: #808093;
  font-size: 11px;
  line-height: 1.55;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.news-card__read {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: auto;
  padding-top: 14px;
  color: #a78bfa;
  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

/* ================================================================
   PODIUM SHARED
================================================================ */

.podium {
  display: grid;
  grid-template-columns: 1fr 1.12fr 1fr;
  align-items: end;
  gap: 10px;
}

.podium-card {
  position: relative;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 310px;
  padding: 22px 15px 0;
  border: 1px solid var(--line);
  border-radius: 18px 18px 13px 13px;
  overflow: hidden;
  background:
      linear-gradient(
          180deg,
          rgba(255, 255, 255, 0.035),
          rgba(255, 255, 255, 0.008)
      );
  transition:
      transform 0.3s ease,
      border-color 0.3s ease,
      box-shadow 0.3s ease;
}

.podium-card:hover {
  transform: translateY(-6px);
}

.podium-card__ambient {
  position: absolute;
  width: 230px;
  height: 180px;
  top: -90px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 50%;
  filter: blur(30px);
  opacity: 0.12;
  pointer-events: none;
}

.podium-rank {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45px;
  height: 22px;
  border-radius: 999px;
  font-size: 8px;
  font-weight: 950;
  letter-spacing: 1px;
}

.podium-avatar {
  position: relative;
  z-index: 2;
  width: 78px;
  height: 78px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 18px;
  border-radius: 50%;
  overflow: hidden;
  background: #181820;
  color: #fff;
  font-size: 28px;
  font-weight: 1000;
}

.podium-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.podium-name {
  position: relative;
  z-index: 2;
  max-width: 100%;
  margin-top: 13px;
  overflow: hidden;
  color: #fff;
  font-size: 14px;
  font-weight: 850;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.podium-tier {
  position: relative;
  z-index: 2;
  margin-top: 6px;
  font-size: 27px;
  font-weight: 1000;
  letter-spacing: -1px;
  filter: drop-shadow(0 4px 12px currentColor);
}

.podium-score {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: baseline;
  gap: 2px;
  margin-top: 2px;
}

.podium-score strong {
  color: #fff;
  font-size: 18px;
  font-weight: 950;
}

.podium-score span {
  color: #707084;
  font-size: 9px;
  font-weight: 800;
}

.podium-base {
  position: relative;
  z-index: 2;
  width: calc(100% + 30px);
  margin-top: auto;
  padding: 11px;
  border-top: 1px solid rgba(255, 255, 255, 0.055);
  background: rgba(255, 255, 255, 0.018);
  color: #676778;
  font-size: 7px;
  font-weight: 950;
  letter-spacing: 1.5px;
  text-align: center;
}

/* ================================================================
   PLAYER PODIUM
================================================================ */

.podium-card--first {
  min-height: 380px;
  padding-top: 35px;
  border-color: rgba(250, 204, 21, 0.28);
  background:
      radial-gradient(
          circle at 50% 0%,
          rgba(250, 204, 21, 0.13),
          transparent 48%
      ),
      linear-gradient(
          180deg,
          rgba(250, 204, 21, 0.055),
          rgba(250, 204, 21, 0.008)
      );
  box-shadow:
      0 25px 70px rgba(0, 0, 0, 0.25),
      0 0 60px rgba(250, 204, 21, 0.045);
}

.podium-card--first:hover {
  border-color: rgba(250, 204, 21, 0.48);
  box-shadow:
      0 32px 80px rgba(0, 0, 0, 0.3),
      0 0 70px rgba(250, 204, 21, 0.08);
}

.podium-card--first .podium-card__ambient {
  background: #facc15;
}

.podium-card--first .podium-rank {
  color: #422006;
  background: linear-gradient(
      135deg,
      #fde68a,
      #f59e0b
  );
  box-shadow: 0 5px 20px rgba(250, 204, 21, 0.28);
}

.podium-card--first .podium-avatar {
  width: 98px;
  height: 98px;
  border: 2px solid rgba(250, 204, 21, 0.5);
  box-shadow:
      0 12px 40px rgba(250, 204, 21, 0.18),
      0 0 0 6px rgba(250, 204, 21, 0.045);
}

.podium-card--first .podium-card__stars {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.podium-card__stars i {
  position: absolute;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #fde68a;
  box-shadow: 0 0 9px #facc15;
  animation: starFloat 3s infinite ease-in-out;
}

.podium-card__stars i:nth-child(1) {
  top: 27%;
  left: 17%;
}

.podium-card__stars i:nth-child(2) {
  top: 42%;
  right: 13%;
  animation-delay: 0.8s;
}

.podium-card__stars i:nth-child(3) {
  top: 17%;
  right: 23%;
  animation-delay: 1.4s;
}

.podium-card__stars i:nth-child(4) {
  top: 32%;
  left: 28%;
  animation-delay: 2s;
}

.podium-card__crown {
  position: absolute;
  z-index: 4;
  top: 5px;
  left: 50%;
  transform: translateX(-50%);
  color: #facc15;
  filter: drop-shadow(
      0 0 12px rgba(250, 204, 21, 0.65)
  );
  animation: crownFloat 3s ease-in-out infinite;
}

.podium-card--first .podium-base {
  color: #facc15;
  background: rgba(250, 204, 21, 0.035);
}

.podium-card--second {
  min-height: 315px;
  border-color: rgba(203, 213, 225, 0.16);
}

.podium-card--second .podium-card__ambient {
  background: #cbd5e1;
}

.podium-card--second .podium-rank {
  color: #1e293b;
  background: linear-gradient(
      135deg,
      #f1f5f9,
      #94a3b8
  );
}

.podium-card--second .podium-avatar {
  border: 2px solid rgba(203, 213, 225, 0.27);
}

.podium-card--second .podium-base {
  color: #aab4c3;
}

.podium-card--third {
  min-height: 290px;
  border-color: rgba(217, 119, 6, 0.17);
}

.podium-card--third .podium-card__ambient {
  background: #d97706;
}

.podium-card--third .podium-rank {
  color: #2a1006;
  background: linear-gradient(
      135deg,
      #fdba74,
      #b45309
  );
}

.podium-card--third .podium-avatar {
  border: 2px solid rgba(217, 119, 6, 0.3);
}

.podium-card--third .podium-base {
  color: #c68143;
}

/* ================================================================
   RANK LIST
================================================================ */

.rank-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 9px;
}

.rank-row {
  position: relative;
  display: grid;
  grid-template-columns: 38px 43px minmax(0, 1fr) 42px 60px 16px;
  align-items: center;
  gap: 12px;
  min-height: 67px;
  padding: 9px 15px;
  border: 1px solid var(--line);
  border-radius: 13px;
  overflow: hidden;
  background: rgba(14, 14, 20, 0.72);
  transition:
      transform 0.22s ease,
      background 0.22s ease,
      border-color 0.22s ease;
}

.rank-row::before {
  content: '';
  position: absolute;
  left: 0;
  top: 13px;
  bottom: 13px;
  width: 2px;
  border-radius: 999px;
  background: #8b5cf6;
  opacity: 0;
  transition: opacity 0.2s;
}

.rank-row:hover {
  transform: translateX(4px);
  background: rgba(25, 25, 34, 0.9);
  border-color: rgba(139, 92, 246, 0.19);
}

.rank-row:hover::before {
  opacity: 1;
}

.rank-row__position {
  color: #5d5d6e;
  font-size: 11px;
  font-weight: 950;
  text-align: center;
}

.rank-row__avatar {
  width: 42px;
  height: 42px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(139, 92, 246, 0.16);
  border-radius: 11px;
  overflow: hidden;
  color: #fff;
  background:
      linear-gradient(
          135deg,
          #7c3aed,
          #312e81
      );
  font-size: 15px;
  font-weight: 950;
}

.rank-row__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rank-row__identity {
  min-width: 0;
}

.rank-row__identity strong {
  display: block;
  overflow: hidden;
  color: #e9e9f0;
  font-size: 12px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rank-row__progress {
  width: 100%;
  height: 3px;
  margin-top: 7px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.05);
}

.rank-row__progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  box-shadow: 0 0 9px currentColor;
  opacity: 0.8;
}

.rank-row__tier {
  font-size: 19px;
  font-weight: 1000;
  text-align: center;
}

.rank-row__score {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 2px;
}

.rank-row__score strong {
  font-size: 13px;
  font-weight: 950;
}

.rank-row__score span {
  color: #626274;
  font-size: 8px;
  font-weight: 800;
}

.rank-row__arrow {
  color: #4e4e5d;
  transition:
      color 0.2s ease,
      transform 0.2s ease;
}

.rank-row:hover .rank-row__arrow {
  color: #a78bfa;
  transform: translateX(2px);
}

/* ================================================================
   CLAN PODIUM
================================================================ */

.podium--clans {
  align-items: stretch;
}

.clan-podium {
  position: relative;
  min-height: 330px;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px 15px 0;
  overflow: hidden;
  border: 1px solid color-mix(
      in srgb,
      var(--clan-color) 20%,
      transparent
  );
  border-radius: 18px 18px 13px 13px;
  background:
      radial-gradient(
          circle at 50% 0%,
          color-mix(
              in srgb,
              var(--clan-color) 12%,
              transparent
          ),
          transparent 48%
      ),
      rgba(14, 14, 20, 0.82);
  transition:
      transform 0.3s ease,
      border-color 0.3s ease,
      box-shadow 0.3s ease;
}

.clan-podium:hover {
  transform: translateY(-6px);
  border-color: color-mix(
      in srgb,
      var(--clan-color) 42%,
      transparent
  );
  box-shadow:
      0 25px 60px rgba(0, 0, 0, 0.3),
      0 0 50px color-mix(
          in srgb,
          var(--clan-color) 7%,
          transparent
      );
}

.clan-podium__glow {
  position: absolute;
  width: 200px;
  height: 160px;
  top: -80px;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 50%;
  background: var(--clan-color);
  filter: blur(35px);
  opacity: 0.13;
}

.clan-podium__rank {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 43px;
  height: 21px;
  border: 1px solid color-mix(
      in srgb,
      var(--clan-color) 25%,
      transparent
  );
  border-radius: 999px;
  color: color-mix(
      in srgb,
      var(--clan-color) 80%,
      white
  );
  background: color-mix(
      in srgb,
      var(--clan-color) 7%,
      transparent
  );
  font-size: 8px;
  font-weight: 950;
  letter-spacing: 1px;
}

.clan-podium__avatar {
  position: relative;
  z-index: 2;
  width: 76px;
  height: 76px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 18px;
  overflow: hidden;
  border: 2px solid color-mix(
      in srgb,
      var(--clan-color) 35%,
      transparent
  );
  border-radius: 18px;
  color: #fff;
  background: var(--clan-color);
  box-shadow:
      0 12px 30px color-mix(
          in srgb,
          var(--clan-color) 18%,
          transparent
      );
  font-size: 27px;
  font-weight: 1000;
}

.clan-podium__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.clan-podium__avatar-ring {
  position: absolute;
  inset: -7px;
  border: 1px solid color-mix(
      in srgb,
      var(--clan-color) 20%,
      transparent
  );
  border-radius: 23px;
  pointer-events: none;
}

.clan-podium__tag {
  position: relative;
  z-index: 2;
  margin-top: 14px;
  color: color-mix(
      in srgb,
      var(--clan-color) 80%,
      white
  );
  font-size: 9px;
  font-weight: 950;
  letter-spacing: 1px;
}

.clan-podium h3 {
  position: relative;
  z-index: 2;
  max-width: 100%;
  margin: 4px 0 0;
  overflow: hidden;
  color: #f4f4f8;
  font-size: 15px;
  font-weight: 900;
  text-align: center;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-podium__stats {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 25px;
  margin-top: 18px;
}

.clan-podium__stats div {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.clan-podium__stats strong {
  color: #f3f3f8;
  font-size: 15px;
  font-weight: 950;
}

.clan-podium__stats span {
  color: #5e5e6f;
  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

.clan-podium__base {
  position: relative;
  z-index: 2;
  width: calc(100% + 30px);
  margin-top: auto;
  padding: 11px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  color: color-mix(
      in srgb,
      var(--clan-color) 65%,
      #777
  );
  background: rgba(255, 255, 255, 0.018);
  font-size: 7px;
  font-weight: 950;
  letter-spacing: 1.5px;
  text-align: center;
}

.clan-podium--first {
  min-height: 375px;
  border-color: color-mix(
      in srgb,
      var(--clan-color) 38%,
      transparent
  );
  box-shadow:
      0 25px 70px rgba(0, 0, 0, 0.28),
      0 0 60px color-mix(
          in srgb,
          var(--clan-color) 7%,
          transparent
      );
}

.clan-podium--first .clan-podium__avatar {
  width: 96px;
  height: 96px;
  border-radius: 22px;
  box-shadow:
      0 15px 40px color-mix(
          in srgb,
          var(--clan-color) 22%,
          transparent
      ),
      0 0 0 5px color-mix(
          in srgb,
          var(--clan-color) 5%,
          transparent
      );
}

.clan-podium__crown {
  position: absolute;
  z-index: 4;
  top: 3px;
  left: 50%;
  transform: translateX(-50%);
  color: var(--clan-color);
  filter: drop-shadow(
      0 0 12px color-mix(
          in srgb,
          var(--clan-color) 60%,
          transparent
      )
  );
  animation: crownFloat 3s ease-in-out infinite;
}

/* ================================================================
   CLAN LIST
================================================================ */

.clans-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 9px;
}

.clan-row {
  position: relative;
  display: grid;
  grid-template-columns: 38px 45px minmax(0, 1fr) 58px 58px 58px 16px;
  align-items: center;
  gap: 12px;
  min-height: 69px;
  padding: 9px 15px;
  border: 1px solid var(--line);
  border-radius: 13px;
  overflow: hidden;
  background: rgba(14, 14, 20, 0.72);
  transition:
      transform 0.22s ease,
      border-color 0.22s ease,
      background 0.22s ease;
}

.clan-row::before {
  content: '';
  position: absolute;
  left: 0;
  top: 13px;
  bottom: 13px;
  width: 2px;
  border-radius: 999px;
  background: var(--row-clan-color);
  opacity: 0;
  transition: opacity 0.2s;
}

.clan-row:hover {
  transform: translateX(4px);
  border-color: color-mix(
      in srgb,
      var(--row-clan-color) 25%,
      transparent
  );
  background: rgba(25, 25, 34, 0.9);
}

.clan-row:hover::before {
  opacity: 1;
}

.clan-row--highlighted {
  border-color: rgba(250, 204, 21, 0.11);
}

.clan-row__position {
  color: #5d5d6e;
  font-size: 11px;
  font-weight: 950;
  text-align: center;
}

.clan-row__avatar {
  width: 43px;
  height: 43px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid color-mix(
      in srgb,
      var(--row-clan-color) 25%,
      transparent
  );
  border-radius: 11px;
  color: #fff;
  background: var(--row-clan-color);
  font-size: 15px;
  font-weight: 950;
}

.clan-row__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.clan-row__identity {
  min-width: 0;
}

.clan-row__name {
  display: flex;
  align-items: center;
  gap: 5px;
  overflow: hidden;
  color: #e9e9f0;
  font-size: 12px;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-row__name > span {
  color: color-mix(
      in srgb,
      var(--row-clan-color) 85%,
      white
  );
}

.clan-row__name svg {
  flex: 0 0 auto;
  color: #facc15;
  filter: drop-shadow(
      0 0 5px rgba(250, 204, 21, 0.55)
  );
}

.clan-row__meta {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 5px;
  overflow: hidden;
  color: #606071;
  font-size: 8px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-row__meta i {
  width: 3px;
  height: 3px;
  flex: 0 0 3px;
  border-radius: 50%;
  background: #444451;
}

.clan-row__metric {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.clan-row__metric strong {
  color: #a78bfa;
  font-size: 12px;
  font-weight: 950;
}

.clan-row__metric span {
  color: #565666;
  font-size: 6px;
  font-weight: 900;
  letter-spacing: 1px;
}

.clan-row__metric--win strong {
  color: #4ade80;
}

.clan-row__metric--loss strong {
  color: #f87171;
}

.clan-row__arrow {
  color: #4e4e5d;
  transition:
      color 0.2s ease,
      transform 0.2s ease;
}

.clan-row:hover .clan-row__arrow {
  color: color-mix(
      in srgb,
      var(--row-clan-color) 80%,
      white
  );
  transform: translateX(2px);
}

/* ================================================================
   EMPTY
================================================================ */

.empty {
  min-height: 180px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border: 1px dashed var(--line-strong);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.012);
}

.empty__icon {
  margin-bottom: 4px;
  color: #525263;
  font-size: 28px;
  font-weight: 300;
}

.empty strong {
  color: #a2a2b2;
  font-size: 12px;
  font-weight: 850;
}

.empty span {
  color: #5e5e70;
  font-size: 9px;
}

/* ================================================================
   LOADING
================================================================ */

.loading {
  min-height: 350px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 18px;
}

.loading__ring {
  width: 44px;
  height: 44px;
  padding: 2px;
  border: 1px solid rgba(139, 92, 246, 0.15);
  border-radius: 50%;
  animation: spin 1.2s linear infinite;
}

.loading__ring span {
  display: block;
  width: 100%;
  height: 100%;
  border: 2px solid transparent;
  border-top-color: #a78bfa;
  border-radius: 50%;
}

.loading__text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.loading__text strong {
  color: #a78bfa;
  font-size: 9px;
  letter-spacing: 2px;
}

.loading__text span {
  color: #555566;
  font-size: 9px;
}

/* ================================================================
   FOOTER
================================================================ */

.home-footer {
  margin-top: 75px;
  padding-top: 26px;
  border-top: 1px solid var(--line);
}

.home-footer__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.home-footer__brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.home-footer__logo {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(139, 92, 246, 0.25);
  border-radius: 9px;
  color: #c4b5fd;
  background: rgba(139, 92, 246, 0.08);
  font-size: 15px;
  font-weight: 1000;
}

.home-footer__brand div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.home-footer__brand strong {
  font-size: 10px;
  font-weight: 950;
  letter-spacing: 1px;
}

.home-footer__brand span {
  color: #555566;
  font-size: 6px;
  font-weight: 900;
  letter-spacing: 1.2px;
}

.footer-socials {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 10px;
  border: 1px solid var(--line);
  border-radius: 8px;
  color: #777789;
  background: rgba(255, 255, 255, 0.02);
  font-size: 8px;
  font-weight: 800;
  transition:
      color 0.2s ease,
      border-color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.social-link:hover {
  transform: translateY(-2px);
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}

.social-link.social_discord:hover {
  border-color: rgba(88, 101, 242, 0.4);
  color: #818cf8;
}

.social-link.social_telegram:hover {
  border-color: rgba(34, 158, 217, 0.4);
  color: #38bdf8;
}

.social-link.social_youtube:hover {
  border-color: rgba(255, 0, 0, 0.4);
  color: #f87171;
}

.social-link.social_vk:hover {
  border-color: rgba(0, 119, 255, 0.4);
  color: #60a5fa;
}

.social-link.social_twitch:hover {
  border-color: rgba(145, 70, 255, 0.4);
  color: #a78bfa;
}

.social-link.social_twitter:hover {
  border-color: rgba(29, 161, 242, 0.4);
  color: #38bdf8;
}

.home-footer__bottom {
  display: flex;
  justify-content: space-between;
  gap: 15px;
  margin-top: 25px;
  padding-top: 15px;
  border-top: 1px solid rgba(255, 255, 255, 0.035);
  color: #4d4d5d;
  font-size: 7px;
  font-weight: 700;
}

/* ================================================================
   ANIMATIONS
================================================================ */

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes heroPulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }

  50% {
    opacity: 0.45;
    transform: scale(0.75);
  }
}

@keyframes buttonShine {
  0% {
    left: -140px;
  }

  20%,
  100% {
    left: calc(100% + 140px);
  }
}

@keyframes crownFloat {
  0%,
  100% {
    transform: translateX(-50%) translateY(0);
  }

  50% {
    transform: translateX(-50%) translateY(-4px);
  }
}

@keyframes starFloat {
  0%,
  100% {
    opacity: 0.35;
    transform: scale(0.8);
  }

  50% {
    opacity: 1;
    transform: scale(1.35);
  }
}

/* ================================================================
   RESPONSIVE
================================================================ */

@media (max-width: 900px) {
  .home-content {
    width: min(100% - 28px, 760px);
    padding-top: 20px;
  }

  .hero {
    min-height: 480px;
  }

  .stats__grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .news-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .news-card--featured {
    grid-column: span 2;
  }

  .podium {
    grid-template-columns: 1fr 1.08fr 1fr;
    gap: 7px;
  }

  .podium-card--first {
    min-height: 350px;
  }

  .podium-card--second {
    min-height: 292px;
  }

  .podium-card--third {
    min-height: 275px;
  }

  .clan-podium {
    min-height: 300px;
  }

  .clan-podium--first {
    min-height: 340px;
  }

  .rank-row {
    grid-template-columns: 32px 40px minmax(0, 1fr) 35px 55px 13px;
    gap: 9px;
  }

  .clan-row {
    grid-template-columns: 32px 40px minmax(0, 1fr) 48px 48px 48px 13px;
    gap: 8px;
  }
}

@media (max-width: 680px) {
  .home-content {
    width: calc(100% - 20px);
    padding: 12px 0 35px;
  }

  .hero {
    min-height: 470px;
    padding: 70px 15px 65px;
    border-radius: 21px;
  }

  .hero__side {
    display: none;
  }

  .hero__bottom {
    left: 15px;
    right: 15px;
  }

  .hero__bottom-center {
    display: none !important;
  }

  .hero__title-main {
    font-size: 66px;
  }

  .hero__title-sub {
    margin-left: 15px;
    font-size: 43px;
    letter-spacing: 7px;
  }

  .hero__subtitle {
    max-width: 310px;
    font-size: 12px;
  }

  .hero-actions {
    width: min(100%, 300px);
    flex-direction: column;
  }

  .hero-btn {
    width: 100%;
  }

  .stats {
    margin-bottom: 45px;
  }

  .stats__grid {
    gap: 6px;
  }

  .stat-card {
    min-height: 76px;
    gap: 9px;
    padding: 11px;
  }

  .stat-card__icon {
    width: 35px;
    height: 35px;
    flex-basis: 35px;
  }

  .stat-card__icon svg {
    width: 16px;
    height: 16px;
  }

  .stat-card__content strong {
    font-size: 17px;
  }

  .stat-card__content span {
    font-size: 7px;
  }

  .stat-card__number {
    display: none;
  }

  .sections {
    gap: 52px;
  }

  .section-head {
    align-items: flex-end;
  }

  .section-head__mark {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }

  .section-head h2 {
    font-size: 18px;
  }

  .section-count {
    display: none;
  }

  .section-link {
    padding: 6px;
    font-size: 8px;
  }

  .section-link svg {
    width: 12px;
  }

  .news-grid {
    grid-template-columns: 1fr;
  }

  .news-card--featured {
    grid-column: auto;
  }

  .news-card__cover,
  .news-card--featured .news-card__cover {
    height: 175px;
  }

  .podium {
    grid-template-columns: 1fr;
    gap: 7px;
  }

  .podium-card--first,
  .podium-card--second,
  .podium-card--third {
    min-height: 0;
    padding-top: 20px;
  }

  .podium-card--first {
    order: -1;
    padding-top: 34px;
  }

  .podium-card--first .podium-avatar {
    width: 82px;
    height: 82px;
  }

  .podium-card--second,
  .podium-card--third {
    display: grid;
    grid-template-columns: 38px 60px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 13px;
  }

  .podium-card--second .podium-rank,
  .podium-card--third .podium-rank {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  .podium-card--second .podium-avatar,
  .podium-card--third .podium-avatar {
    grid-column: 2;
    grid-row: 1 / 4;
    width: 58px;
    height: 58px;
    margin: 0;
  }

  .podium-card--second .podium-name,
  .podium-card--third .podium-name {
    grid-column: 3;
    grid-row: 1;
    margin: 0;
    text-align: left;
  }

  .podium-card--second .podium-tier,
  .podium-card--third .podium-tier {
    grid-column: 4;
    grid-row: 1 / 3;
    margin: 0;
  }

  .podium-card--second .podium-score,
  .podium-card--third .podium-score {
    grid-column: 3;
    grid-row: 2;
    justify-content: flex-start;
    margin: 0;
  }

  .podium-card--second .podium-base,
  .podium-card--third .podium-base {
    grid-column: 3 / 5;
    grid-row: 3;
    width: auto;
    margin-top: 5px;
    padding: 5px 0 0;
    text-align: left;
  }

  .rank-row {
    grid-template-columns: 28px 38px minmax(0, 1fr) 31px 12px;
    gap: 8px;
    min-height: 60px;
    padding: 8px 10px;
  }

  .rank-row__avatar {
    width: 38px;
    height: 38px;
  }

  .rank-row__score {
    display: none;
  }

  .rank-row__arrow {
    grid-column: 5;
  }

  .rank-row__tier {
    font-size: 17px;
  }

  .clan-podium {
    min-height: 0;
    display: grid;
    grid-template-columns: 38px 60px 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 13px;
  }

  .clan-podium--first {
    min-height: 0;
    padding-top: 30px;
  }

  .clan-podium__rank {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  .clan-podium__avatar,
  .clan-podium--first .clan-podium__avatar {
    grid-column: 2;
    grid-row: 1 / 4;
    width: 58px;
    height: 58px;
    margin: 0;
    border-radius: 14px;
  }

  .clan-podium__tag {
    grid-column: 3;
    grid-row: 1;
    margin: 0;
  }

  .clan-podium h3 {
    grid-column: 3;
    grid-row: 2;
    margin: 0;
    text-align: left;
  }

  .clan-podium__stats {
    grid-column: 4;
    grid-row: 1 / 3;
    margin: 0;
    gap: 10px;
  }

  .clan-podium__base {
    grid-column: 3 / 5;
    grid-row: 3;
    width: auto;
    margin-top: 5px;
    padding: 5px 0 0;
    text-align: left;
  }

  .clan-podium__crown {
    top: 3px;
  }

  .clans-list {
    gap: 6px;
  }

  .clan-row {
    grid-template-columns: 28px 40px minmax(0, 1fr) 13px;
    gap: 8px;
    min-height: 62px;
    padding: 8px 10px;
  }

  .clan-row__avatar {
    width: 40px;
    height: 40px;
  }

  .clan-row__meta {
    font-size: 7px;
  }

  .clan-row__metric {
    display: none;
  }

  .clan-row__arrow {
    grid-column: 4;
  }

  .home-footer__top {
    flex-direction: column;
    align-items: flex-start;
  }

  .footer-socials {
    justify-content: flex-start;
  }

  .home-footer__bottom {
    flex-direction: column;
  }
}

@media (max-width: 400px) {
  .hero {
    min-height: 430px;
  }

  .hero__title-main {
    font-size: 57px;
  }

  .hero__title-sub {
    font-size: 36px;
  }

  .stats__grid {
    grid-template-columns: 1fr 1fr;
  }

  .stat-card__icon {
    display: none;
  }

  .stat-card {
    justify-content: center;
  }

  .stat-card__content {
    align-items: center;
  }

  .hero__bottom {
    font-size: 6px;
  }
}
</style>