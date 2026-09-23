import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

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