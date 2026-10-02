<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { myClanApi } from '@/services/myClan.js'
import { clansApi } from '@/services/clans.js'

import ClanNewsTab from '@/components/my-clan/ClanNewsTab.vue'
import ClanForumTab from '@/components/my-clan/ClanForumTab.vue'
import ClanMembersTab from '@/components/my-clan/ClanMembersTab.vue'
import ClanApplicationsTab from '@/components/my-clan/ClanApplicationsTab.vue'
import ClanWarsTab from '@/components/my-clan/ClanWarsTab.vue'
import ClanResourcesTab from '@/components/my-clan/ClanResourcesTab.vue'
import ClanEditModal from '@/components/clan/ClanEditModal.vue'

const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const data = ref(null)
const tab = ref('forum')
const leaving = ref(false)
const leaveError = ref('')
const showSettings = ref(false)

const clan = computed(() => data.value?.clan)
const permissions = computed(() => data.value?.my_permissions ?? {})

const isLeader = computed(() => data.value?.my_role === 'leader')

// Сколько всего участников: подсказывает, можно ли распустить клан
const membersCount = computed(() => data.value?.stats?.members ?? 0)

// Настройки клана доступны только лидеру (сервер проверяет то же самое)
const canEditClan = computed(() => isLeader.value || permissions.value.edit_clan === true)

// Лидер распускает клан, только если он в нём один
const willDissolve = computed(() => isLeader.value && membersCount.value <= 1)

// Лидер с участниками выйти не может — сначала передача лидерства
const blockedAsLeader = computed(() => isLeader.value && membersCount.value > 1)

// Кнопку показываем всем, кроме заблокированного лидера: ему вместо
// кнопки выводим подсказку, куда идти за передачей лидерства
const canLeave = computed(() => !blockedAsLeader.value && !!data.value?.my_role)

async function leaveClan() {
  if (leaving.value) return

  const question = willDissolve.value
      ? 'Вы единственный участник клана. Клан будет РАСПУЩЕН вместе с форумом и ресурсами. Продолжить?'
      : 'Покинуть клан? Вернуться можно будет только по новой заявке.'

  if (!await confirmDialog(question)) return

  leaving.value = true
  leaveError.value = ''

  try {
    await clansApi.leave(clan.value.id)

    // Обновляем профиль: в шапке сайта есть тег клана
    await auth.fetchMe()

    router.push('/clans')
  } catch (e) {
    // Показываем сообщение сервера: оно объясняет и про передачу лидерства
    leaveError.value = e.message || 'Не удалось покинуть клан.'
  } finally {
    leaving.value = false
  }
}

function goToMembers() {
  tab.value = 'members'
}

const tabs = computed(() => {
  const base = [
    { id: 'forum', label: 'Форум' },
    { id: 'resources', label: 'Ресурсы' },
    { id: 'members', label: 'Участники' },
    { id: 'news', label: 'Новости' },
    { id: 'wars', label: 'Войны', highlight: (data.value?.stats?.wars_active || 0) > 0 },
  ]

  // Заявки — только для тех, кто может их принимать (лидер/офицер)
  if (permissions.value.applications) {
    base.splice(3, 0, {
      id: 'applications',
      label: 'Заявки',
      badge: data.value?.stats?.applications || null,
    })
  }

  return base
})

async function load() {
  loading.value = true
  try {
    data.value = await myClanApi.dashboard()

    if (!data.value.clan) {
      router.push('/clans')
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="my-clan-page">
    <!-- Loading -->
    <div v-if="loading" class="clan-loading">
      <div class="loading-orbit">
        <span></span>
        <span></span>
        <span></span>
      </div>

      <div class="loading-title">Загружаем клан</div>
      <div class="loading-text">Получаем актуальную информацию…</div>
    </div>

    <template v-else-if="clan">
      <!-- HERO -->
      <section
          class="clan-hero"
          :style="{ '--clan-color': clan.banner_color || '#8b5cf6' }"
      >
        <div class="hero-noise"></div>
        <div class="hero-glow hero-glow--one"></div>
        <div class="hero-glow hero-glow--two"></div>

        <div class="hero-top">
          <div class="hero-identity">
            <!-- Avatar -->
            <div class="clan-crest">
              <div class="crest-glow"></div>

              <img
                  v-if="clan.avatar"
                  :src="clan.avatar"
                  :alt="clan.name"
                  class="crest-image"
              />

              <div v-else class="crest-fallback">
                {{ clan.tag?.charAt(0) || clan.name?.charAt(0) || '?' }}
              </div>
            </div>

            <!-- Identity -->
            <div class="identity-content">
              <div class="identity-eyebrow">
                <span class="status-dot"></span>
                MY CLAN
              </div>

              <h1 class="clan-name">
                <span class="clan-tag">[{{ clan.tag }}]</span>
                {{ clan.name }}
              </h1>

              <div class="identity-meta">
                <span
                    class="role-badge"
                    :class="{ 'role-badge--leader': isLeader }"
                >
                  <svg
                      v-if="isLeader"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                  >
                    <path d="M12 2l3 6 6 .9-4.5 4.4 1.1 6.2L12 16.5 6.4 19.5l1.1-6.2L3 8.9 9 8z" />
                  </svg>

                  <svg
                      v-else
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                  >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>

                  {{ isLeader ? 'Лидер' : 'Участник' }}
                </span>

                <span class="identity-separator"></span>

                <span class="identity-caption">
                  {{ membersCount }} участников
                </span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="hero-actions">
            <button
                v-if="canEditClan"
                type="button"
                class="action-button action-button--secondary"
                @click="showSettings = true"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path
                    d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
                />
                <path
                    d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.7 1.7-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-2.4v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.7-1.7.1-.1A1.7 1.7 0 0 0 8.4 15a1.7 1.7 0 0 0-1.5-1H6.7v-2.4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L8 8.6l1.7-1.7.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2h2.4v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.7 1.7-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2V14h-.2a1.7 1.7 0 0 0-1.5 1Z"
                />
              </svg>

              Настройки
            </button>

            <button
                v-if="canLeave"
                type="button"
                class="action-button action-button--danger"
                :disabled="leaving"
                @click="leaveClan"
            >
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
              >
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <path d="M16 17l5-5-5-5" />
                <path d="M21 12H9" />
              </svg>

              {{ leaving ? 'Выходим…' : 'Покинуть' }}
            </button>
          </div>
        </div>

        <!-- Leader warning -->
        <div v-if="blockedAsLeader" class="leader-notice">
          <div class="notice-icon">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path d="M12 9v4" />
              <path d="M12 17h.01" />
              <path d="M10.3 3.8 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z" />
            </svg>
          </div>

          <div class="notice-content">
            <strong>Вы лидер этого клана</strong>
            <span>
              Перед выходом необходимо передать лидерство другому участнику.
            </span>
          </div>

          <button
              type="button"
              class="notice-button"
              @click="goToMembers"
          >
            Управление участниками
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
              <path d="M5 12h14" />
              <path d="m13 6 6 6-6 6" />
            </svg>
          </button>
        </div>

        <!-- Leave error -->
        <div v-if="leaveError" class="leave-error">
          <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
          >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v5" />
            <path d="M12 16h.01" />
          </svg>

          {{ leaveError }}
        </div>

        <!-- Stats -->
        <div class="hero-stats">
          <div class="stat-card">
            <div class="stat-icon">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>

            <div class="stat-data">
              <span class="stat-value">{{ membersCount }}</span>
              <span class="stat-label">Участников</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="m12 2 3 7h7l-5.5 4.2 2 7-6.5-4.1L5.5 20l2-6.8L2 9h7z" />
              </svg>
            </div>

            <div class="stat-data">
              <span class="stat-value">
                {{ data.stats?.power ?? 0 }}
              </span>
              <span class="stat-label">Мощность</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon stat-icon--success">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M20 6 9 17l-5-5" />
              </svg>
            </div>

            <div class="stat-data">
              <span class="stat-value">
                {{ data.stats?.wins ?? 0 }}
              </span>
              <span class="stat-label">Побед</span>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon stat-icon--danger">
              <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="m6 6 12 12" />
                <path d="m18 6-12 12" />
              </svg>
            </div>

            <div class="stat-data">
              <span class="stat-value">
                {{ data.stats?.losses ?? 0 }}
              </span>
              <span class="stat-label">Поражений</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Settings -->
      <ClanEditModal
          v-if="showSettings"
          :clan="clan"
          :highlight-styles="data.highlight_styles"
          @close="showSettings = false"
          @saved="load"
      />

      <!-- Navigation -->
      <nav class="clan-tabs" role="tablist">
        <div class="tabs-scroll">
          <button
              v-for="item in tabs"
              :key="item.id"
              type="button"
              class="tab-button"
              :class="{
              'tab-button--active': tab === item.id,
              'tab-button--highlight': item.highlight,
            }"
              role="tab"
              :aria-selected="tab === item.id"
              @click="tab = item.id"
          >
            <span class="tab-icon">
              <!-- Forum -->
              <svg
                  v-if="item.id === 'forum'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.4 9.4 0 0 1-4-.9L3 21l1.9-4.2A8.3 8.3 0 0 1 3 11.5 8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5Z" />
              </svg>

              <!-- Resources -->
              <svg
                  v-else-if="item.id === 'resources'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H21" />
                <path d="M6.5 2H21v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z" />
                <path d="M8 6h9" />
                <path d="M8 10h7" />
              </svg>

              <!-- Members -->
              <svg
                  v-else-if="item.id === 'members'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>

              <!-- News -->
              <svg
                  v-else-if="item.id === 'news'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M4 4h16v16H4z" />
                <path d="M8 8h8" />
                <path d="M8 12h8" />
                <path d="M8 16h5" />
              </svg>

              <!-- Applications -->
              <svg
                  v-else-if="item.id === 'applications'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="M12 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                <path d="m15 15 4 4" />
                <path d="M7 3h10a2 2 0 0 1 2 2v2" />
                <path d="M5 7V5a2 2 0 0 1 2-2" />
              </svg>

              <!-- Wars -->
              <svg
                  v-else-if="item.id === 'wars'"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <path d="m14 4 6 6" />
                <path d="m5 19 9-9" />
                <path d="m14 4 3-1 4 4-1 3" />
                <path d="m5 19-3 1 1-3 9-9" />
              </svg>

              <svg
                  v-else
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
              >
                <circle cx="12" cy="12" r="9" />
              </svg>
            </span>

            <span class="tab-label">{{ item.label }}</span>

            <span
                v-if="item.badge"
                class="tab-badge"
            >
              {{ item.badge }}
            </span>

            <span
                v-if="item.highlight"
                class="tab-live-dot"
            ></span>
          </button>
        </div>
      </nav>

      <!-- Content -->
      <main class="clan-content">
        <div class="content-header">
          <div>
            <div class="content-kicker">CLAN SPACE</div>
            <h2>
              {{
                tabs.find((item) => item.id === tab)?.label || 'Клан'
              }}
            </h2>
          </div>

          <div class="content-status">
            <span class="content-status-dot"></span>
            Онлайн
          </div>
        </div>

        <div class="content-body">
          <ClanForumTab v-if="tab === 'forum'" :permissions="permissions" :clan="clan" />

          <ClanResourcesTab
              v-else-if="tab === 'resources'"
              :permissions="permissions"
              :clan="clan"
          />

          <ClanMembersTab
              v-else-if="tab === 'members'"
              :clan="clan"
          />

          <ClanNewsTab
              v-else-if="tab === 'news'"
              :permissions="permissions"
              :clan="clan"
          />

          <ClanApplicationsTab
              v-else-if="tab === 'applications'"
              :clan="clan"
          />

          <ClanWarsTab
              v-else-if="tab === 'wars'"
              :permissions="permissions"
              :clan="clan"
          />
        </div>
      </main>
    </template>
  </div>
</template>

<style scoped>
.my-clan-page {
  --page-bg: var(--bg, #09090b);
  --card-bg: var(--bg-card, #111114);
  --card-bg-soft: rgba(255, 255, 255, 0.035);
  --border-color: var(--border, rgba(255, 255, 255, 0.08));
  --text-main: var(--text, #f4f4f5);
  --text-secondary: var(--text-dim, #a1a1aa);
  --text-muted-local: var(--text-muted, #71717a);
  --accent-color: var(--accent, #8b5cf6);
  --accent-light-color: var(--accent-light, #a78bfa);

  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  padding: 28px 20px 60px;
  color: var(--text-main);
}

/* =========================
   LOADING
========================= */

.clan-loading {
  min-height: 520px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.loading-orbit {
  position: relative;
  width: 58px;
  height: 58px;
  margin-bottom: 22px;
  border: 1px solid rgba(139, 92, 246, 0.2);
  border-radius: 50%;
}

.loading-orbit::before {
  content: '';
  position: absolute;
  inset: 7px;
  border: 1px solid rgba(139, 92, 246, 0.25);
  border-radius: 50%;
}

.loading-orbit span {
  position: absolute;
  width: 7px;
  height: 7px;
  top: -3px;
  left: 50%;
  margin-left: -3.5px;
  border-radius: 50%;
  background: var(--accent-color);
  box-shadow: 0 0 18px var(--accent-color);
  animation: loading-orbit 1.2s linear infinite;
}

.loading-orbit span:nth-child(2) {
  animation-delay: -0.4s;
}

.loading-orbit span:nth-child(3) {
  animation-delay: -0.8s;
}

@keyframes loading-orbit {
  to {
    transform: rotate(360deg) translateX(25px);
  }
}

.loading-title {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.loading-text {
  margin-top: 7px;
  color: var(--text-muted-local);
  font-size: 13px;
}

/* =========================
   HERO
========================= */

.clan-hero {
  --clan-color: #8b5cf6;

  position: relative;
  overflow: hidden;
  padding: 28px;
  border: 1px solid var(--border-color);
  border-radius: 24px;
  background:
      radial-gradient(
          circle at 10% 0%,
          color-mix(in srgb, var(--clan-color) 17%, transparent),
          transparent 35%
      ),
      radial-gradient(
          circle at 90% 100%,
          color-mix(in srgb, var(--clan-color) 12%, transparent),
          transparent 38%
      ),
      linear-gradient(
          145deg,
          rgba(255, 255, 255, 0.055),
          rgba(255, 255, 255, 0.018)
      ),
      var(--card-bg);
  box-shadow:
      0 24px 80px rgba(0, 0, 0, 0.24),
      inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.hero-noise {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.025;
  background-image:
      radial-gradient(rgba(255, 255, 255, 0.9) 0.6px, transparent 0.6px);
  background-size: 5px 5px;
}

.hero-glow {
  position: absolute;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(90px);
  pointer-events: none;
  opacity: 0.12;
}

.hero-glow--one {
  top: -180px;
  left: 15%;
  background: var(--clan-color);
}

.hero-glow--two {
  right: -180px;
  bottom: -180px;
  background: var(--accent-color);
}

.hero-top {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 30px;
}

.hero-identity {
  display: flex;
  align-items: center;
  min-width: 0;
  gap: 20px;
}

.clan-crest {
  position: relative;
  flex: 0 0 auto;
  width: 104px;
  height: 104px;
  display: grid;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--clan-color) 45%, white 8%);
  border-radius: 28px;
  background:
      linear-gradient(
          145deg,
          color-mix(in srgb, var(--clan-color) 18%, transparent),
          rgba(255, 255, 255, 0.035)
      );
  box-shadow:
      0 0 0 6px rgba(255, 255, 255, 0.025),
      0 18px 50px color-mix(in srgb, var(--clan-color) 14%, transparent);
}

.crest-glow {
  position: absolute;
  inset: 10px;
  border-radius: 20px;
  background: var(--clan-color);
  opacity: 0.12;
  filter: blur(18px);
}

.crest-image {
  position: relative;
  z-index: 1;
  width: 76px;
  height: 76px;
  object-fit: cover;
  border-radius: 19px;
}

.crest-fallback {
  position: relative;
  z-index: 1;

  display: grid;
  place-items: center;

  width: 76px;
  height: 76px;

  border-radius: 19px;

  background:
      linear-gradient(
          145deg,
          color-mix(in srgb, var(--clan-color) 80%, white 5%),
          color-mix(in srgb, var(--clan-color) 35%, black 30%)
      );

  color: #fff;
  font-size: 30px;
  font-weight: 850;
  letter-spacing: -0.05em;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.identity-content {
  min-width: 0;
}

.identity-eyebrow {
  display: flex;
  align-items: center;
  gap: 7px;

  margin-bottom: 8px;

  color: var(--text-muted-local);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.16em;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #34d399;
  box-shadow: 0 0 10px rgba(52, 211, 153, 0.65);
}

.clan-name {
  margin: 0;
  max-width: 650px;

  color: #fff;
  font-size: clamp(27px, 4vw, 40px);
  line-height: 1.05;
  font-weight: 850;
  letter-spacing: -0.045em;

  overflow-wrap: anywhere;
}

.clan-tag {
  color: color-mix(in srgb, var(--clan-color) 82%, white 12%);
}

.identity-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  padding: 6px 10px;

  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;

  background: rgba(255, 255, 255, 0.045);

  color: #d4d4d8;
  font-size: 11px;
  font-weight: 700;
}

.role-badge svg {
  width: 13px;
  height: 13px;
}

.role-badge--leader {
  border-color: color-mix(in srgb, var(--clan-color) 35%, transparent);
  background: color-mix(in srgb, var(--clan-color) 12%, transparent);
  color: var(--accent-light-color);
}

.identity-separator {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #52525b;
}

.identity-caption {
  color: var(--text-muted-local);
  font-size: 12px;
}

/* =========================
   ACTIONS
========================= */

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 9px;
}

.action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  min-height: 40px;
  padding: 0 14px;

  border: 1px solid transparent;
  border-radius: 11px;

  font-family: inherit;
  font-size: 12px;
  font-weight: 750;

  cursor: pointer;
  transition:
      transform 0.18s ease,
      background 0.18s ease,
      border-color 0.18s ease,
      color 0.18s ease;
}

.action-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.action-button svg {
  width: 15px;
  height: 15px;
}

.action-button--secondary {
  border-color: rgba(255, 255, 255, 0.1);
  background: rgba(255, 255, 255, 0.045);
  color: #d4d4d8;
}

.action-button--secondary:hover:not(:disabled) {
  border-color: rgba(255, 255, 255, 0.16);
  background: rgba(255, 255, 255, 0.075);
  color: #fff;
}

.action-button--danger {
  border-color: rgba(248, 113, 113, 0.13);
  background: rgba(248, 113, 113, 0.055);
  color: #fca5a5;
}

.action-button--danger:hover:not(:disabled) {
  border-color: rgba(248, 113, 113, 0.25);
  background: rgba(248, 113, 113, 0.1);
  color: #fecaca;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* =========================
   NOTICES
========================= */

.leader-notice {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: center;
  gap: 13px;

  margin-top: 25px;
  padding: 13px 14px;

  border: 1px solid rgba(251, 191, 36, 0.14);
  border-radius: 15px;

  background: rgba(251, 191, 36, 0.055);
}

.notice-icon {
  flex: 0 0 auto;

  width: 34px;
  height: 34px;

  display: grid;
  place-items: center;

  border-radius: 10px;

  background: rgba(251, 191, 36, 0.1);
  color: #fbbf24;
}

.notice-icon svg {
  width: 17px;
  height: 17px;
}

.notice-content {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.notice-content strong {
  font-size: 12px;
  font-weight: 750;
  color: #fde68a;
}

.notice-content span {
  color: #a1a1aa;
  font-size: 11px;
  line-height: 1.4;
}

.notice-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;

  margin-left: auto;
  flex: 0 0 auto;

  padding: 7px 10px;

  border: 0;
  background: transparent;

  color: #fcd34d;
  font: inherit;
  font-size: 11px;
  font-weight: 750;

  cursor: pointer;
}

.notice-button svg {
  width: 13px;
  height: 13px;
}

.notice-button:hover {
  color: #fef3c7;
}

.leave-error {
  position: relative;
  z-index: 2;

  display: flex;
  align-items: center;
  gap: 8px;

  margin-top: 12px;
  padding: 10px 12px;

  border: 1px solid rgba(248, 113, 113, 0.15);
  border-radius: 11px;

  background: rgba(248, 113, 113, 0.055);
  color: #fca5a5;

  font-size: 11px;
}

.leave-error svg {
  flex: 0 0 auto;
  width: 15px;
  height: 15px;
}

/* =========================
   STATS
========================= */

.hero-stats {
  position: relative;
  z-index: 2;

  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 9px;

  margin-top: 25px;
  padding-top: 24px;

  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.stat-card {
  min-width: 0;

  display: flex;
  align-items: center;
  gap: 11px;

  padding: 13px;

  border: 1px solid rgba(255, 255, 255, 0.055);
  border-radius: 14px;

  background: rgba(0, 0, 0, 0.14);
}

.stat-icon {
  flex: 0 0 auto;

  width: 34px;
  height: 34px;

  display: grid;
  place-items: center;

  border-radius: 10px;

  background: rgba(139, 92, 246, 0.09);
  color: var(--accent-light-color);
}

.stat-icon svg {
  width: 16px;
  height: 16px;
}

.stat-icon--success {
  background: rgba(52, 211, 153, 0.08);
  color: #6ee7b7;
}

.stat-icon--danger {
  background: rgba(248, 113, 113, 0.08);
  color: #fca5a5;
}

.stat-data {
  min-width: 0;

  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat-value {
  color: #f4f4f5;
  font-size: 16px;
  line-height: 1;
  font-weight: 800;
  letter-spacing: -0.03em;

  overflow: hidden;
  text-overflow: ellipsis;
}

.stat-label {
  color: var(--text-muted-local);
  font-size: 10px;
  font-weight: 600;
}

/* =========================
   TABS
========================= */

.clan-tabs {
  position: sticky;
  top: 12px;
  z-index: 20;

  margin-top: 18px;
  padding: 5px;

  border: 1px solid var(--border-color);
  border-radius: 15px;

  background: rgba(14, 14, 17, 0.84);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);

  box-shadow:
      0 10px 35px rgba(0, 0, 0, 0.16),
      inset 0 1px 0 rgba(255, 255, 255, 0.025);
}

.tabs-scroll {
  display: flex;
  gap: 3px;
  overflow-x: auto;
  scrollbar-width: none;
}

.tabs-scroll::-webkit-scrollbar {
  display: none;
}

.tab-button {
  position: relative;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;

  min-height: 38px;
  padding: 0 12px;

  flex: 0 0 auto;

  border: 0;
  border-radius: 10px;

  background: transparent;
  color: var(--text-muted-local);

  font-family: inherit;
  font-size: 11px;
  font-weight: 700;

  cursor: pointer;

  transition:
      color 0.18s ease,
      background 0.18s ease;
}

.tab-button:hover {
  color: #d4d4d8;
  background: rgba(255, 255, 255, 0.035);
}

.tab-button--active {
  background: rgba(255, 255, 255, 0.075);
  color: #fff;
  box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.045),
      0 3px 12px rgba(0, 0, 0, 0.12);
}

.tab-icon {
  display: grid;
  place-items: center;
}

.tab-icon svg {
  width: 15px;
  height: 15px;
}

.tab-button--active .tab-icon {
  color: var(--accent-light-color);
}

.tab-label {
  white-space: nowrap;
}

.tab-badge {
  min-width: 18px;
  height: 18px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  padding: 0 5px;

  border-radius: 999px;

  background: rgba(139, 92, 246, 0.15);
  color: var(--accent-light-color);

  font-size: 9px;
  font-weight: 800;
}

.tab-live-dot {
  width: 5px;
  height: 5px;
  margin-left: -2px;

  border-radius: 50%;
  background: #f87171;
  box-shadow: 0 0 8px rgba(248, 113, 113, 0.8);

  animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-dot {
  50% {
    opacity: 0.4;
    transform: scale(0.7);
  }
}

/* =========================
   CONTENT
========================= */

.clan-content {
  margin-top: 18px;

  border: 1px solid var(--border-color);
  border-radius: 20px;

  background: var(--card-bg);

  box-shadow:
      0 18px 60px rgba(0, 0, 0, 0.14),
      inset 0 1px 0 rgba(255, 255, 255, 0.02);

  overflow: hidden;
}

.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;

  min-height: 76px;
  padding: 18px 22px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.055);
  background: rgba(255, 255, 255, 0.012);
}

.content-kicker {
  margin-bottom: 5px;

  color: var(--text-muted-local);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.15em;
}

.content-header h2 {
  margin: 0;

  color: #f4f4f5;
  font-size: 17px;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.content-status {
  display: flex;
  align-items: center;
  gap: 6px;

  color: var(--text-muted-local);
  font-size: 10px;
  font-weight: 650;
}

.content-status-dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: #34d399;
  box-shadow: 0 0 9px rgba(52, 211, 153, 0.55);
}

.content-body {
  min-width: 0;
  padding: 20px;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {
  .my-clan-page {
    padding: 20px 14px 45px;
  }

  .clan-hero {
    padding: 22px;
    border-radius: 20px;
  }

  .hero-top {
    flex-direction: column;
    gap: 22px;
  }

  .hero-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .hero-actions .action-button {
    flex: 1;
  }

  .hero-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .my-clan-page {
    padding: 12px 10px 35px;
  }

  .clan-hero {
    padding: 17px;
    border-radius: 18px;
  }

  .hero-identity {
    align-items: flex-start;
    gap: 14px;
  }

  .clan-crest {
    width: 76px;
    height: 76px;
    border-radius: 20px;
  }

  .crest-image,
  .crest-fallback {
    width: 56px;
    height: 56px;
    border-radius: 15px;
  }

  .crest-fallback {
    font-size: 23px;
  }

  .clan-name {
    font-size: 25px;
  }

  .identity-eyebrow {
    margin-bottom: 6px;
    font-size: 9px;
  }

  .identity-meta {
    margin-top: 10px;
  }

  .identity-caption {
    font-size: 10px;
  }

  .hero-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .hero-actions .action-button {
    width: 100%;
    padding: 0 10px;
  }

  .leader-notice {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .notice-content {
    flex: 1;
  }

  .notice-button {
    width: 100%;
    margin-left: 47px;
    justify-content: flex-start;
    padding-left: 0;
  }

  .hero-stats {
    gap: 7px;
    margin-top: 20px;
    padding-top: 20px;
  }

  .stat-card {
    padding: 10px;
    gap: 8px;
  }

  .stat-icon {
    width: 30px;
    height: 30px;
  }

  .stat-icon svg {
    width: 14px;
    height: 14px;
  }

  .stat-value {
    font-size: 14px;
  }

  .stat-label {
    font-size: 9px;
  }

  .clan-tabs {
    margin-top: 12px;
    top: 8px;
    border-radius: 13px;
  }

  .tab-button {
    min-height: 36px;
    padding: 0 10px;
  }

  .tab-label {
    font-size: 10px;
  }

  .clan-content {
    margin-top: 12px;
    border-radius: 16px;
  }

  .content-header {
    min-height: 66px;
    padding: 15px 16px;
  }

  .content-body {
    padding: 12px;
  }
}

@media (max-width: 420px) {
  .hero-actions {
    grid-template-columns: 1fr;
  }

  .hero-identity {
    gap: 12px;
  }

  .clan-crest {
    width: 68px;
    height: 68px;
  }

  .crest-image,
  .crest-fallback {
    width: 50px;
    height: 50px;
  }

  .clan-name {
    font-size: 22px;
  }

  .identity-separator {
    display: none;
  }

  .identity-meta {
    gap: 5px;
  }

  .hero-stats {
    grid-template-columns: 1fr 1fr;
  }

  .stat-card {
    min-height: 58px;
  }
}
</style>