<script setup lang="ts">
import { useFamiglia } from '../stato'

const famiglia = useFamiglia()
const voci = ['Impostazioni', 'Avvisi e promemoria', 'Indirizzo per le bollette', 'Esci']
</script>

<template>
  <div class="flex flex-col gap-5 px-5 pt-6">
    <h1 class="m-0 text-[28px] font-extrabold">Account</h1>
    <section class="flex items-center gap-4 rounded-2xl bg-white p-4">
      <span class="flex size-13 items-center justify-center rounded-full bg-[#DCEBFF] text-lg font-bold text-azione">MB</span>
      <span class="flex flex-col">
        <span class="text-lg font-bold">Famiglia {{ famiglia.nome }}</span>
        <span class="text-sm text-secondario">{{ famiglia.persone.map((p) => p.nome).join(', ') }}</span>
      </span>
    </section>
    <ul class="m-0 flex list-none flex-col rounded-2xl bg-white p-0">
      <li v-for="(v, i) in voci" :key="v" class="flex min-h-13 items-center px-4 text-base" :class="i < voci.length - 1 ? 'border-b border-riga' : ''">
        <span class="grow">{{ v }}</span>
        <span class="text-xs text-secondario">Non nel prototipo</span>
      </li>
    </ul>
  </div>
</template>
