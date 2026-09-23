import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/inter/700.css'
import '@fontsource/inter/800.css'
import '@fontsource/inter/900.css'
import './assets/base.css'
import './assets/main.css'

import { useAuthStore } from './stores/auth'

const app = createApp(App)

const pinia = createPinia()

app.use(pinia)
app.use(router)

const auth = useAuthStore(pinia)

await auth.fetchMe()

router.beforeEach((to) => {
    if (to.meta.auth && !auth.isAuthenticated) {
        return {
            name: 'login',
            query: {
                redirect: to.fullPath,
            },
        }
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return {
            name: 'home',
        }
    }
})

app.mount('#app')