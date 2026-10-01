<script setup>
import { computed, onMounted, ref } from 'vue'

import PlayerCard from '@/components/PlayerCard.vue'
import TierTestHistory from '@/components/TierTestHistory.vue'
import ClanBadge from '@/components/ClanBadge.vue'
import RankBadge from '@/components/RankBadge.vue'
import ProfileCustomizeModal from '@/components/ProfileCustomizeModal.vue'
import TierTestForm from '@/components/TierTestForm.vue'

import { api } from '@/services/api.js'
import { useAuthStore } from '@/stores/auth'
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
    const data = await api.get(
        userLink(auth.user)
    )

    recommendations.value =
        data.recommendations ?? []
  } catch {
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

/* ============================================================
   PAGE
============================================================ */

.profile-page {
  --purple: #8b5cf6;
  --purple-light: #a78bfa;
  --gold: #facc15;
  --orange: #f97316;
  --cyan: #06b6d4;

  --surface: #090a18;
  --surface-2: #0d0e1d;

  position: relative;

  width: min(1100px, calc(100% - 40px));

  margin: 28px auto 60px;

  display: flex;
  flex-direction: column;

  gap: 22px;

  color: #f8fafc;
}


/* ============================================================
   AMBIENT
============================================================ */

.profile-page__ambient {
  position: fixed;

  top: -180px;
  left: 50%;

  width: 760px;
  height: 520px;

  transform: translateX(-50%);

  border-radius: 50%;

  background:
      radial-gradient(
          ellipse,
          rgba(139,92,246,.085),
          transparent 68%
      );

  filter: blur(35px);

  pointer-events: none;

  z-index: -4;
}

.profile-page__grid {
  position: fixed;

  inset: 0;

  opacity: .18;

  background-image:
      linear-gradient(
          rgba(139,92,246,.025) 1px,
          transparent 1px
      ),
      linear-gradient(
          90deg,
          rgba(139,92,246,.025) 1px,
          transparent 1px
      );

  background-size: 90px 90px;

  mask-image:
      linear-gradient(
          to bottom,
          black,
          transparent 75%
      );

  pointer-events: none;

  z-index: -3;
}

.profile-page__stars {
  position: fixed;

  inset: 0;

  overflow: hidden;

  pointer-events: none;

  z-index: -2;
}

.star {
  position: absolute;

  width: 2px;
  height: 2px;

  border-radius: 50%;

  background: white;

  opacity: .22;

  box-shadow:
      0 0 7px rgba(255,255,255,.5);
}

.star--1 {
  top: 10%;
  left: 12%;
}

.star--2 {
  top: 19%;
  left: 31%;

  width: 1px;
  height: 1px;
}

.star--3 {
  top: 13%;
  right: 18%;

  opacity: .35;
}

.star--4 {
  top: 32%;
  right: 8%;

  width: 1px;
  height: 1px;
}

.star--5 {
  top: 52%;
  left: 6%;

  opacity: .16;
}

.star--6 {
  top: 68%;
  right: 14%;

  width: 1px;
  height: 1px;
}


/* ============================================================
   HEADER
============================================================ */

.profile-header {
  position: relative;

  padding: 12px 3px 18px;

  border-bottom:
      1px solid rgba(255,255,255,.045);
}

.profile-header__eyebrow {
  display: flex;
  align-items: center;

  gap: 8px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.8px;
}

.profile-header__dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--purple);

  box-shadow:
      0 0 9px rgba(139,92,246,.8);
}

.profile-header__line {
  width: 26px;
  height: 1px;

  background:
      linear-gradient(
          90deg,
          rgba(139,92,246,.5),
          transparent
      );
}

.profile-header__main {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 20px;

  margin-top: 8px;
}

.profile-header h1 {
  margin: 0;

  font-size: clamp(25px, 4vw, 34px);

  line-height: 1;

  font-weight: 950;

  letter-spacing: -1px;
}

.profile-header p {
  margin: 7px 0 0;

  color: #64748b;

  font-size: 10px;
}

.profile-header__status {
  display: flex;
  align-items: center;

  gap: 7px;

  padding: 7px 9px;

  color: #64748b;

  background: rgba(255,255,255,.025);

  border: 1px solid rgba(255,255,255,.05);

  border-radius: 7px;

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1px;
}

.profile-header__status-dot {
  width: 4px;
  height: 4px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow:
      0 0 8px rgba(34,197,94,.7);
}


/* ============================================================
   COMPETITIVE
============================================================ */

.competitive-section {
  position: relative;
}

.section-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;

  gap: 15px;

  margin-bottom: 11px;
}

.section-header span {
  display: block;

  margin-bottom: 4px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.8px;
}

.section-header h2 {
  margin: 0;

  color: #e2e8f0;

  font-size: 17px;
  font-weight: 900;
}

.section-header__mark {
  display: flex !important;
  align-items: center;

  gap: 6px;

  margin: 0 !important;

  color: rgba(250,204,21,.55) !important;
}

.section-header__mark i {
  width: 4px;
  height: 4px;

  margin: 0;

  border-radius: 50%;

  background: var(--gold);

  box-shadow:
      0 0 8px var(--gold);
}

.competitive-grid {
  display: grid;

  grid-template-columns:
      repeat(2, minmax(0, 1fr));

  gap: 12px;
}

.competitive-card {
  position: relative;

  overflow: hidden;

  min-width: 0;

  padding: 15px;

  background:
      linear-gradient(
          145deg,
          rgba(139,92,246,.045),
          rgba(9,10,24,.96)
      );

  border: 1px solid rgba(255,255,255,.055);

  border-radius: 14px;

  box-shadow:
      0 14px 35px rgba(0,0,0,.2);
}

.competitive-card::after {
  content: '';

  position: absolute;

  right: -50px;
  bottom: -70px;

  width: 160px;
  height: 160px;

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          rgba(139,92,246,.07),
          transparent 68%
      );

  pointer-events: none;
}

.competitive-card__top {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  margin-bottom: 12px;
}

.competitive-card__top span {
  display: block;

  margin-bottom: 3px;

  color: #475569;

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.5px;
}

.competitive-card__top h3 {
  margin: 0;

  color: #e2e8f0;

  font-size: 14px;
  font-weight: 900;
}

.competitive-card__top b {
  color: #334155;

  font-size: 7px;

  letter-spacing: 1px;
}


/* ============================================================
   TESTS
============================================================ */

.tests-section {
  position: relative;

  overflow: hidden;

  padding: 18px;

  background:
      radial-gradient(
          ellipse at 50% 100%,
          rgba(139,92,246,.09),
          transparent 55%
      ),
      linear-gradient(
          180deg,
          #101122,
          #080914
      );

  border: 1px solid rgba(139,92,246,.11);

  border-radius: 16px;

  box-shadow:
      0 20px 45px rgba(0,0,0,.25);
}

.tests-section__ambient {
  position: absolute;

  top: -100px;
  right: -60px;

  width: 220px;
  height: 220px;

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          rgba(139,92,246,.1),
          transparent 68%
      );

  filter: blur(15px);

  pointer-events: none;
}

.tests-section__header {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 18px;
}

.tests-section__eyebrow {
  display: block;

  margin-bottom: 4px;

  color: rgba(250,204,21,.55);

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 1.9px;
}

.tests-section__title,
.tests-section h2 {
  margin: 0;

  color: #f8fafc;

  font-size: 17px;
  font-weight: 950;
}

.tests-section__subtitle,
.tests-section p {
  margin: 4px 0 0;

  color: #64748b;

  font-size: 10px;
}

.tests-section__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  min-height: 38px;

  padding: 0 14px;

  color: #080914;

  background:
      linear-gradient(
          135deg,
          #fff4a3,
          #facc15 50%,
          #f97316
      );

  border: 0;

  border-radius: 9px;

  font-size: 10px;
  font-weight: 900;

  cursor: pointer;

  box-shadow:
      0 6px 20px rgba(250,204,21,.18);

  transition:
      transform .2s ease,
      box-shadow .2s ease;
}

.tests-section__button svg {
  width: 15px;
  height: 15px;
}

.tests-section__button:hover {
  transform: translateY(-2px);

  box-shadow:
      0 9px 27px rgba(250,204,21,.28);
}

.tests-section__button:focus-visible {
  outline: 2px solid var(--gold);
  outline-offset: 3px;
}

.tests-section__divider {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;

  gap: 8px;

  margin: 16px 0;
}

.tests-section__divider span {
  flex: 1;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139,92,246,.2)
      );
}

.tests-section__divider span:nth-of-type(2) {
  background:
      linear-gradient(
          90deg,
          rgba(139,92,246,.2),
          transparent
      );
}

.tests-section__divider i {
  width: 4px;
  height: 4px;

  flex: 0 0 4px;

  border-radius: 50%;

  background: var(--purple);

  box-shadow:
      0 0 9px rgba(139,92,246,.65);
}


/* ============================================================
   FOOTER
============================================================ */

.profile-footer {
  display: flex;
  align-items: center;

  gap: 12px;

  padding-top: 4px;
}

.profile-footer > span {
  flex: 1;

  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(255,255,255,.05)
      );
}

.profile-footer > span:last-child {
  background:
      linear-gradient(
          90deg,
          rgba(255,255,255,.05),
          transparent
      );
}

.profile-footer > div {
  display: flex;
  align-items: center;

  gap: 7px;

  color: #334155;

  font-size: 7px;
  font-weight: 900;

  letter-spacing: 2px;
}

.profile-footer i {
  width: 3px;
  height: 3px;

  border-radius: 50%;

  background: var(--purple);

  box-shadow:
      0 0 7px rgba(139,92,246,.5);
}


/* ============================================================
   LOADING
============================================================ */

.profile-loading {
  min-height: 520px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  gap: 7px;
}

.profile-loading__orb {
  position: relative;

  width: 52px;
  height: 52px;

  margin-bottom: 7px;

  border: 1px solid rgba(139,92,246,.16);

  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          rgba(139,92,246,.13),
          transparent 68%
      );

  box-shadow:
      0 0 30px rgba(139,92,246,.08);
}

.profile-loading__orb::before {
  content: '';

  position: absolute;

  inset: 8px;

  border: 1px solid rgba(139,92,246,.12);

  border-radius: 50%;
}

.profile-loading__orb::after {
  content: '';

  position: absolute;

  inset: 17px;

  border: 1px solid rgba(250,204,21,.18);

  border-radius: 50%;
}

.profile-loading__orb span {
  position: absolute;

  top: 50%;
  left: 50%;

  width: 4px;
  height: 4px;

  transform: translate(-50%, -50%);

  border-radius: 50%;

  background: var(--gold);

  box-shadow:
      0 0 8px var(--gold),
      0 0 20px rgba(250,204,21,.45);

  animation:
      profile-pulse 1.8s ease-in-out infinite;
}

@keyframes profile-pulse {
  0%,
  100% {
    opacity: .5;
    transform: translate(-50%, -50%) scale(.85);
  }

  50% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1.15);
  }
}

.profile-loading > span {
  color: #94a3b8;

  font-size: 8px;
  font-weight: 900;

  letter-spacing: 2.5px;
}

.profile-loading small {
  color: #334155;

  font-size: 10px;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 760px) {
  .profile-page {
    width: calc(100% - 24px);

    margin-top: 18px;
    margin-bottom: 40px;

    gap: 17px;
  }

  .competitive-grid {
    grid-template-columns: 1fr;
  }

  .profile-header__main {
    align-items: flex-start;

    flex-direction: column;

    gap: 12px;
  }

  .tests-section__header {
    align-items: stretch;

    flex-direction: column;
  }

  .tests-section__button {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .profile-page {
    width: calc(100% - 16px);
  }

  .profile-header {
    padding-left: 1px;
    padding-right: 1px;
  }

  .profile-header h1 {
    font-size: 25px;
  }

  .profile-header__status {
    display: none;
  }

  .competitive-card,
  .tests-section {
    padding: 14px;
  }

  .section-header__mark {
    display: none !important;
  }
}

@media (prefers-reduced-motion: reduce) {
  .tests-section__button {
    transition: none;
  }

  .profile-loading__orb span {
    animation: none;
  }
}
</style>