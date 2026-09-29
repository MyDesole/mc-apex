<script setup>
import { computed } from 'vue'
import {formatDate, MODE_COLORS, MODE_LABELS, pluralDays} from "@/composables/usePlayerDisplay.js";

const props = defineProps({
  user: { type: Object, required: true },
})

const modes = computed(() => props.user.favorite_modes ?? [])
const daysOnPlatform = computed(() => props.user.days_on_platform ?? 0)
const clanJoinedAt = computed(() => props.user.clan_joined_at)
</script>

<template>
  <div class="meta">
    <p v-if="user.status" class="status">{{ user.status }}</p>
    <p v-else-if="user.bio" class="bio">{{ user.bio }}</p>
    <p v-if="user.quote" class="quote">"{{ user.quote }}"</p>

    <div v-if="modes.length" class="modes">
      <span
          v-for="m in modes"
          :key="m"
          class="mode-badge"
          :style="{ '--color': MODE_COLORS[m] || '#7c3aed' }"
      >
        {{ MODE_LABELS[m] ?? m }}
      </span>
    </div>

    <div class="meta-row">
      <span v-if="daysOnPlatform" class="meta-pill">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M12 6v6l4 2" stroke-linecap="round" />
        </svg>
        С нами {{ daysOnPlatform }} {{ pluralDays(daysOnPlatform) }}
      </span>

      <span v-if="clanJoinedAt && user.clan_member?.clan" class="meta-pill">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
        </svg>
        В [{{ user.clan_member.clan.tag }}] с {{ formatDate(clanJoinedAt) }}
      </span>

      <span v-if="user.discord_tag" class="meta-pill meta-pill--discord">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="#5865f2">
          <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
        </svg>
        {{ user.discord_tag }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

/* === STATUS / BIO / QUOTE === */

.status {
  margin: 0;
  color: var(--accent-color, #a78bfa);
  font-size: 13px;
  font-weight: 700;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bio {
  margin: 0;
  color: #d1d1db;
  font-size: 13px;
  line-height: 1.5;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.quote {
  margin: 4px 0 0;
  color: #b8b8c7;
  font-size: 12px;
  font-style: italic;
  line-height: 1.4;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
  word-break: break-word;
  white-space: normal;
}

/* === MODES === */

.modes {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 8px;
}

.mode-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  color: var(--color);
  background: color-mix(in srgb, var(--color) 15%, transparent);
  border: 1px solid color-mix(in srgb, var(--color) 35%, transparent);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* === META ROW === */

.meta-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: rgba(10, 10, 15, 0.6);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  color: #d1d1db;
  backdrop-filter: blur(6px);
}

.meta-pill--discord {
  color: #8895f5;
  border-color: rgba(88, 101, 242, 0.3);
}

/* === АДАПТИВ === */

@media (max-width: 600px) {
  .bio,
  .status {
    font-size: 11px;
  }

  .quote {
    font-size: 11px;
  }
}
</style>