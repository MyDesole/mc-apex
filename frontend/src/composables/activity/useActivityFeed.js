import { onMounted, onUnmounted, ref } from 'vue'
import { activityApi } from '@/services/activity/activity.js'
import { onEchoReset } from '@/echo.js'

const CHANNEL = 'activity'
const EVENT = '.activity.recorded'

/** Сколько событий держать в памяти: лента не должна расти бесконечно. */
const MAX_ITEMS = 60

/**
 * Лента «Сейчас на APEX»: начальный список по HTTP, новые события — по
 * публичному каналу Reverb. Сокет может отвалиться, поэтому при
 * пересоздании соединения подписываемся заново и подтягиваем пропущенное.
 */
export function useActivityFeed({ limit = 8 } = {}) {
    const items = ref([])
    const loading = ref(true)
    const loadingMore = ref(false)
    const hasMore = ref(true)
    const live = ref(false)

    // id событий, пришедших по сокету, — для подсветки «только что»
    const fresh = ref(new Set())

    let stopReset = null
    let stateTimer = null

    function add(activity, { highlight = false } = {}) {
        if (!activity?.id || items.value.some((item) => item.id === activity.id)) return

        items.value = [activity, ...items.value]
            .sort((a, b) => b.id - a.id)
            .slice(0, MAX_ITEMS)

        if (highlight) {
            fresh.value = new Set([...fresh.value, activity.id])

            setTimeout(() => {
                const next = new Set(fresh.value)
                next.delete(activity.id)
                fresh.value = next
            }, 4000)
        }
    }

    async function load() {
        try {
            const { data } = await activityApi.list({ limit })

            items.value = data ?? []
            hasMore.value = (data?.length ?? 0) >= limit
        } catch {
            items.value = []
            hasMore.value = false
        } finally {
            loading.value = false
        }
    }

    async function loadMore() {
        const last = items.value[items.value.length - 1]

        if (!last || loadingMore.value) return

        loadingMore.value = true

        try {
            const { data } = await activityApi.list({ limit, before: last.id })

            items.value = [...items.value, ...(data ?? [])].slice(0, MAX_ITEMS)
            hasMore.value = (data?.length ?? 0) >= limit && items.value.length < MAX_ITEMS
        } catch {
            hasMore.value = false
        } finally {
            loadingMore.value = false
        }
    }

    /** Пропущенное, пока сокет был недоступен. */
    async function catchUp() {
        try {
            const { data } = await activityApi.list({ limit })

            ;(data ?? []).forEach((activity) => add(activity))
        } catch {
            /* не критично: подтянется при следующем событии */
        }
    }

    function subscribe() {
        if (!window.Echo) return

        window.Echo.channel(CHANNEL).listen(EVENT, (payload) => {
            add(payload?.activity, { highlight: true })
        })
    }

    function unsubscribe() {
        try {
            window.Echo?.leave(CHANNEL)
        } catch {
            /* соединения уже нет */
        }
    }

    function readState() {
        live.value = window.Echo?.connector?.pusher?.connection?.state === 'connected'
    }

    onMounted(() => {
        load()
        subscribe()
        readState()

        // Состояние соединения: точка «в эфире» горит только при живом сокете
        stateTimer = setInterval(readState, 3000)

        stopReset = onEchoReset(() => {
            subscribe()
            catchUp()
        })
    })

    onUnmounted(() => {
        unsubscribe()
        clearInterval(stateTimer)
        stopReset?.()
    })

    return { items, loading, loadingMore, hasMore, live, fresh, loadMore }
}
