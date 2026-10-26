import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from '@/App.vue'
import router from '@/router/index.js'
import '@fontsource/golos-text/400.css'
import '@fontsource/golos-text/500.css'
import '@fontsource/golos-text/600.css'
import '@fontsource/golos-text/700.css'
import '@fontsource/golos-text/800.css'
import '@fontsource/unbounded/500.css'
import '@fontsource/unbounded/600.css'
import '@fontsource/unbounded/700.css'
import '@fontsource/unbounded/800.css'
import './assets/base.css'
import './assets/main.css'
import './assets/transitions.css'
import './assets/backdrops.css'
import './assets/nameEffects.css'
import './assets/chatBubbles.css'
import './assets/content-page.css'
import './echo'

import { useAuthStore } from '@/stores/core/auth.js'
import { initSentry, setSentryUser } from '@/sentry.js'
import { unlockSoundOnFirstGesture } from '@/utils/sound.js'
import { watch } from 'vue'

const app = createApp(App)

initSentry(app, router)

const pinia = createPinia()

app.use(pinia)
app.use(router)

const auth = useAuthStore(pinia)

await auth.fetchMe()

watch(() => auth.user?.id, () => setSentryUser(auth.user), { immediate: true })

router.beforeEach((to) => {
    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.role && !to.meta.role.includes(auth.user?.role)) {
        return { name: 'home' }
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'home' }
    }
})

app.mount('#app')

// Браузер разрешает звук только после действия игрока
unlockSoundOnFirstGesture()