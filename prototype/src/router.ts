import { createRouter, createWebHashHistory } from 'vue-router'
import { useFamiglia } from './stato'

export const router = createRouter({
  // Con l'hash il sito statico funziona senza configurare il server
  history: createWebHashHistory(),
  routes: [
    { path: '/', component: () => import('./pagine/QuestoMese.vue') },
    { path: '/carica', component: () => import('./pagine/Carica.vue'), meta: { senzaBarra: true } },
    { path: '/scadenza/:id', component: () => import('./pagine/Scadenza.vue'), props: true },
    { path: '/schede', component: () => import('./pagine/Schede.vue') },
    { path: '/schede/:area', component: () => import('./pagine/Area.vue'), props: true },
    { path: '/scheda/:id', component: () => import('./pagine/Scheda.vue'), props: true },
    { path: '/documenti', component: () => import('./pagine/Documenti.vue') },
    { path: '/account', component: () => import('./pagine/Account.vue') },
    { path: '/benvenuto', component: () => import('./pagine/Benvenuto.vue'), meta: { senzaBarra: true } },
    { path: '/:altro(.*)', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

// Finché la famiglia non ha fatto il primo avvio, si parte dal benvenuto
router.beforeEach((to) => {
  if (!useFamiglia().primoAvvioFatto && to.path !== '/benvenuto') return '/benvenuto'
})
