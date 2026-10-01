<script setup>
/**
 * Единое окно диалогов приложения.
 *
 * Заменяет встроенные alert/confirm/prompt: те блокируют поток,
 * выглядят чужеродно и не поддаются оформлению. Компонент один на всё
 * приложение — его монтирует App.vue, а вызывают функции из utils/dialog.js.
 */
import { computed, nextTick, ref, watch } from 'vue'
import { dialogState, settleDialog } from '@/utils/dialog'

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
.dlg-bg {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.72);
  backdrop-filter: blur(6px);
}

.dlg {
  width: 100%;
  max-width: 420px;
  padding: 22px 22px 18px;
  background: var(--bg-card, #12121a);
  border: 1px solid var(--border, #22222e);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

/* Опасное действие: рамка и заголовок краснеют */
.dlg--danger {
  border-color: rgba(239, 68, 68, 0.4);
}

.dlg__title {
  margin: 0 0 10px;
  color: var(--text, #e2e2e8);
  font-size: 16px;
  font-weight: 800;
}

.dlg--danger .dlg__title {
  color: #fca5a5;
}

.dlg__message {
  margin: 0 0 16px;
  color: var(--text-dim, #8888a0);
  font-size: 13.5px;
  line-height: 1.55;
  white-space: pre-line;
}

.dlg__input {
  width: 100%;
  margin-bottom: 16px;
  padding: 10px 12px;
  color: var(--text, #e2e2e8);
  background: var(--bg, #0a0a0f);
  border: 1px solid var(--border, #22222e);
  border-radius: 10px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.18s ease;
}

.dlg__input:focus {
  border-color: var(--accent, #7c3aed);
}

.dlg__input--area {
  resize: vertical;
  min-height: 58px;
}

.dlg__actions {
  display: flex;
  gap: 9px;
  justify-content: flex-end;
}

.dlg__btn {
  padding: 9px 17px;
  border: 1px solid transparent;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 0.18s ease, background 0.18s ease, border-color 0.18s ease;
}

.dlg__btn--ghost {
  color: var(--text-dim, #8888a0);
  background: transparent;
  border-color: var(--border, #22222e);
}

.dlg__btn--ghost:hover {
  color: var(--text, #e2e2e8);
  border-color: var(--border-hover, #343443);
}

.dlg__btn--primary {
  color: #fff;
  background: var(--accent, #7c3aed);
}

.dlg__btn--primary:hover {
  background: var(--accent-light, #8b5cf6);
}

.dlg__btn--danger {
  color: #fff;
  background: #dc2626;
}

.dlg__btn--danger:hover {
  background: #ef4444;
}

/* Появление */
.dlg-enter-active,
.dlg-leave-active {
  transition: opacity 0.16s ease;
}

.dlg-enter-active .dlg,
.dlg-leave-active .dlg {
  transition: transform 0.16s ease;
}

.dlg-enter-from,
.dlg-leave-to {
  opacity: 0;
}

.dlg-enter-from .dlg,
.dlg-leave-to .dlg {
  transform: scale(0.96) translateY(6px);
}
</style>
