<script setup>
/**
 * Статус Minecraft-сервера: онлайн, игроки, версия, MOTD и адрес.
 * Обновляется сам раз в 30 секунд — столько же сервер держит кэш.
 */
import { onMounted, onUnmounted, ref } from 'vue'
import ServerAddress from '@/components/core/ServerAddress.vue'
import { siteApi } from '@/services/site/site.js'

const status = ref(null)
const failed = ref(false)

let timer = null

async function load() {
  try {
    status.value = await siteApi.serverStatus()
    failed.value = false
  } catch {
    failed.value = true
  }
}

onMounted(() => {
  load()
  timer = setInterval(load, 30000)
})

onUnmounted(() => clearInterval(timer))
</script>

<template>
  <section class="server-card content-card">
    <div v-if="!status && !failed" class="server-card__row">Проверяем сервер…</div>

    <div v-else-if="failed || !status.configured" class="server-card__row">
      <span class="server-card__dot" />
      <div>
        <strong>Статус недоступен</strong>
        <span>{{ failed ? 'Не удалось получить статус — попробуйте позже.' : 'Адрес сервера ещё не настроен.' }}</span>
      </div>
    </div>

    <template v-else>
      <div class="server-card__row">
        <span class="server-card__dot" :class="{ 'server-card__dot--on': status.online }" />
        <div>
          <strong>{{ status.online ? 'Сервер работает' : 'Сервер не отвечает' }}</strong>
          <span v-if="status.online">{{ status.players }} из {{ status.max }} игроков онлайн</span>
          <span v-else>Возможно, идёт перезапуск — загляните через пару минут.</span>
        </div>
      </div>

      <p v-if="status.motd" class="server-card__motd">{{ status.motd }}</p>

      <div class="server-card__meta">
        <ServerAddress v-if="status.address" :address="status.address" />
        <span v-if="status.version" class="server-card__version">Версия: {{ status.version }}</span>
      </div>
    </template>
  </section>
</template>

<style scoped>
.server-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.server-card__row {
  display: flex;
  align-items: center;
  gap: 14px;

  color: var(--text-dim);
}

.server-card__row div {
  display: flex;
  flex-direction: column;
}

.server-card__row strong {
  color: #fff;
  font-size: 17px;
}

.server-card__dot {
  width: 14px;
  height: 14px;
  flex-shrink: 0;

  background: var(--text-muted);
  border-radius: 3px;
}

.server-card__dot--on {
  background: #4ade80;
  box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.18);
}

.server-card__motd {
  margin: 0;
  padding: 10px 12px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);

  font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace;
  font-size: 13px;
  white-space: pre-line;
}

.server-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 18px;
}

.server-card__version {
  color: var(--text-muted);
  font-size: 14px;
}
</style>
