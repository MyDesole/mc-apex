<script setup>
/**
 * Привязка майнкрафт-аккаунта.
 *
 * Игрок заходит на сервер, получает в чате код и вводит его здесь.
 * После привязки вход в игру идёт по паролю от сайта.
 */
import { onMounted, ref } from 'vue'
import { minecraftApi } from '@/services/minecraft/link.js'
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const linked = ref(false)
const username = ref(null)
const code = ref('')

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await minecraftApi.status()

    linked.value = data.linked
    username.value = data.username
  } catch (e) {
    error.value = e.message || 'Не удалось узнать состояние привязки'
  } finally {
    loading.value = false
  }
}

/** Ввод кода из игры — с дефисом или без, регистр не важен. */
async function link() {
  const value = code.value.trim()

  if (!value) return

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const data = await minecraftApi.link(value)

    linked.value = data.linked
    username.value = data.username
    code.value = ''
    success.value = 'Аккаунт привязан. Теперь заходи на сервер со своим паролем.'
  } catch (e) {
    error.value = e.message || 'Код не подошёл'
  } finally {
    saving.value = false
  }
}

async function unlink() {
  const ok = await confirmDialog(
      'Отвязать майнкрафт-аккаунт? Вход на сервер по паролю перестанет работать.',
      { danger: true, confirmText: 'Отвязать' },
  )

  if (!ok) return

  saving.value = true

  try {
    await minecraftApi.unlink()

    linked.value = false
    username.value = null
    success.value = ''
  } catch (e) {
    await alertDialog(e.message || 'Не удалось отвязать')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="section minecraft-link">
    <h3 class="section__title">Майнкрафт</h3>

    <div v-if="error" class="error">{{ error }}</div>
    <div v-if="success" class="minecraft-link__success">{{ success }}</div>

    <div v-if="loading" class="minecraft-link__state">
      Проверяем привязку...
    </div>

    <!-- Привязан -->
    <div
        v-else-if="linked"
        class="minecraft-link__linked"
    >
      <div class="minecraft-link__info">
        <span class="minecraft-link__label">Привязанный игрок</span>
        <strong class="minecraft-link__name">{{ username }}</strong>
      </div>

      <p class="minecraft-link__hint">
        Вход на сервер идёт по паролю от сайта. Чтобы сменить игрока —
        отвяжи и привяжи заново.
      </p>

      <button
          type="button"
          class="minecraft-link__unlink"
          :disabled="saving"
          @click="unlink"
      >
        Отвязать
      </button>
    </div>

    <!-- Не привязан -->
    <div v-else>
      <p class="minecraft-link__hint">
        Зайди на сервер, получи в чате код и введи его здесь.
        После привязки вход в игру будет по паролю от сайта.
      </p>

      <div class="minecraft-link__row">
        <input
            v-model="code"
            type="text"
            maxlength="16"
            placeholder="XXXX-XXXX"
            autocomplete="off"
            spellcheck="false"
            @keyup.enter="link"
        />

        <button
            type="button"
            class="minecraft-link__submit"
            :disabled="saving || !code.trim()"
            @click="link"
        >
          {{ saving ? '...' : 'Привязать' }}
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/minecraft/MinecraftLinkBlock.css";
</style>
