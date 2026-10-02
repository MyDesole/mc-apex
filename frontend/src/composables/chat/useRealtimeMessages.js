import { onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { echoGeneration } from '@/echo.js'

// Общий ref — один на всё приложение
const latestMessage = ref(null)
const newMessageArrived = ref(false)

let channel = null
let subscribersCount = 0

export function useRealtimeMessages() {
    const auth = useAuthStore()

    function subscribe() {
        if (!auth.user?.id || !window.Echo) return
        if (channel) return // уже подписаны

        channel = window.Echo.private(`App.Models.User.${auth.user.id}`)

        channel.listen('.message.sent', (payload) => {
            latestMessage.value = payload.message
            newMessageArrived.value = true

            setTimeout(() => {
                newMessageArrived.value = false
            }, 100)
        })
    }

    function unsubscribe() {
        if (channel && auth.user?.id) {
            window.Echo.leave(`App.Models.User.${auth.user.id}`)
            channel = null
        }
    }

    // Подписываемся при входе и заново — после пересоздания соединения
    watch(
        () => [auth.user?.id, echoGeneration.value],
        ([id]) => {
            if (!id) return

            // Соединение пересоздано: старая подписка потеряна
            channel = null

            subscribe()
        },
        { immediate: true }
    )

    return {
        latestMessage,
        newMessageArrived,
    }
}