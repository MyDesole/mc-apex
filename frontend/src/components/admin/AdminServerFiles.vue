<script setup>
/**
 * Файловый менеджер игрового сервера: дерево слева, редактор справа.
 * Бэкенд сам проверяет пути, расширения и размер; здесь только удобство.
 * Удаление — в корзину на сервере, с подтверждением.
 */
import { computed, onMounted, ref } from 'vue'
import AppIcon from '@/components/core/AppIcon.vue'
import { gameServerApi } from '@/services/core/gameServer.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const TEXT_EXT = ['properties', 'yml', 'yaml', 'json', 'txt', 'conf', 'cfg', 'log', 'toml', 'xml', 'sh', 'md']
const MAX_TEXT = 1048576

// path -> { entries, open }
const dirs = ref({})
const selectedDir = ref('')
const loading = ref(false)
const error = ref('')
const notice = ref('')

const openPath = ref('')
const text = ref('')
const dirty = ref(false)
const saving = ref(false)

const trash = ref([])
const showTrash = ref(false)
const uploadInput = ref(null)

const join = (dir, name) => (dir ? `${dir}/${name}` : name)
const ext = (name) => name.split('.').pop().toLowerCase()
const isText = (name) => TEXT_EXT.includes(ext(name))
const sizeLabel = (n) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} МБ` : `${Math.max(1, Math.round(n / 1024))} КБ`)

const iconFor = (entry) => (entry.type === 'dir' ? 'box' : isText(entry.name) ? 'doc' : 'frame')

/** Видимые строки дерева с отступом. */
const rows = computed(() => {
  const out = []

  const walk = (dir, depth) => {
    for (const entry of dirs.value[dir]?.entries ?? []) {
      const path = join(dir, entry.name)

      out.push({ ...entry, path, dir, depth })

      if (entry.type === 'dir' && dirs.value[path]?.open) walk(path, depth + 1)
    }
  }

  walk('', 0)

  return out
})

const crumbs = computed(() => {
  const parts = openPath.value ? openPath.value.split('/') : []

  return parts.map((name, i) => ({ name, path: parts.slice(0, i + 1).join('/') }))
})

function fail(e) {
  error.value = e?.message ?? 'Ошибка'
  notice.value = ''
}

function ok(message) {
  notice.value = message
  error.value = ''
}

async function loadDir(path, open = undefined) {
  loading.value = true

  try {
    const data = await gameServerApi.fmTree(path)

    dirs.value[path] = { entries: data.entries ?? [], open: open ?? dirs.value[path]?.open ?? path === '' }
    error.value = ''
  } catch (e) {
    fail(e)
  } finally {
    loading.value = false
  }
}

async function refreshAll() {
  const open = Object.keys(dirs.value).filter((p) => dirs.value[p].open || p === '')

  for (const path of open) await loadDir(path)
}

async function guardDirty() {
  return !dirty.value || (await confirmDialog('Несохранённые правки пропадут. Продолжить?', { danger: true }))
}

async function onRow(row) {
  if (row.type === 'dir') {
    selectedDir.value = row.path

    if (dirs.value[row.path]?.open) {
      dirs.value[row.path].open = false
    } else {
      await loadDir(row.path, true)
    }

    return
  }

  if (!isText(row.name) || row.size > MAX_TEXT) {
    error.value = 'Этот файл нельзя править: бинарный тип или больше 1 МБ. Его можно скачать.'
    return
  }

  if (!(await guardDirty())) return

  try {
    const data = await gameServerApi.fmRead(row.path)

    selectedDir.value = row.dir
    openPath.value = data.path
    text.value = data.content
    dirty.value = false
    error.value = ''
  } catch (e) {
    fail(e)
  }
}

async function save() {
  saving.value = true

  try {
    const res = await gameServerApi.fmWrite(openPath.value, text.value)

    dirty.value = false
    ok(res.backup ? `Сохранено. Прежняя версия: ${res.backup}` : 'Сохранено.')
    await refreshAll()
  } catch (e) {
    fail(e)
  } finally {
    saving.value = false
  }
}

async function create(kind) {
  const where = selectedDir.value ? `в ${selectedDir.value}/` : 'в корне'
  const name = window.prompt(kind === 'dir' ? `Имя новой папки (${where})` : `Имя нового файла, например notes.txt (${where})`)

  if (!name) return

  try {
    await gameServerApi.fmCreate(join(selectedDir.value, name.trim()), kind)
    ok('Создано.')
    await loadDir(selectedDir.value, true)
  } catch (e) {
    fail(e)
  }
}

async function rename(row) {
  const name = window.prompt('Новое имя', row.name)

  if (!name || name === row.name) return

  try {
    await gameServerApi.fmRename(row.path, join(row.dir, name.trim()))
    ok('Переименовано.')
    if (openPath.value === row.path) openPath.value = ''
    await refreshAll()
  } catch (e) {
    fail(e)
  }
}

async function remove(row) {
  const confirmed = await confirmDialog(
    `Убрать «${row.name}» в корзину на сервере? Навсегда файл не удаляется, его можно вернуть вручную.`,
    { danger: true },
  )

  if (!confirmed) return

  try {
    await gameServerApi.fmTrash(row.path)
    ok('Перенесено в корзину.')
    if (openPath.value === row.path) openPath.value = ''
    await refreshAll()
  } catch (e) {
    fail(e)
  }
}

async function upload(event) {
  const file = event.target.files?.[0]

  if (!file) return

  try {
    await gameServerApi.fmUpload(file, selectedDir.value)
    ok(`Загружено: ${file.name}`)
    await loadDir(selectedDir.value, true)
  } catch (e) {
    fail(e)
  } finally {
    event.target.value = ''
  }
}

async function toggleTrash() {
  showTrash.value = !showTrash.value

  if (!showTrash.value) return

  try {
    trash.value = (await gameServerApi.fmTrashList()).items ?? []
  } catch (e) {
    fail(e)
  }
}

onMounted(() => loadDir('', true))
</script>

<template>
  <div class="fm">
    <header class="fm-head">
      <div>
        <h2>Файлы сервера</h2>
        <p>/opt/minecraft/server. Перед правкой остаётся копия, удаление идёт через корзину.</p>
      </div>

      <div class="fm-tools">
        <button type="button" class="fm-btn" @click="create('file')">Новый файл</button>
        <button type="button" class="fm-btn" @click="create('dir')">Новая папка</button>
        <button type="button" class="fm-btn" @click="uploadInput.click()">Загрузить</button>
        <button type="button" class="fm-btn" :class="{ 'is-on': showTrash }" @click="toggleTrash">
          <AppIcon icon="trash" :size="14" /> Корзина
        </button>
        <button type="button" class="fm-btn" :disabled="loading" @click="refreshAll">Обновить</button>
        <input ref="uploadInput" type="file" hidden @change="upload" />
      </div>
    </header>

    <p v-if="error" class="fm-alert fm-alert--bad">{{ error }}</p>
    <p v-if="notice" class="fm-alert fm-alert--ok">{{ notice }}</p>

    <div class="fm-cols">
      <section class="fm-pane fm-tree">
        <div class="fm-pane__bar">
          <span>{{ showTrash ? 'Корзина' : 'Дерево' }}</span>
          <small v-if="!showTrash">выбрана папка: /{{ selectedDir }}</small>
        </div>

        <ul v-if="!showTrash" class="fm-rows">
          <li
              v-for="row in rows"
              :key="row.path"
              class="fm-row"
              :class="{ 'is-active': row.path === openPath, 'is-dir-selected': row.type === 'dir' && row.path === selectedDir }"
              :style="{ paddingLeft: 10 + row.depth * 16 + 'px' }"
          >
            <button type="button" class="fm-row__main" @click="onRow(row)">
              <AppIcon :icon="iconFor(row)" :size="15" :class="'fm-ico fm-ico--' + row.type" />
              <span class="fm-row__name">{{ row.name }}</span>
              <small v-if="row.type === 'dir'">{{ dirs[row.path]?.open ? 'открыта' : '' }}</small>
              <small v-else>{{ sizeLabel(row.size) }}</small>
            </button>

            <span class="fm-row__acts">
              <a v-if="row.type === 'file'" class="fm-act" :href="gameServerApi.fmDownloadUrl(row.path)" title="Скачать">Скачать</a>
              <button type="button" class="fm-act" title="Переименовать" @click="rename(row)">Имя</button>
              <button type="button" class="fm-act fm-act--bad" title="В корзину" @click="remove(row)">
                <AppIcon icon="trash" :size="12" />
              </button>
            </span>
          </li>

          <li v-if="!rows.length && !loading" class="fm-empty">Пусто</li>
        </ul>

        <ul v-else class="fm-rows">
          <li v-for="t in trash" :key="t.path + t.time" class="fm-row fm-row--static">
            <AppIcon icon="doc" :size="15" class="fm-ico" />
            <span class="fm-row__name">{{ t.path }}</span>
            <small>{{ t.size }} · {{ t.time }}</small>
          </li>
          <li v-if="!trash.length" class="fm-empty">Корзина пуста</li>
        </ul>
      </section>

      <section class="fm-pane fm-edit">
        <template v-if="openPath">
          <div class="fm-pane__bar">
            <nav class="fm-crumbs">
              <template v-for="(c, i) in crumbs" :key="c.path">
                <span v-if="i" class="fm-crumbs__sep">/</span>
                <span :class="{ 'is-last': i === crumbs.length - 1 }">{{ c.name }}</span>
              </template>
            </nav>

            <div class="fm-edit__tools">
              <span v-if="dirty" class="fm-dirty">есть правки</span>
              <button type="button" class="fm-btn fm-btn--main" :disabled="!dirty || saving" @click="save">
                {{ saving ? 'Сохраняем...' : 'Сохранить' }}
              </button>
            </div>
          </div>

          <textarea v-model="text" class="fm-editor" spellcheck="false" @input="dirty = true"></textarea>
        </template>

        <div v-else class="fm-placeholder">
          <AppIcon icon="doc" :size="28" />
          <p>Выберите текстовый файл слева: yml, properties, json, txt, log.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "./AdminServerFiles.css";
</style>
