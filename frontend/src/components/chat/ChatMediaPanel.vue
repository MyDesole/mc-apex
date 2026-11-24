<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { chatApi } from '@/services/chat/chat.js'
import ChatVoicePlayer from './ChatVoicePlayer.vue'
import ImageLightbox from '@/components/core/ImageLightbox.vue'
const props = defineProps({ conversationId: { type: Number, required: true } })
defineEmits(['close', 'jump'])
const type = ref('all'), items = ref([]), loading = ref(false), error = ref(''), cursor = ref(null), more = ref(false), photo = ref(null)
const types = [['all','Все'],['image','Фото'],['video','Видео'],['voice','Голосовые'],['file','Файлы']]
let generation = 0
async function load(append = false) {
  const request = ++generation
  const id = props.conversationId
  loading.value = true
  error.value = ''
  try {
    const data = await chatApi.attachments(id, type.value, append ? cursor.value : null)
    if (request !== generation) return
    items.value = append ? [...items.value, ...data.attachments] : data.attachments
    cursor.value = data.next_cursor
    more.value = Boolean(data.has_more)
  } catch (e) { if (request === generation) error.value = e.message || 'Не удалось загрузить вложения' }
  finally { if (request === generation) loading.value = false }
}
function openPhoto(item) {
  const images = items.value.filter(a => a.is_image)
  photo.value = { images, start: images.findIndex(a => a.id === item.id) }
}
watch([() => props.conversationId, type], () => { items.value = []; photo.value = null; load() }, { immediate: true })
onBeforeUnmount(() => { generation++ })
</script>
<template>
  <section class="chat-media" aria-label="Вложения диалога">
    <header><strong>Вложения</strong><button type="button" aria-label="Закрыть вложения" @click="$emit('close')">✕</button></header>
    <nav aria-label="Тип вложения"><button v-for="[id,label] in types" :key="id" type="button" :class="{active:type===id}" @click="type=id">{{label}}</button></nav>
    <div class="chat-media__list">
      <p v-if="error" role="alert">{{error}} <button type="button" @click="load()">Повторить</button></p>
      <p v-else-if="!items.length && !loading" class="chat-media__empty">Вложений этого типа пока нет</p>
      <article v-for="item in items" :key="item.id" class="chat-media__item">
        <button v-if="item.is_image" type="button" class="chat-media__photo" :aria-label="item.name" @click="openPhoto(item)"><img :src="item.url" :alt="item.name" loading="lazy"></button>
        <video v-else-if="item.is_video" :src="item.url" controls playsinline preload="metadata" :aria-label="item.name"></video>
        <ChatVoicePlayer v-else-if="item.is_voice" :file="item" />
        <a v-else :href="item.url" target="_blank" rel="noopener">{{item.name}}</a>
        <div class="chat-media__meta"><span>{{item.user?.username}} · {{item.size}}</span><button type="button" :aria-label="`К сообщению: ${item.name}`" @click="$emit('jump',item.message_id)">К сообщению ↗</button></div>
        <a v-if="item.is_video" class="chat-media__original" :href="item.url" target="_blank" rel="noopener">Открыть видео отдельно</a>
      </article>
      <p v-if="loading" role="status">Загрузка…</p>
      <button v-else-if="more" class="chat-media__more" type="button" @click="load(true)">Загрузить ещё</button>
    </div>
    <ImageLightbox v-if="photo" :images="photo.images" :start="photo.start" @close="photo=null" />
  </section>
</template>
<style scoped>
.chat-media { flex:0 0 auto; max-height:min(420px,50%); overflow:hidden; display:flex; flex-direction:column; background:var(--bg-card); border-bottom:1px solid var(--border); }
header { display:flex; align-items:center; justify-content:space-between; padding:10px 18px 4px; font-size:13px; }
button { color:var(--text-muted); background:transparent; border:0; cursor:pointer; font:inherit; padding:6px 10px; border-radius:6px; }
nav { display:flex; gap:4px; padding:4px 12px 10px; overflow-x:auto; flex-shrink:0; font-size:12px; }
nav button { white-space:nowrap; }
nav button.active { color:var(--text); background:rgba(151,122,211,.16); }
.chat-media__list { overflow:auto; min-height:0; padding:0 16px 12px; display:grid; grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr)); gap:12px; max-height:280px; grid-auto-rows:max-content; align-content:start; }
.chat-media__item { min-width:0; padding:10px; border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.chat-media__photo { display:block; width:100%; padding:0; }
.chat-media__photo img { display:block; width:100%; height:130px; object-fit:cover; border-radius:6px; }
video { display:block; width:100%; max-height:200px; background:#08080c; border-radius:6px; }
.chat-media__meta { display:flex; justify-content:space-between; align-items:center; gap:4px; margin-top:6px; color:var(--text-muted); font-size:10px; }
.chat-media__meta span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.chat-media__meta button { flex-shrink:0; padding:4px; color:#bfa7e8; }
a { font-size:12px; overflow-wrap:anywhere; }
.chat-media__original { display:block; margin-top:6px; font-size:10px; }
p { font-size:12px; color:var(--text-muted); }
.chat-media__empty { grid-column:1/-1; padding:12px; text-align:center; }
</style>
