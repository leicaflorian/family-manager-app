<script setup lang="ts">
/**
 * Pagina *Accedi*: email, password e *Resta collegato* (il `remember` di Fortify),
 * con i link a *Password dimenticata* e a *Iscriviti*.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import FmAvviso from '@/components/fm/FmAvviso.vue';
import FmCampoPassword from '@/components/fm/FmCampoPassword.vue';
import FmPulsante from '@/components/fm/FmPulsante.vue';
import { errori } from '@/lib/errori';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        titolo: 'Bentornato',
        sottotitolo: 'Entra nella tua famiglia.',
        pannello: {
            titolo: 'Tutto ciò che riguarda la famiglia, in un posto solo.',
            testo: 'Le scadenze, le bollette e i documenti di tutti, con chi vive con te.',
        },
        altraStrada: {
            testo: 'Non hai ancora Family Manager?',
            link: 'Iscrivi la tua famiglia',
            href: register().url,
        },
    },
});

const { canResetPassword, status } = defineProps<{
    /** Messaggio del server, per esempio dopo il cambio della password. */
    status?: string;
    /** Si può chiedere il link per una password nuova. */
    canResetPassword: boolean;
}>();

const form = useForm({ email: '', password: '', remember: true });

/**
 * Manda email e password; la password si svuota comunque vada.
 */
function accedi(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Accedi" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="accedi">
        <FmAvviso v-if="status" tono="ok">{{ status }}</FmAvviso>
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
            label="Password"
            name="password"
            :errors="errori(form.errors.password)"
        />
        <div class="flex items-center justify-between gap-2.5">
            <FormKit
                v-model="form.remember"
                type="checkbox"
                label="Resta collegato"
                name="remember"
                :classes="{ outer: 'mb-0' }"
            />
            <Link
                v-if="canResetPassword"
                :href="request()"
                class="text-[15px] font-semibold text-azione"
            >
                Password dimenticata?
            </Link>
        </div>
        <span class="min-h-6 grow lg:hidden" />
        <FmPulsante tipo="submit" largo :disabled="form.processing"
            >Accedi</FmPulsante
        >
    </form>
</template>
