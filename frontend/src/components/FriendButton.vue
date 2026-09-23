<script setup>
import { computed, ref } from 'vue'
import { friendsApi } from '@/services/friends.js'

const props = defineProps({
  userId: { type: Number, required: true },
  friendship: { type: Object, default: null }, // { status, initiated_by_me }
})

const emit = defineEmits(['update'])

const loading = ref(false)
const local = ref(props.friendship)

const state = computed(() => {
  if (!local.value) return 'none'
  if (local.value.status === 'accepted') return 'friends'
  if (local.value.status === 'pending') {
    return local.value.initiated_by_me ? 'outgoing' : 'incoming'
  }
  return 'none'
})

async function add() {
  loading.value = true
  try {
    await friendsApi.add(props.userId)
    local.value = { status: 'pending', initiated_by_me: true }
    emit('update', local.value)
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    loading.value = false
  }
}

async function accept() {
  loading.value = true
  try {
    await friendsApi.accept(props.userId)
    local.value = { status: 'accepted', initiated_by_me: false }
    emit('update', local.value)
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    loading.value = false
  }
}

async function remove() {
  loading.value = true
  try {
    await friendsApi.remove(props.userId)
    local.value = null
    emit('update', null)
  } catch (e) {
    alert(e.message || 'Ошибка')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <button
      v-if="state === 'none'"
      class="friend-btn add"
      :disabled="loading"
      @click="add"
  >
    + В друзья
  </button>

  <button
      v-else-if="state === 'outgoing'"
      class="friend-btn pending"
      :disabled="loading"
      @click="remove"
  >
    Заявка отправлена
  </button>

  <button
      v-else-if="state === 'incoming'"
      class="friend-btn accept"
      :disabled="loading"
      @click="accept"
  >
    Принять заявку
  </button>

  <button
      v-else-if="state === 'friends'"
      class="friend-btn friends"
      :disabled="loading"
      @click="remove"
  >
    ✓ В друзьях
  </button>
</template>

<style scoped>
.friend-btn {
  min-height: 36px;
  padding: 0 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.friend-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.friend-btn.add {
  color: #fff;
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2);
}

.friend-btn.add:hover:not(:disabled) {
  background: var(--accent-light);
  transform: translateY(-1px);
}

.friend-btn.pending {
  color: var(--text-dim);
  background: transparent;
  border-color: var(--border);
}

.friend-btn.pending:hover:not(:disabled) {
  color: #f87171;
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

.friend-btn.accept {
  color: #fff;
  background: #22c55e;
  border-color: #22c55e;
}

.friend-btn.accept:hover:not(:disabled) {
  background: #16a34a;
}

.friend-btn.friends {
  color: #86efac;
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.25);
}

.friend-btn.friends:hover:not(:disabled) {
  color: #fca5a5;
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.25);
}
</style>