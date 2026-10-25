<script setup lang="ts">
import { computed } from 'vue'
import { coloreAreaScuro } from '../colori'
import Icona from '../components/Icona.vue'
import { formatData, formatEuro, quando } from '../logic/formato'
import { nomeArea, prossimaDiScheda, quantoPaghi, scadenzeDiScheda } from '../logic/aree'
import { oggi, useFamiglia } from '../stato'

const props = defineProps<{ id: string }>()
const famiglia = useFamiglia()

const scheda = computed(() => famiglia.schede.find((s) => s.id === props.id))
const prossima = computed(() => prossimaDiScheda(famiglia, props.id, oggi()))
const senzaImporto = computed(() =>
  scadenzeDiScheda(famiglia, props.id).filter((s) => s.importo === null && !s.pagata && s.id !== prossima.value?.id),
)
const documenti = computed(() =>
  famiglia.documenti.filter((d) => d.schedaId === props.id).sort((a, b) => b.data.getTime() - a.data.getTime()),
)
</script>

<template>
  <div v-if="!scheda" class="px-5 pt-6">
    <h1 class="m-0 text-[26px] font-extrabold">Questa scheda non c'è più</h1>
  </div>

  <div v-else class="flex flex-col">
    <header class="flex flex-col gap-2.5 px-5 pt-4 pb-5.5 text-white" :style="{ background: coloreAreaScuro(scheda.area) }">
      <RouterLink :to="`/schede/${scheda.area}`" aria-label="Indietro" class="-ml-2.5 flex size-11 items-center justify-center text-white">
        <Icona nome="indietro" />
      </RouterLink>
      <span class="text-sm font-semibold">{{ nomeArea(scheda.area) }}</span>
      <h1 class="m-0 text-[28px] font-extrabold">{{ scheda.titolo }}</h1>
      <span class="text-[15px]">{{ scheda.sottotitolo }}</span>
    </header>

    <div class="flex flex-col gap-3.5 px-5 pt-4.5">
      <RouterLink v-if="prossima" :to="`/scadenza/${prossima.id}`" class="flex flex-col gap-1 rounded-2xl bg-white p-4 text-current no-underline">
        <span class="text-[13px] font-semibold text-secondario">Prossima scadenza</span>
        <span class="flex items-baseline justify-between gap-3">
          <span class="text-lg font-bold">{{ prossima.titolo }}</span>
          <span v-if="prossima.importo !== null" class="text-lg font-extrabold">{{ formatEuro(prossima.importo) }}</span>
        </span>
        <span class="text-sm text-[#4A5565]">{{ formatData(prossima.data) }} · {{ quando(prossima.data, oggi()) }}</span>
      </RouterLink>

      <div class="grid grid-cols-2 gap-3 rounded-2xl bg-white p-4">
        <span class="flex flex-col gap-1">
          <span class="text-[13px] font-semibold text-secondario">Quanto paghi</span>
          <span class="text-xl font-extrabold">{{ formatEuro(quantoPaghi(famiglia, scheda.id, oggi())) }}</span>
          <span class="text-[13px] text-secondario">nei prossimi 12 mesi</span>
        </span>
        <span v-for="s in senzaImporto.slice(0, 1)" :key="s.id" class="flex flex-col gap-1">
          <span class="text-[13px] font-semibold text-secondario">{{ s.titolo }}</span>
          <span class="text-xl font-extrabold">{{ formatData(s.data) }}</span>
          <span class="text-[13px] text-secondario">{{ quando(s.data, oggi()) }}</span>
        </span>
      </div>

      <div
        v-for="m in scheda.manca"
        :key="m"
        class="flex items-center gap-3 rounded-2xl border border-[#F9C9A6] bg-[#FFF4EC] px-4 py-3.5"
      >
        <span class="flex grow flex-col gap-0.5">
          <span class="text-[13px] font-bold text-attenzione">Manca</span>
          <span class="text-base font-semibold">{{ m }}</span>
        </span>
        <RouterLink to="/carica" class="flex h-11 items-center rounded-xl border border-[#F9C9A6] bg-white px-4 text-[15px] font-bold text-attenzione no-underline">Caricalo</RouterLink>
      </div>

      <section class="flex flex-col gap-2">
        <h2 class="m-0 text-[17px] font-bold">Documenti</h2>
        <div v-if="documenti.length" v-auto-animate class="flex flex-col divide-y divide-riga rounded-2xl bg-white">
          <RouterLink
            v-for="d in documenti"
            :key="d.id"
            :to="{ path: '/documenti', query: { evidenzia: d.id } }"
            class="flex justify-between gap-3 px-4 py-3.5 text-current no-underline"
          >
            <span class="font-semibold">{{ d.titolo }}</span>
            <span class="text-[13px] whitespace-nowrap text-secondario">{{ formatData(d.data) }}</span>
          </RouterLink>
        </div>
        <p v-else class="m-0 rounded-2xl bg-white p-4 text-secondario">Nessun documento, per ora</p>
      </section>
    </div>
  </div>
</template>
