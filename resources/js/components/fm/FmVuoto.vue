<script setup lang="ts">
/**
 * Il riquadro di una pagina o di un elenco ancora vuoto: un'icona, cosa manca e,
 * sotto, cosa si può fare.
 */
import type { LucideIcon } from '@lucide/vue';
import FmIcona from './FmIcona.vue';

const { icona, titolo, testo } = defineProps<{
    /** L'icona in alto. */
    icona: LucideIcon;
    /** Cosa manca. */
    titolo: string;
    /** Il perché, o cosa succederà. */
    testo?: string;
}>();

defineSlots<{
    /** I pulsanti. */
    default?: () => unknown;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-[20px] bg-white px-6 py-10 text-center"
    >
        <span
            class="flex size-14 items-center justify-center rounded-2xl bg-azione-tenue text-azione-scuro"
        >
            <FmIcona :icona="icona" :dimensione="28" />
        </span>
        <h2 class="m-0 text-[19px] font-extrabold">{{ titolo }}</h2>
        <p
            v-if="testo"
            class="m-0 max-w-[420px] text-base leading-normal text-secondario"
        >
            {{ testo }}
        </p>
        <div
            v-if="$slots.default"
            class="mt-2 flex flex-wrap justify-center gap-2.5"
        >
            <slot />
        </div>
    </div>
</template>
