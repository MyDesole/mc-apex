<script setup>
import AppLayout from './layouts/AppLayout.vue'
import DialogHost from './components/DialogHost.vue'
</script>

<template>
  <AppLayout />

  <!-- Единое окно диалогов вместо встроенных alert/confirm/prompt -->
  <DialogHost />
</template>
