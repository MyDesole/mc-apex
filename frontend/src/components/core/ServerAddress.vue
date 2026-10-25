<script setup>
/**
 * Адрес Minecraft-сервера с копированием в один клик.
 */
import { ref } from 'vue'

defineProps({
  address: { type: String, required: true },
})

const copied = ref(false)

async function copy(address) {
  try {
    await navigator.clipboard.writeText(address)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1800)
  } catch {
    /* буфер недоступен — адрес и так виден */
  }
}
</script>

<template>
  <button
      type="button"
      class="server-address"
      :title="copied ? 'Скопировано' : 'Скопировать адрес'"
      @click="copy(address)"
  >
    <code>{{ address }}</code>
    <span>{{ copied ? 'Скопировано' : 'Копировать' }}</span>
  </button>
</template>

<style scoped>
.server-address {
  display: inline-flex;
  align-items: center;
  gap: 10px;

  padding: 6px 6px 6px 12px;

  color: var(--text);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);

  cursor: pointer;
}

.server-address:hover {
  border-color: var(--accent);
}

.server-address code {
  font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace;
  font-size: 14px;
}

.server-address span {
  padding: 3px 8px;

  color: var(--accent-light);
  background: var(--accent-soft);
  border-radius: 4px;

  font-size: 12px;
  font-weight: 600;
}
</style>
