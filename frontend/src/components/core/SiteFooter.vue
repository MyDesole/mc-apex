<script setup>
/**
 * Подвал на всех страницах: разделы, правила, соцсети и адрес сервера.
 * Раньше подвал был только на главной.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import BrandCube from '@/components/core/BrandCube.vue'
import ServerAddress from '@/components/core/ServerAddress.vue'
import { siteApi } from '@/services/site/site.js'

const SOCIAL_LABELS = {
  social_discord: 'Discord',
  social_telegram: 'Telegram',
  social_youtube: 'YouTube',
  social_vk: 'VK',
  social_twitch: 'Twitch',
  social_twitter: 'Twitter',
}

const info = ref({ socials: {}, footer_text: null, server_address: null })

const socials = computed(() =>
    Object.entries(info.value.socials ?? {})
        .filter(([, url]) => url)
        .map(([key, url]) => ({ key, url, label: SOCIAL_LABELS[key] ?? key }))
)

const year = new Date().getFullYear()

onMounted(async () => {
  try {
    info.value = { ...info.value, ...(await siteApi.info()) }
  } catch {
    /* подвал без соцсетей — не повод ломать страницу */
  }
})
</script>

<template>
  <footer class="site-footer">
    <div class="site-footer__inner">
      <div class="site-footer__brand">
        <RouterLink to="/" class="site-footer__logo">
          <BrandCube />
          <strong>APEX TIERS</strong>
        </RouterLink>

        <p>{{ info.footer_text || 'Рейтинг игроков и кланов Minecraft PvP' }}</p>

        <ServerAddress v-if="info.server_address" :address="info.server_address" />
      </div>

      <nav class="site-footer__col" aria-label="Проект">
        <h3>Проект</h3>
        <RouterLink to="/rules">Правила тир-тестов</RouterLink>
        <RouterLink to="/about">О проекте и команда</RouterLink>
        <RouterLink to="/stats">Статистика</RouterLink>
        <RouterLink to="/status">Статус сервера</RouterLink>
        <RouterLink to="/news">Новости</RouterLink>
      </nav>

      <nav class="site-footer__col" aria-label="Сообщество">
        <h3>Сообщество</h3>
        <RouterLink to="/players">Рейтинг</RouterLink>
        <RouterLink to="/clans">Кланы</RouterLink>
        <RouterLink to="/tournaments">Турниры</RouterLink>
        <RouterLink to="/forum">Форум</RouterLink>
      </nav>

      <nav v-if="socials.length" class="site-footer__col" aria-label="Соцсети">
        <h3>Мы в сети</h3>
        <a
            v-for="social in socials"
            :key="social.key"
            :href="social.url"
            target="_blank"
            rel="noopener noreferrer"
        >{{ social.label }}</a>
      </nav>
    </div>

    <div class="site-footer__bottom">
      <span>© {{ year }} APEX TIERS</span>
      <span>Не связан с Mojang Studios и Microsoft. Minecraft — товарный знак Mojang Synergies AB.</span>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  margin-top: 64px;

  background: var(--bg);
  border-top: 1px solid var(--border);
}

.site-footer__inner {
  width: min(1200px, calc(100% - 40px));
  margin: 0 auto;
  padding: 40px 0 28px;

  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(3, minmax(0, 1fr));
  gap: 32px;
}

.site-footer__brand {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
}

.site-footer__logo {
  display: flex;
  align-items: center;
  gap: 10px;

  color: #fff;
}

.site-footer__logo strong {
  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}

.site-footer__brand p {
  margin: 0;

  color: var(--text-muted);

  font-size: 14px;
}

.site-footer__col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.site-footer__col h3 {
  margin: 0 0 4px;

  color: var(--text);

  font-family: var(--font-body);
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0;
}

.site-footer__col a {
  color: var(--text-dim);

  font-size: 14px;
}

.site-footer__col a:hover {
  color: var(--accent-light);
}

.site-footer__bottom {
  width: min(1200px, calc(100% - 40px));
  margin: 0 auto;
  padding: 16px 0 28px;

  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px 24px;

  color: var(--text-muted);
  border-top: 1px solid var(--border);

  font-size: 12px;
}

@media (max-width: 860px) {
  .site-footer__inner {
    grid-template-columns: 1fr 1fr;
  }

  .site-footer__brand {
    grid-column: 1 / -1;
  }
}

@media (max-width: 480px) {
  .site-footer__inner {
    width: calc(100% - 32px);
    grid-template-columns: 1fr;
  }

  .site-footer__bottom {
    width: calc(100% - 32px);
  }
}
</style>
