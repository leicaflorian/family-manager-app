<script setup lang="ts">
import { computed } from 'vue'
import { coloreArea, maiuscola } from '../colori'
import type { Scadenza } from '../data/tipi'
import { formatData, formatEuro, quando } from '../logic/formato'
import { oggi, useFamiglia } from '../stato'

const props = defineProps<{ scadenza: Scadenza }>()
const famiglia = useFamiglia()

const scheda = computed(() => famiglia.schede.find((s) => s.id === props.scadenza.schedaId))
const scaduta = computed(() => !props.scadenza.pagata && props.scadenza.data < oggi())
const nota = computed(() =>
  scaduta.value
    ? `${maiuscola(quando(props.scadenza.data, oggi()))} · ${formatData(props.scadenza.data)}`
    : `${scheda.value?.titolo ?? ''} · ${quando(props.scadenza.data, oggi())}`,
)
</script>

<template>
  <RouterLink :to="`/scadenza/${props.scadenza.id}`" class="flex items-center gap-3 px-4 py-3.5 text-current no-underline">
    <span class="size-2.5 shrink-0 rounded-full" :style="{ background: scheda ? coloreArea(scheda.area) : undefined }" />
    <span class="flex grow flex-col gap-0.5">
      <span class="text-base font-semibold">{{ props.scadenza.titolo }}</span>
      <span class="text-[13px]" :class="scaduta ? 'font-semibold text-scaduto' : 'text-secondario'">{{ nota }}</span>
    </span>
    <span v-if="props.scadenza.importo !== null" class="text-base font-bold whitespace-nowrap">{{ formatEuro(props.scadenza.importo) }}</span>
  </RouterLink>
</template>
