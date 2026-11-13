<script setup>
import { dismissToast, toastState } from '@/utils/toast.js'
</script>

<template>
  <Teleport to="body">
    <TransitionGroup name="toast" tag="div" class="toasts" aria-live="polite">
      <div
          v-for="item in toastState.items"
          :key="item.id"
          class="toast"
          :class="`toast--${item.tone}`"
          :role="item.tone === 'error' ? 'alert' : 'status'"
      >
        <span class="toast__text">{{ item.message }}</span>
        <button type="button" class="toast__close" aria-label="Закрыть" @click="dismissToast(item.id)">×</button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<style src="./ToastHost.css"></style>
