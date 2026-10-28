<script setup lang="ts">
/**
 * *Log in* page: email, password and *Stay signed in* (the `remember` of
 * Fortify), with the links to *Forgot password* and to *Sign up*.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import { errors } from '@/lib/errors';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: trans('Welcome back'),
        subtitle: trans('Enter your family.'),
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                "Everyone's deadlines, bills and documents, with the people you live with.",
            ),
        },
        otherWay: {
            text: trans('No Family Manager yet?'),
            link: trans('Sign up your family'),
            href: register().url,
        },
    },
});

const { canResetPassword, status } = defineProps<{
    /** Message of the server, for example after the password was changed. */
    status?: string;
    /** The link for a new password can be asked for. */
    canResetPassword: boolean;
}>();

const form = useForm({ email: '', password: '', remember: true });

/**
 * Sends email and password; the password is emptied however it goes.
 */
function logIn(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="$t('Log in')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="logIn">
        <FmNotice v-if="status" tone="ok">{{ status }}</FmNotice>
        <FormKit
            v-model="form.email"
            type="email"
            :label="$t('Email')"
            name="email"
            autocomplete="email"
            :errors="errors(form.errors.email)"
        />
        <FmPasswordField
            v-model="form.password"
            :label="$t('Password')"
            name="password"
            :errors="errors(form.errors.password)"
        />
        <div class="flex items-center justify-between gap-2.5">
            <FormKit
                v-model="form.remember"
                type="toggle"
                :label="$t('Stay signed in')"
                name="remember"
                :classes="{ outer: 'mb-0' }"
            />
            <Link
                v-if="canResetPassword"
                :href="request()"
                class="text-[15px] font-semibold text-action"
            >
                {{ $t('Forgot your password?') }}
            </Link>
        </div>
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Log in')
        }}</FmButton>
    </form>
</template>
