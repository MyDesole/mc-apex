<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

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

// Русская плюрализация
function plural(n, one, few, many) {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return few
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
  <div v-if="!clan" class="no-clan">
    <div class="no-clan__icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>

    <div class="no-clan__text">
      <div class="no-clan__title">Не в клане</div>
      <div class="no-clan__sub">Вступи в клан, чтобы участвовать в войнах</div>
    </div>
  </div>

  <RouterLink
      v-else
      :to="`/clans/${clan.id}`"
      class="clan-badge"
      :style="{ '--clan-color': clan.banner_color || '#7c3aed' }"
  >
    <div class="clan-badge__avatar">
      <img
          v-if="clan.avatar_url"
          :src="clan.avatar_url"
          class="avatar-img"
          :alt="clan.name"
      />
      <template v-else>{{ (clan.tag || 'C').charAt(0) }}</template>
    </div>

    <div class="clan-badge__info">
      <div class="clan-badge__name">
        <span class="tag">[{{ clan.tag }}]</span>
        {{ clan.name }}
      </div>

      <div class="clan-badge__meta">
        <span class="role" :style="{ color: roleColors[props.clanMember.role] }">
          {{ roleLabels[props.clanMember.role] }}
        </span>

        <template v-if="clan.members_count">
          <span class="sep">·</span>
          <span>{{ pluralMembers(clan.members_count) }}</span>
        </template>

        <template v-if="clan.power">
          <span class="sep">·</span>
          <span>{{ pluralPower(clan.power) }}</span>
        </template>
      </div>
    </div>

    <div class="clan-badge__arrow">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
  </RouterLink>
</template>

<style scoped>
.no-clan {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 22px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
}

.no-clan__icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  flex-shrink: 0;
}

.no-clan__text { flex: 1; min-width: 0; }

.no-clan__title {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 2px;
}

.no-clan__sub {
  font-size: 12px;
  color: var(--text-dim);
}

.clan-badge {
  position: relative;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  transition: all 0.22s ease;
}

.clan-badge::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--clan-color);
  box-shadow: 0 0 12px var(--clan-color);
}

.clan-badge:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
  transform: translateX(4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
}

.clan-badge__avatar {
  position: relative;
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--clan-color);
  border-radius: 13px;
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 4px 20px color-mix(in srgb, var(--clan-color) 40%, transparent);
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.clan-badge__info { flex: 1; min-width: 0; }

.clan-badge__name {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-badge__name .tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.clan-badge__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-dim);
  flex-wrap: wrap;
}

.clan-badge__meta .role {
  font-weight: 800;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.4px;
}

.clan-badge__meta .sep {
  opacity: 0.4;
}

.clan-badge__arrow {
  color: var(--text-muted);
  flex-shrink: 0;
  transition: all 0.2s;
}

.clan-badge:hover .clan-badge__arrow {
  color: var(--accent-light);
  transform: translateX(3px);
}
</style>