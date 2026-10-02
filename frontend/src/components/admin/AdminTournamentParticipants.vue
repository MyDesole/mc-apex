<script setup>
import { confirm as confirmDialog } from '@/utils/dialog.js'
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/services/core/admin.js'

const props = defineProps({
  tournament: { type: Object, required: true },
})

const emit = defineEmits(['close', 'updated'])

const loading = ref(true)
const participants = ref([])
const processing = ref(null)
const seedsDraft = ref({})

async function load() {
  loading.value = true
  try {
    const data = await adminApi.tournamentParticipants(props.tournament.id)
    participants.value = data.participants

    // инициализируем seeds
    seedsDraft.value = {}
    data.participants.forEach(p => {
      seedsDraft.value[p.id] = p.seed ?? ''
    })
  } finally {
    loading.value = false
  }
}

async function approve(p) {
  processing.value = p.id
  try {
    await adminApi.approveParticipant(props.tournament.id, p.id)
    await load()
  } finally {
    processing.value = null
  }
}

async function reject(p) {
  processing.value = p.id
  try {
    await adminApi.rejectParticipant(props.tournament.id, p.id)
    await load()
  } finally {
    processing.value = null
  }
}

// --- Генерация турнирной сетки ---
const generating = ref(false)
const generateError = ref('')
const generateOk = ref('')

const approvedCount = computed(() =>
    participants.value.filter((p) => p.status === 'approved').length
)

const canGenerate = computed(() =>
    approvedCount.value >= 2 && props.tournament.format === 'single_elim'
)

async function generateBracket() {
  generateError.value = ''
  generateOk.value = ''

  if (!canGenerate.value) {
    generateError.value = approvedCount.value < 2
        ? 'Нужно минимум 2 одобренных участника — сейчас ' + approvedCount.value + '.'
        : 'Генерация поддерживается только для формата single_elim.'
    return
  }

  if (!await confirmDialog('Сформировать сетку? Текущие матчи будут пересозданы.')) return

  generating.value = true

  try {
    const data = await adminApi.generateBracket(props.tournament.id)

    generateOk.value = 'Сетка создана: матчей ' + (data.matches?.length ?? 0) + '. Открой вкладку «Сетка».'
    emit('updated')
  } catch (e) {
    generateError.value = e.message || 'Не удалось сформировать сетку.'
  } finally {
    generating.value = false
  }
}

async function saveSeeds() {
  const seeds = participants.value
      .filter(p => seedsDraft.value[p.id] !== '')
      .map(p => ({ id: p.id, seed: Number(seedsDraft.value[p.id]) }))

  await adminApi.setSeeds(props.tournament.id, seeds)
  await load()
}

const statusLabels = {
  pending: 'На рассмотрении',
  approved: 'Одобрен',
  rejected: 'Отклонён',
  withdrawn: 'Отозван',
}

function displayName(p) {
  if (p.user) return p.user.username
  if (p.clan) return `[${p.clan.tag}] ${p.clan.name}`
  return '—'
}

function displayAvatar(p) {
  if (p.user) return p.user.username.charAt(0).toUpperCase()
  if (p.clan) return p.clan.tag.charAt(0)
  return '?'
}

onMounted(load)
</script>

<template>
  <div class="modal-bg" @click.self="$emit('close')">
    <div class="modal">
      <header class="modal-head">
        <h2>Участники · {{ tournament.name }}</h2>
        <button class="close" @click="$emit('close')">✕</button>
      </header>

      <div class="body">
        <div v-if="loading" class="empty">Загрузка...</div>
        <div v-else-if="!participants.length" class="empty">
          Заявок ещё нет
        </div>

        <template v-else>
          <div class="hint">
            Seed — порядковый номер для расстановки в сетке. Меньше = сильнее.
          </div>

          <div class="list">
            <div
                v-for="p in participants"
                :key="p.id"
                class="row"
                :class="`row--${p.status}`"
            >
              <div class="avatar">
                {{ displayAvatar(p) }}
              </div>

              <div class="info">
                <div class="name">{{ displayName(p) }}</div>
                <div class="meta">
                                    <span class="status" :class="`status-${p.status}`">
                                        {{ statusLabels[p.status] }}
                                    </span>
                  <span v-if="p.user?.tier" class="tier">
                                        Тир: {{ p.user.tier }}
                                    </span>
                </div>
              </div>

              <div class="seed">
                <input
                    v-model="seedsDraft[p.id]"
                    type="number"
                    min="1"
                    placeholder="—"
                    class="seed-input"
                />
              </div>

              <div class="actions">
                <button
                    v-if="p.status === 'pending'"
                    class="btn-approve"
                    :disabled="processing === p.id"
                    @click="approve(p)"
                >
                  Принять
                </button>
                <button
                    v-if="p.status === 'pending'"
                    class="btn-reject"
                    :disabled="processing === p.id"
                    @click="reject(p)"
                >
                  Отклонить
                </button>

                <span v-else class="no-actions">—</span>
              </div>
            </div>
          </div>

          <div class="save-seeds">
            <button class="btn-save" @click="saveSeeds">
              Сохранить сиды
            </button>
          </div>
        </template>
      </div>

      <!-- Генерация сетки: логично делать сразу после одобрения участников -->
      <section class="bracket-gen">
        <div class="bracket-gen__info">
          <div class="bracket-gen__title">Турнирная сетка</div>
          <div class="bracket-gen__sub">
            Одобрено участников: <b>{{ approvedCount }}</b>
            <template v-if="tournament.format !== 'single_elim'">
              · формат <b>{{ tournament.format }}</b> — генерация пока только для single_elim
            </template>
          </div>
        </div>

        <button
            class="btn-generate"
            type="button"
            :disabled="generating || !canGenerate"
            @click="generateBracket"
        >
          {{ generating ? 'Формируем…' : 'Сформировать сетку' }}
        </button>
      </section>

      <p v-if="generateError" class="gen-msg gen-msg--error">{{ generateError }}</p>
      <p v-if="generateOk" class="gen-msg gen-msg--ok">{{ generateOk }}</p>

      <footer class="modal-foot">
        <button class="btn-cancel" @click="$emit('close')">Закрыть</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@import "@/components/admin/AdminTournamentParticipants.css";
</style>
