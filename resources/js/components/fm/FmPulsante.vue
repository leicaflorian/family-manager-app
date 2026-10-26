<script setup lang="ts">
/**
 * Il pulsante della tela, in cinque varianti. Un solo pulsante pieno per pagina:
 * quello che si fa più spesso. Con `href` diventa un link di Inertia.
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmIcona from './FmIcona.vue';

/** Le varianti della pagina *Componenti*. */
export type VariantePulsante =
    | 'pieno'
    | 'bordo'
    | 'tenue'
    | 'testo'
    | 'pericolo';

const {
    variante = 'pieno',
    tipo = 'button',
    href,
    disabled = false,
    icona,
    largo = false,
} = defineProps<{
    /** L'aspetto: pieno (blu), bordo, tenue (azzurro), testo, pericolo (rosso). */
    variante?: VariantePulsante;
    /** Il tipo del pulsante, quando non è un link. */
    tipo?: 'button' | 'submit';
    /** L'indirizzo: se c'è, il pulsante è un link. */
    href?: string;
    /** Disattivato: non si può toccare. */
    disabled?: boolean;
    /** L'icona davanti al testo. */
    icona?: LucideIcon;
    /** A tutta larghezza, come il pulsante principale sul telefono. */
    largo?: boolean;
}>();

defineEmits<{
    /** Il pulsante è stato toccato. */
    click: [evento: MouseEvent];
}>();

const stile: Record<VariantePulsante, string> = {
    pieno: 'h-14 rounded-2xl bg-azione px-6 text-[17px] font-bold text-white hover:bg-azione-scuro active:bg-azione-scuro',
    bordo: 'h-14 rounded-2xl border-2 border-azione bg-white px-6 text-[17px] font-bold text-azione hover:bg-azione-tenue',
    tenue: 'h-11 rounded-xl bg-azione-tenue px-4 text-[15px] font-semibold text-azione-scuro',
    testo: 'h-11 px-3 text-base font-semibold text-azione hover:text-azione-scuro',
    pericolo: 'h-11 px-3 text-base font-semibold text-scaduto',
};

const classi = computed((): string[] => [
    'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-azione',
    'disabled:cursor-not-allowed disabled:border-0 disabled:bg-bordo disabled:text-secondario',
    stile[variante],
    largo ? 'w-full' : '',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classi">
        <FmIcona v-if="icona" :icona="icona" :dimensione="20" />
        <slot />
    </Link>
    <button
        v-else
        :type="tipo"
        :disabled="disabled"
        :class="classi"
        @click="$emit('click', $event)"
    >
        <FmIcona v-if="icona" :icona="icona" :dimensione="20" />
        <slot />
    </button>
</template>
