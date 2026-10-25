<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { coloreAreaScuro } from '../colori'
import Icona from '../components/Icona.vue'
import { formatData, giorniTra, quando } from '../logic/formato'
import { cercaDocumenti } from '../logic/ricerca'
import { oggi, useFamiglia } from '../stato'

/** Entro quanti giorni la scadenza di un documento diventa arancione */
const GIORNI_ATTENZIONE = 60

const route = useRoute()
const famiglia = useFamiglia()
const cerca = ref('')
const evidenziato = computed(() => (typeof route.query.evidenzia === 'string' ? route.query.evidenzia : null))

const risultati = computed(() =>
  cercaDocumenti(famiglia, cerca.value).map((d) => {
    const persona = famiglia.persone.find((p) => p.id === d.personaId)?.nome
    const scheda = famiglia.schede.find((s) => s.id === d.schedaId)
    const giorni = d.scadenza ? giorniTra(oggi(), d.scadenza) : null
    return {
      ...d,
      chi: [persona, scheda?.titolo].filter(Boolean).join(' · '),
      area: scheda?.area ?? 'trasversali',
      vicina: giorni !== null && giorni <= GIORNI_ATTENZIONE,
    }
  }),
)

onMounted(async () => {
  if (!evidenziato.value) return
  await nextTick()
  document.getElementById(`doc-${evidenziato.value}`)?.scrollIntoView({ block: 'center' })
})
</script>

<template>
  <div class="flex flex-col gap-4 px-5 pt-6">
    <h1 class="m-0 text-[28px] font-extrabold">Documenti</h1>
    <FormKit
      v-model="cerca"
      type="search"
      placeholder="Cerca un documento, una persona, un ente"
      aria-label="Cerca un documento, una persona, un ente"
      :classes="{ outer: 'mb-0' }"
    />
    <span v-if="cerca.trim()" class="text-sm text-secondario">
      {{ risultati.length === 1 ? '1 documento' : `${risultati.length} documenti` }} per «{{ cerca.trim() }}»
    </span>

    <div v-auto-animate class="flex flex-col gap-2.5">
      <article
        v-for="d in risultati"
        :id="`doc-${d.id}`"
        :key="d.id"
        class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3.5"
        :class="d.id === evidenziato ? 'ring-2 ring-azione' : ''"
      >
        <span
          class="flex size-10 shrink-0 items-center justify-center rounded-xl"
          :style="{ color: coloreAreaScuro(d.area), background: `color-mix(in srgb, var(--color-${d.area}) 14%, white)` }"
        >
          <Icona nome="documento" :dimensione="20" />
        </span>
        <span class="flex grow flex-col gap-0.5">
          <span class="text-base font-semibold">{{ d.titolo }}</span>
          <span v-if="d.chi" class="text-[13px] text-secondario">{{ d.chi }}</span>
          <span v-if="d.scadenza" class="text-[13px] font-semibold" :class="d.vicina ? 'text-attenzione' : 'text-testo'">
            Scade il {{ formatData(d.scadenza) }} · {{ quando(d.scadenza, oggi()) }}
          </span>
          <span v-else class="text-[13px] text-secondario">{{ formatData(d.data) }}</span>
        </span>
      </article>
      <p v-if="!risultati.length" class="m-0 rounded-2xl bg-white p-4 text-secondario">Nessun documento per «{{ cerca.trim() }}»</p>
    </div>
  </div>
</template>
