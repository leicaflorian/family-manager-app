import { createRouter, createWebHashHistory, type RouteLocationNormalized, type RouterScrollBehavior } from 'vue-router'
import { useFamiglia } from './stato'

/** Il documento evidenziato, la posizione di prima tornando indietro, altrimenti l'inizio */
export function scorrimento(
  to: RouteLocationNormalized,
  _from: RouteLocationNormalized,
  salvata: { left: number; top: number } | null,
): ReturnType<RouterScrollBehavior> {
  if (typeof to.query.evidenzia === 'string') return { el: `#doc-${to.query.evidenzia}`, block: 'center' } as never
  return salvata ?? { top: 0 }
}

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
  scrollBehavior: (to, from, salvata) => scorrimento(to, from, salvata),
})

// Finché la famiglia non ha fatto il primo avvio si parte dal benvenuto, e dopo non si riapre
router.beforeEach((to) => {
  const fatto = useFamiglia().primoAvvioFatto
  if (!fatto && to.path !== '/benvenuto') return '/benvenuto'
  if (fatto && to.path === '/benvenuto') return '/'
})
