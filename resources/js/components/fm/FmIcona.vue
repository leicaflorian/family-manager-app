<script setup lang="ts">
/**
 * Un'icona di Lucide con le misure della tela: tratto 2, arrotondata, 24 px.
 * Senza etichetta è solo decorazione e chi usa un lettore di schermo non la sente.
 */
import type { LucideIcon } from '@lucide/vue';

const {
    dimensione = 24,
    spessore = 2,
    etichetta,
} = defineProps<{
    /** L'icona, importata da `@lucide/vue`. */
    icona: LucideIcon;
    /** Il lato in pixel. */
    dimensione?: number;
    /** Lo spessore del tratto. */
    spessore?: number;
    /** Cosa dice l'icona, quando non c'è un testo accanto. */
    etichetta?: string;
}>();
</script>

<template>
    <component
        :is="icona"
        :size="dimensione"
        :stroke-width="spessore"
        :aria-hidden="etichetta ? undefined : 'true'"
        :role="etichetta ? 'img' : undefined"
        :aria-label="etichetta"
        class="shrink-0"
    />
</template>
