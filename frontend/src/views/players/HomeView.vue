<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { homeApi } from '@/services/players/home.js'
import UserName from '@/components/players/UserName.vue'
import PodiumCard from '@/components/players/PodiumCard.vue'
import BrandCube from '@/components/core/BrandCube.vue'
import LiveFeed from '@/components/activity/LiveFeed.vue'
import { tierColors } from '@/data/players/profileCustomization.js'
import { userLink, clanLink } from '@/utils/links.js'
import { rankColor, rankLabel } from '@/utils/playerStyling.js'
import { useAuthStore } from '@/stores/core/auth.js'

const auth = useAuthStore()

const loading = ref(true)

const hero = ref({})
const statsData = ref({})
const players = ref([])
const clans = ref([])
const news = ref([])

// Главная новость на всю ширину, под ней — полные ряды по три без дыр
const visibleNews = computed(() => {
  const rest = news.value.length - 1
  if (rest < 3) return news.value
  return news.value.slice(0, 1 + rest - (rest % 3))
})

const typeLabels = {
  news: 'Новость',
  update: 'Обновление',
  event: 'Событие',
  announcement: 'Анонс',
}

// За сколько процентов даётся тир — пороги как в TierTestService::TIER_THRESHOLDS
const tierRanges = {
  S: 'турниры',
  A: '71–100%',
  B: '56–70%',
  C: '41–55%',
  D: '21–40%',
  E: '0–20%',
}

const tierLegend = Object.keys(tierColors).map((tier) => ({
  tier,
  color: tierColors[tier],
  range: tierRanges[tier] ?? '',
}))

const leader = computed(() => players.value[0] ?? null)

const topPlayers = computed(() => players.value.slice(0, 3))
const restPlayers = computed(() => players.value.slice(3))

const topClans = computed(() => clans.value.slice(0, 3))
const restClans = computed(() => clans.value.slice(3))

const stats = computed(() => [
  { label: 'игроков', value: statsData.value.players ?? 0 },
  { label: 'кланов', value: statsData.value.clans ?? 0 },
  { label: 'турниров', value: statsData.value.tournaments ?? 0 },
  { label: 'матчей', value: statsData.value.matches ?? 0 },
])

/** Очки общего рейтинга — по ним же отсортирован топ. */
function playerScore(player) {
  return Number(player?.rating_points ?? 0)
}

/** Длина полоски: относительно лидера, очки не ограничены сотней. */
function playerBar(player) {
  const max = playerScore(players.value?.[0])
  return max > 0 ? Math.min(100, (playerScore(player) / max) * 100) : 0
}

function playerTierColor(player) {
  return rankColor(player)
}

function clanColor(clan) {
  return clan?.banner_color || '#7c3aed'
}

function formatDate(value) {
  if (!value) return ''

  return new Date(value).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
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
    statsData.value = data.stats_data ?? {}
    players.value = data.players ?? []
    clans.value = data.clans ?? []
    news.value = data.news ?? []
  } catch (error) {
    console.error('Failed to load home page:', error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="home">
    <!-- =========================================================
         HERO
    ========================================================== -->
    <section class="hero">
      <div class="hero__main">
        <p v-if="hero.hero_badge" class="hero__badge">
          <span class="hero__badge-dot" />
          {{ hero.hero_badge }}
        </p>

        <h1 class="hero__title">
          {{ hero.hero_title || 'APEX TIERS' }}
        </h1>

        <p class="hero__subtitle">
          {{ hero.hero_subtitle || 'Рейтинг игроков и кланов Minecraft PvP' }}
        </p>

        <div class="hero__actions">
          <RouterLink
              :to="hero.hero_primary_url || '/players'"
              class="btn btn-primary hero__btn"
          >
            {{ hero.hero_primary_text || 'Смотреть игроков' }}
            <span aria-hidden="true">→</span>
          </RouterLink>

          <RouterLink
              :to="hero.hero_secondary_url || '/clans'"
              class="btn btn-secondary hero__btn"
          >
            {{ hero.hero_secondary_text || 'Все кланы' }}
          </RouterLink>
        </div>

        <dl v-if="!loading" class="hero__stats">
          <div v-for="stat in stats" :key="stat.label">
            <dt>{{ stat.label }}</dt>
            <dd>{{ stat.value }}</dd>
          </div>
        </dl>
      </div>

      <aside class="hero__side">
        <RouterLink
            v-if="leader"
            :to="userLink(leader)"
            class="leader"
        >
          <span class="leader__label">Лидер рейтинга</span>

          <div class="leader__body">
            <div class="leader__avatar">
              <img
                  v-if="leader.avatar_url"
                  :src="leader.avatar_url"
                  :alt="leader.username"
              />
              <span v-else>{{ playerInitial(leader) }}</span>
            </div>

            <div class="leader__identity">
              <strong>
                <UserName :user="leader" />
              </strong>
              <span>{{ playerScore(leader) }} очков рейтинга</span>
            </div>

            <span
                class="tier-block leader__tier"
                :style="{ '--tier': playerTierColor(leader) }"
            >
              {{ rankLabel(leader) }}
            </span>
          </div>
        </RouterLink>

        <div v-else class="leader leader--empty">
          <BrandCube class="leader__cube" />
          <span>Рейтинг ещё формируется</span>
        </div>

        <div class="tiers">
          <RouterLink to="/rules" class="tiers__label">Тиры и пороги →</RouterLink>

          <ul class="tiers__list">
            <li
                v-for="item in tierLegend"
                :key="item.tier"
                :title="`Тир ${item.tier}: ${item.range}`"
            >
              <span
                  class="tier-block"
                  :style="{ '--tier': item.color }"
              >
                {{ item.tier }}
              </span>
              <small>{{ item.range }}</small>
            </li>
          </ul>
        </div>
      </aside>
    </section>

    <!-- =========================================================
         LOADING
    ========================================================== -->
    <div v-if="loading" class="loading">
      <BrandCube class="loading__cube" />
      <span>Загружаем рейтинг…</span>
    </div>

    <div v-else class="sections">
      <!-- =======================================================
           СЕЙЧАС НА APEX + КАК ПОПАСТЬ В РЕЙТИНГ
      ======================================================== -->
      <div class="pulse">
        <LiveFeed />

        <section class="onboard">
          <h2 class="onboard__title">Как попасть в рейтинг</h2>

          <ol class="onboard__steps">
            <li>
              <strong>Создай аккаунт</strong>
              <span>Ник на сайте может отличаться от игрового</span>
            </li>
            <li>
              <strong>Привяжи Minecraft</strong>
              <span>Укажи ник в профиле — появится 3D-скин и карточка игрока</span>
            </li>
            <li>
              <strong>Подай заявку на тир-тест</strong>
              <span>Тестер оценит 5 аспектов: от блоков до понимания боя</span>
            </li>
            <li>
              <strong>Получи тир</strong>
              <span>Результат сразу попадёт в рейтинг и в ленту</span>
            </li>
          </ol>

          <RouterLink
              :to="auth.isAuthenticated ? '/profile' : '/register'"
              class="btn btn-primary onboard__cta"
          >
            {{ auth.isAuthenticated ? 'Перейти в профиль' : 'Начать' }}
            <span aria-hidden="true">→</span>
          </RouterLink>
        </section>
      </div>

      <!-- =======================================================
           NEWS
      ======================================================== -->
      <section v-if="news.length" class="section">
        <header class="section-head">
          <h2>Новости</h2>

          <RouterLink to="/news" class="section-link">
            Все новости <span aria-hidden="true">→</span>
          </RouterLink>
        </header>

        <div class="news-grid">
          <RouterLink
              v-for="(item, index) in visibleNews"
              :key="item.id"
              :to="`/news/${item.id}`"
              class="news-card"
              :class="{ 'news-card--featured': index === 0 }"
          >
            <div
                class="news-card__cover"
                :style="item.cover_url ? { backgroundImage: `url(${item.cover_url})` } : {}"
            >
              <span v-if="!item.cover_url" class="news-card__fallback">
                <BrandCube />
              </span>
            </div>

            <div class="news-card__body">
              <div class="news-card__meta">
                <span
                    class="news-card__type"
                    :class="`news-card__type--${item.type}`"
                >
                  {{ typeLabels[item.type] || 'Новость' }}
                </span>

                <span v-if="item.is_pinned" class="news-card__pin">
                  Закреплено
                </span>

                <time>{{ formatDate(item.published_at ?? item.created_at) }}</time>
              </div>

              <h3>{{ item.title }}</h3>

              <p v-if="item.excerpt">{{ item.excerpt }}</p>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- =======================================================
           PLAYERS
      ======================================================== -->
      <section class="section">
        <header class="section-head">
          <h2>
            Топ игроков
            <span v-if="players.length" class="section-count">{{ players.length }}</span>
          </h2>

          <RouterLink to="/players" class="section-link">
            Весь рейтинг <span aria-hidden="true">→</span>
          </RouterLink>
        </header>

        <div v-if="!players.length" class="empty">
          <strong>Рейтинг пуст</strong>
          <span>Пока нет игроков с рейтингом</span>
        </div>

        <template v-else>
          <div class="podium">
            <PodiumCard
                v-for="(player, index) in topPlayers"
                :key="player.id"
                :entry="player"
                :position="index + 1"
                :max="playerScore(leader)"
            />
          </div>

          <ol
              v-if="restPlayers.length"
              class="rank-list"
          >
            <li v-for="(player, index) in restPlayers" :key="player.id">
              <RouterLink :to="userLink(player)" class="rank-row">
                <span class="rank-row__position">{{ index + 4 }}</span>

                <span class="rank-row__avatar">
                  <img
                      v-if="player.avatar_url"
                      :src="player.avatar_url"
                      :alt="player.username"
                  />
                  <span v-else>{{ playerInitial(player) }}</span>
                </span>

                <span class="rank-row__name">
                  <UserName :user="player" />
                </span>

                <span class="rank-row__progress" aria-hidden="true">
                  <span
                      :style="{
                        width: `${playerBar(player)}%`,
                        background: playerTierColor(player),
                      }"
                  />
                </span>

                <span class="rank-row__score">{{ playerScore(player) }}</span>

                <span
                    class="tier-block rank-row__tier"
                    :style="{ '--tier': playerTierColor(player) }"
                >
                  {{ rankLabel(player) }}
                </span>
              </RouterLink>
            </li>
          </ol>
        </template>
      </section>

      <!-- =======================================================
           CLANS
      ======================================================== -->
      <section class="section">
        <header class="section-head">
          <h2>
            Топ кланов
            <span v-if="clans.length" class="section-count">{{ clans.length }}</span>
          </h2>

          <RouterLink to="/clans" class="section-link">
            Все кланы <span aria-hidden="true">→</span>
          </RouterLink>
        </header>

        <div v-if="!clans.length" class="empty">
          <strong>Кланов пока нет</strong>
          <span>Здесь появится рейтинг кланов</span>
        </div>

        <template v-else>
          <div class="podium">
            <PodiumCard
                v-for="(clan, index) in topClans"
                :key="clan.id"
                :entry="clan"
                :position="index + 1"
            />
          </div>

          <ol
              v-if="restClans.length"
              class="rank-list"
          >
            <li v-for="(clan, index) in restClans" :key="clan.id">
              <RouterLink
                  :to="clanLink(clan)"
                  class="rank-row rank-row--clan"
                  :class="{ 'rank-row--highlighted': clan.is_highlighted }"
                  :style="{ '--row-clan-color': clanColor(clan) }"
              >
                <span class="rank-row__position">{{ index + 4 }}</span>

                <span class="rank-row__avatar rank-row__avatar--clan">
                  <img
                      v-if="clan.avatar_url"
                      :src="clan.avatar_url"
                      :alt="clan.name"
                  />
                  <span v-else>{{ clanInitial(clan) }}</span>
                </span>

                <span class="rank-row__name">
                  <span class="rank-row__tag">[{{ clan.tag }}]</span>
                  {{ clan.name }}
                  <small>
                    {{ clan.members_count ?? 0 }} участников ·
                    лидер {{ clan.leader?.username || '—' }}
                  </small>
                </span>

                <span class="rank-row__metric">
                  <strong>{{ clan.power ?? 0 }}</strong>
                  <small>сила</small>
                </span>

                <span class="rank-row__metric rank-row__metric--win">
                  <strong>{{ clan.wins ?? 0 }}</strong>
                  <small>победы</small>
                </span>

                <span class="rank-row__metric rank-row__metric--loss">
                  <strong>{{ clan.losses ?? 0 }}</strong>
                  <small>поражения</small>
                </span>
              </RouterLink>
            </li>
          </ol>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "@/views/players/HomeView.css";
</style>
