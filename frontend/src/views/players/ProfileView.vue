<script setup>
import { computed, onMounted, ref } from 'vue'

import PlayerCard from '@/components/players/PlayerCard.vue'
import TierTestHistory from '@/components/tiers/TierTestHistory.vue'
import ClanBadge from '@/components/clan/ClanBadge.vue'
import RankBadge from '@/components/players/RankBadge.vue'
import ProfileCustomizeModal from '@/components/players/ProfileCustomizeModal.vue'
import TierTestForm from '@/components/tiers/TierTestForm.vue'
import BridgeTechniques from '@/components/bridge/BridgeTechniques.vue'
import BridgeTechniqueForm from '@/components/bridge/BridgeTechniqueForm.vue'
import BridgeTechniqueHistory from '@/components/bridge/BridgeTechniqueHistory.vue'

import { api } from '@/services/core/api.js'
import { tierTestsApi } from '@/services/tiers/tierTests.js'

import {
  activeTierTest,
  refreshActiveTierTest,
  clearActiveTierTest,
  addCreatedTierTest,
  refreshTierTests,
} from '@/composables/tiers/tierTestState.js'

import { useAuthStore } from '@/stores/core/auth.js'
import { confirm } from '@/utils/dialog.js'

const auth = useAuthStore()

const user = computed(() => auth.user)

const clanMember = computed(() => {
  return auth.user?.clan_member ?? null
})

const rank = computed(() => {
  return auth.rank ?? {
    position: null,
    total: 0,
  }
})

const recommendations = ref([])

const bridge = ref({
  techniques: [],
  summary: {},
  rank: {
    position: null,
    total: 0,
  },
})

const profileMode = ref('pvp')
const bridgeRank = ref(null)

const isBridgeProfile = computed(() => {
  return profileMode.value === 'bridge'
})

// Модалка подтверждения вида и ссылки на блоки: после подачи их надо обновить
const showBridgeForm = ref(false)
const bridgeBlock = ref(null)
const bridgeHistory = ref(null)

/** После подачи заявки обновляем и список видов, и историю. */
async function onBridgeCreated() {
  showBridgeForm.value = false

  bridgeBlock.value?.load()
  bridgeHistory.value?.load()
}

/* =========================================================
   RECOMMENDATIONS + PROFILE MODE
========================================================= */

async function loadRecommendations() {
  if (!auth.user?.id) return

  try {
    const data = await api.get(
        `/players/${auth.user.id}`,
    )

    recommendations.value =
        data.recommendations ?? []

    bridge.value =
        data.bridge ?? {
          techniques: [],
          summary: {},
          rank: {
            position: null,
            total: 0,
          },
        }

    profileMode.value =
        data.profile_mode ?? 'pvp'

    bridgeRank.value =
        data.bridge_rank ?? null
  } catch (e) {
    console.error(
        'Не удалось загрузить профиль:',
        e,
    )

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

const activeTest = activeTierTest

const showActiveTestModal = ref(false)

async function loadActiveTest() {
  if (!auth.user?.id) return

  try {
    const data = await tierTestsApi.list()

    refreshActiveTierTest(
        data.my_tests,
    )
  } catch {
    clearActiveTierTest()
  }
}

function openTierTestForm() {
  if (activeTierTest.value) {
    showActiveTestModal.value = true

    return
  }

  showTierTestForm.value = true
}

function onTierTestCreated(created) {
  showTierTestForm.value = false

  addCreatedTierTest(created)

  auth.fetchMe()

  refreshTierTests()
}

const cancellingActive = ref(false)
const activeTestCancelError = ref('')

async function cancelActiveTest() {
  if (
      !activeTest.value
      || cancellingActive.value
  ) {
    return
  }

  const ok = await confirm(
      'Отменить заявку на тир-тест? Дождаться тестера будет нельзя.',
      {
        danger: true,
        confirmText: 'Отменить',
      },
  )

  if (!ok) return

  cancellingActive.value = true
  activeTestCancelError.value = ''

  try {
    await tierTestsApi.cancel(
        activeTest.value.id,
    )

    clearActiveTierTest()

    showActiveTestModal.value = false

    refreshTierTests()
  } catch (e) {
    activeTestCancelError.value =
        e.message || 'Не удалось отменить'
  } finally {
    cancellingActive.value = false
  }
}

/* =========================================================
   INIT
========================================================= */

onMounted(async () => {
  if (!auth.initialized) {
    await auth.fetchMe()
  }

  await loadRecommendations()
  await loadActiveTest()
})
</script>

<template>
  <main class="profile-page">
    <template v-if="user">
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

      <!--
        В bridge-режиме этот блок становится главным содержимым
        профиля и располагается перед PvP-информацией.
      -->
      <section
          v-if="isBridgeProfile"
          class="tests-section tests-section--bridge"
      >
        <div class="tests-section__ambient" />

        <header class="tests-section__header">
          <div>
            <span class="tests-section__eyebrow">
              BRIDGE MASTERY
            </span>

            <h2>
              Виды бриджа
            </h2>

            <p>
              Подтверди вид роликом — тестер проверит и поставит оценку
            </p>
          </div>

          <button
              type="button"
              class="tests-section__button tests-section__button--bridge"
              @click="showBridgeForm = true"
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

            Подтвердить вид бриджа
          </button>
        </header>

        <div class="tests-section__divider">
          <span />
          <i />
          <span />
        </div>

        <BridgeTechniques
            ref="bridgeBlock"
            :techniques="bridge.techniques"
            :summary="bridge.summary"
            :rank="bridgeRank"
            editable
        />

        <div class="bridge-history-block">
          <h3 class="bridge-history-block__title">
            История подтверждений
          </h3>

          <BridgeTechniqueHistory ref="bridgeHistory" />
        </div>
      </section>

      <section class="player-section">
        <PlayerCard
            :user="user"
            editable
            :recommendations="recommendations"
            :my-recommendation="null"
            :is-owner="true"
            :can-recommend="false"
            :bridge-mode="isBridgeProfile"
            :bridge-rank="bridgeRank"
            @edit="showCustomize = true"
            @recommendations-updated="loadRecommendations"
        />
      </section>

      <!--
        В bridge-профиле PvP остаётся доступным,
        но визуально вторичен.
      -->
      <section
          class="competitive-section"
          :class="{
            'competitive-section--demoted': isBridgeProfile,
          }"
      >
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

          <article class="competitive-card">
            <div class="competitive-card__top">
              <div>
                <span>
                  02 / LEADERBOARD
                </span>

                <h3>
                  {{ isBridgeProfile
                    ? 'Место в бридже'
                    : 'Рейтинг'
                  }}
                </h3>
              </div>

              <b>
                {{ isBridgeProfile
                  ? 'BRIDGE'
                  : 'RANK'
                }}
              </b>
            </div>

            <RankBadge
                :position="
                  isBridgeProfile
                    ? bridge.rank.position
                    : rank.position
                "
                :total="
                  isBridgeProfile
                    ? bridge.rank.total
                    : rank.total
                "
            />
          </article>
        </div>
      </section>

      <!-- PvP-тесты. В bridge-режиме они вторичны. -->
      <section
          class="tests-section"
          :class="{
            'tests-section--demoted': isBridgeProfile,
          }"
      >
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
              :class="{
                'tests-section__button--disabled': activeTest,
              }"
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

      <!--
        В PvP-режиме bridge остаётся дополнительным разделом.
        В bridge-режиме он уже был показан сверху.
      -->
      <section
          v-if="!isBridgeProfile"
          class="tests-section"
      >
        <header class="tests-section__header">
          <div>
            <span class="tests-section__eyebrow">
              BRIDGE MASTERY
            </span>

            <h2>
              Виды бриджа
            </h2>

            <p>
              Подтверди вид роликом — тестер проверит и поставит оценку
            </p>
          </div>

          <button
              type="button"
              class="tests-section__button"
              @click="showBridgeForm = true"
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

            Подтвердить вид бриджа
          </button>
        </header>

        <div class="tests-section__divider">
          <span />
          <i />
          <span />
        </div>

        <BridgeTechniques
            ref="bridgeBlock"
            :techniques="bridge.techniques"
            :summary="bridge.summary"
            :rank="bridgeRank"
            editable
        />

        <div class="bridge-history-block">
          <h3 class="bridge-history-block__title">
            История подтверждений
          </h3>

          <BridgeTechniqueHistory ref="bridgeHistory" />
        </div>
      </section>

      <footer class="profile-footer">
        <span />

        <div>
          <i />
          KEEP CLIMBING
          <i />
        </div>

        <span />
      </footer>

      <ProfileCustomizeModal
          v-if="showCustomize"
          @close="showCustomize = false"
          @updated="onCustomizeUpdated"
      />

      <TierTestForm
          v-model="showTierTestForm"
          :active-test="activeTest"
          @created="onTierTestCreated"
      />

      <BridgeTechniqueForm
          v-model="showBridgeForm"
          @created="onBridgeCreated"
      />

      <Teleport to="body">
        <div
            v-if="showActiveTestModal"
            class="active-test-modal-bg"
            @click.self="showActiveTestModal = false"
        >
          <div class="active-test-modal">
            <header class="active-test-modal__head">
              <h3>
                {{
                  activeTest?.status === 'in_progress'
                      ? 'Тест уже в работе'
                      : 'Заявка уже отправлена'
                }}
              </h3>

              <button
                  type="button"
                  class="active-test-modal__close"
                  aria-label="Закрыть"
                  @click="showActiveTestModal = false"
              >
                ×
              </button>
            </header>

            <div class="active-test-modal__body">
              <p>
                {{
                  activeTest?.status === 'in_progress'
                      ? 'Тестер уже взял твою заявку в работу. Дождись результата — отменить тест на этом этапе нельзя.'
                      : 'Заявка ждёт тестера. Вторую создавать не нужно — дождись этой или отмени её.'
                }}
              </p>

              <div
                  v-if="activeTest"
                  class="active-test-modal__meta"
              >
                <span>
                  {{
                    activeTest.mode === 'pvp'
                        ? 'PvP'
                        : 'BedWars'
                  }}
                </span>

                <span class="sep">
                  ·
                </span>

                <span>
                  {{
                    new Date(
                        activeTest.created_at,
                    ).toLocaleDateString('ru-RU')
                  }}
                </span>
              </div>

              <p
                  v-if="activeTestCancelError"
                  class="active-test-modal__error"
              >
                {{ activeTestCancelError }}
              </p>
            </div>

            <footer class="active-test-modal__foot">
              <button
                  type="button"
                  class="active-test-modal__close-btn"
                  @click="showActiveTestModal = false"
              >
                Понятно
              </button>

              <button
                  v-if="activeTest?.status === 'pending'"
                  type="button"
                  class="active-test-modal__cancel-btn"
                  :disabled="cancellingActive"
                  @click="cancelActiveTest"
              >
                {{
                  cancellingActive
                      ? 'Отмена…'
                      : 'Отменить заявку'
                }}
              </button>
            </footer>
          </div>
        </div>
      </Teleport>
    </template>

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

.bridge-lead {
  position: relative;

  margin-top: 0;
}

.tests-section--demoted {
  opacity: .62;

  transition: opacity .2s ease;
}

.tests-section--demoted:hover {
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .tests-section--demoted {
    transition: none;
  }
}
</style>