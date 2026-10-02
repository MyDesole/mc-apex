<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import DialogHost from '@/components/core/DialogHost.vue'
</script>

<template>
  <AppLayout />

  <!-- Единое окно диалогов вместо встроенных alert/confirm/prompt -->
  <DialogHost />
</template>
