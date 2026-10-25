<script setup>
/**
 * Витрина игрока в профиле: 3D-скин из Minecraft, ник, карточка
 * игрока и сравнение с другим игроком.
 */
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import MinecraftSkinViewer from '@/components/minecraft/MinecraftSkinViewer.vue'
import PlayerCardModal from '@/components/players/PlayerCardModal.vue'
import BrandCube from '@/components/core/BrandCube.vue'
import { loadSkin } from '@/utils/minecraftSkin.js'
import { useAuthStore } from '@/stores/core/auth.js'

const props = defineProps({
  user: { type: Object, required: true },
  rank: { type: Object, default: null },
  isMe: { type: Boolean, default: false },
})

const emit = defineEmits(['link-minecraft'])

const auth = useAuthStore()

const skin = ref(null)
const skinLoading = ref(true)
const cardOpen = ref(false)
const copied = ref(false)

const nickname = computed(() => props.user?.minecraft_username || null)

/** Сравнение: чужой профиль — я против него, свой — выбрать соперника. */
const compareLink = computed(() => {
  if (props.isMe || !auth.user) {
    return { path: '/compare', query: { a: props.user.username } }
  }

  return { path: '/compare', query: { a: auth.user.username, b: props.user.username } }
})

watch(
    () => [props.user?.id, props.user?.minecraft_username],
    async ([id]) => {
      skinLoading.value = true
      skin.value = null

      try {
        skin.value = await loadSkin(id)
      } finally {
        skinLoading.value = false
      }
    },
    { immediate: true },
)

async function copyNickname() {
  if (!nickname.value) return

  try {
    await navigator.clipboard.writeText(nickname.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1800)
  } catch {
    /* буфер недоступен — ник и так виден */
  }
}
</script>

<template>
  <section class="showcase">
    <div
        class="showcase__stage mc-backdrop"
        :class="user.skin_backdrop ? `mc-backdrop--${user.skin_backdrop}` : null"
    >
      <MinecraftSkinViewer
          v-if="skin"
          :skin="skin"
          :name-tag="nickname"
      />

      <div v-else class="showcase__empty">
        <BrandCube class="showcase__cube" :class="{ 'showcase__cube--loading': skinLoading }" />

        <template v-if="!skinLoading">
          <strong>{{ nickname ? 'Скин не загрузился' : 'Minecraft не привязан' }}</strong>
          <span v-if="nickname">Mojang не ответил — попробуйте позже</span>
          <span v-else-if="isMe">Укажите ник — здесь появится ваш 3D-скин</span>
          <span v-else>Игрок ещё не указал ник из игры</span>
        </template>
      </div>
    </div>

    <div class="showcase__info">
      <span class="showcase__label">Minecraft</span>

      <div v-if="nickname" class="showcase__nick">
        <strong>{{ nickname }}</strong>
        <button
            type="button"
            class="showcase__copy"
            :title="copied ? 'Скопировано' : 'Скопировать ник'"
            @click="copyNickname"
        >
          {{ copied ? 'Скопировано' : 'Копировать' }}
        </button>
      </div>

      <button
          v-else-if="isMe"
          type="button"
          class="btn btn-secondary showcase__link"
          @click="emit('link-minecraft')"
      >
        Привязать ник
      </button>

      <p class="showcase__text">
        Соберите карточку игрока с тиром, аспектами и скином — её удобно
        кинуть в Discord. Или сравните себя с любым игроком по каждому навыку.
      </p>

      <div class="showcase__actions">
        <button
            type="button"
            class="btn btn-primary"
            @click="cardOpen = true"
        >
          Карточка игрока
        </button>

        <RouterLink
            v-if="auth.isAuthenticated"
            :to="compareLink"
            class="btn btn-secondary"
        >
          {{ isMe ? 'Сравнить с игроком' : 'Сравнить со мной' }}
        </RouterLink>
      </div>
    </div>

    <PlayerCardModal
        v-model="cardOpen"
        :user="user"
        :rank="rank"
    />
  </section>
</template>

<style scoped>
.showcase {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);

  overflow: hidden;

  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}

.showcase__stage {
  height: 360px;

  /* Фон — из assets/backdrops.css: шахматка или купленная сцена */
  border-right: 1px solid var(--border);
}

/* 3D-модель поверх декоративных слоёв сцены */
.showcase__stage > * {
  position: relative;
  z-index: 1;
}

.showcase__empty {
  height: 100%;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;

  padding: 24px;

  color: var(--text-muted);

  font-size: 14px;
  text-align: center;
}

.showcase__empty strong {
  color: var(--text);
  font-size: 15px;
}

.showcase__cube {
  width: 56px;
  height: 56px;
  margin-bottom: 10px;
}

.showcase__cube--loading {
  animation: showcase-hop 0.9s ease-in-out infinite;
}

@keyframes showcase-hop {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

.showcase__info {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;

  padding: 28px;
}

.showcase__label {
  color: var(--text-muted);

  font-size: 13px;
  font-weight: 600;
}

.showcase__nick {
  display: flex;
  align-items: center;
  gap: 12px;
}

.showcase__nick strong {
  color: #fff;

  font-family: var(--font-display);
  font-size: 24px;
  letter-spacing: -0.02em;
}

.showcase__copy {
  padding: 4px 10px;

  color: var(--text-dim);
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;

  font-size: 12px;
  font-weight: 600;

  cursor: pointer;
}

.showcase__copy:hover {
  color: #fff;
  border-color: var(--border-hover);
}

.showcase__link {
  align-self: flex-start;
}

.showcase__text {
  margin: 0;

  color: var(--text-dim);

  font-size: 14px;
}

.showcase__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

@media (prefers-reduced-motion: reduce) {
  .showcase__cube--loading {
    animation: none;
  }
}

@media (max-width: 760px) {
  .showcase {
    grid-template-columns: 1fr;
  }

  .showcase__stage {
    height: 300px;
    border-right: 0;
    border-bottom: 1px solid var(--border);
  }

  .showcase__info {
    padding: 20px;
  }
}
</style>
