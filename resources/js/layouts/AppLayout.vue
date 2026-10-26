<script setup lang="ts">
/**
 * Il layout delle pagine dell'app per chi è entrato: sotto i 1024 px la barra in
 * basso del telefono, dai 1024 px la barra laterale. La sezione segnata viene
 * dall'indirizzo della pagina.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmBarraLaterale from '@/components/fm/FmBarraLaterale.vue';
import FmBarraSotto from '@/components/fm/FmBarraSotto.vue';
import type { Sezione } from '@/components/fm/navigazione';
import { sezioneDi, vociDellaBarra } from '@/components/fm/navigazione';
import { Toaster } from '@/components/ui/sonner';

defineSlots<{
    /** La pagina. */
    default: () => unknown;
}>();

const pagina = usePage();
const voci = vociDellaBarra();

const attiva = computed((): Sezione =>
    sezioneDi(pagina.url.split('?')[0] ?? '/', voci),
);
const utente = computed((): string => pagina.props.auth?.user?.name ?? '');
const famiglia = computed((): string => pagina.props.famiglia?.name ?? '');
</script>

<template>
    <div class="flex min-h-dvh bg-sfondo text-testo">
        <FmBarraLaterale
            class="sticky top-0 hidden h-dvh lg:flex"
            :attiva="attiva"
            :voci="voci"
            :famiglia="famiglia"
            :utente="utente"
        />
        <div class="flex min-w-0 grow flex-col">
            <main class="grow px-5 pt-4 pb-6 lg:px-10 lg:pt-8 lg:pb-12">
                <div
                    class="mx-auto flex max-w-[1120px] flex-col gap-4 lg:gap-6"
                >
                    <slot />
                </div>
            </main>
            <div class="sticky bottom-0 z-30 lg:hidden">
                <FmBarraSotto :attiva="attiva" :voci="voci" />
            </div>
        </div>
        <Toaster />
    </div>
</template>
