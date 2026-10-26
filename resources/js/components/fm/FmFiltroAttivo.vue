<script setup lang="ts">
/**
 * Un filtro scelto, sopra un elenco: si toglie con la ×.
 */
import { X } from '@lucide/vue';
import FmIcona from './FmIcona.vue';

const { etichetta } = defineProps<{
    /** Il filtro, per esempio «Laura» o «Lavoro e redditi». */
    etichetta: string;
}>();

defineEmits<{
    /** Il filtro va tolto. */
    togli: [];
}>();
</script>

<template>
    <button
        type="button"
        :aria-label="`Togli il filtro ${etichetta}`"
        class="inline-flex h-9 items-center gap-1.5 rounded-full bg-azione-tenue py-0 pr-2.5 pl-3.5 text-sm font-semibold text-azione-scuro"
        @click="$emit('togli')"
    >
        <span>{{ etichetta }}</span>
        <FmIcona :icona="X" :dimensione="16" />
    </button>
</template>
