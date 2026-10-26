<script setup lang="ts">
/**
 * Pagina *Conferma l'email*: dopo l'iscrizione arriva un'email con un link, che
 * conferma l'indirizzo. Da qui si può farla rimandare, o uscire.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        titolo: 'Conferma la tua email',
        sottotitolo:
            "Ti abbiamo mandato un'email con un link: aprilo e sei dentro.",
        pannello: {
            titolo: 'Ancora un passo.',
            testo: "Confermare l'email ci serve per mandarti i promemoria e, se la dimentichi, la password nuova.",
        },
    },
});

const { status } = defineProps<{
    /** `verification-link-sent` dopo un nuovo invio. */
    status?: string;
}>();

const form = useForm({});
</script>

<template>
    <Head title="Conferma l'email" />

    <form
        class="flex grow flex-col gap-3"
        @submit.prevent="form.submit(send())"
    >
        <FmAvviso v-if="status === 'verification-link-sent'" tono="ok">
            Ti abbiamo mandato un'email nuova. Se non la trovi, guarda nella
            posta indesiderata.
        </FmAvviso>
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Mandami di nuovo l'email</FmPulsante
        >
        <Link
            :href="logout()"
            as="button"
            class="mx-auto h-11 px-3 text-base font-semibold text-azione"
        >
            Esci
        </Link>
    </form>
</template>
