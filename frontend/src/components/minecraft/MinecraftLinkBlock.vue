<script setup>
/**
 * Ник в майнкрафте.
 *
 * Игрок заявляет свой игровой ник здесь, под своей сессией. Ник уникален,
 * и плагин на сервере пускает только того, чей ник совпадает с заявленным —
 * поэтому зайти под чужим ником нельзя.
 */
import { onMounted, ref } from 'vue'
import { minecraftApi } from '@/services/minecraft/link.js'
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const claimed = ref(false)
const nickname = ref(null)
const input = ref('')

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await minecraftApi.status()

    claimed.value = data.claimed
    nickname.value = data.nickname
  } catch (e) {
    error.value = e.message || 'Не удалось узнать состояние'
  } finally {
    loading.value = false
  }
}

async function save() {
  const value = input.value.trim()

  if (!value) return

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const data = await minecraftApi.claim(value)

    claimed.value = data.claimed
    nickname.value = data.nickname
    input.value = ''
    success.value = 'Ник заявлен. Заходи на сервер со своим паролем от сайта.'
  } catch (e) {
    error.value = e.message || 'Не удалось заявить ник'
  } finally {
    saving.value = false
  }
}

async function release() {
  const ok = await confirmDialog(
      `Снять ник ${nickname.value}? Вход на сервер перестанет работать, пока не заявишь его заново.`,
      { danger: true, confirmText: 'Снять' },
  )

  if (!ok) return

  saving.value = true

  try {
    await minecraftApi.release()

    claimed.value = false
    nickname.value = null
    success.value = ''
  } catch (e) {
    await alertDialog(e.message || 'Не удалось снять ник')
  } finally {
    saving.value = false
  }
}

/** Смена ника: сначала снимаем текущий, потом вводим новый. */
async function startChange() {
  const ok = await confirmDialog(
      'Сменить ник? Старый освободится, и его сможет занять кто угодно.',
      { danger: true, confirmText: 'Сменить' },
  )

  if (!ok) return

  try {
    await minecraftApi.release()

    claimed.value = false
    nickname.value = null
  } catch (e) {
    await alertDialog(e.message || 'Не удалось снять ник')
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
      Проверяем ник...
    </div>

    <!-- Ник заявлен -->
    <div
        v-else-if="claimed"
        class="minecraft-link__claimed"
    >
      <div class="minecraft-link__info">
        <span class="minecraft-link__label">Ник на сервере</span>
        <strong class="minecraft-link__name">{{ nickname }}</strong>
      </div>

      <p class="minecraft-link__hint">
        Вход на сервер идёт по паролю от сайта. Ник закреплён за твоим
        аккаунтом: зайти под ним сможешь только ты.
      </p>

      <div class="minecraft-link__actions">
        <button
            type="button"
            class="minecraft-link__change"
            :disabled="saving"
            @click="startChange"
        >
          Сменить ник
        </button>

        <button
            type="button"
            class="minecraft-link__unlink"
            :disabled="saving"
            @click="release"
        >
          Снять
        </button>
      </div>
    </div>

    <!-- Ник не заявлен -->
    <div v-else>
      <p class="minecraft-link__hint">
        Укажи свой ник на сервере — ровно так, как он пишется в игре.
        Зайти под чужим ником не получится: он закрепляется за твоим
        аккаунтом.
      </p>

      <div class="minecraft-link__row">
        <input
            v-model="input"
            type="text"
            maxlength="16"
            placeholder="MyDesole"
            autocomplete="off"
            spellcheck="false"
            @keyup.enter="save"
        />

        <button
            type="button"
            class="minecraft-link__submit"
            :disabled="saving || !input.trim()"
            @click="save"
        >
          {{ saving ? '...' : 'Заявить' }}
        </button>
      </div>

      <p class="minecraft-link__note">
        Только латиница, цифры и подчёркивание, от 3 до 16 символов.
      </p>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/minecraft/MinecraftLinkBlock.css";
</style>
