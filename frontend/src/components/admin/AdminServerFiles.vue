<script setup>
import { computed, onMounted, ref } from 'vue'
import { gameServerApi } from '@/services/core/gameServer.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const TEXT_EXT = ['properties', 'yml', 'yaml', 'json', 'txt', 'conf', 'cfg', 'log', 'toml', 'xml', 'sh', 'md']

const path = ref('')
const entries = ref([])
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

const crumbs = computed(() => {
  const parts = path.value ? path.value.split('/') : []

  return parts.map((name, i) => ({ name, path: parts.slice(0, i + 1).join('/') }))
})

const join = (name) => (path.value ? `${path.value}/${name}` : name)
const isText = (name) => TEXT_EXT.includes(name.split('.').pop().toLowerCase())
const size = (n) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} МБ` : `${Math.max(1, Math.round(n / 1024))} КБ`)

function fail(e) {
  error.value = e?.message ?? 'Ошибка'
  notice.value = ''
}

function ok(message) {
  notice.value = message
  error.value = ''
}

async function load(to = path.value) {
  loading.value = true

  try {
    const data = await gameServerApi.fmTree(to)

    path.value = to
    entries.value = data.entries ?? []
    error.value = ''
  } catch (e) {
    fail(e)
  } finally {
    loading.value = false
  }
}

async function guardDirty() {
  return !dirty.value || (await confirmDialog('Несохранённые правки пропадут. Продолжить?', { danger: true }))
}

async function openEntry(entry) {
  if (entry.type === 'dir') {
    if (await guardDirty()) await load(join(entry.name))
    return
  }

  if (!isText(entry.name) || entry.size > 1048576) {
    error.value = 'Этот файл нельзя править: бинарный тип или больше 1 МБ. Его можно скачать.'
    return
  }

  if (!(await guardDirty())) return

  try {
    const data = await gameServerApi.fmRead(join(entry.name))

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
    ok(res.backup ? `Сохранено. Прежняя версия: ${res.backup}` : 'Файл создан.')
    await load()
  } catch (e) {
    fail(e)
  } finally {
    saving.value = false
  }
}

async function create(kind) {
  const name = window.prompt(kind === 'dir' ? 'Имя новой папки' : 'Имя нового файла (например, notes.txt)')

  if (!name) return

  try {
    await gameServerApi.fmCreate(join(name.trim()), kind)
    ok('Создано.')
    await load()
  } catch (e) {
    fail(e)
  }
}

async function rename(entry) {
  const name = window.prompt('Новое имя', entry.name)

  if (!name || name === entry.name) return

  try {
    await gameServerApi.fmRename(join(entry.name), join(name.trim()))
    ok('Переименовано.')
    await load()
  } catch (e) {
    fail(e)
  }
}

async function remove(entry) {
  const confirmed = await confirmDialog(
    `Убрать «${entry.name}» в корзину на сервере? Файл не удаляется навсегда, его можно вернуть вручную.`,
    { danger: true },
  )

  if (!confirmed) return

  try {
    await gameServerApi.fmTrash(join(entry.name))
    ok('Перенесено в корзину.')
    if (openPath.value === join(entry.name)) openPath.value = ''
    await load()
  } catch (e) {
    fail(e)
  }
}

async function upload(event) {
  const file = event.target.files?.[0]

  if (!file) return

  try {
    await gameServerApi.fmUpload(file, path.value)
    ok(`Загружено: ${file.name}`)
    await load()
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

onMounted(() => load(''))
</script>

<template>
  <div class="fm">
    <header class="fm-head">
      <h2>Файлы сервера</h2>
      <p>/opt/minecraft/server. Удаление идёт через корзину, перед правкой делается копия.</p>
    </header>

    <p v-if="error" class="fm-alert fm-alert--bad">{{ error }}</p>
    <p v-if="notice" class="fm-alert fm-alert--ok">{{ notice }}</p>

    <div class="fm-cols">
      <section class="fm-pane">
        <nav class="fm-crumbs">
          <button type="button" @click="load('')">server</button>
          <template v-for="c in crumbs" :key="c.path">
            <span>/</span>
            <button type="button" @click="load(c.path)">{{ c.name }}</button>
          </template>
        </nav>

        <div class="fm-tools">
          <button type="button" class="fm-btn" @click="create('file')">Новый файл</button>
          <button type="button" class="fm-btn" @click="create('dir')">Новая папка</button>
          <button type="button" class="fm-btn" @click="uploadInput.click()">Загрузить</button>
          <button type="button" class="fm-btn" @click="toggleTrash">Корзина</button>
          <input ref="uploadInput" type="file" hidden @change="upload" />
        </div>

        <p v-if="loading" class="fm-hint">Загрузка...</p>

        <table v-if="!showTrash" class="fm-table">
          <tbody>
            <tr v-if="path">
              <td colspan="4"><button type="button" class="fm-link" @click="load(path.split('/').slice(0, -1).join('/'))">.. наверх</button></td>
            </tr>
            <tr v-for="e in entries" :key="e.name">
              <td>
                <button type="button" class="fm-link" :class="{ 'is-dir': e.type === 'dir' }" @click="openEntry(e)">
                  {{ e.type === 'dir' ? e.name + '/' : e.name }}
                </button>
              </td>
              <td class="fm-dim">{{ e.type === 'dir' ? '' : size(e.size) }}</td>
              <td class="fm-dim">{{ e.mtime }}</td>
              <td class="fm-acts">
                <a v-if="e.type === 'file'" class="fm-act" :href="gameServerApi.fmDownloadUrl(join(e.name))">Скачать</a>
                <button type="button" class="fm-act" @click="rename(e)">Имя</button>
                <button type="button" class="fm-act fm-act--bad" @click="remove(e)">В корзину</button>
              </td>
            </tr>
            <tr v-if="!entries.length && !loading"><td colspan="4" class="fm-dim">Пусто</td></tr>
          </tbody>
        </table>

        <table v-else class="fm-table">
          <tbody>
            <tr v-for="t in trash" :key="t.path">
              <td>{{ t.path }}</td>
              <td class="fm-dim">{{ t.size }}</td>
              <td class="fm-dim">{{ t.time }}</td>
            </tr>
            <tr v-if="!trash.length"><td class="fm-dim">Корзина пуста</td></tr>
          </tbody>
        </table>
      </section>

      <section class="fm-pane">
        <template v-if="openPath">
          <div class="fm-edit-head">
            <b>{{ openPath }}</b>
            <button type="button" class="fm-btn fm-btn--main" :disabled="!dirty || saving" @click="save">
              {{ saving ? 'Сохраняем...' : 'Сохранить' }}
            </button>
          </div>
          <textarea v-model="text" class="fm-editor" spellcheck="false" @input="dirty = true"></textarea>
        </template>
        <p v-else class="fm-hint">Выберите текстовый файл слева (yml, properties, json, txt, log).</p>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import "./AdminServerFiles.css";
</style>
