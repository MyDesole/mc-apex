<script setup>
import { computed, ref } from 'vue'
import PlayerCard from '@/components/PlayerCard.vue'
import TierTestHistory from '@/components/TierTestHistory.vue'
import ClanBadge from '@/components/ClanBadge.vue'
import RankBadge from '@/components/RankBadge.vue'
import ProfileEditModal from '@/components/ProfileEditModal.vue'
import { useAuthStore } from '@/stores/auth'
import AchievementsGrid from "@/components/AchievementsGrid.vue";

const auth = useAuthStore()
import { onMounted } from 'vue'
import { achievementsApi } from '@/services/achievements.js'

const achievements = ref([])
const earnedCount = ref(0)
const totalCount = ref(0)
const points = ref(0)

onMounted(async () => {
  const data = await achievementsApi.list()
  achievements.value = data.achievements
  earnedCount.value = data.earned_count
  totalCount.value = data.total_count
  points.value = data.points
})
const user = computed(() => auth.user)
const aspects = computed(() => auth.user?.aspects ?? [])
const clanMember = computed(() => auth.user?.clan_member ?? null)
const rank = computed(() => auth.rank ?? { position: null, total: 0 })

const showEdit = ref(false)

function onUpdated() {
  auth.fetchMe()
}
</script>

<template>
  <div class="container">
    <template v-if="user">
      <PlayerCard
          :user="user"
          :aspects="aspects"
          editable
          @edit="showEdit = true"
      />

      <section class="blocks">
        <div class="block-col">
          <h3 class="block-title">Клан</h3>
          <ClanBadge :clan-member="clanMember" />
        </div>

        <div class="block-col">
          <h3 class="block-title">Место в топе</h3>
          <RankBadge :position="rank.position" :total="rank.total" />
        </div>
      </section>
      <section class="achievements-section">
        <header class="section-head">
          <h3>Ачивки</h3>
          <span class="section-count">
            {{ earnedCount }} / {{ totalCount }}
            · <b>{{ points }}</b> очков
        </span>
        </header>

        <AchievementsGrid :achievements="achievements" />
      </section>
      <TierTestHistory />

      <ProfileEditModal
          v-if="showEdit"
          :user="user"
          @close="showEdit = false"
          @updated="onUpdated"
      />
    </template>

    <div v-else class="loading">Загрузка...</div>
  </div>
</template>

<style scoped>
.container {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.achievements-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.section-head h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--text);
  letter-spacing: -0.2px;
}

.section-count {
  font-size: 12px;
  color: var(--text-dim);
}

.section-count b {
  color: var(--accent-light);
  font-weight: 900;
}
.blocks {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.block-col {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.block-title {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
}
.loading { padding: 80px; text-align: center; color: var(--text-dim); }
@media (max-width: 700px) {
  .blocks { grid-template-columns: 1fr; }
}
</style>