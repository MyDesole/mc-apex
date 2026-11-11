<script setup>
/**
 * Приглашение игрока в клан (лидер и офицеры): ник, короткое сообщение
 * и список отправленных приглашений с возможностью отменить.
 */
import { onMounted, ref } from 'vue'
import { clansApi } from '@/services/clan/clans.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['close'])

const username = ref('')
const message = ref('')
const sending = ref(false)
const error = ref('')
const ok = ref('')
const list = ref([])
const loading = ref(true)

async function load() {
  try {
    const data = await clansApi.invitations(props.clan.id)
    list.value = data.invitations ?? []
  } finally {
    loading.value = false
  }
}

async function send() {
  const nick = username.value.trim()

  if (!nick || sending.value) return

  sending.value = true
  error.value = ''
  ok.value = ''

  try {
    await clansApi.invite(props.clan.id, nick, message.value.trim() || null)
    ok.value = `Приглашение отправлено: ${nick}`
    username.value = ''
    message.value = ''
    await load()
  } catch (e) {
    error.value = e.errors?.username?.[0] || e.message || 'Не удалось отправить приглашение.'
  } finally {
    sending.value = false
  }
}

async function cancel(item) {
  await clansApi.cancelInvitation(props.clan.id, item.id)
  await load()
}

function until(iso) {
  return iso ? new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }) : ''
}

onMounted(load)
</script>

<template>
  <Teleport to="body">
    <div class="cs-overlay" @click.self="emit('close')">
      <div class="cs-modal cs-modal--narrow">
        <header class="cs-head">
          <h2>Пригласить в клан</h2>
          <button type="button" class="cs-x" aria-label="Закрыть" @click="emit('close')">×</button>
        </header>

        <div class="cs-body">
          <p class="cs-hint">
            Игрок получит уведомление и письмо. Вступление по приглашению бесплатное и не зависит от порога рейтинга.
            Приглашение действует 7 дней.
          </p>

          <label class="cs-field">
            <span>Ник игрока</span>
            <input v-model="username" type="text" maxlength="64" placeholder="Ник на сайте" @keyup.enter="send" />
          </label>

          <label class="cs-field">
            <span>Сообщение <small>необязательно</small></span>
            <input v-model="message" type="text" maxlength="200" placeholder="Например: заходи, играем сегодня вечером" @keyup.enter="send" />
          </label>

          <p v-if="error" class="cs-error">{{ error }}</p>
          <p v-if="ok" class="cs-ok">{{ ok }}</p>

          <button type="button" class="cs-btn" :disabled="sending || !username.trim()" @click="send">
            {{ sending ? 'Отправляем…' : 'Пригласить' }}
          </button>

          <h3 class="cs-sub">Ждут ответа</h3>
          <p v-if="loading" class="cs-hint">Загрузка…</p>
          <p v-else-if="!list.length" class="cs-hint">Неотвеченных приглашений нет.</p>
          <ul v-else class="cs-list">
            <li v-for="item in list" :key="item.id">
              <span><b>{{ item.user?.username }}</b> <small>до {{ until(item.expires_at) }}</small></span>
              <button type="button" class="cs-link" @click="cancel(item)">отменить</button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanDialogs.css";
</style>
