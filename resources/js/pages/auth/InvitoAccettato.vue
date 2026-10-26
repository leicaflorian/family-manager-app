<script setup lang="ts">
/**
 * Pagina *Invito accettato*: chi riceve l'invito vede le facce della famiglia,
 * sceglie la password ed entra. L'email è quella dell'invito, già confermata.
 */
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmFacce from '@/components/fm/FmFacce.vue';
import FmIntestazione from '@/components/fm/FmIntestazione.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { elencoNomi } from '@/lib/nomi';
import { store } from '@/routes/invito';

const { token, invito } = defineProps<{
    /** Il codice del link. */
    token: string;
    /** L'invito, come lo legge chi lo riceve. */
    invito: App.Data.InvitoData;
}>();

setLayoutProps({
    pannello: {
        titolo: `Benvenuti nella famiglia ${invito.famiglia}.`,
        testo: `${elencoNomi(invito.persone.map((p) => p.first_name))} usano già Family Manager per le scadenze, le bollette e i documenti di casa.`,
    },
});

const form = useForm({ password: '' });

/**
 * Crea l'account ed entra; la password si svuota comunque vada.
 */
function entra(): void {
    form.submit(store(token), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Entra nella famiglia" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="entra">
        <FmFacce :persone="invito.persone" />
        <FmIntestazione
            :titolo="`Ciao, ${invito.nome}`"
            :sottotitolo="`${invito.chiInvita} ti ha invitato nella famiglia ${invito.famiglia}. Vedrai le scadenze e i documenti di tutti, e potrai caricarne di nuovi.`"
        />
        <FormKit
            type="email"
            label="La tua email"
            name="email"
            :value="invito.email"
            disabled
        />
        <FmCampoPassword
            v-model="form.password"
            label="Scegli una password"
            name="password"
            help="Almeno 10 caratteri"
            autocomplete="new-password"
            :errors="errori(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Entra nella famiglia</FmPulsante
        >
        <p class="m-0 text-center text-[13px] text-secondario">
            L'invito vale fino al {{ invito.scade }}
        </p>
    </form>
</template>
