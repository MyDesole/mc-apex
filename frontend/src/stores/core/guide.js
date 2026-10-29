import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/services/core/api.js'
import { useAuthStore } from '@/stores/core/auth.js'

/**
 * Путеводитель: состояние с сервера и то, что живёт только во вкладке
 * (открыта ли панель, какие подсказки уже закрыты на этой странице).
 */
export const useGuideStore = defineStore('guide', () => {
    const state = ref(null)
    const busy = ref(false)
    const error = ref('')
    const panelOpen = ref(false)
    // Подсказки, закрытые крестиком: не всплывают снова, пока не нажмёшь «Показать»
    const dismissed = ref(new Set())

    const started = computed(() => !!state.value?.started)
    const finished = computed(() => !!state.value?.finished)
    const hidden = computed(() => !!state.value?.hidden)
    const current = computed(() => state.value?.current ?? null)
    const mascot = computed(() => state.value?.mascot ?? null)
    const doneCount = computed(() => (state.value?.steps ?? []).filter((s) => s.status !== 'todo').length)
    const stepCount = computed(() => state.value?.steps?.length ?? 0)

    async function load() {
        try {
            state.value = await api.get('/guide')
        } catch {
            state.value = null
        }
    }

    async function call(path, body) {
        if (busy.value) return
        busy.value = true
        error.value = ''

        try {
            const data = await api.post(path, body)
            state.value = data

            const auth = useAuthStore()
            if (data.balance !== undefined && auth.user) auth.user.apex_coins = data.balance
        } catch (e) {
            error.value = e.message || 'Не получилось.'
        } finally {
            busy.value = false
        }
    }

    const start = () => call('/guide/start')
    const complete = (step) => call(`/guide/steps/${step}/complete`)
    const skip = (step) => call(`/guide/steps/${step}/skip`)

    async function setHidden(value) {
        await call('/guide/hide', { hidden: value })
        if (!value) panelOpen.value = true
    }

    function dismiss(step) {
        dismissed.value = new Set([...dismissed.value, step])
    }

    function show(step) {
        const next = new Set(dismissed.value)
        next.delete(step)
        dismissed.value = next
    }

    function reset() {
        state.value = null
        panelOpen.value = false
        dismissed.value = new Set()
    }

    return {
        state, busy, error, panelOpen, dismissed,
        started, finished, hidden, current, mascot, doneCount, stepCount,
        load, start, complete, skip, setHidden, dismiss, show, reset,
    }
})
