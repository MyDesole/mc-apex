<script setup>
/**
 * О проекте: что такое APEX и кто его делает.
 */
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import ServerStatusCard from '@/components/core/ServerStatusCard.vue'
import PageLoader from '@/components/core/PageLoader.vue'
import { siteApi } from '@/services/site/site.js'
import { userLink } from '@/utils/links.js'

const team = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    team.value = (await siteApi.team()).team ?? []
  } catch {
    team.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="content-page">
    <header class="content-page__head">
      <h1>О проекте</h1>
      <p>APEX — рейтинг игроков и кланов Minecraft PvP с живыми тир-тестами.</p>
    </header>

    <section class="content-card">
      <h2>Что это</h2>
      <p>
        Каждый игрок может пройти тир-тест: тестер оценивает пять аспектов боя, и по сумме
        выставляется тир от E до A. Высший тир S выдаётся только за победы в турнирах.
      </p>
      <p>
        Вокруг рейтинга — кланы с клановыми войнами, турниры, форум и магазин косметики
        за ApexCoin, которые зарабатываются игрой. Подробнее об оценке — в
        <RouterLink to="/rules">правилах тир-тестов</RouterLink>.
      </p>
    </section>

    <ServerStatusCard />

    <section class="content-card">
      <h2>Команда</h2>

      <PageLoader v-if="loading" compact />

      <p v-else-if="!team.length">Список команды скоро появится.</p>

      <ul v-else class="team">
        <li v-for="member in team" :key="member.id">
          <RouterLink :to="userLink(member)" class="member">
            <span class="member__avatar">
              <img v-if="member.avatar_url" :src="member.avatar_url" :alt="member.username" />
              <span v-else>{{ member.username.charAt(0).toUpperCase() }}</span>
            </span>

            <span class="member__info">
              <strong>{{ member.username }}</strong>
              <span>{{ member.role_label }}</span>
              <small v-if="member.tests">Провёл тестов: {{ member.tests }}</small>
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.team {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;

  margin: 0;
  padding: 0;

  list-style: none;
}

.member {
  display: flex;
  align-items: center;
  gap: 12px;

  padding: 10px 12px;

  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);

  transition: border-color 0.15s ease;
}

.member:hover {
  border-color: var(--accent);
}

.member__avatar {
  width: 44px;
  height: 44px;
  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  overflow: hidden;

  color: #fff;
  background: var(--accent);
  border-radius: 4px;

  font-weight: 700;
}

.member__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.member__info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.member__info strong {
  overflow: hidden;

  color: #fff;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.member__info span {
  color: var(--accent-light);
  font-size: 13px;
}

.member__info small {
  color: var(--text-muted);
  font-size: 12px;
}
</style>
