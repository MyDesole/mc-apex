import { ref } from 'vue'

/**
 * Идёт ли обновление сайта.
 *
 * Во время деплоя с миграциями API отвечает 503 (режим обслуживания Laravel).
 * Сам сайт при этом открыт, поэтому показываем окно «подождите», опрашиваем
 * API и обновляем страницу, как только он снова отвечает.
 */
export const maintenance = ref(false)

const POLL_MS = 5000

let timer = null

/** Статусы, которые бывают только пока сервер перезапускается. */
export function isUpdateStatus(status) {
    return status === 502 || status === 503 || status === 504
}

async function ping() {
    try {
        const response = await fetch('/api/site', {
            credentials: 'include',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        })

        return response.ok
    } catch {
        return false
    }
}

/** Включить окно обновления и ждать, пока сайт вернётся. */
export function startMaintenance() {
    if (maintenance.value) return

    maintenance.value = true

    timer = setInterval(async () => {
        if (!(await ping())) return

        clearInterval(timer)
        timer = null

        // Страница целиком: вместе с ней подтянется новая сборка фронтенда
        window.location.reload()
    }, POLL_MS)
}

/** Для тестов. */
export function resetMaintenance() {
    if (timer) clearInterval(timer)

    timer = null
    maintenance.value = false
}
