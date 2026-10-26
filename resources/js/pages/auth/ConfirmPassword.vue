<script setup lang="ts">
/**
 * Pagina *Conferma la password*: prima di una cosa delicata (per esempio
 * cambiare la password) si riscrive quella di adesso.
 */
import { Head, useForm } from '@inertiajs/vue3';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        titolo: 'Conferma la password',
        sottotitolo:
            'Per sicurezza, prima di andare avanti riscrivi la tua password.',
        pannello: {
            titolo: 'Solo un controllo.',
            testo: 'Lo chiediamo prima delle cose delicate.',
        },
    },
});

const form = useForm({ password: '' });

/**
 * Manda la password; si svuota comunque vada.
 */
function conferma(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Conferma la password" />

    <form
        class="flex grow flex-col gap-3"
        novalidate
        @submit.prevent="conferma"
    >
        <FmCampoPassword
            v-model="form.password"
            label="Password"
            name="password"
            :errors="errori(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Conferma</FmPulsante
        >
    </form>
</template>
