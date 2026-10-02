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
import { clansApi } from '@/services/clan/clans.js'

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
@import "@/views/clan/ClansView.css";
</style>
