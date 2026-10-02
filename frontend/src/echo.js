import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

/*
 * Адрес Reverb берётся из окружения.
 *
 * Раньше значения были захардкожены (localhost:8080), и на сервере
 * браузер пытался подключиться к localhost самого пользователя:
 * соединения не было, поэтому новые сообщения появлялись только после
 * обновления страницы.
 *
 * wsHost и wsPort задаются всегда, в том числе при работе по https:
 * pusher-js строит адрес защищённого соединения из wsPort, поэтому
 * оставлять его по умолчанию нельзя — получалось wss://хост:80,
 * и соединение падало в состояние failed.
 */
const host = import.meta.env.VITE_REVERB_HOST || 'localhost'
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080)
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
const secure = scheme === 'https'

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    // Оба порта одинаковые: иначе wss уйдёт на порт 80
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
    enabledTransports: secure ? ['wss'] : ['ws'],
    authEndpoint: '/broadcasting/auth',
})
