<script setup>
import { computed, useSlots } from 'vue'

const props = defineProps({
  user: { type: Object, default: null },
  clanTag: { type: String, default: null },
  clanColor: { type: String, default: null },
  // compact — только тег + ник в одну строку
  compact: { type: Boolean, default: false },
})

const slots = useSlots()

const tag = props.clanTag ?? props.user?.clan_tag ?? props.user?.clan_member?.clan?.tag ?? null
const color = props.clanColor ?? props.user?.clan_color ?? props.user?.clan_member?.clan?.banner_color ?? null

// Эффект ника из магазина: класс name-fx--{id} из assets/nameEffects.css
const effect = computed(() => props.user?.name_effect || null)

// «Волна» анимирует каждую букву отдельно — только для обычного ника без слота
const waveChars = computed(() =>
    effect.value === 'wave' && !slots.default ? [...(props.user?.username ?? '')] : null,
)
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
        <span class="username__name" :class="effect ? ['name-fx', `name-fx--${effect}`] : null">
            <template v-if="waveChars">
                <span
                    v-for="(char, i) in waveChars"
                    :key="i"
                    class="name-fx__char"
                    :style="{ '--i': i }"
                >{{ char }}</span>
            </template>
            <slot v-else>{{ user?.username ?? '—' }}</slot>
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

/* Свечению и прыгающим буквам нужно место над и под строкой */
.username__name.name-fx {
  overflow: visible;
}

.username--compact .username__tag {
  font-size: 0.85em;
}
</style>
