<script setup>
/**
 * Единое окно диалогов приложения.
 *
 * Заменяет встроенные alert/confirm/prompt: те блокируют поток,
 * выглядят чужеродно и не поддаются оформлению. Компонент один на всё
 * приложение — его монтирует App.vue, а вызывают функции из utils/dialog.js.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { dialogState, settleDialog } from '@/utils/dialog.js'

const inputRef = ref(null)
const inputValue = ref('')
const reasonValue = ref('')

const dialog = computed(() => dialogState.current)

const isPrompt = computed(() => dialog.value?.kind === 'prompt')
const withReason = computed(() => dialog.value?.withReason === true)

// Начальное значение подставляем при показе каждого нового диалога
watch(dialog, async (next) => {
  if (!next) return

  inputValue.value = next.kind === 'prompt' ? (next.initialValue ?? '') : ''
  reasonValue.value = ''

  if (next.kind === 'prompt' || next.withReason) {
    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select?.()
  }
}, { immediate: true })

function accept() {
  const entry = dialog.value
  if (!entry) return

  if (entry.kind === 'prompt') {
    settleDialog(inputValue.value)
    return
  }

  if (entry.withReason) {
    settleDialog({ reason: reasonValue.value })
    return
  }

  settleDialog(true)
}

function cancel() {
  const entry = dialog.value
  if (!entry) return

  // prompt при отмене отдаёт null, confirm — false
  settleDialog(entry.kind === 'prompt' ? null : false)
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    cancel()
    return
  }

  // Enter подтверждает, но в textarea переносит строку
  if (event.key === 'Enter' && !event.shiftKey && event.target?.tagName !== 'TEXTAREA') {
    event.preventDefault()
    accept()
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="dlg">
      <div
          v-if="dialog"
          class="dlg-bg"
          role="dialog"
          aria-modal="true"
          tabindex="-1"
          @keydown="onKeydown"
          @click.self="cancel"
      >
        <div class="dlg" :class="{ 'dlg--danger': dialog.danger }">
          <h3 v-if="dialog.title" class="dlg__title">{{ dialog.title }}</h3>

          <p class="dlg__message">{{ dialog.message }}</p>

          <!-- Ввод строки -->
          <input
              v-if="isPrompt"
              ref="inputRef"
              v-model="inputValue"
              class="dlg__input"
              type="text"
              :placeholder="dialog.placeholder"
              :maxlength="dialog.maxlength ?? undefined"
              @keydown="onKeydown"
          >

          <!-- Необязательный комментарий -->
          <textarea
              v-if="withReason"
              ref="inputRef"
              v-model="reasonValue"
              class="dlg__input dlg__input--area"
              rows="2"
              :placeholder="dialog.reasonPlaceholder || dialog.reasonLabel"
              @keydown="onKeydown"
          ></textarea>

          <div class="dlg__actions">
            <button
                v-if="dialog.showCancel"
                class="dlg__btn dlg__btn--ghost"
                type="button"
                @click="cancel"
            >
              {{ dialog.cancelText }}
            </button>

            <button
                class="dlg__btn"
                :class="dialog.danger ? 'dlg__btn--danger' : 'dlg__btn--primary'"
                type="button"
                autofocus
                @click="accept"
            >
              {{ dialog.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@import "@/components/core/DialogHost.css";
</style>
