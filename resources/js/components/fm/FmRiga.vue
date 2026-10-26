<script setup lang="ts">
/**
 * Una riga d'elenco della tela: davanti il pallino dell'area o un'icona, poi il
 * titolo e il sottotitolo, a destra un importo o una data. Con `href` tutta la
 * riga è un link, e senza niente a destra mostra la freccia.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Area } from '@/lib/aree';
import { sfondoArea } from '@/lib/aree';
import FmIcona from './FmIcona.vue';

/** Il colore del sottotitolo: normale, rosso se scaduto, arancio se serve attenzione. */
export type TonoRiga = 'normale' | 'scaduto' | 'attenzione' | 'pagato';

const {
    titolo,
    sottotitolo,
    href,
    area,
    tono = 'normale',
} = defineProps<{
    /** Il testo principale. */
    titolo: string;
    /** La seconda riga: quando, o perché serve attenzione. */
    sottotitolo?: string;
    /** L'indirizzo che apre la riga. */
    href?: string;
    /** L'area, per il pallino colorato davanti. */
    area?: Area;
    /** Il colore del sottotitolo. */
    tono?: TonoRiga;
}>();

defineSlots<{
    /** Al posto del pallino: un'icona, un avatar, la miniatura di un documento. */
    prima?: () => unknown;
    /** A destra: un importo, una data. */
    destra?: () => unknown;
}>();

const coloreTono: Record<TonoRiga, string> = {
    normale: 'text-secondario',
    scaduto: 'font-semibold text-scaduto',
    attenzione: 'font-semibold text-attenzione',
    pagato: 'font-semibold text-pagato',
};
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex items-center gap-3 px-4 py-3.5 text-inherit no-underline"
        :class="
            href
                ? 'hover:bg-sfondo focus-visible:bg-sfondo focus-visible:outline-none'
                : ''
        "
    >
        <slot name="prima">
            <span
                v-if="area"
                data-pallino
                class="size-2.5 shrink-0 rounded-full"
                :class="sfondoArea[area]"
            />
        </slot>
        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span class="text-base font-semibold text-testo">{{ titolo }}</span>
            <span
                v-if="sottotitolo"
                class="text-[13px]"
                :class="coloreTono[tono]"
                >{{ sottotitolo }}</span
            >
        </span>
        <span
            v-if="$slots.destra"
            class="shrink-0 text-base font-bold whitespace-nowrap text-testo"
        >
            <slot name="destra" />
        </span>
        <FmIcona
            v-else-if="href"
            :icona="ChevronRight"
            :dimensione="20"
            class="text-[#9AA6B6]"
        />
    </component>
</template>
