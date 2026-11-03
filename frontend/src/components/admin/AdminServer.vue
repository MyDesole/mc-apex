<script setup>
/**
 * Панель игрового сервера apevpvp.pro.
 *
 * Состояние, консоль, питание, загрузка обновлений и команда запуска.
 * Все действия идут через backend — он ходит на сервер по ключу.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import AppIcon from '@/components/core/AppIcon.vue'
import { gameServerApi } from '@/services/core/gameServer.js'
import { confirm as confirmDialog } from '@/utils/dialog.js'

const status = ref(null)
const log = ref('')
const loading = ref(true)
const busy = ref('')
const error = ref('')
const notice = ref('')

const exactPlayers = ref(null)
const plugins = ref([])

/* ---------- Конфиги ---------- */
const configFiles = ref([])
const configPath = ref('')
const configText = ref('')
const configDirty = ref(false)

/* ---------- Архивы ---------- */
const archives = ref([])
const backups = ref([])
const archiveFile = ref(null)
const live = ref(true)
const lines = ref(150)
const consoleEl = ref(null)
const consoleAt = ref('')

const commandText = ref('')
const startCommand = ref('')
const startDirty = ref(false)
const uploadTarget = ref('')
const uploadFile = ref(null)
const uploading = ref(false)

let timer = null

const memPercent = computed(() => {
  const total = status.value?.mem_total ?? 0
  const used = status.value?.mem_used ?? 0

  return total > 0 ? Math.round((used / total) * 100) : 0
})

const canStart = computed(() => status.value && !status.value.running)
const canStop = computed(() => status.value && status.value.running)

async function loadStatus() {
  try {
    status.value = await gameServerApi.status()
    error.value = ''
  } catch (e) {
    status.value = null
    error.value = e.message ?? 'Сервер не отвечает'
  } finally {
    loading.value = false
  }
}

const metrics = ref(null)
const pluginsLoaded = ref(false)
let metricsTimer = null

const tpsClass = computed(() => {
  const tps = metrics.value?.tps

  if (tps == null) return ''

  return tps >= 19 ? 'is-ok' : tps >= 15 ? 'is-warn' : 'is-bad'
})

async function loadMetrics() {
  if (!status.value?.running) return

  try {
    metrics.value = await gameServerApi.metrics()
  } catch {
    metrics.value = null
  }
}

async function loadPluginsState() {
  try {
    const data = await gameServerApi.pluginsState()

    plugins.value = data.plugins ?? []
    pluginsLoaded.value = true
  } catch (e) {
    error.value = e.message ?? 'Не удалось получить статус плагинов'
  }
}

async function loadPlugins() {
  try {
    plugins.value = (await gameServerApi.plugins()).plugins ?? []
  } catch {
    plugins.value = []
  }
}

/* ---------- Конфиги ---------- */

async function loadConfigs() {
  try {
    configFiles.value = (await gameServerApi.files()).files ?? []
  } catch (e) {
    configFiles.value = []
    error.value = e.message ?? 'Не удалось получить список конфигов'
  }
}

async function openConfig(path) {
  if (configDirty.value) {
    const ok = await confirmDialog('Несохранённые правки пропадут. Открыть другой файл?', {
      title: 'Конфиг',
      confirmText: 'Открыть',
    })

    if (!ok) return
  }

  busy.value = 'cfg-read'

  try {
    configPath.value = path
    configText.value = (await gameServerApi.readFile(path)).content ?? ''
    configDirty.value = false
  } catch (e) {
    error.value = e.message ?? 'Не удалось прочитать файл'
  } finally {
    busy.value = ''
  }
}

const saveConfig = () => act('cfg-save', async () => {
  await gameServerApi.saveFile(configPath.value, configText.value)
  configDirty.value = false
}, 'Конфиг сохранён')

/* ---------- Архивы ---------- */

async function loadArchives() {
  try {
    const data = await gameServerApi.archives()
    archives.value = data.archives ?? []
    backups.value = data.backups ?? []
  } catch (e) {
    archives.value = []
    backups.value = []
  }
}

function pickArchive(event) {
  archiveFile.value = event.target.files?.[0] ?? null
}

const uploadArchive = () => act('arch-upload', async () => {
  if (!archiveFile.value) return

  const result = await gameServerApi.uploadArchive(archiveFile.value)
  archiveFile.value = null
  notice.value = `Архив загружен: ${result.archive.name}`
  await loadArchives()
})

const applyArchive = (name) => act('arch-apply', async () => {
  const ok = await confirmDialog(
      `Применить архив ${name}? Сервер остановится, сделает бэкап, распакует архив поверх и запустится снова. Игроков выкинет.`,
      { title: 'Применить архив', confirmText: 'Применить', danger: true },
  )

  if (!ok) return

  const result = await gameServerApi.applyArchive(name)
  notice.value = 'Архив применён' + (result.result?.backup ? `, бэкап: ${result.result.backup}` : '')
  await Promise.all([loadStatus(), loadArchives()])
})

const deleteArchive = (name) => act('arch-del', async () => {
  const ok = await confirmDialog(`Удалить архив ${name}?`, {
    title: 'Удалить архив',
    confirmText: 'Удалить',
    danger: true,
  })

  if (!ok) return

  await gameServerApi.deleteArchive(name)
  await loadArchives()
})

const restoreBackup = (name) => act('arch-restore', async () => {
  const ok = await confirmDialog(
      `Откатить сервер к бэкапу ${name}? Текущие файлы будут перезаписаны, сервер перезапустится.`,
      { title: 'Восстановление', confirmText: 'Откатить', danger: true },
  )

  if (!ok) return

  await gameServerApi.restoreBackup(name)
  notice.value = 'Бэкап восстановлен'
  await Promise.all([loadStatus(), loadArchives()])
})

async function loadPlayers() {
  busy.value = 'players'

  try {
    exactPlayers.value = await gameServerApi.players()
  } catch (e) {
    error.value = e.message ?? 'Не удалось узнать игроков'
  } finally {
    busy.value = ''
  }
}

function scrollConsole() {
  const el = consoleEl.value

  if (el) el.scrollTop = el.scrollHeight
}

async function loadConsole() {
  try {
    log.value = (await gameServerApi.console(lines.value)).log ?? ''
    consoleAt.value = new Date().toLocaleTimeString('ru-RU')
    await nextTick()
    scrollConsole()
  } catch (e) {
    error.value = e.message ?? 'Не удалось получить консоль'
  }
}

async function refresh() {
  busy.value = 'refresh'
  await Promise.all([loadStatus(), loadConsole()])
  busy.value = ''
}

async function act(action, call, message) {
  busy.value = action
  error.value = ''
  notice.value = ''

  try {
    await call()
    if (message) notice.value = message
    await loadStatus()
  } catch (e) {
    error.value = e.message ?? 'Не получилось'
  } finally {
    busy.value = ''
  }
}

const restart = () => act('restart', async () => {
  const ok = await confirmDialog(
      'Перезапустить игровой сервер? Игроков выкинет, мир сохранится.',
      { title: 'Перезапуск', confirmText: 'Перезапустить', danger: true },
  )

  if (!ok) return

  await gameServerApi.power('restart')
}, 'Сервер перезапускается')

const stop = () => act('stop', async () => {
  const ok = await confirmDialog('Остановить сервер? Игроков выкинет.', {
    title: 'Остановка',
    confirmText: 'Остановить',
    danger: true,
  })

  if (!ok) return

  await gameServerApi.power('stop')
}, 'Сервер остановлен')

const start = () => act('start', async () => {
  await gameServerApi.power('start')
}, 'Сервер запущен')

const sendCommand = () => act('cmd', async () => {
  const text = commandText.value.trim()

  if (!text) return

  // Свой ввод показываем сразу — не ждём журнала сервера
  log.value = (log.value.trimEnd() + '\n> ' + text + '\n')
  commandText.value = ''
  await nextTick()
  scrollConsole()

  await gameServerApi.command(text)

  // Журнал дописывается с задержкой: пробуем несколько раз
  for (let i = 0; i < 4; i++) {
    await new Promise((resolve) => setTimeout(resolve, 500))
    await loadConsole()
  }
}, 'Команда отправлена')

async function loadStartCommand() {
  try {
    startCommand.value = (await gameServerApi.startCommand()).content ?? ''
    startDirty.value = false
  } catch (e) {
    error.value = e.message ?? 'Не удалось прочитать команду запуска'
  }
}

const saveStart = () => act('start-save', async () => {
  await gameServerApi.saveStartCommand(startCommand.value)
  startDirty.value = false
}, 'Команда запуска сохранена')

function pickFile(event) {
  uploadFile.value = event.target.files?.[0] ?? null
}

const doUpload = () => act('upload', async () => {
  if (!uploadFile.value) return

  uploading.value = true

  try {
    const result = await gameServerApi.upload(uploadFile.value, uploadTarget.value)
    notice.value = `Загружено: ${result.uploaded.name} (${result.uploaded.dir})`
    uploadFile.value = null
  } finally {
    uploading.value = false
  }
})

function toggleLive() {
  live.value = !live.value

  if (live.value) {
    timer = setInterval(loadConsole, 6000)
  } else if (timer) {
    clearInterval(timer)
    timer = null
  }
}

onMounted(async () => {
  await refresh()
  if (live.value) timer = setInterval(loadConsole, 5000)
  loadMetrics()
  metricsTimer = setInterval(loadMetrics, 20000)
  await loadPlugins()
  await loadConfigs()
  await loadArchives()
  await loadStartCommand()
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (metricsTimer) clearInterval(metricsTimer)
})
</script>

<template>
  <div class="srv">
    <header class="srv-head">
      <div>
        <h2>Игровой сервер</h2>
        <p v-if="status">{{ status.host }} · {{ status.jar ?? '—' }}</p>
        <p v-else>Нет связи с сервером</p>
      </div>

      <div class="srv-head__actions">
        <button type="button" class="srv-btn" :disabled="busy === 'refresh'" @click="refresh">
          <AppIcon icon="sparkles" :size="14" /> Обновить
        </button>
        <button type="button" class="srv-btn srv-btn--main" :disabled="!!busy || !canStart" @click="start">
          Запустить
        </button>
        <button type="button" class="srv-btn srv-btn--warn" :disabled="!!busy || !canStop" @click="restart">
          <AppIcon icon="bolt" :size="14" /> Перезапустить
        </button>
        <button type="button" class="srv-btn srv-btn--bad" :disabled="!!busy || !canStop" @click="stop">
          Остановить
        </button>
      </div>
    </header>

    <p v-if="error" class="srv-alert srv-alert--bad">{{ error }}</p>
    <p v-if="notice" class="srv-alert srv-alert--ok">{{ notice }}</p>

    <p v-if="loading" class="srv-hint">Проверяем сервер…</p>

    <template v-if="status">
      <div class="srv-cards">
        <div class="srv-card" :class="status.running ? 'is-ok' : 'is-bad'">
          <span>Состояние</span>
          <b>{{ status.running ? 'работает' : 'остановлен' }}</b>
          <small v-if="status.running">pid {{ status.pid }} · аптайм {{ status.uptime }}</small>
          <small v-else>нажмите «Запустить»</small>
        </div>

        <div class="srv-card">
          <span>Игроков онлайн</span>
          <b>{{ exactPlayers && exactPlayers.online >= 0 ? exactPlayers.online : status.players }}</b>
          <small v-if="exactPlayers && exactPlayers.max">из {{ exactPlayers.max }} · точно</small>
          <small v-else>по журналу входа</small>
          <button
              type="button"
              class="srv-btn srv-btn--sm"
              :disabled="busy === 'players' || !status.running"
              @click="loadPlayers"
          >Точное число</button>
        </div>

        <div class="srv-card" :class="tpsClass">
          <span>TPS</span>
          <b>{{ metrics?.tps ?? '—' }}</b>
          <small v-if="metrics">CPU {{ metrics.cpu }}% · java {{ metrics.java_rss }} МБ</small>
          <small v-else>ждём метрики</small>
        </div>

        <div v-if="metrics?.names?.length" class="srv-card">
          <span>Кто в игре</span>
          <small>{{ metrics.names.join(', ') }}</small>
        </div>

        <div class="srv-card">
          <span>Память</span>
          <b>{{ status.mem_used }} / {{ status.mem_total }} МБ</b>
          <span class="srv-bar"><i :style="{ width: memPercent + '%' }" :class="{ 'is-full': memPercent > 85 }"></i></span>
        </div>

        <div class="srv-card">
          <span>Диск</span>
          <b>{{ status.disk_used }} / {{ status.disk_total }}</b>
          <small>нагрузка {{ status.load }}</small>
        </div>

        <div class="srv-card">
          <span>Журнал</span>
          <b>{{ status.log_size }}</b>
          <small>{{ status.log_updated }}</small>
        </div>
      </div>

      <!-- Консоль -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Консоль</h3>

          <div class="srv-block__tools">
            <select v-model.number="lines" class="srv-input srv-input--sm" @change="loadConsole">
              <option :value="50">50 строк</option>
              <option :value="150">150 строк</option>
              <option :value="300">300 строк</option>
              <option :value="500">500 строк</option>
            </select>

            <button type="button" class="srv-btn srv-btn--sm" :class="{ 'is-on': live }" @click="toggleLive">
              {{ live ? 'Обновляется само' : 'Автообновление: выкл' }}
            </button>

            <button type="button" class="srv-btn srv-btn--sm" @click="loadConsole">Обновить</button>
          </div>
        </header>

        <pre ref="consoleEl" class="srv-console">{{ log || 'Журнал пуст' }}</pre>

        <small v-if="consoleAt" class="srv-hint">Обновлено в {{ consoleAt }}</small>

        <form class="srv-cmd" @submit.prevent="sendCommand">
          <input
              v-model="commandText"
              class="srv-input"
              type="text"
              maxlength="300"
              placeholder="Команда в консоль: say Привет, list, whitelist add ник"
          />
          <button type="submit" class="srv-btn srv-btn--main" :disabled="busy === 'cmd' || !commandText.trim()">
            Отправить
          </button>
        </form>
      </section>

      <!-- Обновления -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Загрузка обновлений</h3>
        </header>

        <div class="srv-upload">
          <input type="file" class="srv-input" @change="pickFile" />
          <select v-model="uploadTarget" class="srv-input srv-input--sm">
            <option value="">в корень сервера</option>
            <option value="plugins">в plugins</option>
          </select>
          <button
              type="button"
              class="srv-btn srv-btn--main"
              :disabled="!uploadFile || uploading"
              @click="doUpload"
          >Загрузить</button>
        </div>

        <p class="srv-hint">
          Можно .jar, .zip, .yml, .json, .txt, .properties. После замены jar перезапустите сервер.
        </p>
      </section>

      <!-- Плагины -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Плагины <small class="srv-count">{{ plugins.length }}</small></h3>
          <div class="srv-block__tools">
            <button type="button" class="srv-btn srv-btn--sm" :disabled="!status.running" @click="loadPluginsState">Проверить статус</button>
            <button type="button" class="srv-btn srv-btn--sm" @click="loadPlugins">Обновить</button>
          </div>
        </header>

        <p v-if="!plugins.length" class="srv-hint">Плагинов нет или каталог недоступен.</p>

        <ul v-else class="srv-plugins">
          <li v-for="plugin in plugins" :key="plugin.name">
            <b>{{ plugin.name }}</b>
            <small>{{ plugin.size }}<template v-if="pluginsLoaded"> · {{ plugin.enabled ? 'включён' : 'не загружен' }}</template></small>
          </li>
        </ul>
      </section>

      <!-- Конфиги -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Конфиги</h3>
          <div class="srv-block__tools">
            <button type="button" class="srv-btn srv-btn--sm" @click="loadConfigs">Обновить список</button>
            <button
                type="button"
                class="srv-btn srv-btn--main srv-btn--sm"
                :disabled="busy === 'cfg-save' || !configPath || !configDirty"
                @click="saveConfig"
            >Сохранить</button>
          </div>
        </header>

        <div class="srv-cfg">
          <div class="srv-cfg__list">
            <template v-for="group in ['root', 'plugins']" :key="group">
              <span class="srv-cfg__group">{{ group === 'root' ? 'Сервер' : 'Плагины' }}</span>
              <button
                  v-for="file in configFiles.filter((f) => f.group === group)"
                  :key="file.path"
                  type="button"
                  class="srv-cfg__file"
                  :class="{ 'is-active': file.path === configPath }"
                  :disabled="busy === 'cfg-read'"
                  @click="openConfig(file.path)"
              >
                <b>{{ file.path }}</b>
                <small>{{ file.size }}</small>
              </button>
            </template>

            <p v-if="!configFiles.length" class="srv-hint">Список пуст.</p>
          </div>

          <div class="srv-cfg__edit">
            <p v-if="!configPath" class="srv-hint">Выбери файл слева.</p>

            <template v-else>
              <div class="srv-cfg__head">
                <b>{{ configPath }}</b>
                <span v-if="configDirty" class="srv-cfg__dirty">есть правки</span>
              </div>
              <textarea
                  v-model="configText"
                  class="srv-code srv-code--cfg"
                  rows="14"
                  spellcheck="false"
                  @input="configDirty = true"
              ></textarea>
            </template>
          </div>
        </div>
      </section>

      <!-- Архивы сервера -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Архивы сервера</h3>
          <button type="button" class="srv-btn srv-btn--sm" @click="loadArchives">Обновить</button>
        </header>

        <div class="srv-upload">
          <input type="file" class="srv-input" accept=".zip" @change="pickArchive" />
          <button
              type="button"
              class="srv-btn srv-btn--main"
              :disabled="!archiveFile || busy === 'arch-upload'"
              @click="uploadArchive"
          >Загрузить zip</button>
        </div>

        <p class="srv-hint">
          Полный архив сервера. Загрузка только складывает файл в очередь; чтобы он применился —
          нажми «Применить»: сервер остановится, сделает бэкап, распакует архив и запустится.
        </p>

        <span class="srv-cfg__group">В очереди</span>
        <ul v-if="archives.length" class="srv-list">
          <li v-for="a in archives" :key="a.name">
            <b>{{ a.name }}</b>
            <small>{{ a.size }} · {{ a.created }}</small>
            <button type="button" class="srv-btn srv-btn--sm srv-btn--warn" :disabled="!!busy" @click="applyArchive(a.name)">
              Применить
            </button>
            <button type="button" class="srv-btn srv-btn--sm" :disabled="!!busy" @click="deleteArchive(a.name)">×</button>
          </li>
        </ul>
        <p v-else class="srv-hint">Архивов нет.</p>

        <span class="srv-cfg__group">Бэкапы</span>
        <ul v-if="backups.length" class="srv-list">
          <li v-for="b in backups" :key="b.name">
            <b>{{ b.name }}</b>
            <small>{{ b.size }} · {{ b.created }}</small>
            <button type="button" class="srv-btn srv-btn--sm" :disabled="!!busy" @click="restoreBackup(b.name)">
              Откатить
            </button>
          </li>
        </ul>
        <p v-else class="srv-hint">Бэкапов нет.</p>
      </section>

      <!-- Команда запуска -->
      <section class="srv-block">
        <header class="srv-block__head">
          <h3>Команда запуска</h3>

          <button
              type="button"
              class="srv-btn srv-btn--main"
              :disabled="busy === 'start-save' || !startDirty"
              @click="saveStart"
          >Сохранить</button>
        </header>

        <textarea
            v-model="startCommand"
            class="srv-code"
            rows="6"
            spellcheck="false"
            @input="startDirty = true"
        ></textarea>

        <p class="srv-hint">
          Это содержимое start.sh. Изменения применятся при следующем запуске или перезапуске.
        </p>
      </section>
    </template>
  </div>
</template>

<style scoped>
@import "./AdminServer.css";
</style>
