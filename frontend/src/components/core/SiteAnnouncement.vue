<script setup>
/**
 * Баннер объявления над страницами и плашка события «xN ApexCoin».
 * Настраивается на «Пульте» админки. Закрытый баннер не возвращается,
 * пока админ не опубликует новое объявление (у каждого свой id).
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { siteApi } from '@/services/site/site.js'

const STORAGE_KEY = 'apex:announcement-dismissed'

const control = ref(null)
const dismissed = ref(null)

const announcement = computed(() => control.value?.announcement ?? null)
const event = computed(() => control.value?.event ?? null)

const visible = computed(() => announcement.value && announcement.value.id !== dismissed.value)
const isInternal = computed(() => announcement.value?.link?.startsWith('/'))

function dismiss() {
  dismissed.value = announcement.value?.id ?? null
  try {
    localStorage.setItem(STORAGE_KEY, dismissed.value ?? '')
  } catch {
    /* без хранилища баннер просто вернётся после перезагрузки */
  }
}

function until(iso) {
  return iso ? new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }) : ''
}

onMounted(async () => {
  try {
    dismissed.value = localStorage.getItem(STORAGE_KEY)
  } catch {
    dismissed.value = null
  }

  try {
    control.value = (await siteApi.info())?.control ?? null
  } catch {
    control.value = null
  }
})
</script>

<template>
  <div v-if="visible" class="announce" :class="`announce--${announcement.tone || 'info'}`" role="status">
    <span class="announce__text">{{ announcement.text }}</span>

    <RouterLink v-if="announcement.link && isInternal" :to="announcement.link" class="announce__link">
      {{ announcement.link_label || 'Подробнее' }} →
    </RouterLink>
    <a v-else-if="announcement.link" :href="announcement.link" class="announce__link" target="_blank" rel="noopener">
      {{ announcement.link_label || 'Подробнее' }} →
    </a>

    <button type="button" class="announce__close" aria-label="Скрыть объявление" @click="dismiss">×</button>
  </div>

  <div v-if="event" class="event-strip">
    Событие: <b>x{{ event.multiplier }} ApexCoin</b> за тир-тесты, ачивки и ежедневный бонус
    <template v-if="event.until"> — до {{ until(event.until) }}</template>
  </div>
</template>

<style scoped>
.announce {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 6px 14px;

  padding: 9px 44px 9px 16px;
  position: relative;

  color: #fff;
  background: #2563eb;

  font-size: 14px;
  text-align: center;
}

.announce--success { background: #15803d; }
.announce--warning { background: #b45309; }
.announce--danger { background: #b91c1c; }

.announce__link {
  color: #fff;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 3px;
}

.announce__close {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);

  width: 28px;
  height: 28px;

  color: rgba(255, 255, 255, 0.85);
  background: none;
  border: 0;

  font-size: 20px;
  line-height: 1;

  cursor: pointer;
}

.event-strip {
  padding: 6px 16px;

  color: #fde68a;
  background: linear-gradient(90deg, rgba(250, 204, 21, 0.14), rgba(250, 204, 21, 0.04));
  border-bottom: 1px solid rgba(250, 204, 21, 0.25);

  font-size: 13px;
  text-align: center;
}

.event-strip b {
  color: #facc15;
}
</style>
