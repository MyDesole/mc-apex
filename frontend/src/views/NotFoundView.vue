<script setup>
import PageState from '@/components/core/PageState.vue'
</script>

<template>
  <PageState kind="notFound" :action="{ to: '/', label: 'На главную' }" />
</template>
