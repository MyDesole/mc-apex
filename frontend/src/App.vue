<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import DialogHost from '@/components/core/DialogHost.vue'
import MaintenanceOverlay from '@/components/core/MaintenanceOverlay.vue'
</script>

<template>
  <AppLayout />

  <!-- Окно «идёт обновление», пока API на деплое отвечает 503 -->
  <MaintenanceOverlay />

  <!-- Единое окно диалогов вместо встроенных alert/confirm/prompt -->
  <DialogHost />
</template>
