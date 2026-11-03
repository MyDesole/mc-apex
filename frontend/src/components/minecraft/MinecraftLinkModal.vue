<script setup>
/**
 * Привязка ника, открытая по ссылке из плагина.
 *
 * Игрок заходит с сервера, переходит по ссылке вида
 * /profile?nick=MyDesole — и сразу видит это окно с уже вписанным ником.
 * Остаётся нажать «Подтвердить».
 */
import { onMounted, ref } from 'vue'
import { minecraftApi } from '@/services/minecraft/link.js'

const props = defineProps({
  /** Ник из игры: подставляем в поле, чтобы не набирать руками */
  nick: { type: String, default: '' },
})

const emit = defineEmits(['close', 'claimed'])

const input = ref(props.nick)
const claimed = ref(false)
const current = ref(null)
const saving = ref(false)
const error = ref('')
const success = ref('')

async function load() {
  try {
    const data = await minecraftApi.status()

    claimed.value = data.claimed
    current.value = data.nickname

    // Ник уже привязан — не предлагаем ввести его же
    if (data.claimed && data.nickname && data.nickname.toLowerCase() === props.nick.toLowerCase()) {
      success.value = 'Этот ник уже привязан к твоему аккаунту — заходи на сервер.'
    }
  } catch (e) {
    error.value = e.message || 'Не удалось проверить ник'
  }
}

async function submit() {
  const value = input.value.trim()

  if (!value) return

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const data = await minecraftApi.claim(value)

    claimed.value = true
    current.value = data.nickname
    success.value = 'Ник привязан. Возвращайся на сервер и введи пароль сайта.'

    emit('claimed', data.nickname)
  } catch (e) {
    error.value = e.message || 'Не удалось привязать ник'
  } finally {
    saving.value = false
  }
}

async function change() {
  saving.value = true
  error.value = ''

  try {
    await minecraftApi.release()

    claimed.value = false
    current.value = null
  } catch (e) {
    error.value = e.message || 'Не удалось сменить ник'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="mlm" role="dialog" aria-modal="true" @click.self="emit('close')">
    <div class="mlm__card">
      <button type="button" class="mlm__x" aria-label="Закрыть" @click="emit('close')">×</button>

      <h2 class="mlm__title">Привязка ника</h2>
      <p class="mlm__text">
        Ник закрепляется за твоим аккаунтом на сайте. Зайти на сервер под чужим ником
        не получится — пароль спрашивается у владельца ника.
      </p>

      <p v-if="error" class="mlm__error">{{ error }}</p>
      <p v-if="success" class="mlm__ok">{{ success }}</p>

      <template v-if="claimed && current">
        <div class="mlm__current">
          <span>Твой ник на сервере</span>
          <b>{{ current }}</b>
        </div>

        <div class="mlm__actions">
          <button type="button" class="mlm__btn mlm__btn--main" @click="emit('close')">Готово</button>
          <button type="button" class="mlm__btn" :disabled="saving" @click="change">Сменить ник</button>
        </div>
      </template>

      <template v-else>
        <label class="mlm__label">
          <span>Ник в игре</span>
          <input
              v-model="input"
              class="mlm__input"
              type="text"
              maxlength="16"
              autocomplete="off"
              spellcheck="false"
              placeholder="MyDesole"
              @keyup.enter="submit"
          />
        </label>

        <div class="mlm__actions">
          <button
              type="button"
              class="mlm__btn mlm__btn--main"
              :disabled="saving || !input.trim()"
              @click="submit"
          >
            {{ saving ? '...' : 'Подтвердить' }}
          </button>
          <button type="button" class="mlm__btn" @click="emit('close')">Позже</button>
        </div>
      </template>

      <p class="mlm__note">Латиница, цифры и подчёркивание, 3–16 символов.</p>
    </div>
  </div>
</template>

<style scoped>
@import "./MinecraftLinkModal.css";
</style>
