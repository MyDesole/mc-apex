<script setup>
/**
 * Оформление и набор клана (лидер): эмблема, цветовая тема, девиз, цвет тега,
 * «кого ищем» и порог рейтинга. Часть вариантов открывается с уровнем клана —
 * закрытые показываются с замочком и уровнем, на котором откроются.
 */
import { computed, onMounted, ref } from 'vue'
import { myClanApi } from '@/services/clan/myClan.js'
import ClanStyleBar from '@/components/clan/ClanStyleBar.vue'

const props = defineProps({
  clan: { type: Object, required: true },
})

const emit = defineEmits(['close', 'saved'])

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const catalog = ref(null)
const level = ref(1)

const form = ref({
  motto: '',
  emblem: null,
  theme: null,
  tag_color: '',
  recruit_text: '',
  min_rating: 0,
})

// Живой предпросмотр: то, как карточка клана будет выглядеть для остальных
const preview = computed(() => {
  const emblem = catalog.value?.emblems.find((e) => e.key === form.value.emblem)
  const theme = catalog.value?.themes.find((t) => t.key === form.value.theme)

  return {
    ...props.clan,
    style: {
      motto: form.value.motto || null,
      emblem: emblem ? { key: emblem.key, glyph: emblem.glyph, title: emblem.title } : null,
      theme: theme ? { key: theme.key, title: theme.title, from: theme.from, to: theme.to } : null,
      tag_color: form.value.tag_color || null,
      recruit_text: form.value.recruit_text || null,
      min_rating: Number(form.value.min_rating) || 0,
    },
  }
})

async function load() {
  try {
    const data = await myClanApi.style()
    catalog.value = data.catalog
    level.value = data.level

    const c = data.current
    form.value = {
      motto: c.motto ?? '',
      emblem: c.emblem?.key ?? null,
      theme: c.theme?.key ?? null,
      tag_color: c.tag_color ?? '',
      recruit_text: c.recruit_text ?? '',
      min_rating: c.min_rating ?? 0,
    }
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить оформление.'
  } finally {
    loading.value = false
  }
}

function pick(field, item) {
  if (!item.unlocked) return
  form.value[field] = form.value[field] === item.key ? null : item.key
}

async function save() {
  saving.value = true
  error.value = ''
  errors.value = {}

  try {
    await myClanApi.updateStyle({
      motto: form.value.motto,
      emblem: form.value.emblem,
      theme: form.value.theme,
      tag_color: form.value.tag_color || null,
      recruit_text: form.value.recruit_text,
      min_rating: Number(form.value.min_rating) || 0,
    })
    emit('saved')
    emit('close')
  } catch (e) {
    errors.value = e.errors ?? {}
    error.value = e.message || 'Не удалось сохранить.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <Teleport to="body">
    <div class="cs-overlay" @click.self="emit('close')">
      <div class="cs-modal">
        <header class="cs-head">
          <h2>Оформление клана</h2>
          <button type="button" class="cs-x" aria-label="Закрыть" @click="emit('close')">×</button>
        </header>

        <p v-if="loading" class="cs-hint">Загрузка…</p>

        <div v-else-if="catalog" class="cs-body">
          <ClanStyleBar :clan="preview" class="cs-preview" />
          <p v-if="!preview.style.emblem && !preview.style.motto && !preview.style.theme" class="cs-hint">
            Выберите эмблему, тему или девиз — так клан увидят в списке и на странице клана.
          </p>

          <label class="cs-field">
            <span>Девиз клана</span>
            <input v-model="form.motto" type="text" :maxlength="catalog.motto_max" placeholder="Например: Кровать — это святое" />
          </label>

          <div class="cs-field">
            <span>Эмблема <small>открывается с уровнем клана (сейчас {{ level }})</small></span>
            <div class="cs-grid">
              <button
                  v-for="e in catalog.emblems"
                  :key="e.key"
                  type="button"
                  class="cs-chip"
                  :class="{ 'is-on': form.emblem === e.key, 'is-locked': !e.unlocked }"
                  :title="e.unlocked ? e.title : `Откроется на ${e.level} уровне`"
                  @click="pick('emblem', e)"
              >
                <span class="cs-glyph">{{ e.glyph }}</span>
                <small>{{ e.unlocked ? e.title : `🔒 ${e.level} ур.` }}</small>
              </button>
            </div>
            <small v-if="errors.emblem" class="cs-error">{{ errors.emblem[0] }}</small>
          </div>

          <div class="cs-field">
            <span>Цветовая тема</span>
            <div class="cs-grid">
              <button
                  v-for="t in catalog.themes"
                  :key="t.key"
                  type="button"
                  class="cs-chip cs-chip--theme"
                  :class="{ 'is-on': form.theme === t.key, 'is-locked': !t.unlocked }"
                  :style="{ background: `linear-gradient(135deg, ${t.from}, ${t.to})` }"
                  :title="t.unlocked ? t.title : `Откроется на ${t.level} уровне`"
                  @click="pick('theme', t)"
              >
                <small>{{ t.unlocked ? t.title : `🔒 ${t.level} ур.` }}</small>
              </button>
            </div>
            <small v-if="errors.theme" class="cs-error">{{ errors.theme[0] }}</small>
          </div>

          <div class="cs-field">
            <span>Цвет тега <small>со {{ catalog.tag_color_level }} уровня</small></span>
            <div class="cs-row">
              <input
                  v-model="form.tag_color"
                  type="color"
                  :disabled="!catalog.tag_color_unlocked"
              />
              <button v-if="form.tag_color" type="button" class="cs-link" @click="form.tag_color = ''">сбросить</button>
              <span v-if="!catalog.tag_color_unlocked" class="cs-hint">🔒 откроется на {{ catalog.tag_color_level }} уровне</span>
            </div>
            <small v-if="errors.tag_color" class="cs-error">{{ errors.tag_color[0] }}</small>
          </div>

          <label class="cs-field">
            <span>Кого ищем <small>показывается в списке кланов</small></span>
            <input v-model="form.recruit_text" type="text" :maxlength="catalog.recruit_max" placeholder="Например: ищем бедварсеров от B-тира, играем вечером" />
          </label>

          <label class="cs-field">
            <span>Минимум очков рейтинга для заявки <small>0 — без порога; по приглашению порог не действует</small></span>
            <input v-model.number="form.min_rating" type="number" min="0" max="100000" />
          </label>

          <p v-if="error" class="cs-error">{{ error }}</p>
        </div>

        <footer class="cs-foot">
          <button type="button" class="cs-btn cs-btn--ghost" @click="emit('close')">Отмена</button>
          <button type="button" class="cs-btn" :disabled="saving || loading" @click="save">
            {{ saving ? 'Сохраняем…' : 'Сохранить' }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@import "@/components/clan/dialogs/ClanDialogs.css";
</style>
