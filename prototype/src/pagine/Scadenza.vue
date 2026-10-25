<script setup lang="ts">
import { computed, ref } from 'vue'
import { coloreAreaScuro } from '../colori'
import Icona from '../components/Icona.vue'
import Intestazione from '../components/Intestazione.vue'
import { daCampoData, formatData, formatEuro, perCampoData, quando } from '../logic/formato'
import { mostraMessaggio } from '../messaggio'
import { oggi, segnaPagata, useFamiglia } from '../stato'

const props = defineProps<{ id: string }>()
const famiglia = useFamiglia()

const scadenza = computed(() => famiglia.scadenze.find((s) => s.id === props.id))
const scheda = computed(() => famiglia.schede.find((s) => s.id === scadenza.value?.schedaId))
const documenti = computed(() => famiglia.documenti.filter((d) => d.schedaId === scheda.value?.id))
const scaduta = computed(() => !!scadenza.value && !scadenza.value.pagata && scadenza.value.data < oggi())

const pannelloAperto = ref(false)
const ricevuta = ref<string | null>(null)
const valori = ref({ data: perCampoData(oggi()), conto: famiglia.conti[0] })

function sceltaRicevuta(evento: Event) {
  ricevuta.value = (evento.target as HTMLInputElement).files?.[0]?.name ?? ricevuta.value
}

function conferma(dati: typeof valori.value) {
  if (!scadenza.value) return
  if (segnaPagata(scadenza.value.id, { data: daCampoData(dati.data), conto: dati.conto, ricevuta: !!ricevuta.value })) {
    mostraMessaggio('Pagamento segnato')
  }
  pannelloAperto.value = false
}
</script>

<template>
  <div v-if="!scadenza" class="flex flex-col gap-4 px-5 pt-4">
    <Intestazione indietro="/" />
    <h1 class="m-0 text-[26px] font-extrabold">Questa scadenza non c'è più</h1>
    <RouterLink to="/" class="flex h-14 items-center justify-center rounded-2xl bg-azione text-[17px] font-bold text-white no-underline">Torna a Questo mese</RouterLink>
  </div>

  <div v-else class="flex flex-col gap-4.5 px-5 pt-4">
    <Intestazione />
    <header class="flex flex-col gap-1.5">
      <RouterLink
        v-if="scheda"
        :to="`/scheda/${scheda.id}`"
        class="text-sm font-semibold no-underline"
        :style="{ color: coloreAreaScuro(scheda.area) }"
      >{{ scheda.titolo }}</RouterLink>
      <h1 class="m-0 text-[26px] font-extrabold">{{ scadenza.titolo }}</h1>
      <span v-if="scadenza.importo !== null" class="text-[40px] font-extrabold">{{ formatEuro(scadenza.importo) }}</span>
      <span class="text-[15px]" :class="scaduta ? 'font-semibold text-scaduto' : 'text-[#4A5565]'">
        Scade il {{ formatData(scadenza.data) }} · {{ quando(scadenza.data, oggi()) }}
      </span>
    </header>

    <p v-if="scadenza.pagata" class="m-0 flex items-center gap-2.5 rounded-2xl bg-[#E6F6F1] px-4 py-3.5 text-base font-semibold text-pagato">
      <Icona nome="spunta" />
      Pagata il {{ formatData(scadenza.pagata.data) }} con {{ scadenza.pagata.conto }}
    </p>
    <button
      v-else-if="scadenza.importo !== null"
      type="button"
      class="h-14 rounded-2xl border-0 bg-azione text-[17px] font-bold text-white active:bg-azione-scuro"
      @click="pannelloAperto = true"
    >
      L'ho pagata
    </button>

    <section v-if="documenti.length" class="flex flex-col gap-2">
      <h2 class="m-0 text-[17px] font-bold">Documenti di {{ scheda?.titolo }}</h2>
      <div class="flex flex-col divide-y divide-riga rounded-2xl bg-white">
        <RouterLink
          v-for="d in documenti"
          :key="d.id"
          :to="{ path: '/documenti', query: { evidenzia: d.id } }"
          class="flex justify-between px-4 py-3.5 text-current no-underline"
        >
          <span class="font-semibold">{{ d.titolo }}</span>
          <span class="text-[13px] text-secondario">{{ formatData(d.data) }}</span>
        </RouterLink>
      </div>
    </section>

    <Transition name="pannello">
      <div v-if="pannelloAperto" class="fixed inset-0 z-30 mx-auto flex max-w-md flex-col justify-end bg-testo/45" @click.self="pannelloAperto = false">
        <div role="dialog" aria-modal="true" aria-labelledby="titolo-pannello" class="flex flex-col gap-4 rounded-t-3xl bg-white px-5 pt-3 pb-7">
          <span class="h-1.25 w-10 self-center rounded-full bg-[#D5DBE3]" />
          <h2 id="titolo-pannello" class="m-0 text-[22px] font-extrabold">L'ho pagata</h2>
          <FormKit v-model="valori" type="form" submit-label="Conferma" @submit="conferma">
            <FormKit type="date" name="data" label="Quando" validation="required" />
            <FormKit type="radio" name="conto" label="Con cosa" :options="famiglia.conti" />
            <label class="mb-3.5 flex h-12 cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-[#9AA6B6] text-[15px] font-semibold text-azione">
              <Icona nome="graffetta" :dimensione="18" />
              {{ ricevuta ?? 'Aggiungi la ricevuta (facoltativo)' }}
              <input type="file" accept="image/*,application/pdf" class="sr-only" @change="sceltaRicevuta" />
            </label>
          </FormKit>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.pannello-enter-active,
.pannello-leave-active {
  transition: opacity 0.2s ease;
}
.pannello-enter-from,
.pannello-leave-to {
  opacity: 0;
}
</style>
