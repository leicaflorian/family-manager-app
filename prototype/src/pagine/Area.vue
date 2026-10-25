<script setup lang="ts">
import { computed } from 'vue'
import { coloreAreaScuro } from '../colori'
import Intestazione from '../components/Intestazione.vue'
import type { Area } from '../data/tipi'
import { formatEuro, quando } from '../logic/formato'
import { nomeArea, prossimaDiScheda, schedeDiArea } from '../logic/aree'
import { prossimoStipendio } from '../logic/stipendio'
import { oggi, useFamiglia } from '../stato'

const props = defineProps<{ area: string }>()
const famiglia = useFamiglia()
const area = computed(() => props.area as Area)

const schede = computed(() =>
  schedeDiArea(famiglia, area.value).map((s) => {
    const prossima = prossimaDiScheda(famiglia, s.id, oggi())
    return { ...s, prossima, scaduta: !!prossima && prossima.data < oggi() }
  }),
)
</script>

<template>
  <div class="flex flex-col gap-4 px-5 pt-4">
    <Intestazione indietro="/schede" />
    <h1 class="m-0 text-[28px] font-extrabold" :style="{ color: coloreAreaScuro(area) }">{{ nomeArea(area) }}</h1>

    <template v-if="area === 'trasversali'">
      <section class="flex flex-col gap-2">
        <h2 class="m-0 text-[17px] font-bold">Persone</h2>
        <ul class="m-0 flex list-none flex-col divide-y divide-riga rounded-2xl bg-white p-0">
          <li v-for="p in famiglia.persone" :key="p.id" class="px-4 py-3.5 font-semibold">{{ p.nome }}</li>
        </ul>
      </section>
      <section class="flex flex-col gap-2">
        <h2 class="m-0 text-[17px] font-bold">Enti</h2>
        <ul class="m-0 flex list-none flex-col divide-y divide-riga rounded-2xl bg-white p-0">
          <li v-for="e in famiglia.enti" :key="e" class="px-4 py-3">{{ e }}</li>
        </ul>
      </section>
    </template>

    <template v-else>
      <div v-if="schede.length" class="flex flex-col divide-y divide-riga rounded-2xl bg-white">
        <RouterLink v-for="s in schede" :key="s.id" :to="`/scheda/${s.id}`" class="flex items-center justify-between gap-3 px-4 py-3.5 text-current no-underline">
          <span class="flex flex-col gap-0.5">
            <span class="text-base font-semibold">{{ s.titolo }}</span>
            <span v-if="s.stipendio" class="text-[13px] text-secondario">
              Stipendio · {{ quando(prossimoStipendio(s.stipendio.giorno, oggi()), oggi()) }}
            </span>
            <span v-else-if="s.prossima" class="text-[13px]" :class="s.scaduta ? 'font-semibold text-scaduto' : 'text-secondario'">
              {{ s.prossima.titolo }} · {{ quando(s.prossima.data, oggi()) }}
            </span>
            <span v-else class="text-[13px] text-secondario">{{ s.sottotitolo }}</span>
          </span>
          <span v-if="s.stipendio" class="text-[15px] font-bold whitespace-nowrap text-pagato">{{ formatEuro(s.stipendio.netto) }}</span>
          <span v-else-if="s.prossima?.importo" class="text-[15px] font-bold whitespace-nowrap">{{ formatEuro(s.prossima.importo) }}</span>
        </RouterLink>
      </div>
      <div v-else class="flex flex-col items-center gap-3 rounded-2xl bg-white p-6 text-center">
        <p class="m-0 text-base text-[#4A5565]">Non c'è ancora niente qui.</p>
        <RouterLink to="/carica" class="flex h-12 items-center rounded-xl bg-azione px-5 font-bold text-white no-underline">Carica un documento</RouterLink>
      </div>
    </template>
  </div>
</template>
