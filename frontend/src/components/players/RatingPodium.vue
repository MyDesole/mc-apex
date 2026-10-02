<script setup>
/**
 * Пьедестал рейтинга: настольная версия и мобильная.
 *
 * Вынесен из PlayersView вместе с оформлением карточки игрока
 * (.rank-player): обе версии используют одну разметку.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import { userLink } from '@/utils/links.js'
import RatingMountainBackground from '@/components/players/RatingMountainBackground.vue'
import {
  accent,
  avatarFrame,
  avatarLetter,
  profileEffectStyle,
  roleLabel,
  scoreOf,
} from '@/utils/playerStyling.js'

const props = defineProps({
  players: { type: Array, default: () => [] },
})

const topThree = computed(() => props.players.slice(0, 3))

/* ===== Палитры, порядок мест и хелперы ===== */

const PODIUM_ORDER = [1, 0, 2]

const MOBILE_ORDER = [0, 1, 2]

const podiumSlots = computed(() =>
    PODIUM_ORDER.filter(i => i < topThree.value.length)
)

const mobileSlots = computed(() =>
    MOBILE_ORDER.filter(i => i < topThree.value.length)
)

function rankOf(slotIdx) {
  return slotIdx + 1
}

</script>

<template>
      <section
          v-if="topThree.length"
          class="mountain"
      >
        <div class="mountain__vignette" />
        <div class="mountain__noise" />

        <RatingMountainBackground />

        <div class="apex-marker">
          <div class="apex-marker__line" />
          <div class="apex-marker__glow" />
          <span>APEX</span>
        </div>

        <!-- =====================================================
             DESKTOP PODIUM
             ===================================================== -->

        <div class="podium">
          <div
              v-for="slot in podiumSlots"
              :key="slot"
              class="podium__slot"
              :class="`podium__slot--rank${rankOf(slot)}`"
          >
            <div
                class="podium__badge"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <svg
                  v-if="slot === 0"
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#facc15"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#facc15"
                    stroke="#a16207"
                    stroke-width="1.4"
                />

                <path
                    d="M12 12.5l1 2 2.2.3-1.6 1.5.4 2.2-2-1.1-2 1.1.4-2.2-1.6-1.5 2.2-.3z"
                    fill="#7c2d12"
                />
              </svg>

              <svg
                  v-else-if="slot === 1"
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#cbd5e1"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#cbd5e1"
                    stroke="#64748b"
                    stroke-width="1.4"
                />

                <text
                    x="12"
                    y="18.6"
                    text-anchor="middle"
                    font-size="7"
                    font-weight="900"
                    fill="#334155"
                >
                  2
                </text>
              </svg>

              <svg
                  v-else
                  class="podium__medal"
                  width="21"
                  height="21"
                  viewBox="0 0 24 24"
                  fill="none"
              >
                <path
                    d="M8 2l4 8 4-8"
                    stroke="#d97706"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <circle
                    cx="12"
                    cy="15"
                    r="6"
                    fill="#f59e0b"
                    stroke="#92400e"
                    stroke-width="1.4"
                />

                <text
                    x="12"
                    y="18.6"
                    text-anchor="middle"
                    font-size="7"
                    font-weight="900"
                    fill="#78350f"
                >
                  3
                </text>
              </svg>

              <span class="podium__rank">
                #{{ rankOf(slot) }}
              </span>
            </div>

            <div
                class="podium__score"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <svg
                  width="12"
                  height="12"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.4"
                  stroke-linecap="round"
                  stroke-linejoin="round"
              >
                <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
              </svg>

              <span>
                {{ scoreOf(topThree[slot]) }}
              </span>

              <small>RATING</small>
            </div>

            <RouterLink
                :to="userLink(topThree[slot])"
                class="podium__card"
                :style="{ '--accent': accent(topThree[slot]) }"
            >
              <div class="podium__card-glow" />

              <div
                  class="rank-player"
                  :class="{
                    'rank-player--legendary':
                        topThree[slot].profile_effect === 'legendary'
                  }"
                  :style="profileEffectStyle(topThree[slot])"
              >
                <div class="rank-player__avatar-wrap">

                  <div
                      v-if="topThree[slot].avatar_url"
                      class="rank-player__avatar"
                  >
                    <img
                        :src="topThree[slot].avatar_url"
                        :alt="topThree[slot].username"
                    />
                  </div>

                  <div
                      v-else
                      class="rank-player__avatar rank-player__avatar--fallback"
                      :style="{ '--accent': accent(topThree[slot]) }"
                  >
                    {{ avatarLetter(topThree[slot]) }}
                  </div>

                  <span
                      v-if="topThree[slot].avatar_frame && topThree[slot].avatar_frame !== 'default'"
                      class="rank-player__frame"
                      :class="`rank-player__frame--${topThree[slot].avatar_frame}`"
                      :style="avatarFrame(topThree[slot])"
                  />

                  <span class="rank-player__online" />
                </div>

                <div class="rank-player__body">
                  <div class="rank-player__top">
                    <span class="rank-player__tier">
                      {{ topThree[slot].tier || '—' }}
                    </span>

                    <span
                        v-if="topThree[slot].clan_tag"
                        class="rank-player__clan"
                    >
                      [{{ topThree[slot].clan_tag }}]
                    </span>
                  </div>

                  <div class="rank-player__name">
                    {{ topThree[slot].username }}
                  </div>

                  <div
                      v-if="roleLabel(topThree[slot])"
                      class="rank-player__role"
                  >
                    {{ roleLabel(topThree[slot]) }}
                  </div>
                </div>
              </div>
            </RouterLink>
          </div>
        </div>
      </section>

      <!-- =======================================================
           MOBILE PODIUM
           ======================================================= -->

      <section
          v-if="topThree.length"
          class="mobile-podium"
      >
        <div
            v-for="slot in mobileSlots"
            :key="slot"
            class="mobile-podium__row"
            :class="`mobile-podium__row--rank${rankOf(slot)}`"
            :style="{ '--accent': accent(topThree[slot]) }"
        >
          <div class="mobile-podium__rank-wrap">
            <span class="mobile-podium__rank">
              #{{ rankOf(slot) }}
            </span>
          </div>

          <RouterLink
              :to="userLink(topThree[slot])"
              class="mobile-podium__card"
          >
            <div
                class="rank-player rank-player--mobile"
                :class="{
                  'rank-player--legendary':
                      topThree[slot].profile_effect === 'legendary'
                }"
                :style="{
                  '--accent': accent(topThree[slot]),
                  ...profileEffectStyle(topThree[slot])
                }"
            >
              <div class="rank-player__avatar-wrap">

                <div
                    v-if="topThree[slot].avatar_url"
                    class="rank-player__avatar"
                >
                  <img
                      :src="topThree[slot].avatar_url"
                      :alt="topThree[slot].username"
                  />
                </div>

                <div
                    v-else
                    class="rank-player__avatar rank-player__avatar--fallback"
                    :style="{ '--accent': accent(topThree[slot]) }"
                >
                  {{ avatarLetter(topThree[slot]) }}
                </div>

                <span
                    v-if="topThree[slot].avatar_frame && topThree[slot].avatar_frame !== 'default'"
                    class="rank-player__frame"
                    :class="`rank-player__frame--${topThree[slot].avatar_frame}`"
                    :style="avatarFrame(topThree[slot])"
                />
              </div>

              <div class="rank-player__body">
                <div class="rank-player__top">
                  <span class="rank-player__tier">
                    {{ topThree[slot].tier || '—' }}
                  </span>

                  <span
                      v-if="topThree[slot].clan_tag"
                      class="rank-player__clan"
                  >
                    [{{ topThree[slot].clan_tag }}]
                  </span>
                </div>

                <div class="rank-player__name">
                  {{ topThree[slot].username }}
                </div>

                <div
                    v-if="roleLabel(topThree[slot])"
                    class="rank-player__role"
                >
                  {{ roleLabel(topThree[slot]) }}
                </div>
              </div>
            </div>
          </RouterLink>

          <span class="mobile-podium__score">
            <svg
                width="11"
                height="11"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
            >
              <path d="M12 2l2.4 6.4 6.6.5-5 4.4 1.5 6.7L12 16.6 6.5 20l1.5-6.7-5-4.4 6.6-.5z" />
            </svg>

            {{ scoreOf(topThree[slot]) }}
          </span>
        </div>
      </section>
</template>

<style>
/* ============================================================
   PODIUM
   ============================================================ */

.podium {
  position: absolute;
  z-index: 10;
  inset: 0;

  pointer-events: none;
}

.podium__slot {
  position: absolute;

  display: flex;
  flex-direction: column;
  align-items: center;

  gap: 7px;

  min-width: 0;

  transform: translate(-50%, -50%);

  pointer-events: auto;
}

.podium__slot--rank1 {
  top: 20%;
  left: 50%;

  z-index: 3;

  width: clamp(220px, 30%, 350px);
}

.podium__slot--rank2 {
  top: 61%;
  left: 26%;

  z-index: 2;

  width: clamp(180px, 24%, 290px);
}

.podium__slot--rank3 {
  top: 80%;
  left: 74%;

  z-index: 1;

  width: clamp(180px, 24%, 290px);
}

/* ============================================================
   BADGE
   ============================================================ */

.podium__badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 4px 10px;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 22%, rgba(8, 8, 14, .92)),
          rgba(8, 8, 14, .88)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 58%, transparent);

  border-radius: 999px;

  box-shadow:
      0 7px 22px
      color-mix(in srgb, var(--accent) 25%, transparent),
      inset 0 1px rgba(255, 255, 255, .07);

  backdrop-filter: blur(10px);
}

.podium__medal {
  display: block;
}

.podium__rank {
  color: #fff;

  font-size: 11px;
  font-weight: 950;
  letter-spacing: .5px;
}

/* ============================================================
   SCORE
   ============================================================ */

.podium__score {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  padding: 4px 11px;

  background: rgba(5, 5, 11, .9);

  border: 1px solid
  color-mix(in srgb, var(--accent) 45%, transparent);

  border-radius: 999px;

  color:
      color-mix(in srgb, var(--accent) 82%, #fff);

  font-size: 12px;
  font-weight: 950;
  letter-spacing: .35px;

  backdrop-filter: blur(10px);

  box-shadow:
      0 5px 18px
      color-mix(in srgb, var(--accent) 18%, transparent),
      inset 0 1px rgba(255, 255, 255, .04);
}

.podium__score svg {
  color: var(--accent);

  filter:
      drop-shadow(
          0 0 5px
          color-mix(in srgb, var(--accent) 70%, transparent)
      );
}

.podium__score small {
  margin-left: 2px;

  color: rgba(255, 255, 255, .3);

  font-size: 7px;
  font-weight: 900;
  letter-spacing: 1px;
}

/* ============================================================
   PLAYER CARD
   ============================================================ */

.podium__card {
  position: relative;

  display: block;

  width: 100%;

  color: inherit;
  text-decoration: none;

  cursor: pointer;

  filter:
      drop-shadow(0 20px 30px rgba(0, 0, 0, .55))
      drop-shadow(
          0 0 22px
          color-mix(in srgb, var(--accent) 28%, transparent)
      );

  transition:
      transform .25s ease,
      filter .25s ease;
}

.podium__card-glow {
  position: absolute;
  z-index: -1;

  inset: 15% 10% -10%;

  border-radius: 50%;

  background:
      radial-gradient(
          ellipse,
          color-mix(in srgb, var(--accent) 25%, transparent),
          transparent 68%
      );

  filter: blur(20px);

  opacity: .65;

  transition: opacity .25s ease;
}

.podium__card:hover {
  transform: translateY(-5px) scale(1.015);

  filter:
      drop-shadow(0 24px 35px rgba(0, 0, 0, .6))
      drop-shadow(
          0 0 30px
          color-mix(in srgb, var(--accent) 42%, transparent)
      );
}

.podium__card:hover .podium__card-glow {
  opacity: 1;
}

/* ============================================================
   RANK PLAYER
   ============================================================ */

.rank-player {
  position: relative;

  display: flex;
  align-items: center;
  gap: 12px;

  width: 100%;
  min-width: 0;

  padding: 8px 10px;

  overflow: hidden;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 10%, transparent),
          rgba(8, 8, 14, .82)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 22%, var(--border));

  border-radius: 14px;

  box-shadow:
      inset 0 1px rgba(255,255,255,.04),
      0 10px 25px rgba(0,0,0,.2);
}

.rank-player::before {
  content: "";

  position: absolute;
  inset: 0;

  pointer-events: none;

  background:
      radial-gradient(
          circle at 0% 50%,
          color-mix(in srgb, var(--accent) 20%, transparent),
          transparent 55%
      );

  opacity: .7;
}

.rank-player--legendary {
  border-color:
      color-mix(in srgb, var(--effect-color) 55%, var(--border));

  box-shadow:
      inset 0 1px rgba(255,255,255,.06),
      0 0 25px
      color-mix(in srgb, var(--effect-color) 18%, transparent);
}

.rank-player--legendary::after {
  content: "";

  position: absolute;
  inset: -50%;

  background:
      conic-gradient(
          from 0deg,
          transparent,
          rgba(250,204,21,.08),
          transparent,
          rgba(249,115,22,.08),
          transparent
      );

  animation: legendarySpin 5s linear infinite;

  pointer-events: none;
}

/* ============================================================
   RANK AVATAR
   ============================================================ */

.rank-player__avatar-wrap {
  position: relative;
  z-index: 2;

  flex: 0 0 auto;

  width: 54px;
  height: 54px;
}

.rank-player__avatar {
  display: block;

  width: 100%;
  height: 100%;

  overflow: hidden;

  background: #090910;

  border: 2px solid
  color-mix(in srgb, var(--accent) 45%, transparent);

  border-radius: 12px;

  box-shadow:
      0 0 20px
      color-mix(in srgb, var(--accent) 20%, transparent);
}

.rank-player__avatar img {
  display: block;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

.rank-player__avatar--fallback {
  display: flex;
  align-items: center;
  justify-content: center;

  color: color-mix(in srgb, var(--accent) 88%, #fff);

  background:
      radial-gradient(
          circle at 30% 25%,
          color-mix(in srgb, var(--accent) 32%, transparent),
          transparent 62%
      ),
      color-mix(in srgb, var(--accent) 10%, #090910);

  font-size: 21px;
  font-weight: 950;

  text-shadow:
      0 0 14px
      color-mix(in srgb, var(--accent) 60%, transparent);
}

.rank-player__frame {
  position: absolute;
  z-index: 3;

  inset: -3px;

  border-radius: 14px;

  pointer-events: none;
}

.rank-player__frame--purple {
  border: 2px solid #7c3aed;
  box-shadow: 0 0 12px #7c3aed;
}

.rank-player__frame--cyan {
  border: 2px solid #06b6d4;
  box-shadow: 0 0 12px #06b6d4;
}

.rank-player__frame--green {
  border: 2px solid #22c55e;
  box-shadow: 0 0 12px #22c55e;
}

.rank-player__frame--gold {
  border: 2px solid #facc15;
  box-shadow: 0 0 14px rgba(250,204,21,.65);
}

.rank-player__frame--orange {
  border: 2px solid #f97316;
  box-shadow: 0 0 14px rgba(249,115,22,.6);
}

.rank-player__frame--pink {
  border: 2px solid #ec4899;
  box-shadow: 0 0 14px rgba(236,72,153,.6);
}

.rank-player__frame--red {
  border: 2px solid #ef4444;
  box-shadow: 0 0 14px rgba(239,68,68,.6);
}

.rank-player__frame--rainbow {
  border: 2px solid transparent;

}

.rank-player__frame--legendary {
  border: 2px solid transparent;

  box-shadow:
      0 0 10px #facc15,
      0 0 25px rgba(249,115,22,.45);
}

.rank-player__frame--season1 {
  border: 2px solid transparent;

  box-shadow:
      0 0 14px rgba(124,58,237,.4);
}

.rank-player__online {
  position: absolute;
  z-index: 4;

  right: -2px;
  bottom: -2px;

  width: 10px;
  height: 10px;

  border: 2px solid #08080f;
  border-radius: 50%;

  background: #22c55e;
}

/* ============================================================
   RANK INFO
   ============================================================ */

.rank-player__body {
  position: relative;
  z-index: 2;

  min-width: 0;
}

.rank-player__top {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 2px;
}

.rank-player__tier {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-width: 25px;
  height: 20px;

  padding: 0 6px;

  color: var(--accent);

  background:
      color-mix(in srgb, var(--accent) 12%, transparent);

  border: 1px solid
  color-mix(in srgb, var(--accent) 35%, transparent);

  border-radius: 6px;

  font-size: 10px;
  font-weight: 950;
}

.rank-player__clan {
  color: var(--text-muted);

  font-size: 10px;
  font-weight: 800;
}

.rank-player__name {
  overflow: hidden;

  color: #fff;

  font-size: 14px;
  font-weight: 900;

  white-space: nowrap;
  text-overflow: ellipsis;
}

.rank-player__role {
  margin-top: 1px;

  overflow: hidden;

  color: var(--text-muted);

  font-size: 9px;
  font-weight: 700;

  white-space: nowrap;
  text-overflow: ellipsis;
}

/* ============================================================
   MOBILE PODIUM
   ============================================================ */

.mobile-podium {
  display: none;

  flex-direction: column;
  gap: 10px;

  margin-bottom: 32px;
}

.mobile-podium__row {
  position: relative;

  display: grid;

  grid-template-columns: 40px minmax(0, 1fr) auto;

  align-items: center;

  gap: 12px;

  padding: 10px 12px;

  overflow: visible;

  background:
      linear-gradient(
          135deg,
          color-mix(in srgb, var(--accent) 7%, transparent),
          transparent 55%
      ),
      var(--bg-card);

  border: 1px solid var(--border);
  border-radius: 14px;

  box-shadow:
      0 8px 25px rgba(0, 0, 0, .12);

  transition:
      transform .2s ease,
      border-color .2s ease;
}

.mobile-podium__row:hover {
  transform: translateY(-1px);
}

.mobile-podium__row--rank1 {
  border-color: rgba(250, 204, 21, .4);

  box-shadow:
      0 0 30px -12px rgba(250, 204, 21, .4),
      0 10px 30px rgba(0, 0, 0, .15);
}

.mobile-podium__row--rank2 {
  border-color: rgba(203, 213, 225, .25);
}

.mobile-podium__row--rank3 {
  border-color: rgba(245, 158, 11, .28);
}

.mobile-podium__rank-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.mobile-podium__rank {
  color: var(--accent);

  font-size: 12px;
  font-weight: 950;
}

.mobile-podium__card {
  display: block;

  min-width: 0;

  color: inherit;
  text-decoration: none;

  cursor: pointer;
}

.mobile-podium__score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;

  min-width: 48px;

  padding: 5px 8px;

  color:
      color-mix(in srgb, var(--accent) 82%, #fff);

  background:
      color-mix(
          in srgb,
          var(--accent) 14%,
          rgba(10, 10, 15, .9)
      );

  border: 1px solid
  color-mix(in srgb, var(--accent) 42%, transparent);

  border-radius: 999px;

  font-size: 12px;
  font-weight: 950;
}
/* Медиа-запросы пьедестала: правила скопированы из PlayersView.css */
@media (max-width: 900px) {
  .rating-page {
    width: calc(100% - 32px);

    margin: 24px auto 48px;
  }

  .rating-head__title {
    font-size: 24px;
  }

  .mountain {
    min-height: 450px;

    aspect-ratio: 1400 / 650;
  }

  .podium__slot--rank1 {
    top: 20%;
    left: 50%;

    width: clamp(180px, 28%, 260px);
  }

  .podium__slot--rank2 {
    top: 61%;
    left: 25%;

    width: clamp(150px, 22%, 220px);
  }

  .podium__slot--rank3 {
    top: 81%;
    left: 75%;

    width: clamp(150px, 22%, 220px);
  }

  .apex-marker {
    top: 8%;
  }

  .rank-player__avatar-wrap {
    width: 48px;
    height: 48px;
  }

  .rest__row {
    grid-template-columns: 52px minmax(180px, 1fr) minmax(140px, .7fr) 76px 20px;

    gap: 11px;
  }
}

@media (max-width: 640px) {
  .rating-page {
    width: calc(100% - 24px);

    margin: 16px auto 40px;
  }

  .rating-head {
    flex-direction: column;
    align-items: stretch;

    gap: 14px;
  }

  .rating-head__eyebrow {
    font-size: 8px;
  }

  .rating-head__title {
    font-size: 22px;

    letter-spacing: -.5px;
  }

  .rating-head__sub {
    font-size: 12.5px;
  }

  .mode-tabs {
    width: 100%;
  }

  .mode-tab {
    flex: 1;

    padding: 9px 10px;

    font-size: 12.5px;
  }

  .sub-tabs {
    gap: 6px;

    padding: 0;
    margin-bottom: 18px;

    flex-wrap: nowrap;

    overflow-x: auto;

    scrollbar-width: none;
  }

  .sub-tabs::-webkit-scrollbar {
    display: none;
  }

  .sub-tab {
    flex-shrink: 0;

    padding: 6px 11px;

    font-size: 12px;

    white-space: nowrap;
  }

  .mountain {
    display: none;
  }

  .mobile-podium {
    display: flex;
  }

  .rank-player {
    padding: 6px 8px;

    gap: 9px;

    border-radius: 11px;
  }

  .rank-player__avatar-wrap {
    width: 42px;
    height: 42px;
  }

  .rank-player__avatar {
    border-radius: 10px;
  }

  .rank-player__name {
    font-size: 12.5px;
  }

  .rank-player__tier {
    height: 18px;

    min-width: 23px;

    font-size: 9px;
  }

  .rank-player__clan {
    font-size: 9px;
  }

  .rank-player__role {
    font-size: 8px;
  }

  .rest__head {
    padding-bottom: 10px;
    margin-bottom: 10px;
  }

  .rest__title {
    font-size: 13px;

    letter-spacing: .6px;
  }

  .rest__row {
    grid-template-columns:
        34px
        minmax(0, 1fr)
        auto
        16px;

    gap: 8px;

    min-height: 60px;

    padding: 7px 8px 7px 5px;
  }

  .rest__rank {
    min-height: 38px;
  }

  .rest__rank-number {
    font-size: 10px;
  }

  .rest__score {
    min-width: 58px;

    padding: 6px 7px;

    font-size: 10px;
  }

  .rest__arrow {
    display: flex;
  }

  .rest-player__avatar-wrap {
    width: 34px;
    height: 34px;
  }

  .rest-player__avatar {
    border-radius: 9px;
  }

  .rest-player__name {
    font-size: 11.5px;
  }

  .rest-player__meta {
    font-size: 8px;
  }

  .rest__eyebrow {
    font-size: 7px;
  }
}

@media (max-width: 420px) {
  .rating-page {
    width: calc(100% - 18px);
  }

  .rating-head__title {
    font-size: 20px;
  }

  .mobile-podium__row {
    grid-template-columns: 34px minmax(0, 1fr) auto;

    gap: 9px;

    padding: 9px;
  }

  .mobile-podium__score {
    min-width: 43px;

    padding: 5px 6px;

    font-size: 11px;
  }

  .rank-player__avatar-wrap {
    width: 38px;
    height: 38px;
  }

  .rank-player__avatar--fallback {
    font-size: 16px;
  }

  .rank-player__name {
    font-size: 12px;
  }

  .rank-player__role {
    display: none;
  }

  .rest__row {
    grid-template-columns:
        30px
        minmax(0, 1fr)
        auto
        14px;

    gap: 6px;

    padding-right: 5px;
  }

  .rest__rank-number {
    font-size: 9px;
  }

  .rest-player {
    gap: 8px;
  }

  .rest-player__avatar-wrap {
    width: 32px;
    height: 32px;
  }

  .rest-player__name {
    font-size: 11px;
  }

  .rest-player__clan {
    margin-right: 2px;
  }

  .rest-player__meta {
    gap: 5px;
  }

  .rest__score {
    min-width: 52px;

    padding: 5px 6px;

    font-size: 9px;
  }
}

</style>
