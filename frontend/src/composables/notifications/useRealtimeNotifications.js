import { onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'

/*
 * Состояние на уровне модуля: все компоненты, которые вызывают
 * useRealtimeNotifications(), получают ОДИН И ТОТ ЖЕ счётчик.
 *
 * Раньше ref создавался внутри функции, поэтому у шапки и страницы
 * уведомлений были разные счётчики: «прочитать всё» на странице не
 * сбрасывало бейдж в шапке, и помогала только перезагрузка.
 */
const unreadCount = ref(0)
const latestNotification = ref(null)
const newNotificationArrived = ref(false)

let channel = null
let subscribedUserId = null

export function useRealtimeNotifications() {
    const auth = useAuthStore()

    /** Принудительно задать счётчик (например, после «прочитать всё»). */
    function setUnreadCount(value) {
        unreadCount.value = Math.max(0, Number(value) || 0)
    }

    /** Пересчитать счётчик с сервера. */
    async function refreshUnreadCount() {
        if (!auth.isAuthenticated) {
            unreadCount.value = 0
            return
        }

        try {
            const { notificationsApi } = await import('@/services/notifications/notification.js')
            const data = await notificationsApi.list()

            unreadCount.value = data.unread_count ?? 0
        } catch (e) {
            // тихо: счётчик не критичен для работы страницы
        }
    }

    function decrementUnread() {
        if (unreadCount.value > 0) {
            unreadCount.value--
        }
    }

    function subscribe() {
        if (!auth.user?.id || !window.Echo) return

        // Уже подписаны на этого пользователя — не дублируем канал
        if (channel && subscribedUserId === auth.user.id) return

        unsubscribe()

        subscribedUserId = auth.user.id
        channel = window.Echo.private(`App.Models.User.${auth.user.id}`)

        channel.listen('.notification.created', (payload) => {
            unreadCount.value++
            latestNotification.value = payload
            newNotificationArrived.value = true

            setTimeout(() => {
                newNotificationArrived.value = false
            }, 100)
        })
    }

    function unsubscribe() {
        if (channel && subscribedUserId) {
            window.Echo?.leave(`App.Models.User.${subscribedUserId}`)
        }

        channel = null
        subscribedUserId = null
    }

    // Следим за сменой юзера — переподписываемся
    watch(
        () => auth.user?.id,
        (id) => {
            unsubscribe()

            if (id) {
                subscribe()
            } else {
                unreadCount.value = 0
            }
        },
        { immediate: true }
    )

    return {
        unreadCount,
        latestNotification,
        newNotificationArrived,
        setUnreadCount,
        refreshUnreadCount,
        decrementUnread,
        subscribe,
        unsubscribe,
    }
}
