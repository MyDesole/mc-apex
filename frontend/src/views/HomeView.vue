<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { homeApi } from '@/services/home.js'

const loading = ref(true)
const players = ref([])
const clans = ref([])

const tierColors = {
  S: '#facc15',
  A: '#f97316',
  B: '#8b5cf6',
  C: '#06b6d4',
  D: '#22c55e',
  E: '#6b7280',
}

onMounted(async () => {
  try {
    const data = await homeApi.top()
    players.value = data.players
    clans.value = data.clans
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="home">
    <!-- HERO -->
    <section class="hero">
      <div class="hero-badge">
        <span class="dot" />
        Season 1 · Live
      </div>

      <h1>
        APEX <span>TIERS</span>
      </h1>

      <p>Рейтинг игроков и кланов Minecraft PvP</p>

      <div class="hero-actions">
        <RouterLink to="/players" class="hero-btn primary">
          Смотреть игроков
        </RouterLink>
        <RouterLink to="/clans" class="hero-btn ghost">
          Все кланы
        </RouterLink>
      </div>
    </section>

    <div v-if="loading" class="loading">
      <div class="spinner" />
      <span>Загрузка топов...</span>
    </div>

    <div v-else class="tops">
      <!-- ТОП ИГРОКОВ -->
      <section class="top-block">
        <header class="top-head">
          <div class="top-head__left">
            <h2>Топ игроков</h2>
            <span class="top-count">Топ {{ players.length }}</span>
          </div>

          <RouterLink to="/players" class="top-link">
            Все игроки
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </RouterLink>
        </header>

        <div v-if="!players.length" class="empty">
          Пока нет игроков с рейтингом
        </div>

        <div v-else class="players-list">
          <RouterLink
              v-for="(player, i) in players"
              :key="player.id"
              :to="`/players/${player.id}`"
              class="player-row"
              :class="{ 'top-1': i === 0, 'top-2': i === 1, 'top-3': i === 2 }"
          >
            <div class="rank">
              <span v-if="i === 0" class="medal">🥇</span>
              <span v-else-if="i === 1" class="medal">🥈</span>
              <span v-else-if="i === 2" class="medal">🥉</span>
              <span v-else class="rank-num">#{{ i + 1 }}</span>
            </div>

            <div class="row-avatar">
              <img
                  v-if="player.avatar_url"
                  :src="player.avatar_url"
                  :alt="player.username"
                  class="row-avatar-img"
              />
              <template v-else>
                {{ (player.username || 'И').charAt(0).toUpperCase() }}
              </template>
            </div>

            <div class="row-info">
              <div class="row-name">{{ player.username }}</div>
              <div class="row-bar">
                <div
                    class="row-bar-fill"
                    :style="{ width: Math.min(player.tier_score, 100) + '%' }"
                />
              </div>
            </div>

            <div class="row-tier" :style="{ color: tierColors[player.tier] }">
              {{ player.tier }}
            </div>

            <div class="row-score">
              <span class="score-value">{{ player.tier_score }}</span>
              <span class="score-suffix">%</span>
            </div>
          </RouterLink>
        </div>
      </section>

      <!-- ТОП КЛАНОВ -->
      <section class="top-block">
        <header class="top-head">
          <div class="top-head__left">
            <h2>Топ кланов</h2>
            <span class="top-count">Топ {{ clans.length }}</span>
          </div>

          <RouterLink to="/clans" class="top-link">
            Все кланы
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M5 12h14M13 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </RouterLink>
        </header>

        <div v-if="!clans.length" class="empty">
          Пока нет кланов
        </div>

        <div v-else class="clans-list">
          <RouterLink
              v-for="(clan, i) in clans"
              :key="clan.id"
              :to="`/clans/${clan.id}`"
              class="clan-row"
              :class="{ 'top-1': i === 0, 'top-2': i === 1, 'top-3': i === 2 }"
          >
            <div class="rank">
              <span v-if="i === 0" class="medal">🥇</span>
              <span v-else-if="i === 1" class="medal">🥈</span>
              <span v-else-if="i === 2" class="medal">🥉</span>
              <span v-else class="rank-num">#{{ i + 1 }}</span>
            </div>

            <div class="clan-avatar" :style="{ background: clan.banner_color }">
              <img
                  v-if="clan.avatar_url"
                  :src="clan.avatar_url"
                  :alt="clan.name"
                  class="avatar-img"
              />
              <template v-else>{{ clan.tag?.charAt(0) || 'C' }}</template>
            </div>

            <div class="clan-info">
              <div class="clan-name">
                <span class="tag">[{{ clan.tag }}]</span>
                {{ clan.name }}
              </div>
              <div class="clan-meta">
                {{ clan.members_count }} участников
                <span class="sep">·</span>
                Лидер: {{ clan.leader?.username }}
              </div>
            </div>

            <div class="clan-stats">
              <div class="stat">
                <span class="stat-value power">{{ clan.power }}</span>
                <span class="stat-label">сила</span>
              </div>
              <div class="stat">
                <span class="stat-value win">{{ clan.wins }}</span>
                <span class="stat-label">побед</span>
              </div>
              <div class="stat">
                <span class="stat-value loss">{{ clan.losses }}</span>
                <span class="stat-label">поражений</span>
              </div>
            </div>
          </RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.home {
  width: min(1100px, calc(100% - 40px));
  margin: 0 auto;
  padding: 32px 0 80px;
}

/* === HERO === */

.hero {
  position: relative;
  text-align: center;
  padding: 72px 20px 88px;
  margin-bottom: 72px;
  border-radius: 24px;
  overflow: hidden;
  background:
      radial-gradient(circle at 50% 0%, rgba(124, 58, 237, 0.18), transparent 55%),
      radial-gradient(circle at 80% 100%, rgba(6, 182, 212, 0.08), transparent 40%),
      var(--bg-card);
  border: 1px solid var(--border);
}

.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
      linear-gradient(rgba(124, 58, 237, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(124, 58, 237, 0.05) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(circle at 50% 30%, black, transparent 70%);
  -webkit-mask-image: radial-gradient(circle at 50% 30%, black, transparent 70%);
  pointer-events: none;
}

.hero-badge {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  margin-bottom: 24px;
  color: #a78bfa;
  background: rgba(124, 58, 237, 0.1);
  border: 1px solid rgba(124, 58, 237, 0.25);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.hero-badge .dot {
  width: 6px;
  height: 6px;
  background: #a78bfa;
  border-radius: 50%;
  box-shadow: 0 0 8px #a78bfa;
  animation: pulse 2s infinite;
}

.hero h1 {
  position: relative;
  margin: 0 0 14px;
  font-size: 64px;
  font-weight: 900;
  letter-spacing: -3px;
  line-height: 1;
  background: linear-gradient(135deg, #e9d5ff 0%, #a78bfa 40%, #7c3aed 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  filter: drop-shadow(0 4px 30px rgba(124, 58, 237, 0.3));
}

.hero h1 span {
  opacity: 0.45;
}

.hero p {
  position: relative;
  margin: 0 0 32px;
  color: var(--text-dim);
  font-size: 16px;
  font-weight: 500;
}

.hero-actions {
  position: relative;
  display: flex;
  gap: 12px;
  justify-content: center;
}

.hero-btn {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 22px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 700;
  transition: all 0.2s ease;
}

.hero-btn.primary {
  color: #fff;
  background: var(--accent);
  box-shadow: 0 6px 24px rgba(124, 58, 237, 0.35);
}

.hero-btn.primary:hover {
  background: var(--accent-light);
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(124, 58, 237, 0.45);
}

.hero-btn.ghost {
  color: var(--text);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
}

.hero-btn.ghost:hover {
  background: rgba(255, 255, 255, 0.06);
  border-color: var(--border-hover);
}

/* === TOPS LAYOUT === */

.tops {
  display: flex;
  flex-direction: column;
  gap: 64px;
}

.top-block {
  animation: fadeIn 0.5s ease;
}

.top-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 0 4px;
}

.top-head__left {
  display: flex;
  align-items: baseline;
  gap: 12px;
}

.top-head h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.top-count {
  padding: 3px 9px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.top-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--accent-light);
  font-size: 13px;
  font-weight: 700;
  transition: all 0.2s;
}

.top-link:hover {
  color: #c4b5fd;
  gap: 10px;
}

/* === ОБЩЕЕ ДЛЯ СПИСКОВ === */

.players-list,
.clans-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-row,
.clan-row {
  position: relative;
  display: grid;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: all 0.22s ease;
  overflow: hidden;
}

.player-row::before,
.clan-row::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: var(--accent);
  opacity: 0;
  transition: opacity 0.22s;
}

.player-row:hover::before,
.clan-row:hover::before {
  opacity: 1;
}

.player-row:hover,
.clan-row:hover {
  background: var(--bg-card-hover);
  border-color: var(--border-hover);
  transform: translateX(4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
}

/* Топ-1/2/3 */
.player-row.top-1,
.clan-row.top-1 {
  border-color: rgba(250, 204, 21, 0.35);
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.06) 0%, var(--bg-card) 40%);
}

.player-row.top-1::before,
.clan-row.top-1::before {
  background: linear-gradient(180deg, #facc15, #f59e0b);
  opacity: 0.8;
}

.player-row.top-2,
.clan-row.top-2 {
  border-color: rgba(192, 192, 192, 0.3);
  background: linear-gradient(90deg, rgba(192, 192, 192, 0.05) 0%, var(--bg-card) 40%);
}

.player-row.top-2::before,
.clan-row.top-2::before {
  background: linear-gradient(180deg, #e5e7eb, #9ca3af);
  opacity: 0.7;
}

.player-row.top-3,
.clan-row.top-3 {
  border-color: rgba(205, 127, 50, 0.3);
  background: linear-gradient(90deg, rgba(205, 127, 50, 0.05) 0%, var(--bg-card) 40%);
}

.player-row.top-3::before,
.clan-row.top-3::before {
  background: linear-gradient(180deg, #cd7f32, #a0522d);
  opacity: 0.7;
}

/* === RANK === */

.rank {
  min-width: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
}

.medal {
  font-size: 22px;
  line-height: 1;
  filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.4));
}

.rank-num {
  font-size: 14px;
  color: var(--text-muted);
  font-weight: 800;
  letter-spacing: -0.5px;
}

/* === PLAYER ROW === */

.player-row {
  grid-template-columns: 44px 48px 1fr 56px 70px;
}

.row-avatar {
  position: relative;
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  border-radius: 12px;
  color: #fff;
  font-size: 18px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.25);
}

.row-avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.player-row.top-1 .row-avatar {
  background: linear-gradient(135deg, #fde047, #f59e0b);
  box-shadow: 0 4px 20px rgba(250, 204, 21, 0.4);
}

.player-row.top-2 .row-avatar {
  background: linear-gradient(135deg, #f3f4f6, #9ca3af);
  box-shadow: 0 4px 20px rgba(192, 192, 192, 0.3);
}

.player-row.top-3 .row-avatar {
  background: linear-gradient(135deg, #e09966, #a0522d);
  box-shadow: 0 4px 20px rgba(205, 127, 50, 0.3);
}

.row-info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.row-name {
  font-size: 15px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row-bar {
  height: 4px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 999px;
  overflow: hidden;
}

.row-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #7c3aed, #a78bfa);
  border-radius: 999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.player-row.top-1 .row-bar-fill {
  background: linear-gradient(90deg, #facc15, #fde047);
}

.player-row.top-2 .row-bar-fill {
  background: linear-gradient(90deg, #9ca3af, #e5e7eb);
}

.player-row.top-3 .row-bar-fill {
  background: linear-gradient(90deg, #a0522d, #cd7f32);
}

.row-tier {
  font-size: 24px;
  font-weight: 900;
  text-align: center;
  letter-spacing: -1px;
  filter: drop-shadow(0 2px 8px currentColor);
  opacity: 0.95;
}

.row-score {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  gap: 1px;
}

.score-value {
  font-size: 17px;
  font-weight: 900;
  color: var(--text);
}

.score-suffix {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-dim);
}

/* === CLAN ROW === */

.clan-row {
  grid-template-columns: 44px 52px 1fr auto;
}

.clan-avatar {
  position: relative;      /* ← чтобы img позиционировался внутри */
  width: 52px;
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  flex-shrink: 0;
  overflow: hidden;        /* ← обрезает картинку по радиусу */
}

.avatar-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.clan-info {
  min-width: 0;
}

.clan-name {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-name .tag {
  color: var(--accent-light);
  margin-right: 4px;
}

.clan-meta {
  font-size: 12px;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clan-meta .sep {
  margin: 0 6px;
  opacity: 0.4;
}

.clan-stats {
  display: flex;
  gap: 28px;
  padding-left: 16px;
  border-left: 1px solid var(--border);
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 48px;
}

.stat-value {
  font-size: 17px;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.5px;
}

.stat-value.power { color: #a78bfa; }
.stat-value.win { color: #4ade80; }
.stat-value.loss { color: #f87171; }

.stat-label {
  font-size: 10px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 700;
  margin-top: 2px;
}

/* === STATES === */

.loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 80px 0;
  color: var(--text-dim);
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(124, 58, 237, 0.15);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.clan-card.highlighted {
  border-color: rgba(250, 204, 21, 0.5);
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.08), var(--bg-card) 50%);
  box-shadow: 0 0 30px rgba(250, 204, 21, 0.08);
}

.clan-card.highlighted::before {
  content: '⭐';
  position: absolute;
  top: 8px;
  right: 10px;
  font-size: 14px;
}
.empty {
  padding: 48px;
  text-align: center;
  color: var(--text-muted);
  font-size: 14px;
  background: var(--bg-card);
  border: 1px dashed var(--border);
  border-radius: 14px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* === АДАПТИВ === */

@media (max-width: 800px) {
  .hero h1 { font-size: 48px; }

  .player-row {
    grid-template-columns: 40px 44px 1fr 48px 60px;
    gap: 12px;
    padding: 12px 16px;
  }

  .row-avatar { width: 42px; height: 42px; font-size: 16px; }

  .clan-row {
    grid-template-columns: 40px 46px 1fr auto;
    gap: 12px;
    padding: 12px 16px;
  }

  .clan-avatar { width: 44px; height: 44px; }

  .clan-stats { gap: 18px; padding-left: 12px; }

  .stat { min-width: 42px; }

  .stat-value { font-size: 15px; }
}

@media (max-width: 600px) {
  .home { width: calc(100% - 24px); padding: 20px 0 60px; }

  .hero {
    padding: 48px 16px 56px;
    margin-bottom: 48px;
    border-radius: 18px;
  }

  .hero h1 { font-size: 38px; letter-spacing: -2px; }

  .hero p { font-size: 14px; }

  .hero-actions { flex-direction: column; }

  .hero-btn { width: 100%; justify-content: center; }

  .top-head h2 { font-size: 18px; }

  .top-count { display: none; }

  .player-row {
    grid-template-columns: 36px 40px 1fr 48px;
    gap: 10px;
    padding: 10px 14px;
  }

  .row-avatar { width: 38px; height: 38px; font-size: 14px; border-radius: 10px; }

  .row-tier { font-size: 20px; }

  .row-score { display: none; }

  .clan-row {
    grid-template-columns: 36px 40px 1fr;
    gap: 10px;
    padding: 10px 14px;
  }

  .clan-avatar { width: 40px; height: 40px; border-radius: 10px; font-size: 16px; }

  .clan-stats { display: none; }

  .medal { font-size: 18px; }
}
</style>