<script setup>
import UserName from '@/components/UserName.vue'
import {tierColor} from "@/composables/useTier.js";
import { MODE_COLORS, MODE_LABELS, pluralDays } from "@/composables/usePlayerDisplay.js";
defineProps({
  friend: { type: Object, required: true },
  x: { type: Number, default: 0 },
  y: { type: Number, default: 0 },
})
</script>

<template>
  <div
      class="friend-hover"
      :style="{ left: x + 'px', top: y + 'px' }"
  >
    <div
        class="friend-hover__card"
        :style="{
        '--accent-color': friend.accent_color
          || friend.banner_color
          || tierColor(friend.tier),
      }"
    >
      <div
          class="friend-hover__cover"
          :style="friend.cover_url ? {
          backgroundImage: `linear-gradient(rgba(10,10,15,0.45), rgba(10,10,15,0.85)), url(${friend.cover_url})`
        } : {}"
      >
        <div class="friend-hover__header">
          <div class="friend-hover__avatar">
            <img
                v-if="friend.avatar_url"
                :src="friend.avatar_url"
                :alt="friend.username"
            />
            <template v-else>{{ (friend.username || 'И').charAt(0).toUpperCase() }}</template>
          </div>

          <div class="friend-hover__body">
            <div class="friend-hover__name">
              <UserName :user="friend" />
              <span v-if="friend.is_verified" class="verified">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                  <path d="M12 2l2.4 3.6 4.2.6 3 3-1.2 4.2L22 18l-3 3-4.2-1.2L12 22l-3-2.4-4.2 1.2-3-3 1.2-4.2L2 9.6l3-3 4.2-.6z" fill="#1da1f2" />
                  <path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                </svg>
              </span>
            </div>

            <p v-if="friend.status" class="friend-hover__status">{{ friend.status }}</p>
            <p v-else-if="friend.bio" class="friend-hover__bio">{{ friend.bio }}</p>
            <p v-if="friend.quote" class="friend-hover__quote">"{{ friend.quote }}"</p>
          </div>

          <div
              class="friend-hover__tier"
              :style="{
              borderColor: tierColor(friend.tier),
              color: tierColor(friend.tier),
              boxShadow: `0 0 16px ${tierColor(friend.tier)}40`,
            }"
          >
            {{ friend.tier ?? '—' }}
          </div>
        </div>
      </div>

      <div class="friend-hover__footer">
        <div
            v-if="Array.isArray(friend.favorite_modes) && friend.favorite_modes.length"
            class="friend-hover__modes"
        >
          <span
              v-for="m in friend.favorite_modes"
              :key="m"
              class="mode-badge"
              :style="{ '--color': MODE_COLORS[m] || '#7c3aed' }"
          >
            {{ MODE_LABELS[m] ?? m }}
          </span>
        </div>

        <div class="friend-hover__meta">
          <span v-if="friend.days_on_platform" class="meta-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" stroke-linecap="round" />
            </svg>
            С нами {{ friend.days_on_platform }} {{ pluralDays(friend.days_on_platform) }}
          </span>

          <span v-if="friend.clan_joined_at && friend.clan_member?.clan" class="meta-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
            </svg>
            В [{{ friend.clan_member.clan.tag }}] с {{ formatDate(friend.clan_joined_at) }}
          </span>
          <span v-else-if="friend.clan_member?.clan" class="meta-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l9 4v6c0 5-3.5 9-9 10-5.5-1-9-5-9-10V6z" />
            </svg>
            [{{ friend.clan_member.clan.tag }}] {{ friend.clan_member.clan.name }}
          </span>

          <span v-if="friend.discord_tag" class="meta-pill meta-pill--discord">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="#5865f2">
              <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
            </svg>
            {{ friend.discord_tag }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ============================================================
   FRIEND HOVER CARD — APEX / CINEMATIC
   ============================================================ */

.friend-hover {
  position: fixed;
  width: 380px;
  z-index: 3000;
  pointer-events: none;
}

.friend-hover__card {
  position: relative;
  overflow: hidden;

  background:
      linear-gradient(
          145deg,
          rgba(17, 18, 37, .98),
          rgba(7, 8, 18, .99)
      );

  border: 1px solid rgba(255, 255, 255, .07);
  border-radius: 16px;

  box-shadow:
      0 28px 70px rgba(0, 0, 0, .72),
      0 0 0 1px rgba(139, 92, 246, .08) inset,
      0 0 40px rgba(139, 92, 246, .06);
}

/* ambient glow */
.friend-hover__card::before {
  content: '';
  position: absolute;
  top: -100px;
  right: -90px;
  width: 230px;
  height: 230px;
  border-radius: 50%;

  background:
      radial-gradient(
          circle,
          color-mix(
              in srgb,
              var(--accent-color, #8b5cf6) 14%,
              transparent
          ),
          transparent 70%
      );

  pointer-events: none;
}

/* subtle bottom accent */
.friend-hover__card::after {
  content: '';
  position: absolute;
  left: 10%;
  right: 10%;
  bottom: 0;
  height: 1px;

  background:
      linear-gradient(
          90deg,
          transparent,
          rgba(139, 92, 246, .35),
          transparent
      );

  pointer-events: none;
}

/* ============================================================
   COVER / HEADER
   ============================================================ */

.friend-hover__cover {
  position: relative;
  padding: 16px;

  min-height: 116px;

  background-color: #080914;
  background-size: cover;
  background-position: center;

  border-bottom: 1px solid rgba(255, 255, 255, .055);
}

.friend-hover__cover::before {
  content: '';
  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          180deg,
          rgba(5, 5, 13, .18),
          rgba(5, 5, 13, .7)
      ),
      linear-gradient(
          135deg,
          rgba(139, 92, 246, .1),
          transparent 55%
      );

  pointer-events: none;
}

.friend-hover__header {
  position: relative;
  z-index: 1;

  display: flex;
  align-items: center;
  gap: 12px;
}

/* ============================================================
   AVATAR
   ============================================================ */

.friend-hover__avatar {
  position: relative;

  width: 58px;
  height: 58px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #f8fafc;

  background:
      radial-gradient(
          circle at 50% 30%,
          color-mix(
              in srgb,
              var(--accent-color, #8b5cf6) 28%,
              transparent
          ),
          transparent 68%
      ),
      #0c0d19;

  border: 1px solid
  color-mix(
      in srgb,
      var(--accent-color, #8b5cf6) 35%,
      rgba(255, 255, 255, .08)
  );

  border-radius: 12px;

  font-size: 22px;
  font-weight: 900;

  box-shadow:
      0 8px 22px rgba(0, 0, 0, .45),
      0 0 18px
      color-mix(
          in srgb,
          var(--accent-color, #8b5cf6) 14%,
          transparent
      );

  isolation: isolate;
}

.friend-hover__avatar::after {
  content: '';
  position: absolute;
  inset: 0;

  background:
      linear-gradient(
          145deg,
          rgba(255, 255, 255, .08),
          transparent 40%
      );

  pointer-events: none;
}

.friend-hover__avatar img {
  position: absolute;
  inset: 0;

  width: 100%;
  height: 100%;

  display: block;

  object-fit: cover;

  filter: saturate(.92);
}

/* ============================================================
   PLAYER INFO
   ============================================================ */

.friend-hover__body {
  flex: 1;
  min-width: 0;
}

.friend-hover__name {
  display: flex;
  align-items: center;
  gap: 5px;

  margin-bottom: 4px;

  color: #f8fafc;

  font-size: 15px;
  font-weight: 900;

  letter-spacing: -.2px;

  text-shadow:
      0 2px 10px rgba(0, 0, 0, .65);

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-hover__status {
  margin: 0;

  color:
      color-mix(
          in srgb,
          var(--accent-color, #a78bfa) 90%,
          white
      );

  font-size: 11px;
  font-weight: 800;

  letter-spacing: .15px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-hover__bio {
  margin: 0;

  color: #a7b0c0;

  font-size: 11px;
  line-height: 1.45;

  overflow: hidden;
  text-overflow: ellipsis;

  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.friend-hover__quote {
  margin: 4px 0 0;

  color: #7f8aa0;

  font-size: 10.5px;
  font-style: italic;
  line-height: 1.4;

  overflow: hidden;
  text-overflow: ellipsis;

  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

/* ============================================================
   VERIFIED
   ============================================================ */

.verified {
  display: inline-flex;
  flex-shrink: 0;

  filter:
      drop-shadow(0 0 5px rgba(29, 161, 242, .55));
}

/* ============================================================
   TIER
   ============================================================ */

.friend-hover__tier {
  position: relative;

  width: 44px;
  height: 44px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  background:
      radial-gradient(
          circle at 50% 35%,
          rgba(255, 255, 255, .06),
          transparent 65%
      ),
      rgba(5, 6, 14, .82);

  border: 1px solid;
  border-radius: 11px;

  font-size: 18px;
  font-weight: 950;
  letter-spacing: -.5px;

  backdrop-filter: blur(10px);

  text-shadow:
      0 0 12px currentColor,
      0 2px 8px rgba(0, 0, 0, .6);
}

/* ============================================================
   FOOTER
   ============================================================ */

.friend-hover__footer {
  position: relative;
  z-index: 1;

  display: flex;
  flex-direction: column;
  gap: 9px;

  padding: 12px 16px 14px;

  background:
      linear-gradient(
          180deg,
          rgba(9, 10, 20, .82),
          rgba(6, 7, 15, .96)
      );
}

/* ============================================================
   MODES
   ============================================================ */

.friend-hover__modes {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.mode-badge {
  display: inline-flex;
  align-items: center;

  min-height: 20px;
  padding: 2px 8px;

  color: var(--color);

  background:
      color-mix(
          in srgb,
          var(--color) 10%,
          rgba(255, 255, 255, .015)
      );

  border: 1px solid
  color-mix(
      in srgb,
      var(--color) 28%,
      transparent
  );

  border-radius: 5px;

  font-size: 9px;
  font-weight: 900;

  text-transform: uppercase;
  letter-spacing: .65px;

  box-shadow:
      inset 0 1px rgba(255, 255, 255, .025);
}

/* ============================================================
   META
   ============================================================ */

.friend-hover__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;

  min-height: 21px;
  padding: 3px 8px;

  color: #8f9bad;

  background:
      rgba(255, 255, 255, .025);

  border: 1px solid rgba(255, 255, 255, .055);
  border-radius: 5px;

  font-size: 9px;
  font-weight: 750;
  letter-spacing: .1px;

  backdrop-filter: blur(8px);
}

.meta-pill svg {
  flex-shrink: 0;
  opacity: .8;
}

.meta-pill--discord {
  color: #929cf0;
  border-color: rgba(88, 101, 242, .25);
  background: rgba(88, 101, 242, .055);
}

/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width: 600px) {
  .friend-hover {
    width: min(380px, calc(100vw - 24px));
  }

  .friend-hover__cover {
    padding: 14px;
  }

  .friend-hover__footer {
    padding: 11px 14px 13px;
  }

  .friend-hover__avatar {
    width: 54px;
    height: 54px;
    border-radius: 11px;
  }

  .friend-hover__tier {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    font-size: 17px;
  }
}

/* ============================================================
   REDUCED MOTION
   ============================================================ */

@media (prefers-reduced-motion: reduce) {
  .friend-hover__card,
  .friend-hover__avatar,
  .friend-hover__tier {
    transition: none;
  }
}
</style>