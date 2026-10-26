<script setup lang="ts">
/**
 * La pagina di una sezione che non c'è ancora: le voci del menu portano già lì,
 * e la pagina vera arriva con il suo task.
 */
import { Head } from '@inertiajs/vue3';
import { Hammer } from '@lucide/vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import FmVuoto from '@/components/fm/FmVuoto.vue';

const { titolo } = defineProps<{
    /** Il nome della sezione. */
    titolo: string;
}>();
</script>

<template>
    <Head :title="titolo" />

    <FmTitolo :titolo="titolo" />
    <FmVuoto :icona="Hammer" titolo="Questa pagina arriva presto" />
</template>
