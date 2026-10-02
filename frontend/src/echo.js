import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

/*
 * Адрес Reverb берётся из окружения.
 *
 * Раньше значения были захардкожены (localhost:8080), и на сервере
 * браузер пытался подключиться к localhost самого пользователя:
 * соединения не было, поэтому новые сообщения появлялись только после
 * обновления страницы. Переменные VITE_REVERB_* заданы в обоих
 * окружениях — локально и на сервере.
 */
const host = import.meta.env.VITE_REVERB_HOST || 'localhost'
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080)
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
const secure = scheme === 'https'

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
    // На сервере Reverb доступен по обычному https через прокси nginx
    enabledTransports: secure ? ['wss'] : ['ws'],
    authEndpoint: '/broadcasting/auth',
})
