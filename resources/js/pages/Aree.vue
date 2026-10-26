<script setup lang="ts">
/**
 * Pagina *Aree*: le schede (casa, veicoli, polizze…) arrivano con la voce delle
 * schede. Intanto la pagina lo dice e porta ai documenti.
 */
import { Head } from '@inertiajs/vue3';
import { Shapes } from '@lucide/vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import FmVuoto from '@/components/fm/FmVuoto.vue';
import { index as documenti } from '@/routes/documenti';
</script>

<template>
    <Head title="Aree" />

    <FmTitolo titolo="Aree" />
    <FmVuoto
        :icona="Shapes"
        titolo="Le schede arrivano presto"
        testo="Qui troverai la casa, i veicoli, le polizze e le altre cose della famiglia, ognuna con i suoi documenti."
    >
        <FmPulsante variante="bordo" :href="documenti().url"
            >Vai ai documenti</FmPulsante
        >
    </FmVuoto>
</template>
