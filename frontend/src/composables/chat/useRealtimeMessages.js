import { onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/core/auth.js'
import { echoGeneration } from '@/echo.js'

// Общие ref — одни на всё приложение
const latestMessage = ref(null)
const newMessageArrived = ref(false)

/** Последнее событие прочтения: кто и в каком диалоге прочитал. */
const latestRead = ref(null)

/** Кто сейчас печатает: { [conversationId]: { id, username, at } }. */
const typingUsers = ref({})

/** Через сколько миллисекунд надпись «печатает» гаснет сама. */
const TYPING_TTL_MS = 6000

let channel = null
let subscribersCount = 0

/** Таймеры, гасящие надпись «печатает». */
const typingTimers = new Map()

export function useRealtimeMessages() {
    const auth = useAuthStore()

    /** Отмечает, что игрок печатает в диалоге. */
    function markTyping(payload) {
        const conversationId = payload?.conversation_id
        const user = payload?.user

        if (!conversationId || !user?.id) return

        typingUsers.value = {
            ...typingUsers.value,
            [conversationId]: {
                id: user.id,
                username: user.username,
                at: Date.now(),
            },
        }

        // Надпись гаснет сама: событие о конце печати не приходит
        clearTimeout(typingTimers.get(conversationId))

        typingTimers.set(conversationId, setTimeout(() => {
            const next = { ...typingUsers.value }

            delete next[conversationId]
            typingUsers.value = next
            typingTimers.delete(conversationId)
        }, TYPING_TTL_MS))
    }

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

        /*
         * Собеседник прочитал: галочка должна встать сразу, а не после
         * обновления страницы.
         */
        channel.listen('.messages.read', (payload) => {
            latestRead.value = payload
        })

        /* Собеседник печатает */
        channel.listen('.user.typing', (payload) => {
            markTyping(payload)
        })
    }

    function unsubscribe() {
        if (channel && auth.user?.id) {
            window.Echo.leave(`App.Models.User.${auth.user.id}`)
            channel = null
        }

        typingTimers.forEach((timer) => clearTimeout(timer))
        typingTimers.clear()
        typingUsers.value = {}
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
        latestRead,
        typingUsers,
    }
}
