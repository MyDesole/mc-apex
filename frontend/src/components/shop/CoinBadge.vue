<script setup>
/**
 * Плашка с балансом ApexCoin для шапки сайта.
 */
import { computed } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { formatCoins } from '@/data/wallet/economy.js'
import AppIcon from '@/components/core/AppIcon.vue'

const auth = useAuthStore()

const balance = computed(() => auth.user?.apex_coins ?? 0)
</script>

<template>
  <RouterLink
      class="coin-badge"
      :to="{ name: 'wallet' }"
      title="Кошелёк ApexCoin"
  >
    <span class="coin-badge__shine"></span>

    <span class="coin-badge__icon">
      <AppIcon
          icon="coin"
          :size="16"
          color="currentColor"
      />
    </span>

    <span class="coin-badge__content">
      <span class="coin-badge__label">
        APEXCOIN
      </span>

      <span class="coin-badge__value">
        {{ formatCoins(balance) }}
      </span>
    </span>

    <span class="coin-badge__arrow">
      <AppIcon
          icon="send"
          :size="12"
          color="currentColor"
      />
    </span>
  </RouterLink>
</template>

<style scoped>
@import "@/components/shop/CoinBadge.css";
</style>
