<script setup lang="ts">
/**
 * La Home, per ora vuota: le scadenze e gli ultimi documenti arrivano con la Home vera.
 */
import { Head, usePage } from '@inertiajs/vue3';
import { LayoutDashboard } from '@lucide/vue';
import { computed } from 'vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import FmVuoto from '@/components/fm/FmVuoto.vue';

const pagina = usePage();
const nome = computed(
    (): string => pagina.props.auth?.user?.name.split(' ')[0] ?? '',
);
</script>

<template>
    <Head title="Home" />

    <FmTitolo :titolo="`Ciao, ${nome}`" />
    <FmVuoto :icona="LayoutDashboard" titolo="La Home arriva presto" />
</template>
