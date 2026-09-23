<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import PlayerCard from '../components/PlayerCard.vue'
import {api} from "@/services/api.js";

const route = useRoute()
const user = ref(null)
const aspects = ref([])
const loading = ref(true)

onMounted(async () => {
  const data = await api.get(`/players/${route.params.id}`)
  user.value = data.user
  aspects.value = data.user.aspects
  loading.value = false
})
</script>

<template>
  <div class="container">
    <div v-if="loading">Загрузка...</div>
    <PlayerCard v-else :user="user" :aspects="aspects" />
  </div>
</template>

<style scoped>
.container {
  width: min(900px, calc(100% - 40px));
  margin: 40px auto;
}
</style>