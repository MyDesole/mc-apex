<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/core/auth.js'
import { myClanApi } from '@/services/clan/myClan.js'
import { clansApi } from '@/services/clan/clans.js'

import ClanNewsTab from '@/components/clan/tabs/ClanNewsTab.vue'
import ClanForumTab from '@/components/clan/tabs/ClanForumTab.vue'
import ClanMembersTab from '@/components/clan/tabs/ClanMembersTab.vue'
import ClanApplicationsTab from '@/components/clan/tabs/ClanApplicationsTab.vue'
import ClanWarsTab from '@/components/clan/tabs/ClanWarsTab.vue'
import ClanResourcesTab from '@/components/clan/tabs/ClanResourcesTab.vue'
import ClanEditModal from '@/components/clan/dialogs/ClanEditModal.vue'
import TabTransition from '@/components/core/TabTransition.vue'

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
    await refresh()
  } finally {
    loading.value = false
  }
}

/**
 * Обновляет данные без спиннера.
 *
 * Нужно после действий внутри открытой модалки: если показать loading,
 * дашборд на мгновение исчезает вместе с модалкой, и форма мигает.
 */
async function refresh() {
  const fresh = await myClanApi.dashboard()

  data.value = fresh

  if (!fresh.clan) {
    router.push('/clans')
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
                  :src="clan.avatar_url"
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
          v-if="showSettings && clan"
          :clan="clan"
          :highlight-styles="data.highlight_styles"
          @close="showSettings = false"
          @updated="refresh"
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
          <TabTransition :active="tab">
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
          </TabTransition>
        </div>
      </main>
    </template>
  </div>
</template>

<style scoped>
@import "@/views/clan/MyClanView.css";
</style>
