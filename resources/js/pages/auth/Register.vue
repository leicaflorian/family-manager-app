<script setup lang="ts">
/**
 * Pagina *Iscriviti*: chi si iscrive crea la sua famiglia ed è il primo account,
 * con il livello *Tutto, come te*. Il cognome è anche il nome della famiglia.
 */
import { Head, useForm } from '@inertiajs/vue3';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        titolo: 'Iscrivi la tua famiglia',
        sottotitolo: 'Quattro domande e siete pronti.',
        pannello: {
            titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
            testo: 'Comincia da te: poi inviti chi vive con te.',
            punti: [
                'Le scadenze di tutta la famiglia, in un posto solo',
                'Le bollette lette da sole, anche per email',
                'I documenti di tutti, sempre a portata',
            ],
        },
        altraStrada: {
            testo: 'Hai già un account?',
            link: 'Accedi',
            href: login().url,
        },
    },
});

defineProps<{
    /** Le regole della password, per il suggerimento del browser. */
    passwordRules: string;
}>();

const form = useForm({
    nome: '',
    cognome: '',
    email: '',
    password: '',
    condizioni: false,
});

/**
 * Manda il modulo; la password si svuota comunque vada.
 */
function iscriviti(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Iscriviti" />

    <form
        class="flex grow flex-col gap-3"
        novalidate
        @submit.prevent="iscriviti"
    >
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="form.nome"
                type="text"
                label="Il tuo nome"
                name="nome"
                autocomplete="given-name"
                :errors="errori(form.errors.nome)"
            />
            <FormKit
                v-model="form.cognome"
                type="text"
                label="Cognome della famiglia"
                name="cognome"
                autocomplete="family-name"
                :errors="errori(form.errors.cognome)"
            />
        </div>
        <FormKit
            v-model="form.email"
            type="email"
            label="Email"
            name="email"
            autocomplete="email"
            :errors="errori(form.errors.email)"
        />
        <FmCampoPassword
            v-model="form.password"
            label="Scegli una password"
            name="password"
            help="Almeno 10 caratteri"
            autocomplete="new-password"
            :errors="errori(form.errors.password)"
        />
        <FormKit
            v-model="form.condizioni"
            type="checkbox"
            label="Ho letto e accetto le condizioni e l'informativa sulla privacy"
            name="condizioni"
            :errors="errori(form.errors.condizioni)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Comincia</FmPulsante
        >
    </form>
</template>
