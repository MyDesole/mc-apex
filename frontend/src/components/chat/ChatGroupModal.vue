<script setup>
/**
 * Групповая беседа: создать новую или управлять открытой.
 *
 * Звать можно только друзей — сервер это тоже проверяет. Переименовать
 * и исключить может создатель, выйти — любой.
 */
import { computed, onMounted, ref } from 'vue'
import { friendsApi } from '@/services/friends/friends.js'
import { chatApi } from '@/services/chat/chat.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const MAX_MEMBERS = 20

const props = defineProps({
  // null — создание новой беседы
  conversation: { type: Object, default: null },
  meId: { type: Number, required: true },
})

const emit = defineEmits(['close', 'created', 'updated', 'left'])

const title = ref(props.conversation?.title ?? '')
const friends = ref([])
const picked = ref(new Set())
const filter = ref('')
const loading = ref(true)
const busy = ref(false)
const error = ref('')

const isCreate = computed(() => !props.conversation)
const isOwner = computed(() => props.conversation?.owner_id === props.meId)
const members = computed(() => props.conversation?.users ?? [])
const memberIds = computed(() => new Set(members.value.map((u) => u.id)))

const candidates = computed(() => {
  const q = filter.value.trim().toLowerCase()

  return friends.value
      .filter((u) => !memberIds.value.has(u.id))
      .filter((u) => !q || u.username.toLowerCase().includes(q))
})

const room = computed(() => MAX_MEMBERS - (isCreate.value ? 1 : members.value.length))

onMounted(async () => {
  try {
    const data = await friendsApi.list()

    friends.value = (data.friends ?? []).map((f) => f.user ?? f).filter((u) => u?.id)
  } catch {
    friends.value = []
  } finally {
    loading.value = false
  }
})

function toggle(id) {
  const next = new Set(picked.value)

  if (next.has(id)) next.delete(id)
  else if (next.size < room.value) next.add(id)

  picked.value = next
}

async function run(action) {
  if (busy.value) return

  busy.value = true
  error.value = ''

  try {
    await action()
  } catch (e) {
    error.value = e.message || 'Не получилось.'
  } finally {
    busy.value = false
  }
}

function create() {
  return run(async () => {
    const data = await chatApi.createGroup(title.value.trim(), [...picked.value])

    emit('created', data.conversation)
  })
}

function invite() {
  return run(async () => {
    const data = await chatApi.addMembers(props.conversation.id, [...picked.value])

    picked.value = new Set()
    emit('updated', data)
  })
}

function rename() {
  return run(async () => {
    const data = await chatApi.renameGroup(props.conversation.id, title.value.trim())

    emit('updated', data)
  })
}

async function kick(user) {
  if (!await confirmDialog(`Исключить ${user.username} из беседы?`)) return

  return run(async () => {
    const data = await chatApi.removeMember(props.conversation.id, user.id)

    emit('updated', data)
  })
}

async function leave() {
  if (!await confirmDialog('Выйти из беседы? Вернуть сможет только участник.')) return

  return run(async () => {
    await chatApi.removeMember(props.conversation.id, props.meId)

    emit('left', props.conversation.id)
  })
}
</script>

<template>
  <Teleport to="body">
    <div class="group-modal-bg" @click.self="emit('close')">
      <div class="group-modal" role="dialog" aria-modal="true">
        <header class="group-modal__head">
          <strong>{{ isCreate ? 'Новая беседа' : 'Беседа' }}</strong>
          <button type="button" class="group-modal__close" aria-label="Закрыть" @click="emit('close')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </header>

        <div class="group-modal__body scroll-thin">
          <label class="group-modal__field">
            <span>Название</span>
            <div class="group-modal__row">
              <input
                  v-model="title"
                  type="text"
                  maxlength="48"
                  placeholder="Например: Пати на бридж"
                  :disabled="!isCreate && !isOwner"
              >
              <button
                  v-if="!isCreate && isOwner"
                  type="button"
                  class="group-modal__btn"
                  :disabled="busy || title.trim().length < 2 || title.trim() === conversation.title"
                  @click="rename"
              >Сохранить</button>
            </div>
          </label>

          <!-- Участники открытой беседы -->
          <section v-if="!isCreate" class="group-modal__section">
            <h4>Участники · {{ members.length }}</h4>
            <ul class="group-modal__list">
              <li v-for="u in members" :key="u.id" class="group-modal__member">
                <span class="group-modal__avatar">
                  <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.username">
                  <template v-else>{{ u.username.charAt(0).toUpperCase() }}</template>
                </span>
                <span class="group-modal__name">{{ u.username }}</span>
                <span v-if="u.id === conversation.owner_id" class="group-modal__tag">создатель</span>
                <button
                    v-else-if="isOwner"
                    type="button"
                    class="group-modal__kick"
                    title="Исключить"
                    :disabled="busy"
                    @click="kick(u)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
              </li>
            </ul>
          </section>

          <section class="group-modal__section">
            <h4>{{ isCreate ? 'Позвать друзей' : 'Добавить друзей' }}<small v-if="picked.size"> · выбрано {{ picked.size }}</small></h4>

            <input v-if="friends.length > 6" v-model="filter" type="text" class="group-modal__filter" placeholder="Найти друга…">

            <p v-if="loading" class="group-modal__hint">Загружаем друзей…</p>
            <p v-else-if="!candidates.length" class="group-modal__hint">
              {{ friends.length ? 'Все друзья уже здесь.' : 'Сначала добавь друзей — звать можно только их.' }}
            </p>

            <ul v-else class="group-modal__list">
              <li v-for="u in candidates" :key="u.id">
                <button
                    type="button"
                    class="group-modal__member group-modal__member--pick"
                    :class="{ 'is-on': picked.has(u.id) }"
                    @click="toggle(u.id)"
                >
                  <span class="group-modal__avatar">
                    <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.username">
                    <template v-else>{{ u.username.charAt(0).toUpperCase() }}</template>
                  </span>
                  <span class="group-modal__name">{{ u.username }}</span>
                  <span class="group-modal__check">
                    <svg v-if="picked.has(u.id)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5" /></svg>
                  </span>
                </button>
              </li>
            </ul>
          </section>
        </div>

        <p v-if="error" class="group-modal__error">{{ error }}</p>

        <footer class="group-modal__foot">
          <button
              v-if="!isCreate"
              type="button"
              class="group-modal__btn group-modal__btn--danger"
              :disabled="busy"
              @click="leave"
          >Выйти</button>

          <button
              v-if="isCreate"
              type="button"
              class="group-modal__btn group-modal__btn--primary"
              :disabled="busy || title.trim().length < 2 || !picked.size"
              @click="create"
          >Создать беседу</button>

          <button
              v-else
              type="button"
              class="group-modal__btn group-modal__btn--primary"
              :disabled="busy || !picked.size"
              @click="invite"
          >Позвать{{ picked.size ? ` (${picked.size})` : '' }}</button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.group-modal-bg {
  position: fixed;
  inset: 0;
  z-index: 1200;

  display: grid;
  place-items: center;
  padding: 16px;

  background: rgba(5, 3, 10, 0.7);
}

.group-modal {
  display: flex;
  flex-direction: column;
  width: min(420px, 100%);
  max-height: min(640px, calc(100dvh - 32px));

  color: #ede9f6;

  background: #17111f;
  border: 1px solid rgba(167, 139, 250, 0.3);
  border-radius: 12px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
}

.group-modal__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;

  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.group-modal__close {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;

  color: inherit;

  background: transparent;
  border: 0;
  opacity: 0.6;

  cursor: pointer;
}

.group-modal__body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 14px 16px;
  overflow-y: auto;
}

.group-modal__field {
  display: flex;
  flex-direction: column;
  gap: 6px;

  font-size: 12px;
}

.group-modal__row {
  display: flex;
  gap: 8px;
}

.group-modal__row input,
.group-modal__filter {
  flex: 1;
  min-width: 0;
  padding: 9px 11px;

  font: inherit;
  font-size: 14px;
  color: inherit;

  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;
}

.group-modal__filter {
  margin-bottom: 8px;
}

.group-modal__section h4 {
  margin: 0 0 8px;

  font-size: 11px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.65;
}

.group-modal__section h4 small {
  text-transform: none;
}

.group-modal__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  padding: 0;

  list-style: none;
}

.group-modal__member {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 6px 8px;

  font: inherit;
  color: inherit;
  text-align: left;

  background: rgba(255, 255, 255, 0.03);
  border: 1px solid transparent;
  border-radius: 6px;
}

.group-modal__member--pick {
  cursor: pointer;
}

.group-modal__member--pick.is-on {
  background: rgba(167, 139, 250, 0.14);
  border-color: rgba(167, 139, 250, 0.5);
}

.group-modal__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 30px;
  height: 30px;
  overflow: hidden;

  font-weight: 800;

  background: rgba(255, 255, 255, 0.08);
  border-radius: 5px;
}

.group-modal__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.group-modal__name {
  flex: 1;
  overflow: hidden;

  font-size: 14px;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.group-modal__tag {
  font-size: 11px;
  color: #fbbf24;
}

.group-modal__check {
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;

  color: #120c1f;

  background: rgba(255, 255, 255, 0.08);
  border-radius: 4px;
}

.is-on .group-modal__check {
  background: #a78bfa;
}

.group-modal__kick {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;

  color: #fca5a5;

  background: transparent;
  border: 0;

  cursor: pointer;
}

.group-modal__hint {
  margin: 0;

  font-size: 13px;
  opacity: 0.65;
}

.group-modal__error {
  margin: 0 16px 8px;

  font-size: 13px;
  color: #fca5a5;
}

.group-modal__foot {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;

  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.group-modal__btn {
  padding: 9px 14px;

  font: inherit;
  font-size: 13px;
  font-weight: 700;
  color: inherit;

  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 6px;

  cursor: pointer;
}

.group-modal__btn:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.group-modal__btn--primary {
  color: #120c1f;

  background: #a78bfa;
  border-color: #a78bfa;
}

.group-modal__btn--danger {
  margin-right: auto;

  color: #fca5a5;

  border-color: rgba(248, 113, 113, 0.4);
}
</style>
