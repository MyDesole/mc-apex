<script setup>
import { RouterLink } from 'vue-router'
import ServerStatusCard from '@/components/core/ServerStatusCard.vue'
</script>

<template>
  <div class="content-page">
    <header class="content-page__head">
      <h1>Статус сервера</h1>
      <p>Работает ли сервер, сколько игроков онлайн и как подключиться.</p>
    </header>

    <ServerStatusCard />

    <section class="content-card">
      <h2>Как подключиться</h2>
      <ol class="connect">
        <li>Открой Minecraft и выбери «Сетевая игра».</li>
        <li>Нажми «Добавить сервер» и вставь адрес сервера — его можно скопировать выше.</li>
        <li>Зайди под своим ником и введи пароль от аккаунта на сайте.</li>
        <li>Чтобы ник засчитывался в рейтинг и появился 3D-скин, укажи его в <RouterLink to="/profile">профиле</RouterLink>.</li>
      </ol>
    </section>
  </div>
</template>

<style scoped>
.connect {
  display: flex;
  flex-direction: column;
  gap: 8px;

  margin: 0;
  padding-left: 22px;
}
</style>
