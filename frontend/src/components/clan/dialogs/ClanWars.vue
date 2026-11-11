<script setup>
/**
 * Войны на публичной странице клана: сводка, вызов, активные вызовы
 * карточками «клан против клана» и история последних войн.
 */
import { prompt as promptDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { playSound } from '@/utils/sound.js'
import { useAuthStore } from '@/stores/core/auth.js'
import AppIcon from '@/components/core/AppIcon.vue'

const props = defineProps({
  clan: Object,
  incoming: Array,
  outgoing: Array,
  isMember: Boolean,
  isLeader: Boolean,
  // История и итоги — из витрины клана
  showcase: { type: Object, default: null },
})

const emit = defineEmits(['refresh'])

const auth = useAuthStore()

const challenging = ref(false)
const sending = ref(false)
const error = ref('')
const form = ref({ scheduled_at: '', notes: '' })

// Вызвать этот клан может руководство другого клана
const canChallenge = computed(() => {
  const me = auth.user?.clan_member
  return Boolean(me && !props.isMember && ['leader', 'officer'].includes(me.role))
})

const total = computed(() => Number(props.clan?.wins || 0) + Number(props.clan?.losses || 0))
const winrate = computed(() => (total.value ? Math.round((props.clan.wins / total.value) * 100) : 0))
const history = computed(() => props.showcase?.form ?? [])

const active = computed(() => [
  ...(props.incoming ?? []).map((w) => ({ war: w, enemy: w.challenger, incoming: true })),
  ...(props.outgoing ?? []).map((w) => ({ war: w, enemy: w.opponent, incoming: false })),
])

const STATUS = {
  pending: 'Ждёт ответа',
  accepted: 'Принята',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}

async function challenge() {
  sending.value = true
  error.value = ''

  try {
    await clansApi.challenge(props.clan.id, {
      ...form.value,
      scheduled_at: form.value.scheduled_at || null,
    })
    playSound('war')   // объявили войну: рог и мечи
    challenging.value = false
    form.value = { scheduled_at: '', notes: '' }
    emit('refresh')
  } catch (e) {
    error.value = e.message || 'Не удалось отправить вызов.'
  } finally {
    sending.value = false
  }
}

async function accept(war) {
  await clansApi.acceptWar(war.id)
  emit('refresh')
}

async function decline(war) {
  await clansApi.declineWar(war.id)
  emit('refresh')
}

async function complete(war) {
  const c = await promptDialog('Счёт нашего клана?')
  const o = await promptDialog('Счёт противника?')
  if (!c || !o) return

  await clansApi.completeWar(war.id, {
    challenger_score: Number(c),
    opponent_score: Number(o),
  })
  emit('refresh')
}

function when(iso) {
  return iso
    ? new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
    : 'время не назначено'
}

function shortDate(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) : ''
}
</script>

<template>
  <div class="cw">
    <!-- Сводка -->
    <section class="cw-sum">
      <div class="cw-sum__item">
        <b class="win">{{ clan.wins || 0 }}</b>
        <span>побед</span>
      </div>
      <div class="cw-sum__item">
        <b class="loss">{{ clan.losses || 0 }}</b>
        <span>поражений</span>
      </div>
      <div class="cw-sum__item">
        <b>{{ winrate }}%</b>
        <span>винрейт</span>
      </div>
      <div class="cw-sum__bar" :title="`${winrate}% побед`">
        <i :style="{ width: `${winrate}%` }" />
      </div>

      <button v-if="canChallenge" type="button" class="cw-btn cw-btn--main" @click="challenging = !challenging">
        <AppIcon icon="sword" :size="14" />
        Вызвать на войну
      </button>
    </section>

    <!-- Форма вызова -->
    <section v-if="challenging" class="cw-form">
      <h4>Вызов клану [{{ clan.tag }}]</h4>
      <div class="cw-form__row">
        <label>
          <span>Когда</span>
          <input v-model="form.scheduled_at" type="datetime-local" />
        </label>
      </div>
      <label>
        <span>Условия</span>
        <textarea v-model="form.notes" rows="2" placeholder="Например: BedWars 4×4, до 3 побед" />
      </label>
      <p v-if="error" class="cw-error">{{ error }}</p>
      <div class="cw-form__actions">
        <button type="button" class="cw-btn" @click="challenging = false">Отмена</button>
        <button type="button" class="cw-btn cw-btn--main" :disabled="sending" @click="challenge">
          {{ sending ? 'Отправляем…' : 'Отправить вызов' }}
        </button>
      </div>
    </section>

    <!-- Активные вызовы -->
    <section class="cw-block">
      <h4 class="cw-title">Активные вызовы <span class="cw-count">{{ active.length }}</span></h4>

      <article v-for="item in active" :key="item.war.id" class="vs">
        <div class="vs__side">
          <span class="vs__crest" :style="{ background: clan.banner_color || '#7c3aed' }">
            <img v-if="clan.avatar_url" :src="clan.avatar_url" alt="" />
            <template v-else>{{ clan.tag?.charAt(0) }}</template>
          </span>
          <span class="vs__name"><small>[{{ clan.tag }}]</small>{{ clan.name }}</span>
        </div>

        <div class="vs__mid">
          <span class="vs__label">VS</span>
          <span class="vs__status" :class="`vs__status--${item.war.status}`">
            {{ item.incoming && item.war.status === 'pending' && isMember ? 'Вас вызвали' : STATUS[item.war.status] }}
          </span>
          <small class="vs__when"><AppIcon icon="clock" :size="12" /> {{ when(item.war.scheduled_at) }}</small>
        </div>

        <RouterLink v-if="item.enemy" :to="`/clans/${item.enemy.id}`" class="vs__side vs__side--enemy">
          <span class="vs__name"><small>[{{ item.enemy.tag }}]</small>{{ item.enemy.name }}</span>
          <span class="vs__crest" :style="{ background: item.enemy.banner_color || '#7c3aed' }">
            <img v-if="item.enemy.avatar_url" :src="item.enemy.avatar_url" alt="" />
            <template v-else>{{ item.enemy.tag?.charAt(0) }}</template>
          </span>
        </RouterLink>

        <p v-if="item.war.notes" class="vs__notes">{{ item.war.notes }}</p>

        <div v-if="isLeader" class="vs__actions">
          <template v-if="item.incoming && item.war.status === 'pending'">
            <button type="button" class="cw-btn cw-btn--main" @click="accept(item.war)">Принять</button>
            <button type="button" class="cw-btn" @click="decline(item.war)">Отклонить</button>
          </template>
          <button
              v-else-if="item.war.status === 'accepted'"
              type="button"
              class="cw-btn cw-btn--main"
              @click="complete(item.war)"
          >
            Внести результат
          </button>
        </div>
      </article>

      <div v-if="!active.length" class="cw-empty">
        <AppIcon icon="shield" :size="18" />
        Активных вызовов нет.
      </div>
    </section>

    <!-- История -->
    <section class="cw-block">
      <h4 class="cw-title">Последние войны</h4>

      <ul v-if="history.length" class="hist">
        <li v-for="w in history" :key="w.id" class="hist__row">
          <span class="hist__res" :class="`hist__res--${w.result}`">
            {{ w.result === 'win' ? 'W' : w.result === 'loss' ? 'L' : 'D' }}
          </span>
          <RouterLink v-if="w.enemy" :to="`/clans/${w.enemy.id}`" class="hist__enemy">
            против <b>[{{ w.enemy.tag }}] {{ w.enemy.name }}</b>
          </RouterLink>
          <b class="hist__score">{{ w.us }} : {{ w.them }}</b>
          <small>{{ shortDate(w.at) }}</small>
        </li>
      </ul>

      <div v-else class="cw-empty">
        <AppIcon icon="sword" :size="18" />
        Завершённых войн пока нет.
      </div>
    </section>
  </div>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanWars.css";
</style>
