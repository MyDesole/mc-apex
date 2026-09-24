<script setup>
import { computed, ref } from 'vue'
import PlayerCard from '@/components/PlayerCard.vue'
import TierTestHistory from '@/components/TierTestHistory.vue'
import ClanBadge from '@/components/ClanBadge.vue'
import RankBadge from '@/components/RankBadge.vue'
import ProfileCustomizeModal from '@/components/ProfileCustomizeModal.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const user = computed(() => auth.user)
const aspects = computed(() => auth.user?.aspects ?? [])
const clanMember = computed(() => auth.user?.clan_member ?? null)
const rank = computed(() => auth.rank ?? { position: null, total: 0 })

// 👇 вот это нужно для открытия модалки
const showCustomize = ref(false)

function onUpdated() {
  showCustomize.value = false
  auth.fetchMe()
}
</script>

<template>
  <div class="container">
    <template v-if="user">
      <PlayerCard
          :user="user"
          editable
          @edit="showCustomize = true"
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

      <TierTestHistory />

      <!-- 👇 модалка -->
      <ProfileCustomizeModal
          v-if="showCustomize"
          @close="showCustomize = false"
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

.loading {
  padding: 80px;
  text-align: center;
  color: var(--text-dim);
}

@media (max-width: 700px) {
  .blocks {
    grid-template-columns: 1fr;
  }
}
</style>