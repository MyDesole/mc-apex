<script setup>
/**
 * Публичная страница клана.
 *
 * Раскладка как у профиля игрока: слева шапка, плитки статистики и вкладки,
 * справа колонка с лидером, составом, трофеями и соцсетями.
 */
import PageLoader from '@/components/core/PageLoader.vue'
import { socialHref } from '@/utils/socialLinks.js'
import { confirm as confirmDialog, prompt as promptDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import { userLink } from '@/utils/links.js'
import ClanMembers from '@/components/clan/dialogs/ClanMembers.vue'
import ClanEvents from '@/components/clan/dialogs/ClanEvents.vue'
import ClanWars from '@/components/clan/dialogs/ClanWars.vue'
import ClanApplications from '@/components/clan/dialogs/ClanApplications.vue'
import ClanPublicOverview from '@/components/clan/ClanPublicOverview.vue'
import TabTransition from '@/components/core/TabTransition.vue'
import ClanEditModal from '@/components/clan/dialogs/ClanEditModal.vue'
import PageState from '@/components/core/PageState.vue'
import ShareToChatButton from '@/components/chat/ShareToChatButton.vue'
import ClanInviteDialog from '@/components/clan/dialogs/ClanInviteDialog.vue'
import PlayerHead from '@/components/players/PlayerHead.vue'
import AppIcon from '@/components/core/AppIcon.vue'

const route = useRoute()
const auth = useAuthStore()

const data = ref(null)
const loading = ref(true)
const tab = ref('overview')
const applicationsCount = ref(0)
const showEdit = ref(false)
const showInvite = ref(false)
const showAllTrophies = ref(false)

const clan = computed(() => data.value?.clan)
const showcase = computed(() => data.value?.showcase ?? null)
const isMember = computed(() => data.value?.is_member)
const isLeader = computed(() => clan.value?.leader_id === auth.user?.id)

// Цвет клана: тема оформления, а без неё — цвет баннера
const clanVars = computed(() => {
  const t = clan.value?.style?.theme

  return { '--clan': t?.from || clan.value?.banner_color || '#7c3aed' }
})

/** Может ли текущий игрок управлять кланом: лидер или офицер. */
const canManage = computed(() => {
  if (!isMember.value) return false
  if (isLeader.value) return true

  const me = clan.value?.members?.find((m) => m.user_id === auth.user?.id)
  return me?.role === 'officer'
})

/**
 * Можно ли подать заявку: авторизованный игрок без клана, клан открыт,
 * есть места, рейтинг не ниже порога. Остальное сервер всё равно проверит.
 */
const canApply = computed(() => {
  if (!auth.isAuthenticated || !clan.value) return false
  if (isMember.value || data.value?.application) return false
  if (auth.user?.clan_member) return false
  if (!clan.value.is_open || clan.value.is_banned) return false

  const cap = data.value?.member_cap
  if (cap && data.value?.members_count >= cap) return false

  const min = Number(clan.value.min_rating || 0)
  const mine = auth.user?.rating_points
  if (min > 0 && mine !== undefined && mine !== null && Number(mine) < min) return false

  return true
})

const winrate = computed(() => {
  const w = Number(clan.value?.wins || 0)
  const l = Number(clan.value?.losses || 0)

  return w + l === 0 ? 0 : Math.round((w / (w + l)) * 100)
})

const level = computed(() => showcase.value?.level ?? null)
const levelPercent = computed(() => {
  const l = level.value
  if (!l) return 0
  const span = l.next - l.current
  return span > 0 ? Math.min(100, Math.max(0, Math.round(((l.xp - l.current) / span) * 100))) : 100
})

// Состав для боковой колонки: лидер первым, затем по рейтингу
const roster = computed(() => {
  const order = { leader: 0, officer: 1, member: 2 }

  return [...(clan.value?.members ?? [])]
      .filter((m) => m.user)
      .sort((a, b) => (order[a.role] - order[b.role]) || ((b.user.rating_points ?? 0) - (a.user.rating_points ?? 0)))
})

const trophies = computed(() => showcase.value?.trophies ?? [])
const trophiesDone = computed(() => trophies.value.filter((t) => t.done).length)
const trophiesShown = computed(() => (showAllTrophies.value ? trophies.value : trophies.value.slice(0, 6)))

const ageText = computed(() => {
  const d = showcase.value?.age_days ?? 0
  if (d < 1) return 'основан сегодня'
  return `основан ${d} ${plural(d, ['день', 'дня', 'дней'])} назад`
})

const tabs = computed(() => {
  const list = [
    { id: 'overview', label: 'Обзор' },
    { id: 'members', label: 'Участники', count: data.value?.members_count },
    { id: 'wars', label: 'Войны', count: (data.value?.incoming_wars?.length || 0) + (data.value?.outgoing_wars?.length || 0) || null },
    { id: 'events', label: 'Мероприятия', count: clan.value?.events?.length || null },
  ]

  if (canManage.value) list.push({ id: 'applications', label: 'Заявки', badge: applicationsCount.value || null })

  return list
})

const hasSocials = computed(() => clan.value?.socials && Object.values(clan.value.socials).some((v) => v))

const socialLabels = {
  discord: 'Discord',
  telegram: 'Telegram',
  youtube: 'YouTube',
  vk: 'VK',
  website: 'Сайт',
}

function plural(n, forms) {
  const a = Math.abs(n) % 100
  const b = a % 10
  if (a > 10 && a < 20) return forms[2]
  if (b > 1 && b < 5) return forms[1]
  if (b === 1) return forms[0]
  return forms[2]
}

function isOnline(user) {
  return user?.last_seen_at && Date.now() - new Date(user.last_seen_at).getTime() < 5 * 60 * 1000
}

/** Ошибка загрузки: 404 или прочая — без неё страница оставалась пустой. */
const loadError = ref(null)

async function load() {
  loading.value = !data.value
  loadError.value = null

  try {
    data.value = await clansApi.show(route.params.id)

    if (canManage.value) {
      try {
        const apps = await clansApi.applications(route.params.id)
        applicationsCount.value = apps.applications?.length ?? 0
      } catch {
        applicationsCount.value = 0
      }
    }
  } catch (e) {
    loadError.value = e?.status === 404 ? 'notFound' : 'error'
  } finally {
    loading.value = false
  }
}

async function apply() {
  const fee = clan.value?.entry_fee ?? 0

  // Если вступление платное — предупреждаем до отправки заявки
  if (fee > 0) {
    const ok = await confirmDialog(
        `Вступление в этот клан стоит ${fee} ApexCoin. `
        + 'Деньги спишутся при принятии заявки. Отправить заявку?',
        { title: 'Платное вступление' },
    )

    if (!ok) return
  }

  const message = await promptDialog('Сообщение лидеру (опционально):')
  if (message === null) return
  await clansApi.apply(route.params.id, message)
  await load()
}

async function leave() {
  if (!await confirmDialog('Покинуть клан?')) return
  await clansApi.leave(route.params.id)
  await load()
}

function onApplicationsChanged() {
  applicationsCount.value = 0
  load()
}

onMounted(load)
</script>

<template>
  <PageLoader v-if="loading" />

  <div v-else-if="clan" class="clan-page" :style="clanVars">
    <div class="clan-layout">
      <!-- ================= ОСНОВНАЯ КОЛОНКА ================= -->
      <div class="clan-col">
        <!-- Шапка -->
        <header class="hero" :class="{ 'hero--cover': clan.cover_url }">
          <div
              v-if="clan.cover_url"
              class="hero__cover"
              :style="{ backgroundImage: `url(${clan.cover_url})` }"
              aria-hidden="true"
          />
          <!-- Огромный тег на фоне — как буква тира в профиле -->
          <span class="hero__watermark" aria-hidden="true">{{ clan.tag }}</span>

          <div class="hero__top">
            <span v-if="showcase?.rank" class="hero__rank" :class="{ 'hero__rank--top': showcase.rank.position <= 3 }">
              <AppIcon :icon="showcase.rank.position === 1 ? 'crown' : 'trophy'" :size="13" />
              #{{ showcase.rank.position }} по силе
            </span>
            <ShareToChatButton :path="`/clans/${clan.id}`" label="Поделиться" />
          </div>

          <div class="hero__body">
            <div class="crest">
              <div class="crest__img">
                <img v-if="clan.avatar_url" :src="clan.avatar_url" alt="" />
                <template v-else>{{ clan.tag?.charAt(0) }}</template>
              </div>
              <span v-if="clan.style?.emblem" class="crest__emblem" :title="clan.style.emblem.title">
                {{ clan.style.emblem.glyph }}
              </span>
            </div>

            <div class="hero__title">
              <span class="hero__tag" :style="clan.style?.tag_color ? { color: clan.style.tag_color } : null">[{{ clan.tag }}]</span>
              <h1>{{ clan.name }}</h1>
              <p v-if="clan.style?.motto" class="hero__motto">✦ {{ clan.style.motto }} ✦</p>

              <div class="hero__chips">
                <span class="chip chip--level">{{ clan.level || 1 }} уровень</span>
                <span class="chip" :class="clan.is_open ? 'chip--open' : 'chip--closed'">
                  {{ clan.is_open ? 'Набор открыт' : 'Набор закрыт' }}
                </span>
                <span v-if="clan.entry_fee > 0" class="chip chip--fee">Вход {{ clan.entry_fee }} ₳</span>
                <span class="chip">{{ ageText }}</span>
              </div>
            </div>
          </div>

          <!-- Опыт клана: полоса в духе опыта Minecraft -->
          <div v-if="level" class="xp">
            <div class="xp__row">
              <span>Опыт клана · до {{ level.level + 1 }} уровня осталось {{ Math.max(0, level.next - level.xp) }}</span>
              <span>{{ level.xp }} / {{ level.next }}</span>
            </div>
            <div class="xp__bar"><i :style="{ width: `${levelPercent}%` }" /></div>
          </div>

          <!-- Действия -->
          <div class="hero__actions">
            <button v-if="canApply" class="btn btn--main" @click="apply">Подать заявку</button>

            <span v-else-if="data.application" class="btn btn--wait">
              <AppIcon icon="clock" :size="14" />
              Заявка на рассмотрении
            </span>

            <RouterLink v-if="isMember" to="/my-clan" class="btn btn--main">Открыть «Мой клан»</RouterLink>

            <button v-if="canManage" class="btn" @click="showInvite = true">Пригласить игрока</button>
            <button v-if="isLeader" class="btn" @click="showEdit = true">Настройки</button>
            <button v-if="isMember && !isLeader" class="btn btn--ghost" @click="leave">Покинуть клан</button>
          </div>
        </header>

        <!-- Плитки статистики -->
        <section class="tiles">
          <div class="tile">
            <span class="tile__icon"><AppIcon icon="bolt" :size="16" /></span>
            <b>{{ clan.power }}</b>
            <span>сила</span>
          </div>
          <div class="tile">
            <span class="tile__icon"><AppIcon icon="handshake" :size="16" /></span>
            <b>{{ data.members_count }}<small>/{{ data.member_cap }}</small></b>
            <span>участников</span>
          </div>
          <div class="tile">
            <span class="tile__icon"><AppIcon icon="sword" :size="16" /></span>
            <b><em class="win">{{ clan.wins }}</em><small class="tile__sep">/</small><em class="loss">{{ clan.losses }}</em></b>
            <span>войны В / П</span>
          </div>
          <div class="tile">
            <span class="tile__icon"><AppIcon icon="chart" :size="16" /></span>
            <b>{{ winrate }}%</b>
            <span>винрейт</span>
          </div>
        </section>

        <!-- Вкладки -->
        <nav class="tabs">
          <button
              v-for="t in tabs"
              :key="t.id"
              type="button"
              :class="{ active: tab === t.id }"
              @click="tab = t.id"
          >
            {{ t.label }}
            <span v-if="t.count" class="tabs__count">{{ t.count }}</span>
            <span v-if="t.badge" class="tabs__badge">{{ t.badge }}</span>
          </button>
        </nav>

        <TabTransition :active="tab">
          <ClanPublicOverview
              v-if="tab === 'overview'"
              :clan="clan"
              :showcase="showcase"
              :is-member="isMember"
              @go="tab = $event"
          />

          <ClanMembers
              v-else-if="tab === 'members'"
              :clan="clan"
              :is-leader="isLeader"
              :can-manage="canManage"
              @refresh="load"
          />

          <ClanWars
              v-else-if="tab === 'wars'"
              :clan="clan"
              :incoming="data.incoming_wars"
              :outgoing="data.outgoing_wars"
              :is-member="isMember"
              :is-leader="isLeader"
              :showcase="showcase"
              @refresh="load"
          />

          <ClanEvents
              v-else-if="tab === 'events'"
              :clan="clan"
              :can-manage="canManage"
          />

          <ClanApplications
              v-else-if="tab === 'applications'"
              :clan="clan"
              :can-manage="canManage"
              @refresh="onApplicationsChanged"
          />
        </TabTransition>
      </div>

      <!-- ================= БОКОВАЯ КОЛОНКА ================= -->
      <aside class="side">
        <!-- Лидер -->
        <section v-if="clan.leader" class="card">
          <h3 class="card__title">Лидер</h3>
          <RouterLink :to="userLink(clan.leader)" class="leader">
            <PlayerHead :user="clan.leader" :size="44" />
            <span class="leader__info">
              <b>{{ clan.leader.username }}</b>
              <small>
                <i class="dot" :class="{ 'dot--on': isOnline(clan.leader) }" />
                {{ isOnline(clan.leader) ? 'в сети' : 'не в сети' }} · {{ clan.leader.rating_points ?? 0 }} очк.
              </small>
            </span>
            <AppIcon icon="crown" :size="16" class="leader__crown" />
          </RouterLink>
        </section>

        <!-- Состав -->
        <section class="card">
          <div class="card__head">
            <h3 class="card__title">Состав <span class="count">{{ data.members_count }}</span></h3>
            <button type="button" class="card__link" @click="tab = 'members'">Все →</button>
          </div>
          <div class="roster">
            <RouterLink
                v-for="m in roster.slice(0, 15)"
                :key="m.id"
                :to="userLink(m.user)"
                class="roster__item"
                :class="`roster__item--${m.role}`"
                :title="`${m.user.username}${m.role === 'leader' ? ' · лидер' : m.role === 'officer' ? ' · офицер' : ''}`"
            >
              <PlayerHead :user="m.user" :size="40" />
              <i v-if="isOnline(m.user)" class="roster__online" />
            </RouterLink>
          </div>
          <p v-if="data.members_count > 15" class="card__more">и ещё {{ data.members_count - 15 }}</p>
        </section>

        <!-- Трофеи -->
        <section v-if="trophies.length" class="card">
          <div class="card__head">
            <h3 class="card__title">Трофеи <span class="count">{{ trophiesDone }}/{{ trophies.length }}</span></h3>
          </div>
          <ul class="trophies">
            <li v-for="t in trophiesShown" :key="t.key" class="trophy" :class="{ 'trophy--locked': !t.done }">
              <span class="trophy__icon"><AppIcon :icon="t.icon" :size="15" /></span>
              <span class="trophy__text">
                <b>{{ t.title }}</b>
                <small>{{ t.text }}</small>
              </span>
              <AppIcon v-if="t.done" icon="check" :size="14" class="trophy__ok" />
            </li>
          </ul>
          <button v-if="trophies.length > 6" type="button" class="card__btn" @click="showAllTrophies = !showAllTrophies">
            {{ showAllTrophies ? 'Свернуть' : `Показать все (${trophies.length})` }}
          </button>
        </section>

        <!-- Соцсети -->
        <section v-if="hasSocials" class="card">
          <h3 class="card__title">Соцсети</h3>
          <div class="socials">
            <component
                :is="socialHref(key, url) ? 'a' : 'span'"
                v-for="(url, key) in clan.socials"
                v-show="url"
                :key="key"
                :href="socialHref(key, url) || undefined"
                :target="socialHref(key, url) ? '_blank' : undefined"
                :rel="socialHref(key, url) ? 'noopener' : undefined"
                class="social"
                :class="key"
                :title="socialHref(key, url) ? socialLabels[key] : `${socialLabels[key]}: ${url}`"
            >
              {{ socialLabels[key] }}
            </component>
          </div>
        </section>
      </aside>
    </div>

    <ClanEditModal
        v-if="showEdit"
        :clan="clan"
        @close="showEdit = false"
        @updated="load"
    />

    <ClanInviteDialog v-if="showInvite" :clan="clan" @close="showInvite = false" />
  </div>

  <PageState
      v-else
      :kind="loadError || 'notFound'"
      :title="loadError === 'error' ? null : 'Клан не найден'"
      :text="loadError === 'error' ? null : 'Возможно, клан распущен или переименован.'"
      :action="{ to: '/clans', label: 'Все кланы' }"
  />
</template>

<style scoped>
@import "@/views/clan/ClanView.css";
</style>
