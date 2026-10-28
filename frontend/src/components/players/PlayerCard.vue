<script setup>
import { computed, onMounted, ref, watch } from 'vue'

import TierHistoryChart from '@/components/tiers/TierHistoryChart.vue'
import ProfileRecommendations from '@/components/players/ProfileRecommendations.vue'

import { api } from '@/services/core/api.js'
import { tierColor } from '@/composables/players/useTier.js'

import PlayerProfileHeader from '@/components/players/profile/PlayerProfileHeader.vue'
import PlayerProfileSocials from '@/components/players/profile/PlayerProfileSocials.vue'
import PlayerProfileAspects from '@/components/players/profile/PlayerProfileAspects.vue'
import PlayerProfileShowcase from '@/components/players/profile/PlayerProfileShowcase.vue'
import PlayerProfileFriends from '@/components/players/profile/PlayerProfileFriends.vue'
import PlayerFriendHoverCard from '@/components/players/profile/PlayerFriendHoverCard.vue'
import AchievementModal from '@/components/players/profile/AchievementModal.vue'
import { profileLayout } from '@/data/players/profileLayouts.js'
import ProfileEntrance from '@/components/players/ProfileEntrance.vue'
import CursorTrail from '@/components/players/CursorTrail.vue'
import ProfileBuilderGrid from '@/components/players/builder/ProfileBuilderGrid.vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },

  editable: {
    type: Boolean,
    default: false,
  },

  recommendations: {
    type: Array,
    default: () => [],
  },

  myRecommendation: {
    type: Object,
    default: null,
  },

  canRecommend: {
    type: Boolean,
    default: false,
  },

  isOwner: {
    type: Boolean,
    default: false,
  },

  /*
   * Бридж-профиль: в статистике показываем звание бриджера, а блок
   * аспектов скрываем — он про PvP.
   */
  bridgeMode: {
    type: Boolean,
    default: false,
  },

  bridgeRank: {
    type: Object,
    default: null,
  },

  // Подтверждённые виды: хедер показывает их вместо прогресса тира
  bridgeTechniques: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'edit',
  'recommendationsUpdated',
  'builder',
])

/* =========================================================
   ACCENT / BACKGROUND
   ========================================================= */

const accent = computed(() =>
    props.user.accent_color ||
    props.user.banner_color ||
    tierColor(props.user.tier)
)

const cardStyle = computed(() => {
  const url = props.user.card_background_url

  return {
    ...(url
        ? {
          backgroundImage: `url(${url})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        }
        : {}),

    '--accent-color': accent.value,
  }
})

/*
 * Макет из магазина: класс .profile-layout--{id} меняет расстановку
 * блоков, цвета, фон и шрифты (см. profileLayouts.css).
 */
const layout = computed(() => profileLayout(props.user.profile_layout))

const layoutClass = computed(() => (layout.value
    ? [
      'profile-layout--themed',
      `profile-layout--${props.user.profile_layout}`,
      `profile-layout--side-${layout.value.arrangement}`,
      // Светлые макеты перекрашивают тёмные по умолчанию блоки (см. profileLayouts.css)
      { 'profile-layout--light': layout.value.light },
    ]
    : []))

/* =========================================================
   DATA
   ========================================================= */

const history = ref([])

async function loadHistory(userId) {
  if (!userId) {
    history.value = []
    return
  }

  try {
    const data = await api.get(`/players/${userId}/tier-history`)
    history.value = data.history ?? []
  } catch {
    history.value = []
  }
}

onMounted(() => {
  loadHistory(props.user.id)
})

watch(
    () => props.user.id,
    (id) => {
      closeAchievement()
      hoveredFriend.value = null
      loadHistory(id)
    }
)

/* =========================================================
   ACHIEVEMENTS
   ========================================================= */

const allAchievements = computed(() =>
    props.user.all_achievements ??
    props.user.achievements ??
    []
)

const openedAchievement = ref(null)
const showAchievement = ref(false)

function openAchievement(achievement) {
  openedAchievement.value = achievement
  showAchievement.value = true
}

function closeAchievement() {
  showAchievement.value = false
  openedAchievement.value = null
}

/* =========================================================
   FRIENDS
   ========================================================= */

const allFriends = computed(() =>
    props.user.friends_all ??
    props.user.friends ??
    []
)

const hoveredFriend = ref(null)

const hoverPosition = ref({
  x: 0,
  y: 0,
})

function onFriendEnter(friend, event) {
  hoveredFriend.value = friend
  updateHoverPosition(event)
}

function updateHoverPosition(event) {
  const rect = event.currentTarget.getBoundingClientRect()

  const cardWidth = 320
  const cardHeight = 200
  const gap = 12

  let x = rect.left - cardWidth - gap
  let y = rect.top + rect.height / 2 - cardHeight / 2

  if (x < gap) {
    x = rect.right + gap
  }

  const maxY = window.innerHeight - cardHeight - gap

  if (y < gap) {
    y = gap
  }

  if (y > maxY) {
    y = maxY
  }

  hoverPosition.value = {
    x,
    y,
  }
}

function onFriendLeave() {
  hoveredFriend.value = null
}

/* =========================================================
   PROFILE STATS
   ========================================================= */

const profileStats = computed(() => [
  {
    key: 'tier',
    // «Bridge Новичок» в узкой плитке переносился — приставку убираем,
    // что это бридж, и так понятно по подписи
    value: props.bridgeMode
        ? (props.bridgeRank?.label?.replace(/^bridge\s+/i, '') ?? 'Нет')
        // До первого тир-теста тира нет, хотя в базе у новичка стоит E
        : (props.user.tier_tested === false ? 'Нет' : (props.user.tier || '—')),
    label: props.bridgeMode ? 'Звание' : 'Тир',
    icon: '◆',
    accent: true,
  },

  {
    key: 'achievements',
    value: allAchievements.value.length,
    label: 'Ачивки',
    icon: '✦',
  },

  {
    key: 'friends',
    value: allFriends.value.length,
    label: 'Друзья',
    icon: '♢',
  },

  {
    key: 'recommendations',
    value: props.recommendations.length,
    label: 'Рекомендации',
    icon: '↗',
  },
])

/* =========================================================
   OPTIONAL PROFILE DATA
   ========================================================= */

const hasSocials = computed(() =>
    props.user.socials &&
    Object.keys(props.user.socials).length > 0
)

const hasAspects = computed(() =>
    Array.isArray(props.user.aspects)
        ? props.user.aspects.length > 0
        : !!props.user.aspects
)

const hasBio = computed(() =>
    !!(
        props.user.bio ||
        props.user.description ||
        props.user.about
    )
)

const bioText = computed(() =>
    props.user.bio ||
    props.user.description ||
    props.user.about ||
    ''
)

const hasAnyExtraInfo = computed(() =>
    hasSocials.value ||
    hasAspects.value ||
    hasBio.value ||
    history.value.length > 0
)

/* =========================================================
   КОНСТРУКТОР ПРОФИЛЯ
   ========================================================= */

const auth = useAuthStore()

// Редактирование открывает кнопка «Конструктор» в шапке своего профиля
const building = ref(false)
const savingBlocks = ref(false)
const blocksError = ref('')

/*
 * Профиль рисуется блоками, если игрок их настроил или сейчас настраивает.
 * Пустой список — тоже настройка (игрок убрал все блоки); обычный профиль
 * только при null, его возвращает «Сбросить».
 */
const builderMode = computed(() =>
    building.value || Array.isArray(props.user.profile_blocks)
)

// Страница прячет свои блоки (Minecraft-карточку, клан…): они теперь в сетке
watch(builderMode, (on) => emit('builder', on), { immediate: true })

// Настройки страницы из конструктора видны сразу, ещё до сохранения
const previewPage = ref(null)

const currentPage = computed(() => previewPage.value ?? props.user.profile_page ?? {})

const compactHeader = computed(() => currentPage.value.header === 'compact')

/*
 * Части шапки (ник, роль, плашки, полоса тира): спрятать и оформить.
 * Класс ставим только у изменённого свойства — остальное как в теме.
 */
const ITEM_DEFAULTS = { size: 'm', font: 'default', weight: 'default', spacing: 'normal', case: 'none' }

function headerItemClasses(items) {
  if (!Array.isArray(items) || !items.length) return []
  const classes = ['ph-items']
  for (const item of items) {
    if (item.hidden) classes.push(`ph-hide--${item.id}`)
    for (const [key, base] of Object.entries(ITEM_DEFAULTS)) {
      if (item[key] && item[key] !== base) classes.push(`ph-${item.id}-${key}--${item[key]}`)
    }
  }
  return classes
}

// Студия профиля: фон, шрифт, ширина страницы и вид шапки
const studioClass = computed(() => {
  if (!builderMode.value) return []
  const p = currentPage.value
  return [
    `pp-bg--${p.bg || 'none'}`,
    `pp-font--${p.font || 'default'}`,
    `pp-width--${p.width || 'normal'}`,
    // Шапка профиля (стили в ProfileStudio.css)
    `ph-layout--${p.hdr_layout || 'classic'}`,
    `ph-height--${p.hdr_height || 'normal'}`,
    `ph-bg--${p.hdr_bg || 'theme'}`,
    `ph-ava--${p.hdr_avatar || 'circle'}`,
    `ph-ava-size--${p.hdr_avatar_size || 'm'}`,
    ...headerItemClasses(p.hdr_items),
  ]
})

const studioStyle = computed(() => {
  const p = currentPage.value
  const style = { '--accent-color': accent.value }
  if (!builderMode.value) return style
  if (p.bg_from) style['--pp-from'] = p.bg_from
  if (p.bg_to) style['--pp-to'] = p.bg_to
  // Порядок частей шапки
  for (const [i, item] of (p.hdr_items || []).entries()) style[`--ph-o-${item.id}`] = i
  return style
})

async function persistBlocks(blocks, page = null) {
  savingBlocks.value = true
  blocksError.value = ''

  try {
    await api.put('/players/me/blocks', { blocks, page })
    // Свой профиль берёт данные из стора: обновляем его
    await auth.fetchMe()
    building.value = false
  } catch (e) {
    blocksError.value = e.message || 'Не удалось сохранить профиль.'
  } finally {
    savingBlocks.value = false
  }
}

function saveBlocks({ blocks, page }) {
  persistBlocks(blocks, page)
}

async function resetBlocks() {
  if (!await confirmDialog('Вернуть обычный профиль? Свои блоки и их настройки пропадут.')) return

  persistBlocks(null)
}

/* =========================================================
   PROFILE COMPLETENESS
   ========================================================= */

const profileCompleteness = computed(() => {
  const checks = [
    !!props.user.avatar_url,
    !!props.user.cover_url,
    !!props.user.tier,
    hasBio.value,
    hasSocials.value,
    hasAspects.value,
    allAchievements.value.length > 0,
    allFriends.value.length > 0,
  ]

  const completed = checks.filter(Boolean).length

  return Math.round((completed / checks.length) * 100)
})
</script>

<template>
  <div
      class="profile-layout"
      :class="[layoutClass, studioClass, { 'profile-layout--builder': builderMode }]"
      :style="studioStyle"
  >
    <!-- Появление профиля и след курсора: косметика для гостей профиля -->
    <ProfileEntrance :key="`entrance-${user?.id}`" :entrance="user?.profile_entrance" />
    <CursorTrail :key="`trail-${user?.id}-${user?.cursor_trail}`" :trail="user?.cursor_trail" />

    <!-- =====================================================
         КОНСТРУКТОР: шапка сверху, дальше блоки игрока
         ===================================================== -->

    <template v-if="builderMode">
      <main class="player-card player-card--builder-head" :style="cardStyle">
        <PlayerProfileHeader
            data-block="header"
            :user="user"
            :compact="compactHeader"
            :slim="currentPage.hdr_mode === 'blocks'"
            :editable="editable"
            :cover-url="user.cover_url"
            :accent="accent"
            :bridge-mode="bridgeMode"
            :bridge-rank="bridgeRank"
            :bridge-techniques="bridgeTechniques"
            @edit="emit('edit')"
            @build="building = true"
        >
          <template v-if="$slots['header-actions']" #actions>
            <slot name="header-actions" />
          </template>
        </PlayerProfileHeader>
      </main>

      <p v-if="blocksError" class="builder-error">{{ blocksError }}</p>

      <ProfileBuilderGrid
          :blocks="user.profile_blocks"
          :page="user.profile_page"
          :user="user"
          :owner="editable"
          :editing="building"
          :saving="savingBlocks"
          @save="saveBlocks"
          @cancel="building = false; previewPage = null"
          @reset="resetBlocks"
          @preview-page="previewPage = $event"
          @profile="emit('edit')"
      >
        <template #builtin="{ block }">
          <!-- Часть шапки: та же шапка, но показывает только свой кусок -->
          <PlayerProfileHeader
              v-if="block.type.startsWith('h_')"
              :part="block.type.slice(2)"
              :user="user"
              :cover-url="user.cover_url"
              :accent="accent"
              :bridge-mode="bridgeMode"
              :bridge-rank="bridgeRank"
              :bridge-techniques="bridgeTechniques"
          />

          <!-- Статистика -->
          <div v-else-if="block.type === 'stats'" class="profile-stats">
            <div
                v-for="stat in profileStats"
                :key="stat.key"
                class="profile-stat"
                :class="{ 'profile-stat--accent': stat.accent }"
            >
              <div class="profile-stat__icon">{{ stat.icon }}</div>
              <div class="profile-stat__content">
                <strong>{{ stat.value }}</strong>
                <span>{{ stat.label }}</span>
              </div>
            </div>
          </div>

          <!-- Соцсети -->
          <div v-else-if="block.type === 'socials'">
            <PlayerProfileSocials v-if="hasSocials" :socials="user.socials" />
            <p v-else class="builder-muted">
              {{ editable ? 'Добавь соцсети в студии → «Профиль».' : 'Соцсетей пока нет.' }}
            </p>
          </div>

          <!-- История тиров / виды бриджа -->
          <div v-else-if="block.type === 'games'">
            <template v-if="bridgeMode">
              <h3 class="block-title">Виды бриджа</h3>
              <ul v-if="bridgeTechniques.length" class="bridge-card-list">
                <li v-for="row in bridgeTechniques" :key="row.id" class="bridge-card-item">
                  <div class="bridge-card-item__main">
                    <span class="bridge-card-item__name">{{ row.technique?.label }}</span>
                  </div>
                  <div class="bridge-card-item__scores">
                    <span class="bridge-card-item__score"><b>{{ row.score }}</b>/10</span>
                  </div>
                </li>
              </ul>
              <p v-else class="builder-muted">Пока нет подтверждённых видов.</p>
            </template>
            <template v-else>
              <h3 class="block-title">История тиров</h3>
              <div v-if="history.length" class="chart-section">
                <TierHistoryChart :history="history" />
              </div>
              <p v-else class="builder-muted">Истории тиров пока нет.</p>
            </template>
          </div>

          <!-- Аспекты -->
          <div v-else-if="block.type === 'aspects'">
            <PlayerProfileAspects v-if="hasAspects" :aspects="user.aspects" />
            <p v-else class="builder-muted">Аспектов пока нет.</p>
          </div>

          <!-- Ачивки -->
          <div v-else-if="block.type === 'achievements'" class="side-panel">
            <PlayerProfileShowcase
                v-if="allAchievements.length"
                :achievements="allAchievements"
                @open="openAchievement"
            />
            <p v-else class="builder-muted">Ачивок пока нет.</p>
          </div>

          <!-- Друзья -->
          <div v-else-if="block.type === 'friends'" class="side-panel">
            <PlayerProfileFriends
                v-if="allFriends.length"
                :friends="allFriends"
                @enter="onFriendEnter"
                @leave="onFriendLeave"
            />
            <p v-else class="builder-muted">Друзей пока нет.</p>
          </div>

          <!-- Рекомендации -->
          <div v-else-if="block.type === 'recommendations'" class="side-panel side-panel--recommendations">
            <ProfileRecommendations
                :target-user="user"
                :recommendations="recommendations"
                :my-recommendation="myRecommendation"
                :can-recommend="canRecommend"
                :is-owner="isOwner"
                @updated="emit('recommendationsUpdated')"
            />
          </div>

          <!-- Minecraft-карточка, клан, место, рейтинг, сезоны: данные у страницы -->
          <slot v-else name="block" :block="block" />
        </template>
      </ProfileBuilderGrid>
    </template>

    <!-- =====================================================
         MAIN PROFILE CARD
         ===================================================== -->

    <template v-else>
    <main
        class="player-card"
        :style="cardStyle"
    >
      <!-- Header -->
      <PlayerProfileHeader
          data-block="header"
          :user="user"
          :editable="editable"
          :cover-url="user.cover_url"
          :accent="accent"
          :bridge-mode="bridgeMode"
          :bridge-rank="bridgeRank"
          :bridge-techniques="bridgeTechniques"
          @edit="emit('edit')"
          @build="building = true"
      />

      <!-- ===================================================
           PROFILE SUMMARY
           =================================================== -->


      <!-- ===================================================
           STATS
           =================================================== -->

      <section class="profile-stats" data-block="stats">
        <div
            v-for="stat in profileStats"
            :key="stat.key"
            class="profile-stat"
            :class="{ 'profile-stat--accent': stat.accent }"
        >
          <div class="profile-stat__icon">
            {{ stat.icon }}
          </div>

          <div class="profile-stat__content">
            <strong>
              {{ stat.value }}
            </strong>

            <span>
              {{ stat.label }}
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           SOCIALS
           =================================================== -->

      <section
          v-if="hasSocials"
          class="profile-section profile-section--socials"
          data-block="socials"
      >
        <div class="section-heading">
          <div>

            <h3>Соцсети</h3>
          </div>
        </div>

        <PlayerProfileSocials
            :socials="user.socials"
        />
      </section>

      <!-- Empty socials -->
      <section
          v-else-if="editable"
          class="profile-section profile-empty"
          data-block="socials"
      >
        <div class="profile-empty__icon">
          @
        </div>

        <div class="profile-empty__content">
          <strong>Добавь соцсети</strong>
          <span>
            Добавь ссылки на свои социальные сети, чтобы друзья могли тебя найти.
          </span>
        </div>

        <button
            class="profile-empty__action"
            type="button"
            @click="emit('edit')"
        >
          Добавить
        </button>
      </section>

      <!-- ===================================================
           BRIDGE TECHNIQUES (бридж-профиль)
           =================================================== -->

      <section
          v-if="bridgeMode"
          class="profile-section"
          data-block="games"
      >
        <div class="section-heading">
          <div>

            <h3>Виды бриджа</h3>
          </div>

          <span
              v-if="bridgeTechniques.length"
              class="section-heading__count"
          >
            {{ bridgeTechniques.length }}
          </span>
        </div>

        <ul
            v-if="bridgeTechniques.length"
            class="bridge-card-list"
        >
          <li
              v-for="row in bridgeTechniques"
              :key="row.id"
              class="bridge-card-item"
          >
            <div class="bridge-card-item__main">
              <span class="bridge-card-item__name">
                {{ row.technique?.label }}
              </span>

              <span
                  v-if="row.variants?.length"
                  class="bridge-card-item__variants"
              >
                <span
                    v-for="variant in row.variants"
                    :key="variant.id"
                    class="bridge-card-item__pill"
                    :class="{ 'bridge-card-item__pill--special': variant.is_special }"
                >
                  {{ variant.label }}
                </span>
              </span>
            </div>

            <div class="bridge-card-item__scores">
              <span class="bridge-card-item__score">
                <b>{{ row.score }}</b>/10
              </span>

              <!-- Владение видом: 10 делений, как полоска опыта -->
              <span class="bridge-card-item__bar" :aria-label="`Владение ${row.score} из 10`">
                <i
                    v-for="n in 10"
                    :key="n"
                    :class="{ 'is-on': n <= row.score }"
                />
              </span>

              <span class="bridge-card-item__total">
                аспекты {{ row.total }}/300
              </span>
            </div>
          </li>
        </ul>

        <div
            v-else
            class="profile-empty profile-empty--large"
        >
          <div class="profile-empty__icon profile-empty__icon--chart">
            ⌂
          </div>

          <div class="profile-empty__content">
            <strong>Пока нет подтверждённых видов</strong>

            <span>
              Подтверди вид роликом — тестер проверит и поставит оценку.
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           TIER HISTORY (PvP-профиль)
           =================================================== -->

      <section
          v-else
          class="profile-section"
          data-block="games"
      >
        <div class="section-heading">
          <div>

            <h3>История тиров</h3>
          </div>

          <span
              v-if="history.length"
              class="section-heading__count"
          >
            {{ history.length }} тестов
          </span>
        </div>

        <div
            v-if="history.length"
            class="chart-section"
        >
          <TierHistoryChart
              :history="history"
          />
        </div>

        <div
            v-else
            class="profile-empty profile-empty--large"
        >
          <div class="profile-empty__icon profile-empty__icon--chart">
            ↗
          </div>

          <div class="profile-empty__content">
            <strong>Истории тиров пока нет</strong>

            <span>
              Здесь появится история изменения рейтинга игрока.
            </span>
          </div>
        </div>
      </section>

      <!-- ===================================================
           ASPECTS
           =================================================== -->

      <section
          v-if="hasAspects && !bridgeMode"
          class="profile-section"
          data-block="aspects"
      >
        <div class="section-heading">
          <div>

            <h3>Аспекты</h3>
          </div>
        </div>

        <PlayerProfileAspects
            :aspects="user.aspects"
        />
      </section>

      <!-- ===================================================
           EMPTY PROFILE INFO
           =================================================== -->

      <section
          v-else-if="!hasAnyExtraInfo"
          class="profile-empty profile-empty--profile"
          data-block="empty"
      >
        <div class="profile-empty__visual">
          <span></span>
          <span></span>
          <span></span>
        </div>

        <div class="profile-empty__content">
          <strong>
            {{ editable ? 'Сделай профиль своим' : 'Новый игрок' }}
          </strong>

          <span>
            {{
              editable
                  ? 'Добавь описание, соцсети и пройди тир-тест — так профиль будет выглядеть намного живее.'
                  : 'У этого игрока пока недостаточно информации для заполненного профиля.'
            }}
          </span>
        </div>

        <button
            v-if="editable"
            class="profile-empty__action"
            type="button"
            @click="building = true"
        >
          Открыть студию профиля
        </button>
      </section>

    </main>

    <!-- =====================================================
         SIDE COLUMN
         ===================================================== -->

    <aside class="side-column">
      <!-- Achievements -->
      <section class="side-panel" data-block="achievements">
        <PlayerProfileShowcase
            v-if="allAchievements.length"
            :achievements="allAchievements"
            @open="openAchievement"
        />

        <div
            v-else
            class="side-empty"
        >
          <div class="side-empty__icon">
            ✦
          </div>

          <strong>Ачивок пока нет</strong>

          <span>
            Новые достижения появятся здесь.
          </span>
        </div>
      </section>

      <!-- Friends -->
      <section class="side-panel" data-block="friends">
        <PlayerProfileFriends
            v-if="allFriends.length"
            :friends="allFriends"
            @enter="onFriendEnter"
            @leave="onFriendLeave"
        />

        <div
            v-else
            class="side-empty"
        >
          <div class="side-empty__icon">
            ♢
          </div>

          <strong>Друзей пока нет</strong>

          <span>
            Когда появятся друзья, они будут отображаться здесь.
          </span>
        </div>
      </section>

      <!-- Recommendations -->
      <section
          v-if="!isOwner || recommendations.length || canRecommend"
          class="side-panel side-panel--recommendations"
      >
        <div class="side-panel__heading">
          <div>
          </div>


        </div>

        <ProfileRecommendations
            :target-user="user"
            :recommendations="recommendations"
            :my-recommendation="myRecommendation"
            :can-recommend="canRecommend"
            :is-owner="isOwner"
            @updated="emit('recommendationsUpdated')"
        />
      </section>
    </aside>
    </template>

    <!-- =====================================================
         FRIEND HOVER
         ===================================================== -->

    <Teleport to="body">
      <Transition name="friend-hover">
        <PlayerFriendHoverCard
            v-if="hoveredFriend"
            :friend="hoveredFriend"
            :x="hoverPosition.x"
            :y="hoverPosition.y"
        />
      </Transition>
    </Teleport>

    <!-- =====================================================
         ACHIEVEMENT MODAL
         ===================================================== -->

    <Teleport to="body">
      <AchievementModal
          v-if="showAchievement && openedAchievement"
          :achievement="openedAchievement"
          @close="closeAchievement"
      />
    </Teleport>
  </div>
</template>

<style scoped>
@import "@/components/players/PlayerCard.css";
</style>

<!-- Макеты не scoped: им нужно достать и до шапки, и до вложенных панелей -->
<style>
@import "@/components/players/profileLayouts.css";
</style>
