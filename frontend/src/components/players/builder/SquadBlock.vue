<script setup>
/**
 * Блок «Тиммейты»: карточки игроков APEX, с кем играешь.
 *
 * В отличие от списка друзей — это витрина сквада: крупный аватар в
 * кольце цвета тира, роль (дуо, тренер, соперник…) своим цветом и
 * подпись. Аватар, тир и эффект ника берём свежими с сервера по id.
 */
import { computed, ref, watch } from 'vue'
import { api } from '@/services/core/api.js'
import UserName from '@/components/players/UserName.vue'
import { teammateRole } from '@/data/players/profileBlocks.js'
import { rankColor, rankLabel } from '@/utils/playerStyling.js'

const props = defineProps({
  items: { type: Array, default: () => [] },
  editing: { type: Boolean, default: false },
})

const players = ref({})

const ids = computed(() => props.items.map((i) => i.user_id).filter(Boolean))

watch(
    () => ids.value.join(','),
    async (list) => {
      if (!list) return

      try {
        const data = await api.get('/players/teammates', { params: { ids: list } })
        players.value = Object.fromEntries((data.players ?? []).map((p) => [p.id, p]))
      } catch {
        // Без свежих данных карточки покажут сохранённый ник
      }
    },
    { immediate: true },
)

const cards = computed(() =>
    props.items.map((item) => {
      const player = players.value[item.user_id] ?? null
      return {
        key: item.user_id || item.username,
        item,
        player,
        role: teammateRole(item.role),
        name: player?.username ?? item.username,
      }
    }),
)
</script>

<template>
  <div v-if="cards.length" class="squad">
    <component
        :is="card.player ? 'RouterLink' : 'div'"
        v-for="(card, i) in cards"
        :key="card.key"
        :to="card.player ? `/user/${encodeURIComponent(card.name)}` : undefined"
        class="squad-card"
        :style="{
          '--role': card.role.color,
          '--tier': card.player ? rankColor(card.player) : '#6b7280',
          '--i': i,
        }"
    >
      <span class="squad-card__role">{{ card.role.icon }} {{ card.role.label }}</span>

      <span class="squad-card__avatar">
        <img v-if="card.player?.avatar_url" :src="card.player.avatar_url" :alt="card.name" loading="lazy" />
        <template v-else>{{ card.name.charAt(0).toUpperCase() }}</template>
        <span v-if="card.player" class="squad-card__tier">{{ rankLabel(card.player) }}</span>
      </span>

      <span class="squad-card__name">
        <UserName v-if="card.player" :user="card.player" />
        <template v-else>{{ card.name }}</template>
      </span>

      <span v-if="card.item.note" class="squad-card__note">«{{ card.item.note }}»</span>
    </component>
  </div>

  <p v-else-if="editing" class="squad-empty">Открой ⚙ и найди игроков по нику — они встанут сюда карточками.</p>
</template>

<style scoped>
.squad {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(138px, 1fr));
  gap: 10px;
}

.squad-card {
  position: relative;
  overflow: hidden;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;

  padding: 34px 10px 14px;

  color: inherit;
  background:
      linear-gradient(180deg, color-mix(in srgb, var(--role) 30%, transparent), transparent 62%),
      repeating-linear-gradient(-45deg, color-mix(in srgb, var(--role) 7%, transparent) 0 6px, transparent 6px 14px),
      rgba(255, 255, 255, 0.025);
  border: 1px solid color-mix(in srgb, var(--role) 38%, transparent);
  border-radius: 16px;

  text-align: center;
  text-decoration: none;

  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  animation: squad-in 0.45s cubic-bezier(0.2, 0.8, 0.2, 1) both;
  animation-delay: calc(var(--i) * 60ms);
}

a.squad-card:hover {
  border-color: var(--role);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.35), 0 0 24px color-mix(in srgb, var(--role) 35%, transparent);
  transform: translateY(-4px);
}

@keyframes squad-in {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.96);
  }
}

/* Роль — флажок сверху */
.squad-card__role {
  position: absolute;
  top: 0;
  left: 50%;

  padding: 4px 12px 5px;

  color: #fff;
  background: var(--role);
  border-radius: 0 0 10px 10px;
  box-shadow: 0 4px 14px color-mix(in srgb, var(--role) 45%, transparent);

  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;

  transform: translateX(-50%);
}

/* Аватар в кольце цвета тира */
.squad-card__avatar {
  position: relative;

  width: 66px;
  height: 66px;

  display: flex;
  align-items: center;
  justify-content: center;

  color: #fff;
  background: #1f1b2b;
  border-radius: 20px;
  box-shadow:
      0 0 0 2px var(--tier),
      0 0 0 5px color-mix(in srgb, var(--tier) 18%, transparent),
      0 8px 20px rgba(0, 0, 0, 0.4);

  font-size: 24px;
  font-weight: 900;

  transition: transform 0.2s ease;
}

a.squad-card:hover .squad-card__avatar {
  transform: scale(1.06) rotate(-2deg);
}

.squad-card__avatar img {
  width: 100%;
  height: 100%;

  border-radius: inherit;
  object-fit: cover;
}

.squad-card__tier {
  position: absolute;
  bottom: -9px;
  left: 50%;

  padding: 1px 7px;

  color: #0d0b14;
  background: var(--tier);
  border-radius: 6px;

  font-size: 10px;
  font-weight: 900;
  white-space: nowrap;

  transform: translateX(-50%);
}

.squad-card__name {
  max-width: 100%;
  margin-top: 6px;

  overflow: hidden;

  color: var(--text, #fff);

  font-size: 14px;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.squad-card__note {
  color: var(--text-muted, #a8a3b8);

  font-size: 11px;
  font-style: italic;
  line-height: 1.3;
}

.squad-empty {
  margin: 0;
  color: var(--text-muted, #8a8598);
  font-size: 13px;
}

@media (prefers-reduced-motion: reduce) {
  .squad-card {
    animation: none;
  }
}
</style>
