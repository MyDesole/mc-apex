import { onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useRealtimeNotifications() {
    const auth = useAuthStore()

    // Текущее количество непрочитанных (реактивно)
    const unreadCount = ref(0)

    // Последнее прилетевшее уведомление (для тоста)
    const latestNotification = ref(null)

    // Флаг для тоста — чтобы различать «пришло новое» от «загрузили из БД»
    const newNotificationArrived = ref(false)

    let channel = null

    function subscribe() {
        if (!auth.user?.id || !window.Echo) return

        // Отписываемся на всякий случай, если уже были подписаны
        unsubscribe()

        channel = window.Echo.private(`App.Models.User.${auth.user.id}`)

        channel.listen('.notification.created', (payload) => {
            unreadCount.value++
            latestNotification.value = payload
            newNotificationArrived.value = true

            // Сбрасываем флаг через секунду, чтобы тост не висел вечно
            setTimeout(() => {
                newNotificationArrived.value = false
            }, 100)
        })
    }

    function unsubscribe() {
        if (channel && auth.user?.id) {
            window.Echo.leave(`App.Models.User.${auth.user.id}`)
            channel = null
        }
    }

    // Следим за сменой юзера — переподписываемся
    watch(
        () => auth.user?.id,
        (id) => {
            unsubscribe()
            if (id) subscribe()
        },
        { immediate: true }
    )

    onUnmounted(unsubscribe)

    return {
        unreadCount,
        latestNotification,
        newNotificationArrived,
    }
}