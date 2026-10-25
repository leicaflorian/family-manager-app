<script setup lang="ts">
import { computed, ref } from 'vue'
import { coloreAreaScuro } from '../colori'
import Icona from '../components/Icona.vue'
import { AREE, attenzioneArea, conteggioArea, nomeArea } from '../logic/aree'
import { oggi, useFamiglia } from '../stato'

const famiglia = useFamiglia()
const cerca = ref('')

const riquadri = computed(() =>
  AREE.map((a) => {
    const attenzione = attenzioneArea(famiglia, a.id, oggi())
    return {
      ...a,
      conteggio: conteggioArea(famiglia, a.id),
      attenzione,
      urgente: !!attenzione?.includes('scadut'),
    }
  }),
)

const normalizza = (t: string) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
const trovate = computed(() => {
  const c = normalizza(cerca.value)
  if (!c) return []
  return famiglia.schede.filter((s) => normalizza(`${s.titolo} ${s.sottotitolo}`).includes(c))
})
</script>

<template>
  <div class="flex flex-col gap-3.5 px-4 pt-6">
    <h1 class="mx-1 my-0 text-[28px] font-extrabold">Schede</h1>
    <FormKit
      v-model="cerca"
      type="search"
      placeholder="Cerca in tutte le schede"
      aria-label="Cerca in tutte le schede"
      :classes="{ outer: 'mb-0' }"
    />

    <div v-if="cerca.trim()" v-auto-animate class="flex flex-col divide-y divide-riga rounded-2xl bg-white">
      <RouterLink v-for="s in trovate" :key="s.id" :to="`/scheda/${s.id}`" class="flex flex-col gap-0.5 px-4 py-3.5 text-current no-underline">
        <span class="font-semibold">{{ s.titolo }}</span>
        <span class="text-[13px] text-secondario">{{ nomeArea(s.area) }} · {{ s.sottotitolo }}</span>
      </RouterLink>
      <p v-if="!trovate.length" class="m-0 p-4 text-secondario">Nessuna scheda per «{{ cerca.trim() }}»</p>
    </div>

    <div v-else class="grid grid-cols-2 gap-2.5">
      <RouterLink
        v-for="r in riquadri"
        :key="r.id"
        :to="`/schede/${r.id}`"
        class="flex min-h-28 flex-col gap-1.5 rounded-2xl bg-white p-3.5 text-current no-underline"
      >
        <span
          class="flex size-9 items-center justify-center rounded-[10px]"
          :style="{ color: coloreAreaScuro(r.id), background: `color-mix(in srgb, var(--color-${r.id}) 14%, white)` }"
        >
          <Icona :nome="r.id" :dimensione="20" />
        </span>
        <span class="text-[15px] leading-tight font-bold">{{ r.nome }}</span>
        <span class="text-[13px] text-secondario">{{ r.conteggio }}</span>
        <span
          v-if="r.attenzione"
          class="self-start rounded-full px-2 py-0.5 text-xs font-bold"
          :class="r.urgente ? 'bg-[#FDECEF] text-scaduto' : 'bg-attenzione-sfondo text-attenzione'"
        >{{ r.attenzione }}</span>
      </RouterLink>
    </div>
  </div>
</template>
