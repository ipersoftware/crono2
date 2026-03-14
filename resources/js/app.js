import { createUnhead, headSymbol } from '@unhead/vue'
import Echo from 'laravel-echo'
import { createPinia } from 'pinia'
import Pusher from 'pusher-js'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

window.Pusher = Pusher

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.provide(headSymbol, createUnhead())

app.mount('#app')
