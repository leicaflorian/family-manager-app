<script setup lang="ts">
import { useRoute } from 'vue-router'
import Icona from './Icona.vue'

const route = useRoute()
const voci = [
  { a: '/', etichetta: 'Questo mese', icona: 'calendario' },
  { a: '/schede', etichetta: 'Schede', icona: 'griglia' },
  { a: '/documenti', etichetta: 'Documenti', icona: 'documento' },
  { a: '/account', etichetta: 'Account', icona: 'persona' },
]
const attiva = (a: string) => (a === '/' ? route.path === '/' : route.path.startsWith(a))
const sinistra = voci.slice(0, 2)
const destra = voci.slice(2)
</script>

<template>
  <nav class="fixed inset-x-0 bottom-0 z-10 mx-auto grid h-21 max-w-md grid-cols-5 items-center border-t border-[#E3E8EF] bg-white px-1 pb-3">
    <RouterLink
      v-for="v in sinistra"
      :key="v.a"
      :to="v.a"
      class="flex min-h-13 flex-col items-center justify-center gap-1 text-xs no-underline"
      :class="attiva(v.a) ? 'font-semibold text-azione' : 'font-medium text-secondario'"
    >
      <Icona :nome="v.icona" />{{ v.etichetta }}
    </RouterLink>
    <span class="flex justify-center">
      <RouterLink
        to="/carica"
        aria-label="Carica un documento"
        class="-mt-7 flex size-15 items-center justify-center rounded-full bg-azione text-white shadow-[0_6px_16px_rgba(30,111,217,0.35)] active:bg-azione-scuro"
      >
        <Icona nome="piu" :dimensione="28" :spessore="2.5" />
      </RouterLink>
    </span>
    <RouterLink
      v-for="v in destra"
      :key="v.a"
      :to="v.a"
      class="flex min-h-13 flex-col items-center justify-center gap-1 text-xs no-underline"
      :class="attiva(v.a) ? 'font-semibold text-azione' : 'font-medium text-secondario'"
    >
      <Icona :nome="v.icona" />{{ v.etichetta }}
    </RouterLink>
  </nav>
</template>
