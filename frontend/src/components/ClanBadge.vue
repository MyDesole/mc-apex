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
</script>

<template>
  <div v-if="!clan" class="no-clan">
    <div class="no-clan__icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
      </svg>
    </div>

    <div class="no-clan__text">
      <div class="no-clan__title">Не в клане</div>
      <div class="no-clan__sub">Вступи в клан, чтобы участвовать в войнах</div>
    </div>

    <RouterLink to="/clans" class="no-clan__btn">
      Найти клан
    </RouterLink>
  </div>

  <RouterLink
      v-else
      :to="`/clans/${clan.id}`"
      class="clan-badge"
      :style="{ '--clan-color': clan.banner_color }"
  >
    <div class="clan-badge__avatar">
      {{ (clan.tag || 'C').charAt(0) }}
    </div>

    <div class="clan-badge__info">
      <div class="clan-badge__name">
        <span class="tag">[{{ clan.tag }}]</span>
        {{ clan.name }}
      </div>
      <div class="clan-badge__meta">
                <span
                    class="role"
                    :style="{ color: roleColors[clanMember.role] }"
                >
                    {{ roleLabels[clanMember.role] }}
                </span>
        <span class="sep">·</span>
        <span>{{ clan.members_count }} участников</span>
        <span class="sep">·</span>
        <span>{{ clan.power }} силы</span>
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
/* === НЕ В КЛАНЕ === */

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

.no-clan__btn {
  padding: 9px 16px;
  color: #fff;
  background: var(--accent);
  border-radius: 9px;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  transition: all 0.2s;
}

.no-clan__btn:hover {
  background: var(--accent-light);
  transform: translateY(-1px);
}

/* === В КЛАНЕ === */

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
  box-shadow: 0 4px 20px color-mix(in srgb, var(--clan-color) 40%, transparent);
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

@media (max-width: 600px) {
  .no-clan {
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
  }

  .no-clan__btn { width: 100%; text-align: center; }

  .clan-badge__meta .sep,
  .clan-badge__meta span:not(.role) {
    display: none;
  }
}
</style>