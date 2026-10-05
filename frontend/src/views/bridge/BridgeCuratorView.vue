<script setup>
/**
 * Каталог бриджа для куратора: виды и подвиды.
 *
 * Куратор добавляет виды строительства и подвиды к ним. Подвиды потом
 * показываются пиллами под названием вида в профилях.
 */
import { onMounted, ref } from 'vue'
import { bridgeCuratorApi } from '@/services/bridge/bridge.js'
import { alert as alertDialog, confirm as confirmDialog } from '@/utils/dialog.js'

const loading = ref(true)
const error = ref('')
const catalog = ref([])

const newTechnique = ref({ label: '', description: '' })
const creating = ref(false)

// Какой вид сейчас редактируем и какое поле
const editing = ref(null)
const editValue = ref('')

const variantInput = ref({})
const variantBusy = ref(null)

async function load() {
  loading.value = true
  error.value = ''

  try {
    const data = await bridgeCuratorApi.catalog()

    catalog.value = data.data ?? []
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить каталог'
  } finally {
    loading.value = false
  }
}

async function addTechnique() {
  if (!newTechnique.value.label.trim()) return

  creating.value = true
  error.value = ''

  try {
    await bridgeCuratorApi.createTechnique({
      label: newTechnique.value.label.trim(),
      description: newTechnique.value.description.trim() || null,
    })

    newTechnique.value = { label: '', description: '' }
    await load()
  } catch (e) {
    error.value = e.message || 'Не удалось добавить вид'
  } finally {
    creating.value = false
  }
}

function startEdit(technique, field) {
  editing.value = { id: technique.id, field }
  editValue.value = technique[field] ?? ''
}

function cancelEdit() {
  editing.value = null
  editValue.value = ''
}

async function saveEdit(technique) {
  const { field } = editing.value

  try {
    await bridgeCuratorApi.updateTechnique(technique.id, { [field]: editValue.value })

    cancelEdit()
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось сохранить')
  }
}

async function toggleActive(technique) {
  try {
    await bridgeCuratorApi.updateTechnique(technique.id, { is_active: !technique.is_active })
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось переключить')
  }
}

async function removeTechnique(technique) {
  const ok = await confirmDialog(
      `Удалить вид «${technique.label}»? Если по нему есть заявки, он будет скрыт, а не удалён.`,
      { danger: true, confirmText: 'Удалить' },
  )

  if (!ok) return

  try {
    await bridgeCuratorApi.deleteTechnique(technique.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось удалить')
  }
}

async function addVariant(technique) {
  const label = (variantInput.value[technique.id] ?? '').trim()

  if (!label) return

  variantBusy.value = technique.id

  try {
    await bridgeCuratorApi.createVariant(technique.id, label)

    variantInput.value[technique.id] = ''
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось добавить подвид')
  } finally {
    variantBusy.value = null
  }
}

async function removeVariant(variant) {
  try {
    await bridgeCuratorApi.deleteVariant(variant.id)
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось удалить подвид')
  }
}

async function toggleVariant(variant) {
  try {
    await bridgeCuratorApi.updateVariant(variant.id, { is_active: !variant.is_active })
    await load()
  } catch (e) {
    await alertDialog(e.message || 'Не удалось переключить подвид')
  }
}

onMounted(load)
</script>

<template>
  <main class="curator">
    <header class="curator__head">
      <div>
        <div class="curator__eyebrow">
          BRIDGE CATALOG
        </div>

        <h1>Виды бриджа</h1>

        <p>
          Виды строительства и подвиды к ним. Подвиды показываются пиллами
          под названием вида в профилях игроков.
        </p>
      </div>

      <div class="curator__count">
        <span>{{ catalog.length }}</span>
        видов
      </div>
    </header>

    <div v-if="error" class="curator__error">{{ error }}</div>

    <!-- Добавление вида -->
    <section class="curator__create">
      <h2>Новый вид</h2>

      <div class="create-row">
        <input
            v-model="newTechnique.label"
            type="text"
            maxlength="96"
            placeholder="Название, например Moonwalk"
            @keyup.enter="addTechnique"
        />

        <input
            v-model="newTechnique.description"
            type="text"
            maxlength="255"
            placeholder="Короткое описание (необязательно)"
            @keyup.enter="addTechnique"
        />

        <button
            type="button"
            :disabled="creating || !newTechnique.label.trim()"
            @click="addTechnique"
        >
            {{ creating ? '...' : 'Добавить' }}
        </button>
      </div>
    </section>

    <div v-if="loading" class="curator__state">Загрузка каталога...</div>

    <div v-else-if="!catalog.length" class="curator__state">
      Каталог пуст — добавь первый вид
    </div>

    <section v-else class="curator__list">
      <article
          v-for="technique in catalog"
          :key="technique.id"
          class="tech"
          :class="{ 'tech--off': !technique.is_active }"
      >
        <header class="tech__head">
          <div class="tech__titles">
            <template v-if="editing?.id === technique.id && editing.field === 'label'">
              <input
                  v-model="editValue"
                  class="tech__input"
                  maxlength="96"
                  @keyup.enter="saveEdit(technique)"
                  @keyup.esc="cancelEdit"
              />

              <button type="button" class="tech__mini" @click="saveEdit(technique)">ок</button>
              <button type="button" class="tech__mini" @click="cancelEdit">отмена</button>
            </template>

            <template v-else>
              <span
                  class="tech__label"
                  @click="startEdit(technique, 'label')"
              >
                {{ technique.label }}
              </span>

              <span class="tech__key">{{ technique.key }}</span>
            </template>
          </div>

          <div class="tech__actions">
            <span
                v-if="!technique.is_active"
                class="tech__badge"
            >
              скрыт
            </span>

            <button type="button" class="tech__mini" @click="toggleActive(technique)">
              {{ technique.is_active ? 'скрыть' : 'показать' }}
            </button>

            <button type="button" class="tech__mini tech__mini--danger" @click="removeTechnique(technique)">
              удалить
            </button>
          </div>
        </header>

        <p
            v-if="technique.description"
            class="tech__desc"
        >
          {{ technique.description }}
        </p>

        <!-- Подвиды: пиллы -->
        <div class="tech__variants">
          <span
              v-for="variant in technique.variants"
              :key="variant.id"
              class="pill"
              :class="{ 'pill--off': !variant.is_active }"
          >
            {{ variant.label }}

            <button
                type="button"
                class="pill__toggle"
                :title="variant.is_active ? 'Скрыть подвид' : 'Показать подвид'"
                @click="toggleVariant(variant)"
            >
              {{ variant.is_active ? '◦' : '◌' }}
            </button>

            <button
                type="button"
                class="pill__remove"
                title="Удалить подвид"
                @click="removeVariant(variant)"
            >
              ✕
            </button>
          </span>

          <span
              v-if="!technique.variants.length"
              class="tech__no-variants"
          >
            подвидов нет
          </span>
        </div>

        <!-- Добавить подвид -->
        <div class="tech__add-variant">
          <input
              v-model="variantInput[technique.id]"
              type="text"
              maxlength="96"
              placeholder="Новый подвид, например «с удержанием»"
              @keyup.enter="addVariant(technique)"
          />

          <button
              type="button"
              class="tech__mini"
              :disabled="variantBusy === technique.id || !(variantInput[technique.id] ?? '').trim()"
              @click="addVariant(technique)"
          >
            + подвид
          </button>
        </div>
      </article>
    </section>
  </main>
</template>

<style scoped>
@import "@/views/bridge/BridgeCuratorView.css";
</style>
