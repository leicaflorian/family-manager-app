<script setup lang="ts">
import { useRouter } from 'vue-router'
import Icona from './Icona.vue'

const props = defineProps<{ titolo?: string; indietro?: string; chiudi?: boolean }>()
const router = useRouter()

function torna() {
  if (props.indietro) router.push(props.indietro)
  else if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>

<template>
  <div class="flex h-11 items-center gap-2">
    <button
      type="button"
      :aria-label="props.chiudi ? 'Chiudi' : 'Indietro'"
      class="-ml-2.5 flex size-11 items-center justify-center text-current"
      @click="torna"
    >
      <Icona :nome="props.chiudi ? 'chiudi' : 'indietro'" />
    </button>
    <h1 v-if="props.titolo" class="m-0 text-lg font-bold">{{ props.titolo }}</h1>
  </div>
</template>
