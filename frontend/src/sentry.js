/**
 * Sentry для фронтенда: ошибки Vue и необработанные промисы.
 *
 * Включается только в продакшен-сборке и только при заданном
 * VITE_SENTRY_DSN — локально и в тестах ничего не отправляется.
 * Библиотека грузится отдельным чанком, чтобы не утяжелять старт.
 */
export async function initSentry(app, router) {
    const dsn = import.meta.env.VITE_SENTRY_DSN

    if (!dsn || !import.meta.env.PROD) return

    const Sentry = await import('@sentry/vue')

    Sentry.init({
        app,
        dsn,
        environment: import.meta.env.MODE,
        release: import.meta.env.VITE_RELEASE || undefined,
        integrations: [Sentry.browserTracingIntegration({ router })],
        tracesSampleRate: 0.1,
        // Шум от расширений браузера и обрывов сети нам не нужен
        ignoreErrors: ['ResizeObserver loop', 'Failed to fetch', 'Load failed', 'NetworkError'],
    })
}

/** Привязать ошибки к игроку: только id и ник, без почты. */
export async function setSentryUser(user) {
    if (!import.meta.env.VITE_SENTRY_DSN || !import.meta.env.PROD) return

    const Sentry = await import('@sentry/vue')
    Sentry.setUser(user ? { id: String(user.id), username: user.username } : null)
}
