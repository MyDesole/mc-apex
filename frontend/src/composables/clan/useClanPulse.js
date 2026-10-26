/**
 * Сводка «Моего клана» для меню сайта: клан, отметился ли игрок
 * сегодня, сколько активных войн и заявок. Одна на всю вкладку, чтобы
 * меню и вкладка «Обзор» показывали одно и то же.
 */
import { computed, ref } from 'vue'
import { myClanApi } from '@/services/clan/myClan.js'

const pulse = ref(null)
let pending = null

export async function refreshClanPulse() {
    if (pending) return pending

    pending = myClanApi.pulse()
        .then((data) => { pulse.value = data })
        .catch(() => { pulse.value = null })
        .finally(() => { pending = null })

    return pending
}

export function clearClanPulse() {
    pulse.value = null
}

export function useClanPulse() {
    // Есть повод заглянуть в клан: не отметился, идёт война или ждут заявки
    const attention = computed(() => {
        const p = pulse.value
        if (!p) return 0

        return (p.checked_in ? 0 : 1) + (p.wars_active || 0) + (p.applications || 0)
    })

    return { pulse, attention, refreshClanPulse }
}
