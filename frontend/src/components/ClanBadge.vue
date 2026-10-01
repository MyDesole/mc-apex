<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { clanLink } from '@/utils/links.js'

const props = defineProps({
  clanMember: { type: Object, default: null },
})

const clan = computed(() => props.clanMember?.clan)

const roleLabels = {
  leader: 'Лидер',
  officer: 'Офицер',
  member: 'Участник',
}

const roleColors = {
  leader: '#facc15',
  officer: '#60a5fa',
  member: '#9ca3af',
}

function plural(n, one, few, many) {
  const mod10 = n % 10
  const mod100 = n % 100

  if (mod10 === 1 && mod100 !== 11) return one
  if (
      [2, 3, 4].includes(mod10) &&
      ![12, 13, 14].includes(mod100)
  ) {
    return few
  }

  return many
}

function pluralMembers(n) {
  if (n == null) return ''
  return `${n} ${plural(n, 'участник', 'участника', 'участников')}`
}

function pluralPower(n) {
  if (n == null) return ''
  return `${n} ${plural(n, 'сила', 'силы', 'сил')}`
}
</script>

<template>
  <!-- NO CLAN -->
  <div v-if="!clan" class="no-clan">
    <div class="no-clan__signal">
      <span class="no-clan__signal-ring"></span>

      <svg
          width="22"
          height="22"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
      >
        <path
            d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
      </svg>
    </div>

    <div class="no-clan__content">
      <div class="no-clan__eyebrow">
        <span></span>
        CLAN STATUS
      </div>

      <div class="no-clan__title">
        Не в клане
      </div>

      <div class="no-clan__sub">
        Вступи в клан, чтобы участвовать в войнах
      </div>
    </div>
  </div>

  <!-- CLAN -->
  <RouterLink
      v-else
      :to="clanLink(clan)"
      class="clan-badge"
      :style="{
      '--clan-color': clan.banner_color || '#7c3aed',
    }"
  >
    <div class="clan-badge__glow"></div>

    <div class="clan-badge__topline">
      <span class="clan-badge__label">
        CLAN IDENTITY
      </span>

      <span class="clan-badge__status">
        <i></i>
        ACTIVE
      </span>
    </div>

    <div class="clan-badge__main">
      <div class="clan-badge__avatar">
        <div class="clan-badge__avatar-glow"></div>

        <img
            v-if="clan.avatar_url"
            :src="clan.avatar_url"
            class="avatar-img"
            :alt="clan.name"
        />

        <template v-else>
          {{ (clan.tag || 'C').charAt(0) }}
        </template>
      </div>

      <div class="clan-badge__info">
        <div class="clan-badge__tag">
          [{{ clan.tag }}]
        </div>

        <div class="clan-badge__name">
          {{ clan.name }}
        </div>

        <div class="clan-badge__meta">
          <span
              class="role"
              :style="{
              '--role-color':
                roleColors[props.clanMember.role] || '#9ca3af'
            }"
          >
            {{ roleLabels[props.clanMember.role] || 'Участник' }}
          </span>

          <template v-if="clan.members_count">
            <span class="separator">/</span>
            <span>{{ pluralMembers(clan.members_count) }}</span>
          </template>

          <template v-if="clan.power">
            <span class="separator">/</span>
            <span>{{ pluralPower(clan.power) }}</span>
          </template>
        </div>
      </div>

      <div class="clan-badge__arrow">
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
          <path
              d="M9 18l6-6-6-6"
              stroke-linecap="round"
              stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>

    <div class="clan-badge__line">
      <span></span>
    </div>
  </RouterLink>
</template>

<style scoped>
/* =========================================================
   NO CLAN
========================================================= */

.no-clan {
  position: relative;
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 17px 18px;
  min-height: 86px;

  background:
      radial-gradient(
          circle at 0% 50%,
          rgba(139, 92, 246, 0.1),
          transparent 38%
      ),
      linear-gradient(
          135deg,
          rgba(11, 12, 25, 0.98),
          rgba(7, 8, 17, 0.98)
      );

  border: 1px dashed rgba(255, 255, 255, 0.09);
  border-radius: 15px;
  overflow: hidden;
}

.no-clan::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139, 92, 246, 0.04),
          transparent
      );

  opacity: 0.7;
}

.no-clan__signal {
  position: relative;
  width: 46px;
  height: 46px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  color: #8b5cf6;

  background:
      radial-gradient(
          circle,
          rgba(139, 92, 246, 0.16),
          rgba(139, 92, 246, 0.03) 70%
      );

  border: 1px solid rgba(139, 92, 246, 0.22);
  border-radius: 12px;

  box-shadow:
      inset 0 0 18px rgba(139, 92, 246, 0.05),
      0 0 22px rgba(139, 92, 246, 0.08);
}

.no-clan__signal-ring {
  position: absolute;
  inset: -5px;

  border: 1px solid rgba(139, 92, 246, 0.08);
  border-radius: 15px;
}

.no-clan__content {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.no-clan__eyebrow {
  display: flex;
  align-items: center;
  gap: 6px;

  margin-bottom: 4px;

  color: #6f7185;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 1.4px;
  text-transform: uppercase;
}

.no-clan__eyebrow span {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #6f7185;
  box-shadow: 0 0 8px rgba(111, 113, 133, 0.5);
}

.no-clan__title {
  color: #f4f4f8;
  font-size: 14px;
  font-weight: 800;
}

.no-clan__sub {
  margin-top: 3px;

  color: #77798b;
  font-size: 11px;
  line-height: 1.45;
}

/* =========================================================
   CLAN CARD
========================================================= */

.clan-badge {
  --clan-color: #7c3aed;

  position: relative;
  display: block;

  padding: 15px 16px 13px;

  color: inherit;
  text-decoration: none;

  background:
      radial-gradient(
          circle at 0% 50%,
          color-mix(in srgb, var(--clan-color) 12%, transparent),
          transparent 42%
      ),
      linear-gradient(
          135deg,
          rgba(14, 15, 31, 0.98),
          rgba(8, 9, 19, 0.98)
      );

  border: 1px solid rgba(255, 255, 255, 0.065);
  border-radius: 15px;

  overflow: hidden;

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.025),
      0 10px 30px rgba(0, 0, 0, 0.22);

  transition:
      transform 0.22s ease,
      border-color 0.22s ease,
      box-shadow 0.22s ease;
}

.clan-badge:hover {
  transform: translateY(-2px);

  border-color: color-mix(
      in srgb,
      var(--clan-color) 35%,
      rgba(255, 255, 255, 0.08)
  );

  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.035),
      0 14px 38px rgba(0, 0, 0, 0.3),
      0 0 28px color-mix(
          in srgb,
          var(--clan-color) 10%,
          transparent
      );
}

.clan-badge::before {
  content: '';

  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;

  width: 2px;

  background: var(--clan-color);

  box-shadow:
      0 0 10px var(--clan-color),
      0 0 24px color-mix(
          in srgb,
          var(--clan-color) 55%,
          transparent
      );
}

.clan-badge__glow {
  position: absolute;

  width: 130px;
  height: 130px;

  left: -55px;
  top: 15px;

  background: var(--clan-color);
  opacity: 0.045;

  filter: blur(30px);
  border-radius: 50%;

  pointer-events: none;
}

/* =========================================================
   TOPLINE
========================================================= */

.clan-badge__topline {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 12px;
}

.clan-badge__label {
  color: #646679;

  font-size: 8px;
  font-weight: 900;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.clan-badge__status {
  display: flex;
  align-items: center;
  gap: 5px;

  color: #717488;

  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.8px;
}

.clan-badge__status i {
  width: 5px;
  height: 5px;

  border-radius: 50%;
  background: #22c55e;

  box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);
}

/* =========================================================
   MAIN
========================================================= */

.clan-badge__main {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  gap: 13px;
}

.clan-badge__avatar {
  position: relative;

  width: 48px;
  height: 48px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-shrink: 0;

  overflow: hidden;

  color: #fff;

  background:
      linear-gradient(
          145deg,
          color-mix(in srgb, var(--clan-color) 85%, white),
          var(--clan-color)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--clan-color) 70%,
      rgba(255, 255, 255, 0.15)
  );

  border-radius: 12px;

  font-size: 19px;
  font-weight: 900;

  box-shadow:
      0 5px 18px color-mix(
          in srgb,
          var(--clan-color) 25%,
          transparent
      );
}

.clan-badge__avatar::after {
  content: '';

  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.15),
          transparent 45%
      );

  pointer-events: none;
}

.clan-badge__avatar-glow {
  position: absolute;
  inset: -15px;

  background: var(--clan-color);
  opacity: 0.22;

  filter: blur(15px);
}

.avatar-img {
  position: relative;
  z-index: 2;

  width: 100%;
  height: 100%;

  object-fit: cover;
}

/* =========================================================
   INFO
========================================================= */

.clan-badge__info {
  flex: 1;
  min-width: 0;
}

.clan-badge__tag {
  margin-bottom: 1px;

  color: color-mix(
      in srgb,
      var(--clan-color) 75%,
      #c4b5fd
  );

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.clan-badge__name {
  overflow: hidden;

  color: #f2f2f7;

  font-size: 15px;
  font-weight: 800;
  line-height: 1.2;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-badge__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 5px;

  margin-top: 6px;

  color: #6f7183;

  font-size: 10px;
  line-height: 1.2;
}

.clan-badge__meta .role {
  color: var(--role-color);

  font-size: 9px;
  font-weight: 900;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.clan-badge__meta .separator {
  color: #3f4150;
  font-weight: 700;
}

/* =========================================================
   ARROW
========================================================= */

.clan-badge__arrow {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 28px;
  height: 28px;

  flex-shrink: 0;

  color: #545669;

  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.045);
  border-radius: 8px;

  transition:
      color 0.2s ease,
      background 0.2s ease,
      transform 0.2s ease;
}

.clan-badge:hover .clan-badge__arrow {
  color: var(--clan-color);

  background: color-mix(
      in srgb,
      var(--clan-color) 10%,
      transparent
  );

  border-color: color-mix(
      in srgb,
      var(--clan-color) 20%,
      transparent
  );

  transform: translateX(2px);
}

/* =========================================================
   BOTTOM SIGNAL
========================================================= */

.clan-badge__line {
  position: relative;

  height: 1px;

  margin-top: 13px;

  background: rgba(255, 255, 255, 0.045);
}

.clan-badge__line span {
  display: block;

  width: 32px;
  height: 1px;

  background: var(--clan-color);

  box-shadow: 0 0 8px var(--clan-color);

  transition: width 0.25s ease;
}

.clan-badge:hover .clan-badge__line span {
  width: 72px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 600px) {
  .clan-badge {
    padding: 14px;
  }

  .clan-badge__topline {
    margin-bottom: 10px;
  }

  .clan-badge__avatar {
    width: 44px;
    height: 44px;
    border-radius: 11px;
  }

  .clan-badge__name {
    font-size: 14px;
  }

  .clan-badge__arrow {
    width: 26px;
    height: 26px;
  }
}

/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {
  .clan-badge,
  .clan-badge__arrow,
  .clan-badge__line span {
    transition: none;
  }

  .clan-badge:hover {
    transform: none;
  }

  .clan-badge:hover .clan-badge__arrow {
    transform: none;
  }

  .clan-badge:hover .clan-badge__line span {
    width: 32px;
  }
}
</style>