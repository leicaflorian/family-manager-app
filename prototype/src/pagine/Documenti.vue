<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { coloreAreaScuro } from '../colori'
import Icona from '../components/Icona.vue'
import Pannello from '../components/Pannello.vue'
import type { Categoria } from '../data/tipi'
import { formatData, giorniTra, quando } from '../logic/formato'
import { cercaDocumenti, opzioniFiltro } from '../logic/ricerca'
import { oggi, useFamiglia } from '../stato'

/** Entro quanti giorni la scadenza di un documento diventa arancione */
const GIORNI_ATTENZIONE = 60

const route = useRoute()
const famiglia = useFamiglia()
const cerca = ref('')
// Nei menu «tutti» è la stringa vuota
const filtro = reactive({ categoria: '', personaId: '', ente: '' })
const pannelloFiltri = ref(false)
const opzioni = computed(() => opzioniFiltro(famiglia))
type Opzione = { value: string; label: string }
const tutte = (etichetta: string, valori: readonly (string | Opzione)[]): Opzione[] => [
  { value: '', label: etichetta },
  ...valori.map((v) => (typeof v === 'string' ? { value: v, label: v } : v)),
]

const filtriAttivi = computed(() =>
  [
    { chiave: 'categoria' as const, etichetta: filtro.categoria },
    { chiave: 'personaId' as const, etichetta: famiglia.persone.find((p) => p.id === filtro.personaId)?.nome ?? '' },
    { chiave: 'ente' as const, etichetta: filtro.ente },
  ].filter((f) => filtro[f.chiave]),
)

function togliFiltri() {
  Object.assign(filtro, { categoria: '', personaId: '', ente: '' })
}
const evidenziato = computed(() => (typeof route.query.evidenzia === 'string' ? route.query.evidenzia : null))

const risultati = computed(() =>
  cercaDocumenti(famiglia, cerca.value, {
    categoria: (filtro.categoria as Categoria) || null,
    personaId: filtro.personaId || null,
    ente: filtro.ente || null,
  }).map((d) => {
    const persona = famiglia.persone.find((p) => p.id === d.personaId)?.nome
    const scheda = famiglia.schede.find((s) => s.id === d.schedaId)
    const giorni = d.scadenza ? giorniTra(oggi(), d.scadenza) : null
    return {
      ...d,
      chi: [d.categoria, persona, d.ente ?? scheda?.titolo].filter(Boolean).join(' · '),
      area: scheda?.area ?? 'trasversali',
      vicina: giorni !== null && giorni <= GIORNI_ATTENZIONE,
    }
  }),
)
const quanti = computed(() => (risultati.value.length === 1 ? '1 documento' : `${risultati.value.length} documenti`))
</script>

<template>
  <div class="flex flex-col gap-4 px-5 pt-6">
    <h1 class="m-0 text-[28px] font-extrabold">Documenti</h1>
    <div class="flex items-center gap-2">
      <FormKit
        v-model="cerca"
        type="search"
        placeholder="Cerca un documento, una persona, un ente"
        aria-label="Cerca un documento, una persona, un ente"
        :classes="{ outer: '$reset min-w-0 grow' }"
      />
      <button
        type="button"
        :aria-label="filtriAttivi.length ? `Filtri, ${filtriAttivi.length} attivi` : 'Filtri'"
        class="relative flex size-12 shrink-0 items-center justify-center rounded-xl border border-bordo bg-white text-testo"
        @click="pannelloFiltri = true"
      >
        <Icona nome="filtro" />
        <span v-if="filtriAttivi.length" class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-azione text-xs font-bold text-white">
          {{ filtriAttivi.length }}
        </span>
      </button>
    </div>

    <div v-if="filtriAttivi.length" v-auto-animate class="flex flex-wrap gap-2">
      <button
        v-for="f in filtriAttivi"
        :key="f.chiave"
        type="button"
        :aria-label="`Togli il filtro ${f.etichetta}`"
        class="flex h-9 items-center gap-1.5 rounded-full border-0 bg-[#E4EEFB] px-3.5 text-sm font-semibold text-azione-scuro"
        @click="filtro[f.chiave] = ''"
      >
        {{ f.etichetta }}
        <Icona nome="chiudi" :dimensione="14" />
      </button>
    </div>

    <span v-if="cerca.trim() || filtriAttivi.length" class="text-sm text-secondario">
      {{ quanti }}<template v-if="cerca.trim()"> per «{{ cerca.trim() }}»</template>
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
      <p v-if="!risultati.length" class="m-0 rounded-2xl bg-white p-4 text-secondario">Nessun documento trovato</p>
    </div>

    <Pannello v-model="pannelloFiltri" titolo="Filtra i documenti">
      <FormKit v-model="filtro.categoria" type="select" name="categoria" label="Che tipo" :options="tutte('Tutti i tipi', opzioni.categorie)" />
      <FormKit v-model="filtro.personaId" type="select" name="persona" label="Di chi" :options="tutte('Tutta la famiglia', opzioni.persone)" />
      <FormKit v-model="filtro.ente" type="select" name="ente" label="Da chi arriva" :options="tutte('Tutti gli enti', opzioni.enti)" />
      <button type="button" class="h-14 rounded-2xl border-0 bg-azione text-[17px] font-bold text-white active:bg-azione-scuro" @click="pannelloFiltri = false">
        Mostra {{ quanti }}
      </button>
      <button v-if="filtriAttivi.length" type="button" class="h-11 border-0 bg-transparent text-base font-semibold text-azione" @click="togliFiltri">
        Togli i filtri
      </button>
    </Pannello>
  </div>
</template>
