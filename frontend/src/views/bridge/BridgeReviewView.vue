<script setup>
/**
 * Панель бридж-тестера: проверка заявок на виды бриджа.
 *
 * Доступна только роли bridge_tester и админу — остальные игроки этот
 * раздел не видят. Тестер смотрит видео, ставит оценки и подтверждает
 * вид либо отклоняет его с причиной.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import BridgeRankBadge from '@/components/bridge/BridgeRankBadge.vue'
import { bridgeReviewApi } from '@/services/bridge/bridge.js'
import { alert as alertDialog } from '@/utils/dialog.js'

const loading = ref(true)
const error = ref('')
const rows = ref([])

// Звания: тестер выдаёт их вручную
const ranks = ref([])
const bridgers = ref([])
const history = ref([])
const tab = ref('queue')
const rankBusy = ref(null)

// Подвиды выбранной заявки: тестер включает и выключает их
const reviewVariants = ref([])

const rankLabel = computed(() => {
  if (!form.value.rank_id) return 'не меняем'

  return ranks.value.find(r => r.id === form.value.rank_id)?.label ?? 'не меняем'
})

const active = ref(null)
const processing = ref(false)
const form = ref(empty())

function empty() {
  return {
    stability: 0,
    speed: 0,
    difficulty: 0,
    score: 0,
    notes: '',
    rank_id: null,
  }
}

const total = computed(() =>
    (Number(form.value.stability) || 0)
    + (Number(form.value.speed) || 0)
    + (Number(form.value.difficulty) || 0),
)

/*
 * Тира в бридже нет: раньше он считался из суммы аспектов, но ранг
 * бриджера выдаёт тестер вручную. Оставляем только общую статистику.
 */

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [pending, rankList, playerList, historyList] = await Promise.all([
      bridgeReviewApi.pending(),
      bridgeReviewApi.ranks(),
      bridgeReviewApi.players(),
      bridgeReviewApi.history(),
    ])

    rows.value = pending.data ?? []
    ranks.value = rankList.data ?? []
    bridgers.value = playerList.data ?? []
    history.value = historyList.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить данные'
  } finally {
    loading.value = false
  }
}

/** Тестер выдаёт звание бриджеру. */
async function setRank(entry, rankId) {
  rankBusy.value = entry.user.id
  error.value = ''

  try {
    const result = await bridgeReviewApi.assignRank(entry.user.id, rankId || null)

    entry.rank = result.rank
  } catch (e) {
    error.value = e.message || 'Не удалось выдать звание'
  } finally {
    rankBusy.value = null
  }
}

function open(row) {
  active.value = row
  form.value = empty()
  error.value = ''

  // Отмечаем подвиды, которые заявил игрок
  const claimed = (row.variants ?? []).map(v => v.id)

  reviewVariants.value = (row.technique?.variants ?? []).map(v => ({
    ...v,
    enabled: claimed.includes(v.id),
  }))
}

/** Тестер включает или выключает подвид заявки. */
function toggleReviewVariant(variant) {
  variant.enabled = !variant.enabled
}

function close() {
  active.value = null
}

async function confirm() {
  processing.value = true
  error.value = ''

  try {
    await bridgeReviewApi.review(active.value.id, {
      confirm: true,
      stability: Number(form.value.stability) || 0,
      speed: Number(form.value.speed) || 0,
      difficulty: Number(form.value.difficulty) || 0,
      score: Number(form.value.score) || 0,
      notes: form.value.notes || null,
      variants: reviewVariants.value.filter(v => v.enabled).map(v => v.id),
    })

    // Звание выдаём отдельно: оно не обязательно при каждом подтверждении
    if (form.value.rank_id && active.value.user?.id) {
      await bridgeReviewApi.assignRank(active.value.user.id, form.value.rank_id)
    }

    close()
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось подтвердить'
  } finally {
    processing.value = false
  }
}

async function reject() {
  if (!form.value.notes.trim()) {
    error.value = 'Укажи причину отказа — игрок должен понять, что не так'

    return
  }

  processing.value = true
  error.value = ''

  try {
    await bridgeReviewApi.review(active.value.id, {
      confirm: false,
      notes: form.value.notes,
    })

    close()
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось отклонить'
  } finally {
    processing.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="bridge-review">
    <header class="bridge-review__head">
      <div>
        <div class="bridge-review__eyebrow">
          BRIDGE REVIEW
        </div>

        <h1>Проверка бриджа</h1>

        <p>
          Заявки на виды бриджа. Вид становится подтверждённым только после
          твоей проверки.
        </p>
      </div>

      <div class="bridge-review__count">
        <span>{{ rows.length }}</span>
        в очереди
      </div>
    </header>

    <!-- Очередь заявок и бриджеры с званиями -->
    <div class="review-tabs">
      <button
          type="button"
          class="review-tab"
          :class="{ 'review-tab--active': tab === 'queue' }"
          @click="tab = 'queue'"
      >
        Очередь
        <span v-if="rows.length" class="review-tab__badge">{{ rows.length }}</span>
      </button>

      <button
          type="button"
          class="review-tab"
          :class="{ 'review-tab--active': tab === 'bridgers' }"
          @click="tab = 'bridgers'"
      >
        Бриджеры
        <span v-if="bridgers.length" class="review-tab__badge">{{ bridgers.length }}</span>
      </button>

      <button
          type="button"
          class="review-tab"
          :class="{ 'review-tab--active': tab === 'history' }"
          @click="tab = 'history'"
      >
        История
        <span v-if="history.length" class="review-tab__badge">{{ history.length }}</span>
      </button>
    </div>

    <div v-if="error" class="bridge-review__error">{{ error }}</div>

    <div v-if="loading" class="bridge-review__state">
      Загрузка...
    </div>

    <div v-else-if="tab === 'queue' && !rows.length" class="bridge-review__state">
      Заявок на проверку нет
    </div>

    <div v-else-if="tab === 'bridgers' && !bridgers.length" class="bridge-review__state">
      Пока нет бриджеров с подтверждёнными видами
    </div>

    <div v-else-if="tab === 'history' && !history.length" class="bridge-review__state">
      Проверок пока не было
    </div>

    <!-- ============ ИСТОРИЯ: все проверки, включая чужие ============ -->
    <div v-else-if="tab === 'history'" class="history">
      <article
          v-for="row in history"
          :key="row.id"
          class="history-row"
          :class="row.is_confirmed ? 'history-row--ok' : 'history-row--no'"
      >
        <div class="history-row__who">
          <RouterLink
              :to="'/players/' + (row.user?.id ?? '')"
              class="history-row__name"
          >
            {{ row.user?.username }}
          </RouterLink>

          <span class="history-row__tech">{{ row.technique?.label }}</span>
        </div>

        <div class="history-row__result">
          <span
              v-if="row.is_confirmed"
              class="history-row__score"
          >
            {{ row.score }}/10 · {{ row.total }}/300
          </span>

          <span v-else class="history-row__rejected">отклонено</span>
        </div>

        <div class="history-row__reviewer">
          <span class="history-row__label">проверил</span>
          {{ row.reviewer?.username ?? '—' }}
        </div>
      </article>
    </div>

    <!-- ================= БРИДЖЕРЫ: выдача званий ================= -->
    <div v-else-if="tab === 'bridgers'" class="bridgers">
      <article
          v-for="entry in bridgers"
          :key="entry.user.id"
          class="bridger"
      >
        <div class="bridger__player">
          <div class="bridger__avatar">
            <img
                v-if="entry.user.avatar_url"
                :src="entry.user.avatar_url"
                :alt="entry.user.username"
            />

            <span v-else>
              {{ (entry.user.username || 'И')[0].toUpperCase() }}
            </span>
          </div>

          <div>
            <RouterLink
                :to="'/players/' + entry.user.id"
                class="bridger__name"
            >
              {{ entry.user.username }}
            </RouterLink>

            <div class="bridger__meta">
              видов: <b>{{ entry.techniques_count }}</b>
              · аспекты: <b>{{ entry.aspects_total }}</b>
            </div>
          </div>
        </div>

        <BridgeRankBadge
            :rank="entry.rank"
            empty-label="без звания"
            size="sm"
        />

        <select
            class="bridger__select"
            :disabled="rankBusy === entry.user.id"
            :value="entry.rank?.id ?? ''"
            @change="setRank(entry, $event.target.value ? Number($event.target.value) : null)"
        >
          <option value="">— снять звание —</option>
          <option
              v-for="rank in ranks"
              :key="rank.id"
              :value="rank.id"
          >
            {{ rank.label }}
          </option>
        </select>
      </article>
    </div>

    <div v-else class="bridge-review__list">
      <article
          v-for="row in rows"
          :key="row.id"
          class="request"
      >
        <div class="request__player">
          <div class="request__avatar">
            <img
                v-if="row.user?.avatar_url"
                :src="row.user.avatar_url"
                :alt="row.user.username"
            />

            <span v-else>
              {{ (row.user?.username || 'И')[0].toUpperCase() }}
            </span>
          </div>

          <div>
            <div class="request__name">
              {{ row.user?.username }}
            </div>

            <div class="request__meta">
              текущий тир: <b>{{ row.user?.tier ?? '—' }}</b>
              · {{ row.user?.tier_score ?? 0 }}%
            </div>

            <BridgeRankBadge
                v-if="row.user?.bridge_rank"
                :rank="row.user.bridge_rank"
                size="sm"
            />
          </div>
        </div>

        <div class="request__technique">
          <span class="request__label">Вид</span>
          {{ row.technique?.label }}
        </div>

        <a
            v-if="row.video_url"
            :href="row.video_url"
            target="_blank"
            rel="noopener"
            class="request__video"
        >
          Смотреть видео
        </a>

        <button
            type="button"
            class="request__open"
            @click="open(row)"
        >
          Проверить
        </button>
      </article>
    </div>

    <!-- Проверка заявки -->
    <Teleport to="body">
      <div
          v-if="active"
          class="review-modal-bg"
          @click.self="close"
      >
        <div class="review-modal">
          <header class="review-modal__head">
            <div>
              <h2>{{ active.technique?.label }}</h2>

              <div class="review-modal__player">
                <span>{{ active.user?.username }}</span>

                <!-- Текущее звание: видно, какой ранг у игрока сейчас -->
                <BridgeRankBadge
                    :rank="active.user?.bridge_rank"
                    empty-label="без звания"
                    size="sm"
                />
              </div>
            </div>

            <button type="button" class="review-modal__close" @click="close">✕</button>
          </header>

          <div class="review-modal__body">
            <div v-if="error" class="bridge-review__error">{{ error }}</div>

            <a
                v-if="active.video_url"
                :href="active.video_url"
                target="_blank"
                rel="noopener"
                class="review-modal__video"
            >
              Открыть видео в новой вкладке
            </a>

            <p class="review-modal__note">
              Проверь, что ролик записан на сервере, длится около двух минут,
              неудачи не обрезаны, виден CPS-мод и Keystrokes. При подозрении
              на читы, монтаж или чужой клип — отклони и вызови на проверку.
            </p>

            <div class="review-grid">
              <label class="review-field">
                <span>Стабильность <i>0–100</i></span>
                <input v-model.number="form.stability" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Скорость <i>0–100</i></span>
                <input v-model.number="form.speed" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Сложность <i>0–100</i></span>
                <input v-model.number="form.difficulty" type="number" min="0" max="100" />
              </label>

              <label class="review-field">
                <span>Владение видом <i>0–10</i></span>
                <input v-model.number="form.score" type="number" min="0" max="10" />
              </label>
            </div>

            <div class="review-result">
              <div>
                <span class="review-result__label">Общая статистика</span>
                <span class="review-result__value">{{ total }} / 300</span>
              </div>

              <div>
                <span class="review-result__label">Звание</span>
                <span class="review-result__value review-result__value--tier">
                  {{ rankLabel }}
                </span>
              </div>
            </div>

            <!-- Подвиды: тестер включает и выключает их перед подтверждением -->
            <div
                v-if="reviewVariants.length"
                class="review-field review-field--wide"
            >
              <span>Подвиды <i>тестер подтверждает набор</i></span>

              <div class="review-variants">
                <button
                    v-for="variant in reviewVariants"
                    :key="variant.id"
                    type="button"
                    class="review-variant"
                    :class="{
                      'review-variant--on': variant.enabled,
                      'review-variant--special': variant.is_special,
                    }"
                    @click="toggleReviewVariant(variant)"
                >
                  <span class="review-variant__mark">{{ variant.enabled ? '✓' : '+' }}</span>
                  {{ variant.label }}
                  <span v-if="variant.is_special" class="review-variant__star">★</span>
                </button>
              </div>
            </div>

            <label
                v-if="ranks.length"
                class="review-field review-field--wide"
            >
              <span>Звание бриджера <i>необязательно</i></span>

              <select v-model.number="form.rank_id">
                <option :value="null">— не менять звание —</option>
                <option
                    v-for="rank in ranks"
                    :key="rank.id"
                    :value="rank.id"
                >
                  {{ rank.label }}
                </option>
              </select>
            </label>

            <label class="review-field review-field--wide">
              <span>Комментарий <i>обязателен при отказе</i></span>
              <textarea
                  v-model="form.notes"
                  rows="3"
                  maxlength="1000"
                  placeholder="Что видно на видео, замечания"
              />
            </label>
          </div>

          <footer class="review-modal__foot">
            <button
                type="button"
                class="review-modal__reject"
                :disabled="processing"
                @click="reject"
            >
              Отклонить
            </button>

            <button
                type="button"
                class="review-modal__confirm"
                :disabled="processing"
                @click="confirm"
            >
              {{ processing ? '...' : 'Подтвердить вид' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
@import "@/views/bridge/BridgeReviewView.css";
</style>
