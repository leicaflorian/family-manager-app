<script setup lang="ts">
/**
 * Pagina *Nuova password*: si arriva dal link dell'email e si sceglie la password nuova.
 */
import { Head, useForm } from '@inertiajs/vue3';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        titolo: 'Scegli la password nuova',
        sottotitolo: 'Dopo entri con questa.',
        pannello: {
            titolo: 'Succede a tutti.',
            testo: 'La password nuova la scegli tu, e i dati della famiglia restano come sono.',
        },
    },
});

const props = defineProps<{
    /** Il codice del link dell'email. */
    token: string;
    /** L'email dell'account. */
    email: string;
    /** Le regole della password, per il suggerimento del browser. */
    passwordRules: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

/**
 * Salva la password nuova. Il modulo la chiede una volta sola: la conferma che
 * vuole Fortify è la stessa.
 */
function salva(): void {
    form.transform((dati) => ({
        ...dati,
        password_confirmation: dati.password,
    })).submit(update(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Nuova password" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="salva">
        <FormKit
            v-model="form.email"
            type="email"
            label="Email"
            name="email"
            readonly
            :errors="errori(form.errors.email)"
        />
        <FmCampoPassword
            v-model="form.password"
            label="Scegli una password nuova"
            name="password"
            help="Almeno 10 caratteri"
            autocomplete="new-password"
            :errors="errori(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Salva la password</FmPulsante
        >
    </form>
</template>
