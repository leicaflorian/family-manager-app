<script setup lang="ts">
/**
 * Un messaggio dentro la pagina, con la sua icona: un'informazione, una cosa
 * fatta (verde), una cosa che chiede attenzione (arancio).
 */
import type { LucideIcon } from '@lucide/vue';
import { CircleAlert, Info, MailCheck } from '@lucide/vue';
import FmIcona from './FmIcona.vue';

/** Il tono del messaggio. */
export type TonoAvviso = 'info' | 'ok' | 'attenzione';

const { tono = 'info', icona } = defineProps<{
    /** Informazione, fatto o attenzione. */
    tono?: TonoAvviso;
    /** Un'icona diversa da quella del tono. */
    icona?: LucideIcon;
}>();

defineSlots<{
    /** Il testo del messaggio. */
    default: () => unknown;
}>();

const stile: Record<TonoAvviso, { classi: string; icona: LucideIcon }> = {
    info: {
        classi: 'bg-sfondo text-[#4A5565] [&>svg]:text-azione',
        icona: Info,
    },
    ok: { classi: 'bg-[#E6F4EE] text-[#14533F]', icona: MailCheck },
    attenzione: {
        classi: 'bg-attenzione-sfondo text-attenzione',
        icona: CircleAlert,
    },
};
</script>

<template>
    <div
        role="status"
        class="flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm leading-[1.45]"
        :class="stile[tono].classi"
    >
        <FmIcona
            :icona="icona ?? stile[tono].icona"
            :dimensione="20"
            class="mt-px"
        />
        <span><slot /></span>
    </div>
</template>
