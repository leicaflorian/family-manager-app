<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icona from '../components/Icona.vue'
import Intestazione from '../components/Intestazione.vue'
import { daCampoData, perCampoData, spostaGiorni } from '../logic/formato'
import { mostraMessaggio } from '../messaggio'
import { aggiungiDocumento, oggi, useFamiglia } from '../stato'

// Il prototipo non legge il file: dopo una finta lettura propone sempre la stessa bolletta
const DURATA_LETTURA = 2000

const router = useRouter()
const famiglia = useFamiglia()
const passo = ref<'scegli' | 'lettura' | 'proposta'>('scegli')
const anteprima = ref<string | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

const valori = ref({
  titolo: 'Bolletta luce di ottobre',
  schedaId: 'luce',
  importo: 62.4,
  scadenza: perCampoData(spostaGiorni(oggi(), 11)),
})
const schede = famiglia.schede.map((s) => ({ value: s.id, label: s.titolo }))

function scelto(evento: Event) {
  const file = (evento.target as HTMLInputElement).files?.[0]
  // Scelta annullata: si resta qui
  if (!file) return
  anteprima.value = file.type.startsWith('image/') ? URL.createObjectURL(file) : null
  passo.value = 'lettura'
  timer = setTimeout(() => (passo.value = 'proposta'), DURATA_LETTURA)
}

function salva(dati: typeof valori.value) {
  aggiungiDocumento({
    titolo: dati.titolo,
    schedaId: dati.schedaId,
    importo: Number(dati.importo),
    scadenza: daCampoData(dati.scadenza),
  })
  mostraMessaggio('Bolletta salvata: la trovi nelle scadenze')
  router.push('/')
}

onBeforeUnmount(() => {
  clearTimeout(timer)
  if (anteprima.value) URL.revokeObjectURL(anteprima.value)
})
</script>

<template>
  <div class="flex min-h-dvh flex-col gap-5 bg-white px-5 pt-4 pb-7">
    <Intestazione titolo="Carica un documento" chiudi />

    <template v-if="passo === 'scegli'">
      <div class="flex grow flex-col justify-center gap-4">
        <p class="m-0 text-center text-base text-[#4A5565]">Una bolletta, una ricevuta, un documento: fai una foto o scegli il file.</p>
        <label class="flex h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl bg-azione text-[17px] font-bold text-white active:bg-azione-scuro">
          <Icona nome="fotocamera" :dimensione="32" />
          Scatta una foto
          <input type="file" accept="image/*" capture="environment" class="sr-only" @change="scelto" />
        </label>
        <label class="flex h-20 cursor-pointer items-center justify-center gap-2 rounded-2xl border-2 border-azione text-[17px] font-bold text-azione">
          <Icona nome="cartella" />
          Scegli un file
          <input type="file" accept="image/*,application/pdf" class="sr-only" @change="scelto" />
        </label>
      </div>
    </template>

    <template v-else-if="passo === 'lettura'">
      <div class="flex grow flex-col items-center justify-center gap-7">
        <div class="relative flex h-75 w-55 items-center justify-center overflow-hidden rounded-2xl border border-[#E3E8EF] bg-sfondo shadow-[0_10px_30px_rgba(24,33,47,0.1)]">
          <img v-if="anteprima" :src="anteprima" alt="" class="size-full object-cover" @error="anteprima = null" />
          <Icona v-else nome="documento" :dimensione="64" :spessore="1.5" class="text-bordo" />
          <span class="riga-scanner absolute inset-x-0 h-0.75 bg-brand shadow-[0_0_18px_6px_rgba(51,153,255,0.45)]" />
        </div>
        <div class="flex flex-col items-center gap-2 text-center">
          <h2 class="m-0 text-[22px] font-extrabold">Sto leggendo il documento…</h2>
          <p class="m-0 max-w-70 text-base text-[#4A5565]">Cerco chi l'ha mandato, quanto c'è da pagare e quando scade.</p>
        </div>
      </div>
      <p class="m-0 text-center text-[13px] text-secondario">Ci vogliono pochi secondi</p>
    </template>

    <template v-else>
      <div class="flex items-center gap-3.5 rounded-2xl bg-[#F3F8FF] p-3.5">
        <span class="flex h-17 w-13 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#DCE3EC] bg-white">
          <img v-if="anteprima" :src="anteprima" alt="" class="size-full object-cover" @error="anteprima = null" />
          <Icona v-else nome="documento" :dimensione="22" class="text-secondario" />
        </span>
        <span class="flex flex-col gap-1">
          <span class="text-[17px] font-bold">Ho trovato una bolletta della luce</span>
          <span class="text-sm text-[#4A5565]">Controlla e correggi se serve</span>
        </span>
      </div>
      <FormKit v-model="valori" type="form" submit-label="Va bene, salva" @submit="salva">
        <FormKit type="text" name="titolo" label="Che cos'è" validation="required" />
        <FormKit type="select" name="schedaId" label="Di chi è" :options="schede" validation="required" />
        <div class="grid grid-cols-2 gap-3">
          <FormKit type="number" name="importo" label="Importo (€)" number="float" step="0.01" min="0" validation="required|min:0" />
          <FormKit type="date" name="scadenza" label="Scade il" validation="required" />
        </div>
        <p class="mb-3 flex items-start gap-2.5 rounded-xl bg-sfondo px-3.5 py-3 text-sm text-[#4A5565]">
          <Icona nome="info" :dimensione="18" class="mt-px shrink-0 text-azione" />
          Ti ricorderò di pagarla 3 giorni prima della scadenza.
        </p>
      </FormKit>
      <RouterLink to="/" class="flex h-11 items-center justify-center text-base font-semibold text-azione no-underline">Annulla</RouterLink>
    </template>
  </div>
</template>

<style scoped>
.riga-scanner {
  animation: scansione 1.6s ease-in-out infinite alternate;
}
@keyframes scansione {
  from { top: 8%; }
  to { top: 90%; }
}
@media (prefers-reduced-motion: reduce) {
  .riga-scanner { animation: none; top: 46%; }
}
</style>
