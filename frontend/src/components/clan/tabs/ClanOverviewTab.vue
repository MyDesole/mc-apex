<script setup>
/**
 * «Обзор» клана: ежедневная отметка и уровень, объявление руководства,
 * цели недели, ближайшие войны, лучшие бойцы и лента активности.
 */
import { computed, onMounted, ref } from 'vue'
import { myClanApi } from '@/services/clan/myClan.js'
import { refreshClanPulse } from '@/composables/clan/useClanPulse.js'
import PlayerHead from '@/components/players/PlayerHead.vue'

const props = defineProps({
  clan: { type: Object, required: true },
})

// Переход на другую вкладку «Моего клана»
const emit = defineEmits(['go'])

const loading = ref(true)
const error = ref('')
const data = ref(null)

const checkingIn = ref(false)
const checkinMessage = ref('')

const editingMotd = ref(false)
const motdDraft = ref('')
const savingMotd = ref(false)
const motdError = ref('')

const linkCopied = ref(false)
const showPerks = ref(false)

const MOTD_MAX = 500

const level = computed(() => data.value?.level)
const levelPercent = computed(() => {
  const l = level.value
  if (!l) return 0
  const span = l.next - l.current
  return span > 0 ? Math.min(100, Math.round(((l.xp - l.current) / span) * 100)) : 100
})

const checkin = computed(() => data.value?.checkin)

const ROLE_LABELS = { leader: 'Лидер', officer: 'Офицер', member: 'Участник' }

const FEED_ICONS = {
  join: '＋',
  donation: '◆',
  war_win: '★',
  war_loss: '✕',
  war_draw: '＝',
  topic: '✎',
  news: '✦',
}

async function load() {
  loading.value = true
  error.value = ''

  try {
    data.value = await myClanApi.overview()
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить обзор клана.'
  } finally {
    loading.value = false
  }
}

async function checkIn() {
  if (checkingIn.value || checkin.value?.done) return

  checkingIn.value = true
  checkinMessage.value = ''

  try {
    const result = await myClanApi.checkIn()
    checkinMessage.value = result.message
    await load()
    refreshClanPulse()
  } catch (e) {
    checkinMessage.value = e.message || 'Не удалось отметиться.'
  } finally {
    checkingIn.value = false
  }
}

function startMotdEdit() {
  motdDraft.value = data.value?.motd?.text || ''
  motdError.value = ''
  editingMotd.value = true
}

async function saveMotd(text = motdDraft.value) {
  savingMotd.value = true
  motdError.value = ''

  try {
    const result = await myClanApi.updateMotd(text)
    data.value.motd = result.motd
    editingMotd.value = false
  } catch (e) {
    motdError.value = e.message || 'Не удалось сохранить объявление.'
  } finally {
    savingMotd.value = false
  }
}

async function copyInvite() {
  const url = `${window.location.origin}/clans/${props.clan.id}`

  try {
    await navigator.clipboard.writeText(url)
  } catch {
    window.prompt('Скопируйте ссылку на клан:', url)
  }

  linkCopied.value = true
  setTimeout(() => { linkCopied.value = false }, 2000)
}

function goalPercent(goal) {
  return goal.target > 0 ? Math.min(100, Math.round((goal.value / goal.target) * 100)) : 0
}

function timeAgo(value) {
  if (!value) return ''

  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (diff < 60) return 'только что'
  if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`
  if (diff < 86400) return `${Math.floor(diff / 3600)} ч назад`
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)} дн назад`

  return new Date(value).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

function formatDate(value) {
  if (!value) return 'Время не назначено'

  return new Date(value).toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const resetsIn = computed(() => {
  const at = data.value?.goals?.resets_at
  if (!at) return ''
  const days = Math.max(0, Math.ceil((new Date(at).getTime() - Date.now()) / 86400000))
  return days <= 1 ? 'обновятся завтра' : `обновятся через ${days} дн`
})

onMounted(load)
</script>

<template>
  <div class="overview">
    <div v-if="loading && !data" class="overview-state">Загружаем обзор…</div>
    <div v-else-if="error && !data" class="overview-state overview-state--error">{{ error }}</div>

    <template v-else-if="data">
      <!-- Отметка и уровень -->
      <div class="ov-row ov-row--top">
        <section class="ov-card checkin" :class="{ 'checkin--done': checkin.done }">
          <div class="ov-card__head">
            <span class="ov-kicker">Ежедневная отметка</span>
            <span v-if="checkin.streak > 0" class="streak" title="Дней подряд">
              <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                <path d="M12 2c1 4-3 6-3 10a3 3 0 0 0 6 0c0-1.5-.6-2.6-1.2-3.6C16 10 18 12.5 18 15.5A6 6 0 0 1 6 15.5C6 9.5 12 7 12 2Z" />
              </svg>
              {{ checkin.streak }} дн подряд
            </span>
          </div>

          <p class="checkin__text">
            {{ checkin.done
              ? 'Вы уже отметились сегодня. Возвращайтесь завтра, чтобы не сбить серию.'
              : `Отмечайтесь каждый день: +10 опыта клану и ${level.bonuses.checkin_coins} ApexCoin в казну.` }}
          </p>

          <button
              type="button"
              class="checkin__button"
              :disabled="checkin.done || checkingIn"
              @click="checkIn"
          >
            <svg v-if="checkin.done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" width="16" height="16">
              <path d="M5 12l5 5L20 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            {{ checkin.done ? 'Отмечено' : checkingIn ? 'Отмечаем…' : 'Отметиться' }}
          </button>

          <p v-if="checkinMessage" class="checkin__message">{{ checkinMessage }}</p>

          <div class="checkin__today">
            <div class="heads">
              <PlayerHead
                  v-for="user in checkin.today"
                  :key="user.id"
                  :user="user"
                  :size="24"
                  :title="user.username"
              />
            </div>
            <span>Сегодня отметились {{ checkin.today_count }} из {{ checkin.members }}</span>
          </div>
        </section>

        <section class="ov-card level">
          <div class="ov-card__head">
            <span class="ov-kicker">Уровень клана</span>
          </div>

          <div class="level__main">
            <div class="level__badge">{{ level.level }}</div>
            <div class="level__info">
              <strong>{{ level.xp }} опыта</strong>
              <span>до {{ level.level + 1 }} уровня — {{ Math.max(0, level.next - level.xp) }}</span>
            </div>
          </div>

          <div class="bar"><i :style="{ width: `${levelPercent}%` }" /></div>

          <ul class="level__hints">
            <li><b>+10</b> опыта за отметку участника</li>
            <li><b>+100</b> за победу в клановой войне</li>
          </ul>

          <div class="level__bonuses">
            <div>
              <b>{{ level.bonuses.member_cap }}</b>
              <span>мест в клане</span>
            </div>
            <div>
              <b>{{ level.bonuses.checkin_coins }}</b>
              <span>₳ в казну за отметку</span>
            </div>
            <div>
              <b>−{{ level.bonuses.treasury_discount }}%</b>
              <span>на покупки из казны</span>
            </div>
          </div>

          <button type="button" class="ov-link level__toggle" @click="showPerks = !showPerks">
            {{ showPerks ? 'Скрыть бонусы уровней' : 'Что дают следующие уровни' }}
          </button>

          <ul v-if="showPerks" class="perks">
            <li
                v-for="perk in level.perks"
                :key="perk.level"
                class="perk"
                :class="{ 'perk--open': perk.unlocked, 'perk--next': perk.level === level.level + 1 }"
            >
              <span class="perk__level">{{ perk.level }}</span>
              <span>{{ perk.text }}</span>
            </li>
          </ul>
        </section>
      </div>

      <!-- Объявление -->
      <section class="ov-card motd">
        <div class="ov-card__head">
          <span class="ov-kicker">Объявление клана</span>
          <button
              v-if="data.can_edit_motd && !editingMotd"
              type="button"
              class="ov-link"
              @click="startMotdEdit"
          >
            {{ data.motd ? 'Изменить' : 'Написать' }}
          </button>
        </div>

        <template v-if="editingMotd">
          <textarea
              v-model="motdDraft"
              class="motd__input"
              :maxlength="MOTD_MAX"
              rows="3"
              placeholder="Например: сбор на КВ в субботу в 18:00, всем быть в Discord"
          />
          <div class="motd__actions">
            <span class="motd__counter">{{ motdDraft.length }}/{{ MOTD_MAX }}</span>
            <button v-if="data.motd" type="button" class="ov-button ov-button--ghost" :disabled="savingMotd" @click="saveMotd('')">
              Убрать
            </button>
            <button type="button" class="ov-button ov-button--ghost" :disabled="savingMotd" @click="editingMotd = false">
              Отмена
            </button>
            <button type="button" class="ov-button" :disabled="savingMotd" @click="saveMotd()">
              {{ savingMotd ? 'Сохраняем…' : 'Сохранить' }}
            </button>
          </div>
          <p v-if="motdError" class="motd__error">{{ motdError }}</p>
        </template>

        <template v-else-if="data.motd">
          <p class="motd__text">{{ data.motd.text }}</p>
          <div v-if="data.motd.author" class="motd__meta">
            <PlayerHead :user="data.motd.author" :size="18" />
            {{ data.motd.author.username }} · {{ timeAgo(data.motd.updated_at) }}
          </div>
        </template>

        <p v-else class="motd__empty">
          {{ data.can_edit_motd
            ? 'Напишите участникам, что сейчас важно: сбор на войну, правила, ссылки.'
            : 'Руководство пока ничего не написало.' }}
        </p>
      </section>

      <!-- Цели недели -->
      <section class="ov-card goals">
        <div class="ov-card__head">
          <span class="ov-kicker">Цели недели</span>
          <span class="ov-muted">{{ resetsIn }}</span>
        </div>

        <div class="goals__list">
          <div
              v-for="goal in data.goals.items"
              :key="goal.id"
              class="goal"
              :class="{ 'goal--done': goal.value >= goal.target }"
          >
            <div class="goal__top">
              <span>{{ goal.label }}</span>
              <b>{{ goal.value }} / {{ goal.target }}</b>
            </div>
            <div class="bar bar--thin"><i :style="{ width: `${goalPercent(goal)}%` }" /></div>
          </div>
        </div>
      </section>

      <div class="ov-row">
        <!-- Войны -->
        <section class="ov-card">
          <div class="ov-card__head">
            <span class="ov-kicker">Клановые войны</span>
            <button type="button" class="ov-link" @click="emit('go', 'wars')">Все войны →</button>
          </div>

          <div v-if="!data.wars.length" class="ov-empty">
            Сейчас войн нет. Бросьте вызов другому клану во вкладке «Войны».
          </div>

          <button
              v-for="war in data.wars"
              :key="war.id"
              type="button"
              class="war-row"
              @click="emit('go', 'wars')"
          >
            <span class="war-row__avatar">
              <img v-if="war.enemy?.avatar_url" :src="war.enemy.avatar_url" :alt="war.enemy.name" />
              <template v-else>{{ war.enemy?.tag?.charAt(0) || '?' }}</template>
            </span>
            <span class="war-row__info">
              <strong>[{{ war.enemy?.tag }}] {{ war.enemy?.name }}</strong>
              <span>{{ formatDate(war.scheduled_at) }} · {{ war.participants }} бойцов</span>
            </span>
            <span class="war-row__status" :class="`war-row__status--${war.status}`">
              {{ war.status === 'accepted' ? 'Принята' : war.incoming ? 'Вас вызвали' : 'Ждёт ответа' }}
            </span>
          </button>
        </section>

        <!-- Лучшие бойцы -->
        <section class="ov-card">
          <div class="ov-card__head">
            <span class="ov-kicker">Лучшие бойцы</span>
            <button type="button" class="ov-link" @click="emit('go', 'members')">Все участники →</button>
          </div>

          <RouterLink
              v-for="(member, index) in data.top_members"
              :key="member.id"
              :to="`/user/${member.username}`"
              class="top-row"
          >
            <span class="top-row__place" :class="`top-row__place--${index + 1}`">{{ index + 1 }}</span>
            <PlayerHead :user="member" :size="28" />
            <span class="top-row__info">
              <strong>{{ member.username }}</strong>
              <span>{{ ROLE_LABELS[member.role] || 'Участник' }}</span>
            </span>
            <b class="top-row__points">{{ member.rating_points }}</b>
          </RouterLink>
        </section>
      </div>

      <div class="ov-row">
        <!-- Лента -->
        <section class="ov-card feed">
          <div class="ov-card__head">
            <span class="ov-kicker">Что происходит в клане</span>
          </div>

          <div v-if="!data.feed.length" class="ov-empty">Пока тихо. Станьте первым — создайте тему на форуме.</div>

          <div v-for="(item, index) in data.feed" :key="index" class="feed-row" :class="`feed-row--${item.type}`">
            <span class="feed-row__icon">{{ FEED_ICONS[item.type] || '•' }}</span>
            <span class="feed-row__text">
              <b v-if="item.user">{{ item.user.username }}</b>
              {{ item.text }}
            </span>
            <span class="feed-row__time">{{ timeAgo(item.at) }}</span>
          </div>
        </section>

        <!-- Быстрые действия -->
        <section class="ov-card quick">
          <div class="ov-card__head">
            <span class="ov-kicker">Быстрые действия</span>
          </div>

          <button type="button" class="quick-row" @click="copyInvite">
            <span class="quick-row__icon">⇪</span>
            <span>
              <strong>{{ linkCopied ? 'Ссылка скопирована' : 'Пригласить друзей' }}</strong>
              <small>Ссылка на страницу клана</small>
            </span>
          </button>

          <button type="button" class="quick-row" @click="emit('go', 'treasury')">
            <span class="quick-row__icon">◆</span>
            <span>
              <strong>Казна: {{ data.treasury }} ApexCoin</strong>
              <small>Скинуться на оформление клана</small>
            </span>
          </button>

          <button type="button" class="quick-row" @click="emit('go', 'forum')">
            <span class="quick-row__icon">✎</span>
            <span>
              <strong>Форум клана</strong>
              <small>Обсудить тактику и сборы</small>
            </span>
          </button>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
@import "./ClanOverviewTab.css";
</style>
