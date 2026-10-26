<script setup lang="ts">
/**
 * Il layout delle pagine di accesso (Accedi, Iscriviti, Password dimenticata…).
 * Sul telefono la pagina è bianca, con il logo in alto e il pulsante in fondo.
 * Sul computer a sinistra c'è il pannello azzurro e a destra il modulo in una
 * carta, come nella tavola *Accedi*.
 */
import { Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import FmIcona from '@/components/fm/FmIcona.vue';
import FmIntestazione from '@/components/fm/FmIntestazione.vue';
import FmLogo from '@/components/fm/FmLogo.vue';

/** Cosa dice il pannello azzurro del computer. */
export type PannelloAccesso = {
    /** La frase grande. */
    titolo: string;
    /** Il testo sotto. */
    testo: string;
    /** I punti in fondo al pannello, con la spunta. */
    punti?: string[];
};

/** La riga in fondo che porta all'altra pagina («Hai già un account? Accedi»). */
export type AltraStrada = {
    /** La domanda. */
    testo: string;
    /** Il testo del link. */
    link: string;
    /** Dove porta. */
    href: string;
};

const {
    titolo = '',
    sottotitolo,
    pannello = {
        titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
        testo: '',
    },
    altraStrada,
} = defineProps<{
    /** Il titolo della pagina. Senza, la pagina scrive il suo (per esempio con un nome). */
    titolo?: string;
    /** La frase sotto il titolo. */
    sottotitolo?: string;
    /** Il pannello azzurro del computer. */
    pannello?: PannelloAccesso;
    /** La riga in fondo. */
    altraStrada?: AltraStrada;
}>();

defineSlots<{
    /** Il modulo della pagina, con il suo pulsante. */
    default: () => unknown;
}>();
</script>

<template>
    <div class="flex min-h-dvh bg-white text-testo lg:bg-sfondo">
        <aside
            class="hidden w-[42%] max-w-[600px] flex-col justify-between gap-5 bg-brand p-12 text-[#0B2540] lg:flex"
        >
            <div class="flex items-center gap-3 text-white">
                <FmLogo bianco :dimensione="44" />
                <span class="text-[22px] font-extrabold">Family Manager</span>
            </div>
            <div class="flex max-w-[420px] flex-col gap-3.5">
                <p class="m-0 text-[34px] leading-[1.15] font-extrabold">
                    {{ pannello.titolo }}
                </p>
                <p v-if="pannello.testo" class="m-0 text-[17px] leading-normal">
                    {{ pannello.testo }}
                </p>
            </div>
            <ul
                v-if="pannello.punti?.length"
                class="m-0 flex list-none flex-col gap-2.5 p-0 text-base"
            >
                <li
                    v-for="punto in pannello.punti"
                    :key="punto"
                    class="flex items-center gap-2.5"
                >
                    <FmIcona :icona="CircleCheck" :dimensione="22" />
                    {{ punto }}
                </li>
            </ul>
            <span v-else />
        </aside>

        <main
            class="flex grow items-stretch justify-center lg:items-center lg:p-12"
        >
            <div
                class="flex w-full max-w-[480px] flex-col gap-[18px] px-5 pt-8 pb-7 lg:rounded-3xl lg:bg-white lg:p-9"
            >
                <div class="flex items-center gap-2.5 lg:hidden">
                    <FmLogo :dimensione="36" />
                    <span class="text-[17px] font-extrabold"
                        >Family Manager</span
                    >
                </div>
                <FmIntestazione
                    v-if="titolo"
                    :titolo="titolo"
                    :sottotitolo="sottotitolo"
                />
                <div class="flex grow flex-col">
                    <slot />
                </div>
                <p
                    v-if="altraStrada"
                    class="m-0 text-center text-[15px] text-[#4A5565]"
                >
                    {{ altraStrada.testo }}
                    <Link
                        :href="altraStrada.href"
                        class="font-semibold text-azione hover:text-azione-scuro"
                        >{{ altraStrada.link }}</Link
                    >
                </p>
            </div>
        </main>
    </div>
</template>
