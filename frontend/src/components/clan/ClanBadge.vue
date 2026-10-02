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
@import "@/components/clan/ClanBadge.css";
</style>
