<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/services/admin.js'

const settings = ref(null)
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const tab = ref('hero')

async function load() {
  loading.value = true
  try {
    settings.value = await adminApi.siteSettings()
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  saved.value = false
  try {
    await adminApi.updateSiteSettings(settings.value)
    saved.value = true
    setTimeout(() => saved.value = false, 2000)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="empty">Загрузка...</div>

  <div v-else-if="settings" class="home-editor">
    <div class="head">
      <h2>Настройки главной</h2>
      <button class="btn-save" :disabled="saving" @click="save">
        {{ saving ? 'Сохранение...' : saved ? '✓ Сохранено' : 'Сохранить' }}
      </button>
    </div>

    <nav class="tabs">
      <button :class="{ active: tab === 'hero' }" @click="tab = 'hero'">Hero</button>
      <button :class="{ active: tab === 'socials' }" @click="tab = 'socials'">Соцсети</button>
      <button :class="{ active: tab === 'footer' }" @click="tab = 'footer'">Футер</button>
    </nav>

    <!-- HERO -->
    <div v-if="tab === 'hero'" class="section">
      <div class="field">
        <label>Заголовок</label>
        <input v-model="settings.hero.hero_title" type="text" />
      </div>
      <div class="field">
        <label>Подзаголовок</label>
        <input v-model="settings.hero.hero_subtitle" type="text" />
      </div>
      <div class="field">
        <label>Бейдж (сверху)</label>
        <input v-model="settings.hero.hero_badge" type="text" placeholder="Season 1 · Live" />
      </div>

      <div class="row">
        <div class="field">
          <label>Кнопка 1 — текст</label>
          <input v-model="settings.hero.hero_primary_text" type="text" />
        </div>
        <div class="field">
          <label>Кнопка 1 — ссылка</label>
          <input v-model="settings.hero.hero_primary_url" type="text" />
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label>Кнопка 2 — текст</label>
          <input v-model="settings.hero.hero_secondary_text" type="text" />
        </div>
        <div class="field">
          <label>Кнопка 2 — ссылка</label>
          <input v-model="settings.hero.hero_secondary_url" type="text" />
        </div>
      </div>
    </div>

    <!-- SOCIALS -->
    <div v-else-if="tab === 'socials'" class="section">
      <div class="field">
        <label>Discord</label>
        <input v-model="settings.socials.social_discord" type="text" placeholder="https://discord.gg/..." />
      </div>
      <div class="field">
        <label>Telegram</label>
        <input v-model="settings.socials.social_telegram" type="text" placeholder="https://t.me/..." />
      </div>
      <div class="field">
        <label>YouTube</label>
        <input v-model="settings.socials.social_youtube" type="text" placeholder="https://youtube.com/@..." />
      </div>
      <div class="field">
        <label>VK</label>
        <input v-model="settings.socials.social_vk" type="text" placeholder="https://vk.com/..." />
      </div>
      <div class="field">
        <label>Twitch</label>
        <input v-model="settings.socials.social_twitch" type="text" placeholder="https://twitch.tv/..." />
      </div>
      <div class="field">
        <label>Twitter / X</label>
        <input v-model="settings.socials.social_twitter" type="text" placeholder="https://x.com/..." />
      </div>
    </div>

    <!-- FOOTER -->
    <div v-else-if="tab === 'footer'" class="section">
      <div class="field">
        <label>Текст футера</label>
        <input v-model="settings.footer.footer_text" type="text" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.home-editor {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}

.btn-save {
  padding: 10px 22px;
  color: #fff;
  background: var(--accent);
  border: 0;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
}

.btn-save:hover:not(:disabled) {
  background: var(--accent-light);
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.tabs {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid var(--border);
}

.tabs button {
  padding: 10px 18px;
  color: var(--text-dim);
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
}

.tabs button.active {
  color: var(--text);
  border-bottom-color: var(--accent);
}

.section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: var(--text-dim);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.field input,
.field textarea {
  width: 100%;
  padding: 10px 12px;
  color: var(--text);
  background: #0d0d14;
  border: 1px solid var(--border);
  border-radius: 9px;
  font: inherit;
  outline: none;
}

.field input:focus,
.field textarea:focus {
  border-color: var(--accent);
}

.empty {
  padding: 40px;
  text-align: center;
  color: var(--text-dim);
}

@media (max-width: 600px) {
  .row {
    grid-template-columns: 1fr;
  }
}
</style>