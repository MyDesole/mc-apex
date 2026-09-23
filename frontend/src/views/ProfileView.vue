<script setup>
import { computed } from 'vue'
import PlayerCard from '../components/PlayerCard.vue'
import { useAuthStore } from '../stores/auth'
import TierTestHistory from "@/components/TierTestHistory.vue";

const auth = useAuthStore()

const user = computed(() => auth.user)
const aspects = computed(() => auth.user?.aspects ?? [])
</script>

<template>
  <div class="container">
    <PlayerCard
        v-if="user"
        :user="user"
        :aspects="aspects"
    />
    <TierTestHistory         v-if="user"
    />

    <div v-else>Загрузка...</div>
  </div>
</template>

<style scoped>
.container {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
}
</style>