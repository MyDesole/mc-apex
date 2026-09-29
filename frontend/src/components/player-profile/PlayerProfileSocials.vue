<script setup>
import { computed } from 'vue'

const props = defineProps({
  socials: { type: Object, default: () => ({}) },
})

const labels = {
  discord: 'Discord', telegram: 'Telegram',
  youtube: 'YouTube', vk: 'VK', website: 'Сайт',
}

const hasSocials = computed(() =>
    props.socials && Object.values(props.socials).some(v => v)
)
</script>

<template>
  <div v-if="hasSocials" class="pp-socials">
    <a
        v-for="(url, key) in socials"
        :key="key"
        v-show="url"
        :href="url"
        target="_blank"
        rel="noopener"
        class="social-link"
        :class="key"
    >
      {{ labels[key] }}
    </a>
  </div>
</template>

<style scoped>
.pp-socials {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.social-link {
  display: inline-flex;
  align-items: center;
  padding: 7px 12px;
  color: var(--text-dim);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  transform: translateY(-1px);
  color: #fff;
}

.social-link.discord:hover {
  background: #5865f2;
  border-color: #5865f2;
}

.social-link.telegram:hover {
  background: #229ed9;
  border-color: #229ed9;
}

.social-link.youtube:hover {
  background: #ff0000;
  border-color: #ff0000;
}

.social-link.vk:hover {
  background: #0077ff;
  border-color: #0077ff;
}

.social-link.website:hover {
  background: var(--accent, #7c3aed);
  border-color: var(--accent, #7c3aed);
}
</style>