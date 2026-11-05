<script setup>
/**
 * Ранги сервера игрока: выдать на срок или навсегда, снять.
 * Ранги тестера, медиа и модератора следуют за ролью на сайте.
 */
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({ user: Object })
const emit = defineEmits(['close', 'updated'])

const state = ref(null)
const busy = ref(false)
const error = ref('')
const setup = ref(null)
const days = ref({})

const DURATIONS = [
  { value: 7, label: '7 дней' },
  { value: 30, label: '30 дней' },
  { value: 90, label: '90 дней' },
  { value: 365, label: 'Год' },
  { value: null, label: 'Навсегда' },
]

const dayOf = (key) => (key in days.value ? days.value[key] : 30)
const until = (iso) => (iso ? `до ${new Date(iso).toLocaleDateString('ru-RU')}` : 'навсегда')

async function load() {
  try {
    state.value = await adminApi.ranks(props.user.id)
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить ранги'
  }
}

async function act(fn) {
  busy.value = true
  error.value = ''
  try {
    state.value = await fn()
    emit('updated')
  } catch (e) {
    error.value = e.message || 'Не получилось'
  } finally {
    busy.value = false
  }
}

const grant = (r) => act(() => adminApi.grantRank(props.user.id, r.key, dayOf(r.key)))
const revoke = (r) => act(() => adminApi.revokeRank(props.user.id, r.key))

async function showSetup() {
  setup.value = (await adminApi.rankSetup()).commands.join('\n')
}

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal rk">
      <header class="modal-head">
        <h2>Ранги сервера: {{ user.username }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <p v-if="error" class="rk-err">{{ error }}</p>
        <p v-if="state && !state.linked" class="rk-warn">
          Minecraft-ник не привязан. Ранг запишется и уйдёт на сервер, как только игрок привяжет ник.
        </p>
        <p v-else-if="state" class="rk-nick">Ник на сервере: <b>{{ state.nick }}</b></p>

        <div v-for="r in state?.ranks ?? []" :key="r.key" class="role-option rk-row" :class="{ active: r.active }">
          <div class="rk-main">
            <div class="role-label">{{ r.name }}
              <small v-if="r.active"> · {{ until(r.expires_at) }}<template v-if="r.source === 'role'"> · по роли</template></small>
            </div>
            <div class="role-desc">{{ r.about }}</div>
          </div>
          <template v-if="r.role">
            <span class="rk-auto">авто по роли</span>
          </template>
          <template v-else>
            <select v-if="!r.active" class="rk-sel" :value="dayOf(r.key)" @change="days[r.key] = $event.target.value === '' ? null : Number($event.target.value)">
              <option v-for="d in DURATIONS" :key="d.label" :value="d.value ?? ''">{{ d.label }}</option>
            </select>
            <button v-if="!r.active" class="btn-save" :disabled="busy" @click="grant(r)">Выдать</button>
            <template v-else>
              <button class="btn-cancel" :disabled="busy" @click="grant(r)">Продлить</button>
              <button class="btn-cancel" :disabled="busy" @click="revoke(r)">Снять</button>
            </template>
          </template>
        </div>

        <button v-if="!setup" class="rk-link" @click="showSetup">Команды для создания групп на сервере</button>
        <template v-else>
          <p class="role-desc">Выполните один раз в консоли сервера, затем настройте TAB на префиксы LuckPerms.</p>
          <textarea class="rk-code" readonly rows="8" :value="setup" />
        </template>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Закрыть</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminRoleModal.css";

.rk { max-width: 560px; }
.body { max-height: 70vh; overflow-y: auto; }
.rk-row { cursor: default; flex-wrap: wrap; }
.rk-main { flex: 1; min-width: 140px; }
.rk-sel { min-height: 36px; background: #0d0d14; color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 0 8px; }
.rk-row .btn-save, .rk-row .btn-cancel { min-height: 36px; padding: 0 12px; }
.rk-auto { font-size: 12px; color: var(--text-dim); }
.rk-err { color: #ff6b6b; margin: 0; font-size: 13px; }
.rk-warn { margin: 0; font-size: 13px; color: #f5c451; }
.rk-nick { margin: 0; font-size: 13px; color: var(--text-dim); }
.rk-link { align-self: flex-start; background: none; border: 0; padding: 0; color: var(--accent); font-size: 13px; cursor: pointer; }
.rk-code { width: 100%; font-family: monospace; font-size: 12px; background: #0d0d14; color: var(--text); border: 1px solid var(--border); border-radius: 6px; padding: 8px; }
</style>
