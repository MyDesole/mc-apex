<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/services/core/api.js'

const props = defineProps({ userId: { type: Number, required: true }, isMe: Boolean })
const data = ref(null), editing = ref(false), busy = ref(false), error = ref(''), saved = ref(false)
const draft = ref({ enabled: true, mode: 'duels', theme: 'classic', title: '', duel_id: null, achievement_id: null })
const modes = { duels: 'Дуэли', bedwars: 'BedWars', fastbridge: 'FastBridge' }
const themes = { classic: 'Классика', obsidian: 'Обсидиан', aurora: 'Сияние', ember: 'Искры' }
let version = 0
watch(() => [props.userId, props.isMe], async () => {
  const request = ++version; data.value = null; editing.value = false; error.value = ''; busy.value = false; saved.value = false
  try { const fresh = await api.get(`/players/${props.userId}/showcase`); if (request === version) data.value = fresh }
  catch (e) { if (request === version && props.isMe) error.value = e.message || 'Не удалось загрузить витрину.' }
}, { immediate: true })
function edit() { draft.value = { enabled: true, mode: 'duels', theme: 'classic', title: '', duel_id: null, achievement_id: null, ...data.value?.settings }; editing.value = true; saved.value = false }
async function save() {
  const request = version
  busy.value = true; error.value = ''
  try { const fresh = await api.put('/players/me/showcase', draft.value); if (request === version) { data.value = fresh; editing.value = false; saved.value = true } }
  catch (e) { if (request === version) error.value = e.message || 'Не удалось сохранить витрину.' }
  finally { if (request === version) busy.value = false }
}
async function reset() {
  const request = version
  busy.value = true; error.value = ''
  try { const fresh = await api.delete('/players/me/showcase'); if (request === version) { data.value = fresh; editing.value = false } }
  catch (e) { if (request === version) error.value = e.message || 'Не удалось скрыть витрину.' }
  finally { if (request === version) busy.value = false }
}
onBeforeUnmount(() => { version++ })
</script>

<template>
  <section v-if="data?.visible || isMe" class="donor-showcase" :class="`donor-showcase--${data?.settings?.theme || 'classic'}`">
    <header><div><span class="donor-showcase__eyebrow">Личная витрина <b v-if="data?.rank">{{ data.rank }}</b></span><h3>{{ data?.settings?.title || 'Моя игра. Мои достижения.' }}</h3></div><button v-if="isMe && data?.eligible" type="button" @click="edit">Настроить</button></header>
    <p v-if="error" role="alert">{{ error }}</p><p v-if="saved" role="status">Витрина сохранена.</p>
    <p v-if="isMe && data && !data.eligible">Оформление витрины доступно с VIP. Сохранённые настройки останутся у тебя после окончания ранга.</p>
    <div v-if="data?.visible" class="donor-showcase__cards">
      <div><small>Любимый режим</small><strong>{{ modes[data.settings.mode] }}</strong><RouterLink :to="{ name: 'duels', query: { mode: data.settings.mode } }">К режиму →</RouterLink></div>
      <div v-if="data.duel"><small>Закреплённый матч</small><strong>{{ data.duel.won ? 'Победа' : 'Матч' }} · {{ data.duel.kit }}</strong><span>Против {{ data.duel.opponent }} · {{ data.duel.duration_seconds }} с</span></div>
      <div v-if="data.achievement"><small>Моё достижение</small><strong>{{ data.achievement.name }}</strong></div>
    </div>
    <p v-else-if="isMe && data?.eligible && !editing">Выбери любимый режим, оформление, свой матч и полученный трофей.</p>
    <form v-if="editing" class="donor-showcase__form" @submit.prevent="save">
      <label>Заголовок<input v-model="draft.title" maxlength="48" placeholder="Моя игра. Мои достижения."></label>
      <label>Любимый режим<select v-model="draft.mode"><option v-for="(label,key) in modes" :key="key" :value="key">{{ label }}</option></select></label>
      <label>Оформление<select v-model="draft.theme"><option v-for="(label,key) in themes" :key="key" :value="key">{{ label }}</option></select></label>
      <label>Свой матч<select v-model="draft.duel_id"><option :value="null">Не закреплять</option><option v-for="d in data.options?.duels || []" :key="d.id" :value="d.id">{{ d.kit }} · {{ d.won ? 'победа' : 'поражение' }} против {{ d.opponent }}</option></select></label>
      <label>Полученное достижение<select v-model="draft.achievement_id"><option :value="null">Не закреплять</option><option v-for="a in data.options?.achievements || []" :key="a.id" :value="a.id">{{ a.name }}</option></select></label>
      <label class="donor-showcase__toggle"><input v-model="draft.enabled" type="checkbox"> Показывать витрину</label>
      <footer><button :disabled="busy" type="submit">{{ busy ? 'Сохраняю…' : 'Сохранить' }}</button><button type="button" :disabled="busy" @click="editing=false">Отмена</button><button type="button" :disabled="busy" @click="reset">Сбросить</button></footer>
    </form>
    <button v-else-if="isMe && data?.settings && !data.eligible" type="button" :disabled="busy" @click="reset">Удалить сохранённую витрину</button>
  </section>
</template>

<style src="./DonorShowcase.css"></style>
