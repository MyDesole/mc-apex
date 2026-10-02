<script setup>
import { alert as alertDialog } from '@/utils/dialog.js'
import { computed, ref } from 'vue'
import { friendsApi } from '@/services/friends/friends.js'

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
    await alertDialog(e.message || 'Ошибка')
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
    await alertDialog(e.message || 'Ошибка')
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
    await alertDialog(e.message || 'Ошибка')
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
@import "@/components/friends/FriendButton.css";
</style>
