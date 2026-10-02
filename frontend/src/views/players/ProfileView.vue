<script setup>
import { computed, onMounted, ref } from 'vue'

import PlayerCard from '@/components/players/PlayerCard.vue'
import TierTestHistory from '@/components/tiers/TierTestHistory.vue'
import ClanBadge from '@/components/clan/ClanBadge.vue'
import RankBadge from '@/components/players/RankBadge.vue'
import ProfileCustomizeModal from '@/components/players/ProfileCustomizeModal.vue'
import TierTestForm from '@/components/tiers/TierTestForm.vue'

import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { userLink } from '@/utils/links.js'

const auth = useAuthStore()

const user = computed(() => auth.user)

const clanMember = computed(() =>
    auth.user?.clan_member ?? null
)

const rank = computed(() =>
        auth.rank ?? {
          position: null,
          total: 0,
        }
)

const recommendations = ref([])


/* =========================================================
   RECOMMENDATIONS
========================================================= */

async function loadRecommendations() {
  if (!auth.user?.id) {
    return
  }

  try {
    // userLink() даёт путь фронтенда (/user/ник), а нужен эндпоинт API:
    // /api/user/... не существует, запрос падал с 404
    const data = await api.get(`/players/${auth.user.id}`)

    recommendations.value =
        data.recommendations ?? []
  } catch (e) {
    console.error('Не удалось загрузить отзывы:', e)
    recommendations.value = []
  }
}


/* =========================================================
   CUSTOMIZATION
========================================================= */

const showCustomize = ref(false)

function onCustomizeUpdated() {
  showCustomize.value = false

  auth.fetchMe()
}


/* =========================================================
   TIER TEST
========================================================= */

const showTierTestForm = ref(false)

function openTierTestForm() {
  showTierTestForm.value = true
}

function onTierTestCreated() {
  showTierTestForm.value = false

  auth.fetchMe()
}


/* =========================================================
   INIT
========================================================= */

onMounted(async () => {
  if (!auth.initialized) {
    await auth.fetchMe()
  }

  await loadRecommendations()
})
</script>


<template>
  <main class="profile-page">

    <template v-if="user">

      <!-- =====================================================
           AMBIENT
      ====================================================== -->

      <div
          class="profile-page__ambient"
          aria-hidden="true"
      />

      <div
          class="profile-page__grid"
          aria-hidden="true"
      />

      <div
          class="profile-page__stars"
          aria-hidden="true"
      >
        <span class="star star--1" />
        <span class="star star--2" />
        <span class="star star--3" />
        <span class="star star--4" />
        <span class="star star--5" />
        <span class="star star--6" />
      </div>


      <!-- =====================================================
           PAGE HEADER
      ====================================================== -->

      <header class="profile-header">

        <div class="profile-header__eyebrow">
          <span class="profile-header__dot" />

          PLAYER PROFILE

          <span class="profile-header__line" />

          <span>
            ID // {{ user.id }}
          </span>
        </div>


        <div class="profile-header__main">

          <div>
            <h1>
              Профиль игрока
            </h1>

            <p>
              Статистика · прогресс · достижения
            </p>
          </div>

          <div class="profile-header__status">
            <span class="profile-header__status-dot" />

            ONLINE PROFILE
          </div>

        </div>

      </header>


      <!-- =====================================================
           PLAYER CARD
      ====================================================== -->

      <section class="player-section">

        <PlayerCard
            :user="user"
            editable
            :recommendations="recommendations"
            :my-recommendation="null"
            :is-owner="true"
            :can-recommend="false"
            @edit="showCustomize = true"
            @recommendations-updated="loadRecommendations"
        />

      </section>


      <!-- =====================================================
           COMPETITIVE STATUS
      ====================================================== -->

      <section class="competitive-section">

        <header class="section-header">

          <div>
            <span>
              COMPETITIVE STATUS
            </span>

            <h2>
              Клан и рейтинг
            </h2>
          </div>

          <div class="section-header__mark">
            <i />
            STATUS
          </div>

        </header>


        <div class="competitive-grid">

          <!-- CLAN -->

          <article class="competitive-card">

            <div class="competitive-card__top">
              <div>
                <span>
                  01 / ALLIANCE
                </span>

                <h3>
                  Клан
                </h3>
              </div>

              <b>
                CLAN
              </b>
            </div>

            <ClanBadge
                :clan-member="clanMember"
            />

          </article>


          <!-- RANK -->

          <article class="competitive-card">

            <div class="competitive-card__top">
              <div>
                <span>
                  02 / LEADERBOARD
                </span>

                <h3>
                  Рейтинг
                </h3>
              </div>

              <b>
                RANK
              </b>
            </div>

            <RankBadge
                :position="rank.position"
                :total="rank.total"
            />

          </article>

        </div>

      </section>


      <!-- =====================================================
           TIER TESTS
      ====================================================== -->

      <section class="tests-section">

        <div class="tests-section__ambient" />

        <header class="tests-section__header">

          <div>

            <span class="tests-section__eyebrow">
              NEXT ASCENT
            </span>

            <h2>
              Тир-тесты
            </h2>

            <p>
              Новый результат — новый шаг вверх
            </p>

          </div>


          <button
              type="button"
              class="tests-section__button"
              @click="openTierTestForm"
          >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path
                  d="M12 5v14M5 12h14"
                  stroke-linecap="round"
              />
            </svg>

            Новый тир-тест

          </button>

        </header>


        <div class="tests-section__divider">
          <span />
          <i />
          <span />
        </div>


        <TierTestHistory />

      </section>


      <!-- =====================================================
           FOOTER
      ====================================================== -->

      <footer class="profile-footer">

        <span />

        <div>
          <i />
          KEEP CLIMBING
          <i />
        </div>

        <span />

      </footer>


      <!-- =====================================================
           MODALS
      ====================================================== -->

      <ProfileCustomizeModal
          v-if="showCustomize"
          @close="showCustomize = false"
          @updated="onCustomizeUpdated"
      />

      <TierTestForm
          v-model="showTierTestForm"
          @created="onTierTestCreated"
      />

    </template>


    <!-- =======================================================
         LOADING
    ======================================================== -->

    <div
        v-else
        class="profile-loading"
    >

      <div class="profile-loading__orb">
        <span />
      </div>

      <span>
        ASCENDING...
      </span>

      <small>
        Загрузка профиля
      </small>

    </div>

  </main>
</template>

<style scoped>
@import "@/views/players/ProfileView.css";
</style>
