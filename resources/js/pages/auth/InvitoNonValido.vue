<script setup lang="ts">
/**
 * Pagina *Questo invito non vale più*: il link è scaduto, è già stato usato o
 * non è mai esistito. La pagina è la stessa nei tre casi.
 */
import { Head } from '@inertiajs/vue3';
import FmIntestazione from '@/components/fm/FmIntestazione.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        pannello: {
            titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
            testo: 'Gli inviti valgono 7 giorni e si usano una volta sola.',
        },
    },
});
</script>

<template>
    <Head title="Invito non valido" />

    <div class="flex grow flex-col gap-5">
        <FmIntestazione
            titolo="Questo invito non vale più"
            sottotitolo="Forse è scaduto, o è già stato usato. Chiedi a chi ti ha invitato di mandartene uno nuovo; se hai già un account, entra da Accedi."
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante :href="login().url" largo>Vai ad Accedi</FmPulsante>
    </div>
</template>
