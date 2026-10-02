<script setup>
import { computed, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const mode = ref('delta')        // 'delta' | 'set'
const winsDelta = ref(0)
const lossesDelta = ref(0)
const winsSet = ref(props.clan.wins)
const lossesSet = ref(props.clan.losses)
const reason = ref('')
const loading = ref(false)
const error = ref('')

const previewWins = computed(() => {
  if (mode.value === 'set') return winsSet.value
  return Math.max(0, props.clan.wins + winsDelta.value)
})

const previewLosses = computed(() => {
  if (mode.value === 'set') return lossesSet.value
  return Math.max(0, props.clan.losses + lossesDelta.value)
})

async function submit() {
  loading.value = true
  error.value = ''

  try {
    const payload = { reason: reason.value || null }

    if (mode.value === 'set') {
      payload.wins = winsSet.value
      payload.losses = lossesSet.value
      await adminApi.setClanStats(props.clan.id, payload)
    } else {
      payload.wins = winsDelta.value
      payload.losses = lossesDelta.value
      await adminApi.clanStats(props.clan.id, payload)
    }

    emit('updated')
  } catch (e) {
    error.value = e.message || 'Ошибка'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>
          Статистика
          <span class="tag">[{{ clan.tag }}]</span>
          {{ clan.name }}
        </h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="error" class="error">{{ error }}</div>

        <!-- Текущее -->
        <div class="current">
          <div class="current__block">
            <span class="current__label">Побед сейчас</span>
            <span class="current__value win">{{ clan.wins }}</span>
          </div>
          <div class="current__block">
            <span class="current__label">Поражений сейчас</span>
            <span class="current__value loss">{{ clan.losses }}</span>
          </div>
          <div class="current__block">
            <span class="current__label">Сила</span>
            <span class="current__value power">{{ clan.power }}</span>
          </div>
        </div>

        <!-- Режим -->
        <div class="mode-switch">
          <button
              :class="{ active: mode === 'delta' }"
              @click="mode = 'delta'"
          >
            Прибавить / убавить
          </button>
          <button
              :class="{ active: mode === 'set' }"
              @click="mode = 'set'"
          >
            Установить точное
          </button>
        </div>

        <!-- Delta -->
        <div v-if="mode === 'delta'" class="grid">
          <div class="field">
            <label>Победы (дельта)</label>
            <div class="delta">
              <button
                  class="delta__btn"
                  :disabled="winsDelta <= -999"
                  @click="winsDelta--"
              >−</button>
              <input
                  v-model.number="winsDelta"
                  type="number"
                  min="-999"
                  max="999"
              />
              <button
                  class="delta__btn"
                  :disabled="winsDelta >= 999"
                  @click="winsDelta++"
              >+</button>
            </div>
          </div>

          <div class="field">
            <label>Поражения (дельта)</label>
            <div class="delta">
              <button
                  class="delta__btn"
                  :disabled="lossesDelta <= -999"
                  @click="lossesDelta--"
              >−</button>
              <input
                  v-model.number="lossesDelta"
                  type="number"
                  min="-999"
                  max="999"
              />
              <button
                  class="delta__btn"
                  :disabled="lossesDelta >= 999"
                  @click="lossesDelta++"
              >+</button>
            </div>
          </div>
        </div>

        <!-- Set -->
        <div v-else class="grid">
          <div class="field">
            <label>Победы</label>
            <input
                v-model.number="winsSet"
                type="number"
                min="0"
                max="100000"
            />
          </div>

          <div class="field">
            <label>Поражения</label>
            <input
                v-model.number="lossesSet"
                type="number"
                min="0"
                max="100000"
            />
          </div>
        </div>

        <!-- Причина -->
        <div class="field">
          <label>Причина (опционально)</label>
          <input
              v-model="reason"
              type="text"
              maxlength="255"
              placeholder="Например: правка ошибки, ручная корректировка"
          />
        </div>

        <!-- Превью -->
        <div class="preview">
          <div class="preview__title">Итог после сохранения</div>
          <div class="preview__row">
            <span class="preview__label">Победы:</span>
            <span class="preview__value">
                            {{ clan.wins }}
                            <span class="arrow">→</span>
                            <b class="win">{{ previewWins }}</b>
                        </span>
          </div>
          <div class="preview__row">
            <span class="preview__label">Поражения:</span>
            <span class="preview__value">
                            {{ clan.losses }}
                            <span class="arrow">→</span>
                            <b class="loss">{{ previewLosses }}</b>
                        </span>
          </div>
        </div>
      </div>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Отмена</button>
        <button class="btn-save" :disabled="loading" @click="submit">
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminClanStatsModal.css";
</style>
