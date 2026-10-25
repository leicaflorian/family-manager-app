<script setup lang="ts">
import { createMessage, type FormKitNode } from '@formkit/core'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { completaPrimoAvvio } from '../stato'

// Il prototipo prova il percorso, non i dati: quello che si scrive qui non cambia la famiglia inventata
const router = useRouter()
const passo = ref(1)
const PASSI = 4

const persone = ref(['Marco', 'Laura', 'Giulia'])
const casa = ref({ indirizzo: 'via Roma 12, Bologna', proprieta: 'Di proprietà' })
const bollette = ref(['Luce', 'Gas', 'Acqua', 'Internet'])
const fornitori = ref<Record<string, string>>({ Luce: 'Edison', Gas: 'Enel', Acqua: 'Hera', Internet: 'Fastweb', 'Rifiuti (TARI)': 'Comune' })
const elencoFornitori: Record<string, string[]> = {
  Luce: ['Edison', 'Enel', 'Hera', 'A2A', 'Altro'],
  Gas: ['Enel', 'Eni Plenitude', 'Hera', 'Edison', 'Altro'],
  Acqua: ['Hera', 'Acea', 'Altro'],
  Internet: ['Fastweb', 'TIM', 'Vodafone', 'Iliad', 'Altro'],
  'Rifiuti (TARI)': ['Comune', 'Altro'],
}

const gruppi = ref<Record<number, FormKitNode | undefined>>({})
const titolo = computed(() => ['', 'Benvenuti', 'Chi siete?', 'La vostra casa', 'Le bollette di casa'][passo.value])

function avanti() {
  const gruppo = gruppi.value[passo.value]
  if (gruppo) {
    // Mostra gli errori dei campi del passo, come farebbe l'invio del form
    gruppo.walk((n) => n.store.set(createMessage({ key: 'submitted', value: true, visible: false })))
    if (!gruppo.context?.state.valid) return
  }
  passo.value = Math.min(PASSI, passo.value + 1)
}

function fatto() {
  // Invio da tastiera prima dell'ultimo passo: si va solo avanti
  if (passo.value < PASSI) return avanti()
  completaPrimoAvvio()
  router.push('/')
}
</script>

<template>
  <div class="flex min-h-dvh flex-col gap-6 bg-white px-5 pt-6 pb-7">
    <div v-if="passo > 1" class="flex flex-col gap-2.5">
      <span class="text-sm font-semibold text-secondario">Passo {{ passo }} di {{ PASSI }}</span>
      <div class="grid grid-cols-4 gap-1.5">
        <span v-for="n in PASSI" :key="n" class="h-1.5 rounded-full" :class="n <= passo ? 'bg-azione' : 'bg-riga'" />
      </div>
    </div>

    <FormKit type="form" :actions="false" :incomplete-message="false" form-class="flex grow flex-col" @submit="fatto">
      <!-- 1. Benvenuto -->
      <div v-show="passo === 1" class="flex grow flex-col items-center justify-center gap-5 text-center">
        <span class="flex size-24 items-center justify-center rounded-[28px] bg-brand text-white">
          <svg aria-hidden="true" width="60" height="60" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 30 32 10l24 20" />
            <circle cx="24" cy="34" r="4" /><circle cx="40" cy="34" r="4" /><circle cx="32" cy="42" r="3" />
            <path d="M16 54c0-6 4-10 8-10s8 4 8 10M32 54c0-6 4-10 8-10s8 4 8 10" />
          </svg>
        </span>
        <h1 class="m-0 text-[32px] font-extrabold">Family Manager</h1>
        <p class="m-0 max-w-70 text-lg text-[#4A5565]">Tutto ciò che riguarda la famiglia, in un posto solo.</p>
      </div>

      <!-- 2. Chi siete -->
      <FormKit v-show="passo === 2" type="group" name="persone" @node="(n: FormKitNode) => (gruppi[2] = n)">
        <div class="flex grow flex-col">
          <h1 class="m-0 mb-2 text-[28px] font-extrabold">{{ titolo }}</h1>
          <p class="m-0 mb-5 text-base text-[#4A5565]">Le persone della famiglia: ognuna avrà i suoi documenti e le sue scadenze.</p>
          <div v-auto-animate>
            <FormKit
              v-for="(_, i) in persone"
              :key="i"
              v-model="persone[i]"
              type="text"
              :name="`persona${i}`"
              :label="i === 0 ? 'Tu' : `Persona ${i + 1}`"
              validation="required"
            />
          </div>
          <button type="button" class="h-11 self-start border-0 bg-transparent p-0 text-base font-semibold text-azione" @click="persone.push('')">
            + Aggiungi una persona
          </button>
        </div>
      </FormKit>

      <!-- 3. La casa -->
      <FormKit v-show="passo === 3" type="group" name="casa" @node="(n: FormKitNode) => (gruppi[3] = n)">
        <div class="flex grow flex-col">
          <h1 class="m-0 mb-2 text-[28px] font-extrabold">{{ titolo }}</h1>
          <p class="m-0 mb-5 text-base text-[#4A5565]">Serve per le bollette e le spese di casa.</p>
          <FormKit v-model="casa.indirizzo" type="text" name="indirizzo" label="Indirizzo" validation="required" />
          <FormKit v-model="casa.proprieta" type="radio" name="proprieta" label="La casa è" :options="['Di proprietà', 'In affitto']" />
        </div>
      </FormKit>

      <!-- 4. Le bollette -->
      <div v-show="passo === 4" class="flex grow flex-col">
        <h1 class="m-0 mb-2 text-[28px] font-extrabold">{{ titolo }}</h1>
        <p class="m-0 mb-5 text-base text-[#4A5565]">Quali arrivano a {{ casa.indirizzo.split(',')[0] }}? Ti ricorderemo le scadenze e leggeremo noi gli importi.</p>
        <FormKit v-model="bollette" type="checkbox" name="bollette" :options="Object.keys(elencoFornitori)" />
        <div v-auto-animate class="grid grid-cols-2 gap-x-3">
          <FormKit
            v-for="b in bollette"
            :key="b"
            v-model="fornitori[b]"
            type="select"
            :name="`fornitore-${b}`"
            :label="`Fornitore ${b.toLowerCase()}`"
            :options="elencoFornitori[b]"
          />
        </div>
      </div>

      <div class="mt-auto flex flex-col gap-2 pt-4">
        <button
          v-if="passo < PASSI"
          type="button"
          class="h-14 rounded-2xl border-0 bg-azione text-[17px] font-bold text-white active:bg-azione-scuro"
          @click="avanti"
        >
          {{ passo === 1 ? 'Cominciamo' : 'Avanti' }}
        </button>
        <button v-else type="submit" class="h-14 rounded-2xl border-0 bg-azione text-[17px] font-bold text-white active:bg-azione-scuro">
          Fatto, entriamo
        </button>
        <button
          v-if="passo > 1"
          type="button"
          class="h-11 border-0 bg-transparent text-base font-semibold text-azione"
          @click="passo--"
        >
          Indietro
        </button>
      </div>
    </FormKit>
  </div>
</template>
