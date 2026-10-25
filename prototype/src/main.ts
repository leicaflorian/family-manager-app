import { autoAnimatePlugin } from '@formkit/auto-animate/vue'
import { plugin as formkit } from '@formkit/vue'
import { createApp } from 'vue'
import App from './App.vue'
import formkitConfig from './formkit.config'
import { router } from './router'
import './style.css'

createApp(App).use(router).use(formkit, formkitConfig).use(autoAnimatePlugin).mount('#app')
