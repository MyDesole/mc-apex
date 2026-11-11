<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import DialogHost from '@/components/core/DialogHost.vue'
import MaintenanceOverlay from '@/components/core/MaintenanceOverlay.vue'
import WinterDecor from '@/components/core/WinterDecor.vue'
import TierChatDock from '@/components/tiers/TierChatDock.vue'
</script>

<template>
  <AppLayout />

  <!-- Зимнее оформление: снег и ёлочки (с середины ноября по середину марта) -->
  <WinterDecor />

  <!-- Заметный чат тестируемого с тестером, пока идёт заявка на тир-тест -->
  <TierChatDock />

  <!-- Окно «идёт обновление», пока API на деплое отвечает 503 -->
  <MaintenanceOverlay />

  <!-- Единое окно диалогов вместо встроенных alert/confirm/prompt -->
  <DialogHost />
</template>
