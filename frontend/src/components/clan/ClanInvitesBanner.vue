<script setup>
/**
 * Приглашения в кланы: заметная плашка со списком тех, кто позвал игрока.
 * Принять можно в один клик — вступление по приглашению бесплатное.
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { clansApi } from '@/services/clan/clans.js'
import { useAuthStore } from '@/stores/core/auth.js'
import ClanStyleBar from '@/components/clan/ClanStyleBar.vue'

const router = useRouter()
const auth = useAuthStore()

const invitations = ref([])
const busy = ref(0)
const error = ref('')

async function load() {
  if (!auth.isAuthenticated) return

  try {
    const data = await clansApi.myInvitations()
    invitations.value = data.invitations ?? []
  } catch {
    invitations.value = []
  }
}

async function accept(item) {
  busy.value = item.id
  error.value = ''

  try {
    const { clan_id } = await clansApi.acceptInvitation(item.id)
    await auth.fetchMe()
    router.push(`/clans/${clan_id}`)
  } catch (e) {
    error.value = e.message || 'Не удалось принять приглашение.'
    await load()
  } finally {
    busy.value = 0
  }
}

async function decline(item) {
  busy.value = item.id

  try {
    await clansApi.declineInvitation(item.id)
  } finally {
    busy.value = 0
    await load()
  }
}

onMounted(load)
</script>

<template>
  <section v-if="invitations.length" class="inv">
    <div class="inv__title">
      <span class="inv__bell" aria-hidden="true">🔔</span>
      Вас приглашают в {{ invitations.length === 1 ? 'клан' : 'кланы' }}
    </div>

    <article v-for="item in invitations" :key="item.id" class="inv__card">
      <div class="inv__avatar" :style="{ background: item.clan.banner_color || '#7c3aed' }">
        <img v-if="item.clan.avatar_url" :src="item.clan.avatar_url" alt="" />
        <template v-else>{{ item.clan.tag?.charAt(0) }}</template>
      </div>

      <div class="inv__info">
        <strong>[{{ item.clan.tag }}] {{ item.clan.name }}</strong>
        <small>
          {{ item.clan.members_count }} участников · {{ item.clan.level }} ур.
          · зовёт {{ item.inviter?.username || 'руководство' }}
        </small>
        <ClanStyleBar :clan="item.clan" compact />
        <em v-if="item.message">«{{ item.message }}»</em>
      </div>

      <div class="inv__actions">
        <button type="button" class="inv__ok" :disabled="busy === item.id" @click="accept(item)">
          Вступить бесплатно
        </button>
        <button type="button" class="inv__no" :disabled="busy === item.id" @click="decline(item)">
          Отказаться
        </button>
      </div>
    </article>

    <p v-if="error" class="inv__error">{{ error }}</p>
  </section>
</template>

<style scoped>
.inv {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 18px;
  padding: 14px 16px;
  border: 1px solid rgba(250, 204, 21, 0.55);
  border-radius: 10px;
  background: linear-gradient(135deg, rgba(250, 204, 21, 0.14), rgba(124, 58, 237, 0.12));
  box-shadow: 0 0 28px -8px rgba(250, 204, 21, 0.5);
}

.inv__title {
  font-weight: 800;
  font-size: 16px;
}

.inv__card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(10, 10, 15, 0.55);
}

.inv__avatar {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  flex: none;
  overflow: hidden;
  border-radius: 8px;
  font-weight: 800;
  font-size: 20px;
}

.inv__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.inv__info {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.inv__info small {
  color: #a5a5c0;
}

.inv__info em {
  color: #c9c9e0;
  font-size: 13px;
}

.inv__actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.inv__ok,
.inv__no {
  padding: 8px 14px;
  border: 0;
  border-radius: 6px;
  font-weight: 700;
  cursor: pointer;
}

.inv__ok {
  background: linear-gradient(135deg, #22c55e, #06b6d4);
  color: #fff;
}

.inv__no {
  background: rgba(255, 255, 255, 0.08);
  color: #d4d4e8;
}

.inv__ok:disabled,
.inv__no:disabled {
  opacity: 0.5;
}

.inv__error {
  margin: 0;
  color: #fca5a5;
  font-size: 13px;
}

@media (max-width: 640px) {
  .inv__card {
    flex-wrap: wrap;
  }

  .inv__actions {
    flex-direction: row;
    width: 100%;
  }
}
</style>
