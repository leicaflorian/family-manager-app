<script setup lang="ts">
/**
 * Il riquadro bianco delle pagine. Con `elenco` tiene delle righe (`FmRiga`)
 * separate da una linea, senza margini dentro perché le righe hanno i loro.
 */
const { titolo, elenco = false } = defineProps<{
    /** Il titolo della sezione, sopra il contenuto. */
    titolo?: string;
    /** Contiene un elenco di righe. */
    elenco?: boolean;
}>();
</script>

<template>
    <section class="flex flex-col gap-2">
        <h2 v-if="titolo" class="m-0 text-[17px] font-bold">{{ titolo }}</h2>
        <div
            data-riquadro
            class="flex flex-col overflow-hidden bg-white"
            :class="
                elenco
                    ? 'divide-y divide-riga rounded-2xl'
                    : 'gap-3.5 rounded-[20px] p-5'
            "
        >
            <slot />
        </div>
    </section>
</template>
