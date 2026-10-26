<script setup lang="ts">
/**
 * Il pannello che sale dal basso sopra la pagina scurita, per le azioni brevi e
 * i filtri. Sul computer è una finestra al centro. Si chiude con la maniglia, con
 * la ×, con Esc e toccando fuori; finché è aperto il focus resta dentro.
 */
import { X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import FmIcona from './FmIcona.vue';

const { titolo } = defineProps<{
    /** Il titolo del pannello. */
    titolo: string;
}>();

/** Aperto o chiuso. */
const aperto = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** Il contenuto del pannello. */
    default: () => unknown;
}>();
</script>

<template>
    <DialogRoot v-model:open="aperto">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-40 bg-[rgba(24,33,47,0.45)]"
            />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[90dvh] flex-col gap-3.5 overflow-y-auto rounded-t-3xl bg-white px-5 pt-2.5 pb-[max(20px,env(safe-area-inset-bottom))] text-testo outline-none lg:inset-x-auto lg:top-1/2 lg:bottom-auto lg:left-1/2 lg:w-full lg:max-w-[560px] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-3xl lg:p-7 lg:shadow-[0_24px_60px_rgba(24,33,47,0.25)]"
            >
                <DialogClose
                    aria-label="Chiudi il pannello"
                    tabindex="-1"
                    class="mx-auto flex h-5 w-16 items-center justify-center lg:hidden"
                >
                    <span class="h-[5px] w-10 rounded-full bg-bordo" />
                </DialogClose>
                <div class="flex items-center justify-between gap-3">
                    <DialogTitle
                        class="m-0 text-[19px] font-extrabold lg:text-[22px]"
                        >{{ titolo }}</DialogTitle
                    >
                    <DialogClose
                        aria-label="Chiudi"
                        class="-mr-2.5 flex size-11 shrink-0 items-center justify-center text-secondario"
                    >
                        <FmIcona :icona="X" :dimensione="22" />
                    </DialogClose>
                </div>
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
