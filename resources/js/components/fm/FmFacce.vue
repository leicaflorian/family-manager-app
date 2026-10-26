<script setup lang="ts">
/**
 * Le facce della famiglia, una sopra l'altra: i cerchi con le iniziali, come
 * nella tavola *Invito accettato*.
 */
import { computed } from 'vue';
import { elencoNomi } from '@/lib/nomi';

const { persone, dimensione = 48 } = defineProps<{
    /** Le persone da mostrare. */
    persone: App.Data.PersonaBreveData[];
    /** Il lato di ogni cerchio, in pixel. */
    dimensione?: number;
}>();

/** I colori dei cerchi, a giro. */
const colori = ['bg-azione', 'bg-pagato', 'bg-attenzione', 'bg-testo'];

const nomi = computed(() => elencoNomi(persone.map((p) => p.first_name)));
</script>

<template>
    <ul :aria-label="nomi" class="m-0 flex list-none p-0">
        <li
            v-for="(persona, i) in persone"
            :key="persona.id"
            aria-hidden="true"
            class="flex items-center justify-center rounded-full font-bold text-white shadow-[0_0_0_3px_#fff] not-first:-ml-2.5"
            :class="colori[i % colori.length]"
            :style="{
                width: `${dimensione}px`,
                height: `${dimensione}px`,
                fontSize: `${Math.round(dimensione * 0.36)}px`,
            }"
        >
            {{ persona.initials }}
        </li>
    </ul>
</template>
