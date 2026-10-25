<script setup lang="ts">
import { format } from '@formkit/tempo'
import { computed } from 'vue'
import { maiuscola } from '../colori'
import Icona from '../components/Icona.vue'
import RigaScadenza from '../components/RigaScadenza.vue'
import { fineMese, formatEuro, giorniTra } from '../logic/formato'
import { daPagareEntro, prossime, totale } from '../logic/scadenze'
import { oggi, useFamiglia } from '../stato'

const famiglia = useFamiglia()
const data = maiuscola(format(oggi(), 'dddd D MMMM', 'it'))

const entroFineMese = computed(() => daPagareEntro(famiglia.scadenze, oggi(), fineMese(oggi())))
const scadute = computed(() => entroFineMese.value.filter((s) => s.data < oggi()).length)
const sottotitolo = computed(() => {
  const n = entroFineMese.value.length
  const pagamenti = n === 1 ? '1 pagamento' : `${n} pagamenti`
  if (!scadute.value) return pagamenti
  return `${pagamenti}, ${scadute.value === 1 ? 'uno già scaduto' : `${scadute.value} già scaduti`}`
})
const prossimi = computed(() => prossime(famiglia.scadenze, oggi(), 30))

const posta = computed(() =>
  famiglia.posta.map((p) => {
    const documento = famiglia.documenti.find((d) => d.id === p.documentoId)
    const scheda = famiglia.schede.find((s) => s.id === documento?.schedaId)
    const giorni = giorniTra(p.arrivata, oggi())
    return {
      ...p,
      nota: `${giorni === 0 ? 'Oggi' : giorni === 1 ? 'Ieri' : `${giorni} giorni fa`}${scheda ? ` · già collegata a ${scheda.titolo}` : ''}`,
    }
  }),
)
</script>

<template>
  <div class="flex flex-col gap-5 px-5 pt-6">
    <header class="flex flex-col gap-0.5">
      <span class="text-sm text-secondario">{{ data }}</span>
      <h1 class="m-0 text-[28px] font-extrabold">Questo mese</h1>
    </header>

    <section class="flex flex-col gap-1.5 rounded-[20px] bg-azione p-5 text-white">
      <span class="text-[15px] font-medium">Da pagare entro fine mese</span>
      <span class="text-[40px] font-extrabold tracking-tight">{{ formatEuro(totale(entroFineMese)) }}</span>
      <span class="text-[15px]">{{ entroFineMese.length ? sottotitolo : 'Niente da pagare, per ora' }}</span>
    </section>

    <section class="flex flex-col gap-2.5">
      <h2 class="m-0 text-[17px] font-bold">Prossimi 30 giorni</h2>
      <div v-if="prossimi.length" v-auto-animate class="flex flex-col divide-y divide-riga rounded-2xl bg-white">
        <RigaScadenza v-for="s in prossimi" :key="s.id" :scadenza="s" />
      </div>
      <p v-else class="m-0 rounded-2xl bg-white p-4 text-secondario">Niente in scadenza nei prossimi 30 giorni</p>
    </section>

    <section v-if="posta.length" class="flex flex-col gap-2.5">
      <h2 class="m-0 text-[17px] font-bold">Arrivato per posta</h2>
      <RouterLink
        v-for="p in posta"
        :key="p.id"
        :to="{ path: '/documenti', query: { evidenzia: p.documentoId } }"
        class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3.5 text-current no-underline"
      >
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-attenzione-sfondo text-casa-scuro">
          <Icona nome="posta" :dimensione="20" />
        </span>
        <span class="flex flex-col gap-0.5">
          <span class="text-[15px] font-semibold">{{ p.mittente }} · {{ p.oggetto }}</span>
          <span class="text-[13px] text-secondario">{{ p.nota }}</span>
        </span>
      </RouterLink>
    </section>
  </div>
</template>
