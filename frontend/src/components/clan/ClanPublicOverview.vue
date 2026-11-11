<script setup>
/**
 * Вкладка «Обзор» публичной страницы клана: о клане и наборе, форма в войнах,
 * активность за неделю, лучшие бойцы и ближайшие мероприятия.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { userLink } from '@/utils/links.js'
import { rankLabel } from '@/utils/playerStyling.js'
import PlayerHead from '@/components/players/PlayerHead.vue'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  clan: { type: Object, required: true },
  showcase: { type: Object, default: null },
  isMember: { type: Boolean, default: false },
})

const emit = defineEmits(['go'])

const RESULT = {
  win: { short: 'П', label: 'Победа' },
  loss: { short: 'П', label: 'Поражение' },
  draw: { short: 'Н', label: 'Ничья' },
}

const form = computed(() => props.showcase?.form ?? [])
// Полоса формы читается слева направо: старые войны слева, свежая — справа
const formStrip = computed(() => [...form.value].reverse())

const streakText = computed(() => {
  const s = props.showcase?.streak
  if (!s || s.count < 2) return ''
  return s.result === 'win' ? `Серия: ${s.count} победы подряд 🔥` : `Серия: ${s.count} поражения подряд`
})

const activity = computed(() => props.showcase?.activity ?? null)
const activityMax = computed(() => Math.max(1, ...(activity.value?.days ?? []).map((d) => d.count)))

const top = computed(() =>
  [...(props.clan.members ?? [])]
    .filter((m) => m.user)
    .sort((a, b) => (b.user.rating_points ?? 0) - (a.user.rating_points ?? 0))
    .slice(0, 5),
)

const upcoming = computed(() => {
  const now = Date.now()

  return (props.clan.events ?? [])
    .filter((e) => e.starts_at && new Date(e.starts_at).getTime() >= now - 3600 * 1000)
    .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))
    .slice(0, 3)
})

function day(iso) {
  return new Date(iso).toLocaleDateString('ru-RU', { weekday: 'short' })
}

function when(iso) {
  return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function shortDate(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) : ''
}
</script>

<template>
  <div class="ov">
    <!-- О клане и набор -->
    <section class="box">
      <h3 class="box__title">О клане</h3>
      <p v-if="clan.description" class="about">{{ clan.description }}</p>
      <p v-else class="muted">Руководство пока ничего о себе не рассказало.</p>

      <div v-if="clan.style?.recruit_text || clan.min_rating > 0" class="recruit">
        <span class="recruit__icon"><AppIcon icon="target" :size="15" /></span>
        <div>
          <b>Кого ищем</b>
          <p v-if="clan.style?.recruit_text">{{ clan.style.recruit_text }}</p>
          <small v-if="clan.min_rating > 0">Заявки — от {{ clan.min_rating }} очков рейтинга. По приглашению порога нет.</small>
        </div>
      </div>
    </section>

    <div class="ov__grid">
      <!-- Форма в войнах -->
      <section class="box">
        <div class="box__head">
          <h3 class="box__title">Форма в войнах</h3>
          <button type="button" class="box__link" @click="emit('go', 'wars')">Войны →</button>
        </div>

        <template v-if="form.length">
          <div class="form">
            <span
                v-for="w in formStrip"
                :key="w.id"
                class="form__cell"
                :class="`form__cell--${w.result}`"
                :title="`${RESULT[w.result].label} ${w.us}:${w.them} против [${w.enemy?.tag ?? '?'}]`"
            >
              {{ w.result === 'win' ? 'W' : w.result === 'loss' ? 'L' : 'D' }}
            </span>
          </div>
          <p v-if="streakText" class="streak">{{ streakText }}</p>

          <ul class="wars">
            <li v-for="w in form.slice(0, 5)" :key="w.id" class="war">
              <span class="war__res" :class="`war__res--${w.result}`">{{ RESULT[w.result].label }}</span>
              <RouterLink v-if="w.enemy" :to="`/clans/${w.enemy.id}`" class="war__enemy">
                <span class="war__avatar" :style="{ background: w.enemy.banner_color || '#7c3aed' }">
                  <img v-if="w.enemy.avatar_url" :src="w.enemy.avatar_url" alt="" />
                  <template v-else>{{ w.enemy.tag?.charAt(0) }}</template>
                </span>
                <span>[{{ w.enemy.tag }}] {{ w.enemy.name }}</span>
              </RouterLink>
              <b class="war__score">{{ w.us }} : {{ w.them }}</b>
              <small class="muted">{{ shortDate(w.at) }}</small>
            </li>
          </ul>
        </template>

        <div v-else class="empty">
          <AppIcon icon="sword" :size="20" />
          <span>Клан ещё не воевал. Первую войну можно объявить во вкладке «Войны».</span>
        </div>
      </section>

      <!-- Активность за неделю -->
      <section class="box">
        <h3 class="box__title">Активность за неделю</h3>

        <div v-if="activity" class="act">
          <div class="act__nums">
            <div><b>{{ activity.checkins }}</b><span>отметок</span></div>
            <div><b>{{ activity.active_members }}</b><span>активных участников</span></div>
          </div>

          <div class="act__bars">
            <div v-for="d in activity.days" :key="d.date" class="act__day" :title="`${d.count} отметок`">
              <span class="act__track"><i :style="{ height: `${Math.max(4, (d.count / activityMax) * 100)}%` }" :class="{ zero: !d.count }" /></span>
              <small>{{ day(d.date) }}</small>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="ov__grid">
      <!-- Лучшие бойцы -->
      <section class="box">
        <div class="box__head">
          <h3 class="box__title">Лучшие бойцы</h3>
          <button type="button" class="box__link" @click="emit('go', 'members')">Все участники →</button>
        </div>

        <ol class="top">
          <li v-for="(m, i) in top" :key="m.id" class="top__row">
            <span class="top__place" :class="`top__place--${i + 1}`">{{ i + 1 }}</span>
            <RouterLink :to="userLink(m.user)" class="top__user">
              <PlayerHead :user="m.user" :size="30" />
              <span class="top__name">{{ m.user.username }}</span>
            </RouterLink>
            <span class="top__tier">{{ rankLabel(m.user) }}</span>
            <b class="top__pts">{{ m.user.rating_points ?? 0 }}</b>
          </li>
        </ol>
      </section>

      <!-- Мероприятия -->
      <section class="box">
        <div class="box__head">
          <h3 class="box__title">Ближайшие мероприятия</h3>
          <button type="button" class="box__link" @click="emit('go', 'events')">Все →</button>
        </div>

        <ul v-if="upcoming.length" class="events">
          <li v-for="e in upcoming" :key="e.id" class="event">
            <span class="event__date">
              <b>{{ new Date(e.starts_at).getDate() }}</b>
              <small>{{ new Date(e.starts_at).toLocaleDateString('ru-RU', { month: 'short' }) }}</small>
            </span>
            <span class="event__info">
              <b>{{ e.title }}</b>
              <small>{{ when(e.starts_at) }}</small>
            </span>
          </li>
        </ul>

        <div v-else class="empty">
          <AppIcon icon="clock" :size="20" />
          <span>Запланированных мероприятий нет.</span>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/ClanPublicOverview.css";
</style>
