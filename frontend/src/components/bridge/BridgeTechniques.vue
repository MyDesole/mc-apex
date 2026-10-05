<script setup>
/**
 * Виды бриджа, которые знает игрок.
 *
 * Показывает подтверждённые виды ярко, заявленные — серыми, остальные
 * приглушённо. Подача заявки живёт в отдельной модалке подтверждения,
 * история — в отдельном блоке.
 */
import { computed, onMounted, ref } from 'vue'
import { bridgeApi } from '@/services/bridge/bridge.js'

const props = defineProps({
  // Подтверждённые виды из ответа профиля
  techniques: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  rank: { type: Object, default: () => ({ position: null, total: 0 }) },
  // Своя ли это страница: в чужом профиле только просмотр
  editable: { type: Boolean, default: false },
})

// Каталог с моими заявками — нужен только в своём профиле
const catalog = ref([])
const loading = ref(false)
const error = ref('')
const togglingVariants = ref(null)

const confirmedCount = computed(() => props.summary?.confirmed_count ?? props.techniques.length)
const aspectsTotal = computed(() => props.summary?.aspects_total ?? 0)
const maxTotal = computed(() => Math.max(1, confirmedCount.value * 300))

/** Свои виды: подтверждённые + заявленные, в одном списке с состоянием. */
const rows = computed(() => {
  if (!props.editable) {
    return props.techniques.map(row => ({
      technique: row.technique,
      submission: row,
    }))
  }

  // В своём профиле показываем только то, что игрок уже заявил
  return catalog.value.filter(row => row.submission)
})

async function loadCatalog() {
  if (!props.editable) return

  loading.value = true
  error.value = ''

  try {
    const data = await bridgeApi.techniques()

    catalog.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить виды бриджа'
  } finally {
    loading.value = false
  }
}

/**
 * Игрок включает и выключает подвиды своей заявки.
 *
 * Пока заявку не подтвердил тестер, набор можно менять в любой момент.
 */
async function toggleVariant(row, variant) {
  const current = (row.submission.variants ?? []).map(v => v.id)
  const has = current.includes(variant.id)

  const next = has
      ? current.filter(id => id !== variant.id)
      : [...current, variant.id]

  togglingVariants.value = row.submission.id
  error.value = ''

  try {
    await bridgeApi.setVariants(row.submission.id, next)

    await loadCatalog()
  } catch (e) {
    error.value = e.message || 'Не удалось изменить подвиды'
  } finally {
    togglingVariants.value = null
  }
}

/** Отмечен ли подвид в заявке. */
function isVariantOn(row, variantId) {
  return (row.submission?.variants ?? []).some(v => v.id === variantId)
}

onMounted(loadCatalog)

defineExpose({ load: loadCatalog })
</script>

<template>
  <section class="bridge-block">
    <header class="bridge-block__head">
      <div>
        <div class="bridge-block__eyebrow">
          BRIDGE MASTERY
        </div>

        <h3 class="bridge-block__title">
          {{ editable ? 'Мои виды бриджа' : 'Виды бриджа' }}
        </h3>
      </div>

      <div class="bridge-block__summary">
        <span class="bridge-block__count">
          {{ confirmedCount }}
        </span>

        <span class="bridge-block__count-label">
          подтверждено
        </span>

        <span
            v-if="confirmedCount"
            class="bridge-block__total"
        >
          {{ aspectsTotal }} / {{ maxTotal }}
        </span>
      </div>
    </header>

    <div v-if="error" class="bridge-block__error">{{ error }}</div>

    <div v-if="loading" class="bridge-block__state">
      Загрузка видов...
    </div>

    <div v-else-if="!rows.length" class="bridge-block__state">
      {{ editable
          ? 'Пока ничего не подтверждено — нажми «Подтвердить вид»'
          : 'Пока нет подтверждённых видов' }}
    </div>

    <ul v-else class="bridge-list">
      <li
          v-for="row in rows"
          :key="row.technique.id"
          class="bridge-item"
          :class="{
            'bridge-item--confirmed': row.submission?.is_confirmed,
            'bridge-item--pending': row.submission && !row.submission.is_confirmed,
            'bridge-item--rejected': row.submission?.status === 'rejected',
          }"
      >
        <div class="bridge-item__main">
          <span class="bridge-item__label">
            {{ row.technique.label }}
          </span>

          <span
              v-if="row.technique.description"
              class="bridge-item__desc"
          >
            {{ row.technique.description }}
          </span>

          <!--
            Подвиды: у неподтверждённой заявки игрок включает и выключает их
            сам, у подтверждённой — просто показываем набор.
          -->
          <span
              v-if="row.technique.variants?.length"
              class="variant-pills"
          >
            <template v-if="editable && row.submission && !row.submission.is_confirmed">
              <button
                  v-for="variant in row.technique.variants"
                  :key="variant.id"
                  type="button"
                  class="variant-pill variant-pill--toggle"
                  :class="{
                    'variant-pill--on': isVariantOn(row, variant.id),
                    'variant-pill--special': variant.is_special,
                  }"
                  :disabled="togglingVariants === row.submission.id"
                  @click="toggleVariant(row, variant)"
              >
                <span class="variant-pill__mark">
                  {{ isVariantOn(row, variant.id) ? '✓' : '+' }}
                </span>
                {{ variant.label }}
              </button>
            </template>

            <template v-else>
              <span
                  v-for="variant in (row.submission?.variants?.length
                      ? row.submission.variants
                      : row.technique.variants)"
                  :key="variant.id"
                  class="variant-pill"
                  :class="{ 'variant-pill--special': variant.is_special }"
              >
                {{ variant.label }}
                <span v-if="variant.is_special" class="variant-pill__star">★</span>
              </span>
            </template>
          </span>
        </div>

        <!-- Подтверждено: оценка тестера -->
        <div
            v-if="row.submission?.is_confirmed"
            class="bridge-item__score"
        >
          <span class="bridge-item__score-value">
            {{ row.submission.score }}/10
          </span>

          <span class="bridge-item__aspects">
            {{ row.submission.total }}/300
          </span>
        </div>

        <div
            v-else
            class="bridge-item__pending"
        >
          <span class="bridge-item__badge">
            {{ row.submission?.status === 'rejected' ? 'отклонён' : 'на проверке' }}
          </span>

          <a
              v-if="row.submission?.has_video"
              :href="row.submission.video_url"
              target="_blank"
              rel="noopener"
              class="bridge-item__link"
          >
            видео
          </a>
        </div>
      </li>
    </ul>

    <!--
      Требования к ролику: за несоответствие заявку отклоняют, поэтому
      блок заметный, а не серый текст.
    -->
    <aside
        v-if="editable"
        class="bridge-requirements"
    >
      <div class="bridge-requirements__title">
        <span class="bridge-requirements__icon">!</span>
        Каким должно быть видео
      </div>

      <ul class="bridge-requirements__list">
        <li>Записано <b>на сервере</b>, не в одиночном мире</li>
        <li>Длительность <b>около 2 минут</b></li>
        <li><b>Неудачи обрезать нельзя</b></li>
        <li>Должен быть виден <b>CPS-мод и Keystrokes</b></li>
        <li>Несколько бриджей — <b>отдельный ролик на каждый</b></li>
      </ul>

      <p class="bridge-requirements__warn">
        За подозрение в читах, монтаже или чужом клипе вызовем на проверку.
      </p>
    </aside>
  </section>
</template>

<style scoped>
@import "@/components/bridge/BridgeTechniques.css";
</style>
