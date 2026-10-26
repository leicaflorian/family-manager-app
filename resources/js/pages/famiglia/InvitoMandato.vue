<script setup lang="ts">
/**
 * Pagina *Invito mandato*: l'email è partita, e il link si può copiare per
 * mandarlo anche in un altro modo.
 */
import { Head } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import FmTitolo from '@/components/fm/FmTitolo.vue';
import { famiglia } from '@/routes';

const { invito } = defineProps<{
    /** L'invito appena mandato. */
    invito: App.Data.InvitoMandatoData;
}>();

const copiato = ref(false);

/**
 * Copia il link negli appunti.
 */
async function copia(): Promise<void> {
    await navigator.clipboard.writeText(invito.link);
    copiato.value = true;
}
</script>

<template>
    <Head title="Invito mandato" />

    <div class="flex max-w-[560px] flex-col gap-5">
        <FmTitolo titolo="Invito mandato" />
        <FmAvviso tono="ok">
            Abbiamo mandato l'email a {{ invito.email }}. Se non arriva, copia
            il link e mandalo tu, per esempio in un messaggio.
        </FmAvviso>
        <FormKit
            type="text"
            label="Il link per entrare"
            name="link"
            :value="invito.link"
            readonly
            :help="`Vale 7 giorni, fino al ${invito.scade}, e si usa una volta sola.`"
        />
        <div class="flex flex-wrap gap-2.5">
            <FmPulsante :icona="copiato ? Check : Copy" @click="copia">{{
                copiato ? 'Copiato' : 'Copia il link'
            }}</FmPulsante>
            <FmPulsante variante="testo" :href="famiglia().url"
                >Torna alla famiglia</FmPulsante
            >
        </div>
    </div>
</template>
