<script setup>
import { prompt as promptDialog } from '@/utils/dialog.js'
import { ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'

const props = defineProps({
  clan: Object,
  incoming: Array,
  outgoing: Array,
  isMember: Boolean,
  isLeader: Boolean,
})

const emit = defineEmits(['refresh'])

const challenging = ref(false)
const form = ref({ scheduled_at: '', notes: '' })

async function challenge() {
  await clansApi.challenge(props.clan.id, {
    ...form.value,
    scheduled_at: form.value.scheduled_at || null,
  })
  challenging.value = false
  form.value = { scheduled_at: '', notes: '' }
  emit('refresh')
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

const statusLabels = {
  pending: 'Ожидает',
  accepted: 'Принята',
  declined: 'Отклонена',
  completed: 'Завершена',
  cancelled: 'Отменена',
}
</script>

<template>
  <div class="wars">
    <div class="actions">
      <button v-if="isMember" class="btn-challenge" @click="challenging = !challenging">
        + Вызвать клан
      </button>
    </div>

    <div v-if="challenging" class="form">
      <input v-model="form.scheduled_at" type="datetime-local" />
      <textarea v-model="form.notes" rows="2" placeholder="Условия..." />
      <div class="form-actions">
        <button class="btn-cancel" @click="challenging = false">Отмена</button>
        <button class="btn-save" @click="challenge">Отправить вызов</button>
      </div>
    </div>

    <!-- Входящие -->
    <section v-if="incoming?.length">
      <h3>Входящие вызовы</h3>
      <div class="war-list">
        <div v-for="w in incoming" :key="w.id" class="war-row">
          <div class="war-info">
            <span class="tag">[{{ w.challenger.tag }}]</span>
            <span>{{ w.challenger.name }}</span>
          </div>
          <div v-if="w.scheduled_at" class="war-date">
            {{ new Date(w.scheduled_at).toLocaleString('ru-RU') }}
          </div>
          <div class="war-actions" v-if="isLeader">
            <button class="btn-accept" @click="accept(w)">Принять</button>
            <button class="btn-decline" @click="decline(w)">Отклонить</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Исходящие -->
    <section v-if="outgoing?.length">
      <h3>Исходящие вызовы</h3>
      <div class="war-list">
        <div v-for="w in outgoing" :key="w.id" class="war-row">
          <div class="war-info">
            <span class="tag">[{{ w.opponent.tag }}]</span>
            <span>{{ w.opponent.name }}</span>
          </div>
          <div class="status" :class="`status-${w.status}`">
            {{ statusLabels[w.status] }}
          </div>
          <div v-if="w.status === 'accepted' && isLeader" class="war-actions">
            <button class="btn-accept" @click="complete(w)">Внести результат</button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="!incoming?.length && !outgoing?.length" class="empty">
      Войн пока нет
    </div>
  </div>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanWars.css";
</style>
