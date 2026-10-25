<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { tierAppealsApi } from '@/services/tiers/tierTests.js'
import { ASPECTS, ASPECT_MAX } from '@/data/players/aspects.js'
import { userLink } from '@/utils/links.js'
import { alert } from '@/utils/dialog.js'

/*
 * Апелляции на результат тир-теста. Отклонить — с объяснением,
 * принять — с новыми оценками: появится пересмотренный тест.
 */

const STATUSES = [
  { key: 'pending', label: 'Ожидают' },
  { key: 'approved', label: 'Приняты' },
  { key: 'rejected', label: 'Отклонены' },
]

const status = ref('pending')
const appeals = ref([])
const loading = ref(true)

// Открытая форма решения: id апелляции → { resolution, aspects }
const openedId = ref(null)
const form = reactive({ resolution: '', aspects: {} })
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    appeals.value = (await tierAppealsApi.list(status.value)).appeals ?? []
  } catch {
    appeals.value = []
  } finally {
    loading.value = false
  }
}

function fieldsFor(appeal) {
  return ASPECTS[appeal.tier_test?.mode] ?? ASPECTS.pvp
}

function open(appeal) {
  openedId.value = appeal.id
  form.resolution = ''
  // Стартуем с оценок исходного теста — модератор правит только спорное
  const original = appeal.tier_test?.aspects ?? {}
  form.aspects = Object.fromEntries(fieldsFor(appeal).map((f) => [f.key, Number(original[f.key] ?? 0)]))
}

function scoreOf(aspects) {
  return Object.values(aspects).reduce((sum, v) => sum + (Number(v) || 0), 0)
}

async function resolve(appeal, decision) {
  if (saving.value) return
  saving.value = true

  try {
    await tierAppealsApi.resolve(appeal.id, {
      decision,
      resolution: form.resolution.trim() || null,
      ...(decision === 'approved' ? { aspects: form.aspects } : {}),
    })
    openedId.value = null
    await load()
  } catch (e) {
    await alert(e.message || 'Не удалось сохранить решение', { title: 'Апелляция' })
  } finally {
    saving.value = false
  }
}

watch(status, load)
onMounted(load)
</script>

<template>
  <section class="appeals">
    <header class="appeals__head">
      <div>
        <h2>Апелляции</h2>
        <p>Игроки оспаривают результат тир-теста в течение 14 дней. Свои тесты пересматривать нельзя.</p>
      </div>

      <div class="appeals__filter">
        <button
            v-for="s in STATUSES"
            :key="s.key"
            type="button"
            :class="{ active: status === s.key }"
            @click="status = s.key"
        >
          {{ s.label }}
        </button>
      </div>
    </header>

    <p v-if="loading" class="appeals__empty">Загрузка…</p>
    <p v-else-if="!appeals.length" class="appeals__empty">Апелляций нет.</p>

    <article v-for="a in appeals" v-else :key="a.id" class="appeal">
      <div class="appeal__top">
        <RouterLink :to="userLink(a.user)" class="appeal__player">{{ a.user?.username }}</RouterLink>
        <span class="appeal__meta">
          {{ a.tier_test?.mode === 'pvp' ? 'PvP' : 'BedWars' }} ·
          тест #{{ a.tier_test_id }} · тестер {{ a.tier_test?.tester?.username ?? '—' }}
        </span>
        <span class="appeal__result">{{ a.tier_test?.result_tier }} · {{ a.tier_test?.result_score }}%</span>
      </div>

      <blockquote class="appeal__reason">{{ a.reason }}</blockquote>

      <p v-if="a.tier_test?.notes" class="appeal__notes">Комментарий тестера: {{ a.tier_test.notes }}</p>

      <p v-if="a.status !== 'pending'" class="appeal__decision" :class="`appeal__decision--${a.status}`">
        {{ a.status === 'approved' ? 'Принята' : 'Отклонена' }}
        <template v-if="a.revised_test"> → {{ a.revised_test.result_tier }} ({{ a.revised_test.result_score }}%)</template>
        · {{ a.reviewer?.username }}
        <template v-if="a.resolution">: {{ a.resolution }}</template>
      </p>

      <template v-else>
        <button v-if="openedId !== a.id" type="button" class="btn btn-secondary" @click="open(a)">
          Рассмотреть
        </button>

        <div v-else class="appeal__form">
          <div class="appeal__aspects">
            <label v-for="f in fieldsFor(a)" :key="f.key">
              <span>{{ f.label }}</span>
              <input v-model.number="form.aspects[f.key]" type="number" min="0" :max="ASPECT_MAX" />
            </label>
            <strong class="appeal__score">{{ scoreOf(form.aspects) }}%</strong>
          </div>

          <textarea
              v-model="form.resolution"
              rows="2"
              placeholder="Комментарий для игрока (обязателен при отказе)"
          ></textarea>

          <div class="appeal__actions">
            <button type="button" class="btn btn-primary" :disabled="saving" @click="resolve(a, 'approved')">
              Принять с новыми оценками
            </button>
            <button type="button" class="btn btn-secondary" :disabled="saving" @click="resolve(a, 'rejected')">
              Отклонить
            </button>
            <button type="button" class="appeal__cancel" @click="openedId = null">Закрыть</button>
          </div>
        </div>
      </template>
    </article>
  </section>
</template>

<style scoped>
.appeals {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.appeals__head {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  justify-content: space-between;
}

.appeals__head h2 {
  margin: 0;
}

.appeals__head p,
.appeals__empty {
  margin: 4px 0 0;
  color: var(--text-muted);
}

.appeals__filter {
  display: flex;
  gap: 6px;
}

.appeals__filter button {
  padding: 6px 12px;
  color: var(--text-muted);
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 6px;
  cursor: pointer;
}

.appeals__filter button.active {
  color: var(--text);
  border-color: var(--accent);
  background: var(--accent-soft);
}

.appeal {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.appeal__top {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: baseline;
}

.appeal__player {
  font-weight: 700;
  color: var(--text);
}

.appeal__meta {
  font-size: 13px;
  color: var(--text-muted);
}

.appeal__result {
  margin-left: auto;
  font-family: var(--font-display);
  color: var(--accent-light);
}

.appeal__reason {
  margin: 0;
  padding: 10px 12px;
  border-left: 3px solid var(--accent);
  background: rgba(255, 255, 255, 0.02);
  white-space: pre-wrap;
}

.appeal__notes {
  margin: 0;
  font-size: 13px;
  color: var(--text-muted);
}

.appeal__decision {
  margin: 0;
  font-size: 14px;
}

.appeal__decision--approved {
  color: #4ade80;
}

.appeal__decision--rejected {
  color: #fca5a5;
}

.appeal__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.appeal__aspects {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: flex-end;
}

.appeal__aspects label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--text-muted);
}

.appeal__aspects input {
  width: 92px;
}

.appeal__score {
  padding-bottom: 8px;
  font-family: var(--font-display);
}

.appeal__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.appeal__cancel {
  color: var(--text-muted);
  background: none;
  border: none;
  cursor: pointer;
}
</style>
