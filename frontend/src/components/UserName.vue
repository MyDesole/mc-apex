<script setup>
const props = defineProps({
  user: { type: Object, default: null },
  clanTag: { type: String, default: null },
  clanColor: { type: String, default: null },
  // compact — только тег + ник в одну строку
  compact: { type: Boolean, default: false },
})

const tag = props.clanTag ?? props.user?.clan_tag ?? props.user?.clan_member?.clan?.tag ?? null
const color = props.clanColor ?? props.user?.clan_color ?? props.user?.clan_member?.clan?.banner_color ?? null
</script>

<template>
    <span class="username" :class="{ 'username--compact': compact }">
        <span
            v-if="tag"
            class="username__tag"
            :style="color ? { color } : {}"
        >
            [{{ tag }}]
        </span>
        <span class="username__name">
            <slot>{{ user?.username ?? '—' }}</slot>
        </span>
    </span>
</template>

<style scoped>
.username {
  display: inline-flex;
  align-items: baseline;
  gap: 5px;
  min-width: 0;
}

.username__tag {
  color: var(--accent-light);
  font-weight: 800;
  font-size: 0.92em;
  flex-shrink: 0;
  letter-spacing: -0.3px;
}

.username__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.username--compact .username__tag {
  font-size: 0.85em;
}
</style>