<script setup>
import { computed } from 'vue'

const props = defineProps({
  socials: {
    type: Object,
    default: () => ({}),
  },
})

const socialItems = computed(() => {
  const socials = props.socials || {}

  const items = [
    {
      key: 'discord',
      label: 'Discord',
      value: socials.discord,
      icon: 'discord',
      accent: '#5865f2',
    },
    {
      key: 'telegram',
      label: 'Telegram',
      value: socials.telegram,
      icon: 'telegram',
      accent: '#229ed9',
    },
    {
      key: 'youtube',
      label: 'YouTube',
      value: socials.youtube,
      icon: 'youtube',
      accent: '#ef4444',
    },
    {
      key: 'twitch',
      label: 'Twitch',
      value: socials.twitch,
      icon: 'twitch',
      accent: '#9146ff',
    },
    {
      key: 'vk',
      label: 'VK',
      value: socials.vk,
      icon: 'vk',
      accent: '#0077ff',
    },
    {
      key: 'steam',
      label: 'Steam',
      value: socials.steam,
      icon: 'steam',
      accent: '#66c0f4',
    },
    {
      key: 'website',
      label: 'Website',
      value: socials.website,
      icon: 'website',
      accent: '#a78bfa',
    },
  ]

  return items.filter(item => item.value)
})

function normalizeUrl(value) {
  if (!value) return '#'

  if (
      value.startsWith('http://') ||
      value.startsWith('https://')
  ) {
    return value
  }

  return `https://${value}`
}

function displayValue(item) {
  if (!item.value) return ''

  if (
      item.value.startsWith('http://') ||
      item.value.startsWith('https://')
  ) {
    try {
      return new URL(item.value).hostname.replace(/^www\./, '')
    } catch {
      return item.value
    }
  }

  return item.value
}
</script>

<template>
  <section
      v-if="socialItems.length"
      class="socials"
  >
    <div class="socials__head">
      <div>
        <div class="socials__eyebrow">
          <span class="socials__eyebrow-line" />
          CONNECTIONS
        </div>

        <h3 class="socials__title">
          Социальные сети
        </h3>
      </div>

      <span class="socials__count">
        {{ socialItems.length }} LINK{{ socialItems.length === 1 ? '' : 'S' }}
      </span>
    </div>

    <div class="socials__grid">
      <a
          v-for="item in socialItems"
          :key="item.key"
          class="social-card"
          :href="normalizeUrl(item.value)"
          target="_blank"
          rel="noopener noreferrer"
          :style="{ '--social-accent': item.accent }"
      >
        <span class="social-card__glow" />

        <span class="social-card__icon">
          <!-- Discord -->
          <svg
              v-if="item.icon === 'discord'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M19.54 5.32A16.87 16.87 0 0 0 15.44 4l-.5 1.02a15.2 15.2 0 0 0-5.88 0L8.56 4a16.8 16.8 0 0 0-4.1 1.32C1.86 9.1 1.15 12.8 1.5 16.45a16.94 16.94 0 0 0 5.05 2.56l1.22-1.65c-.67-.25-1.3-.55-1.9-.9l.46-.35a12.1 12.1 0 0 0 10.34 0l.47.35c-.6.35-1.24.65-1.9.9l1.21 1.65a16.95 16.95 0 0 0 5.05-2.56c.41-4.23-.7-7.9-1.96-11.13ZM8.48 14.28c-1.01 0-1.84-.93-1.84-2.07s.81-2.08 1.84-2.08 1.85.93 1.84 2.08c0 1.14-.82 2.07-1.84 2.07Zm7.04 0c-1.01 0-1.84-.93-1.84-2.07s.81-2.08 1.84-2.08 1.85.93 1.84 2.08c0 1.14-.82 2.07-1.84 2.07Z" />
          </svg>

          <!-- Telegram -->
          <svg
              v-else-if="item.icon === 'telegram'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M21.7 3.4 18.4 20c-.25 1.17-.91 1.46-1.84.91l-5.07-3.74-2.45 2.36c-.27.27-.5.5-1.02.5l.36-5.17 9.42-8.51c.41-.36-.09-.56-.64-.2L5.52 13.4.55 11.84c-1.08-.34-1.1-1.08.23-1.6L20.2 2.7c.91-.34 1.7.2 1.5.7Z" />
          </svg>

          <!-- YouTube -->
          <svg
              v-else-if="item.icon === 'youtube'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M23.5 6.2a3 3 0 0 0-2.1-2.12C19.55 3.5 12 3.5 12 3.5s-7.55 0-9.4.58A3 3 0 0 0 .5 6.2 31.2 31.2 0 0 0 0 12a31.2 31.2 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.12c1.85.58 9.4.58 9.4.58s7.55 0 9.4-.58a3 3 0 0 0 2.1-2.12A31.2 31.2 0 0 0 24 12a31.2 31.2 0 0 0-.5-5.8ZM9.6 15.9V8.1l6.4 3.9-6.4 3.9Z" />
          </svg>

          <!-- Twitch -->
          <svg
              v-else-if="item.icon === 'twitch'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M4 2 2 5v15h5v3l3-3h4l6-6V2H4Zm14 11-3 3h-4l-2 2v-2H5V4h13v9ZM15 7h2v5h-2V7Zm-5 0h2v5h-2V7Z" />
          </svg>

          <!-- VK -->
          <svg
              v-else-if="item.icon === 'vk'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M13.07 18.5c-6.8 0-10.68-4.67-10.84-12.44h3.4c.11 5.7 2.63 8.11 4.63 8.61V6.06h3.2v4.92c1.97-.21 4.04-2.45 4.74-4.92h3.2c-.53 3-2.78 5.24-4.38 6.16 1.6.74 4.16 2.69 5.13 6.28h-3.52c-.63-2.25-2.55-3.99-5.17-4.24v4.24h-.39Z" />
          </svg>

          <!-- Steam -->
          <svg
              v-else-if="item.icon === 'steam'"
              viewBox="0 0 24 24"
              fill="currentColor"
          >
            <path d="M12 2a10 10 0 0 0-9.95 9.05l5.3 2.19a2.75 2.75 0 1 1-.8 2.02L2.3 13.17A10 10 0 1 0 12 2Zm5.03 5.43a3.15 3.15 0 1 1-3.15 3.15 3.15 3.15 0 0 1 3.15-3.15Zm0 1.27a1.88 1.88 0 1 0 0 3.76 1.88 1.88 0 0 0 0-3.76Zm-7.83 5.22a1.48 1.48 0 1 0 0 2.96 1.48 1.48 0 0 0 0-2.96Z" />
          </svg>

          <!-- Website -->
          <svg
              v-else
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18" />
            <path d="M12 3c2.2 2.45 3.3 5.45 3.3 9S14.2 18.55 12 21" />
            <path d="M12 3C9.8 5.45 8.7 8.45 8.7 12S9.8 18.55 12 21" />
          </svg>
        </span>

        <span class="social-card__body">
          <span class="social-card__label">
            {{ item.label }}
          </span>

          <span class="social-card__value">
            {{ displayValue(item) }}
          </span>
        </span>

        <span class="social-card__arrow">
          <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
          >
            <path d="M7 17 17 7" />
            <path d="M7 7h10v10" />
          </svg>
        </span>
      </a>
    </div>
  </section>
</template>

<style scoped>
@import "@/components/players/profile/PlayerProfileSocials.css";
</style>
