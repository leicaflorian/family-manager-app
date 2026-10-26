<script setup lang="ts">
/**
 * Pagina *Password dimenticata*: si scrive l'email e arriva un link per sceglierne
 * una nuova. Dopo l'invio dice dove guardare e offre di rimandarlo.
 */
import { Head, useForm } from '@inertiajs/vue3';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        titolo: 'Password dimenticata',
        sottotitolo:
            'Scrivi la tua email: ti mandiamo un link per sceglierne una nuova.',
        pannello: {
            titolo: 'Succede a tutti.',
            testo: 'Ti mandiamo un link: la password nuova la scegli tu, e i dati della famiglia restano come sono.',
        },
        altraStrada: {
            testo: 'Te la sei ricordata?',
            link: 'Accedi',
            href: login().url,
        },
    },
});

const { status } = defineProps<{
    /** C'è quando il link è partito. */
    status?: string;
}>();

const form = useForm({ email: '' });

/**
 * Chiede il link per l'email scritta.
 */
function mandaLink(): void {
    form.submit(email());
}
</script>

<template>
    <Head title="Password dimenticata" />

    <form
        class="flex grow flex-col gap-3"
        novalidate
        @submit.prevent="mandaLink"
    >
        <FormKit
            v-model="form.email"
            type="email"
            label="Email"
            name="email"
            autocomplete="email"
            :errors="errori(form.errors.email)"
        />
        <FmAvviso v-if="status" tono="ok">
            <strong
                >Ti abbiamo scritto{{
                    form.email ? ` a ${form.email}` : ''
                }}.</strong
            >
            Il link vale un'ora. Se non lo trovi, guarda nella posta
            indesiderata.
        </FmAvviso>
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing">
            {{ status ? 'Mandami di nuovo il link' : 'Mandami il link' }}
        </FmPulsante>
    </form>
</template>
