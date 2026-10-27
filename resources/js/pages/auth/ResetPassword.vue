<script setup lang="ts">
/**
 * *New password* page: it is reached from the link of the email and the new
 * password is chosen.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import { errors } from '@/lib/errors';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: trans('Choose the new password'),
        subtitle: trans('Then you log in with this one.'),
        panel: {
            title: trans('It happens to everyone.'),
            text: trans(
                'You choose the new password, and the family data stay as they are.',
            ),
        },
    },
});

const props = defineProps<{
    /** The code of the link of the email. */
    token: string;
    /** The email of the account. */
    email: string;
    /** The password rules, for the hint of the browser. */
    passwordRules: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

/**
 * Saves the new password. The form asks for it only once: the confirmation
 * Fortify wants is the same.
 */
function save(): void {
    form.transform((data) => ({
        ...data,
        password_confirmation: data.password,
    })).submit(update(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="$t('New password')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="save">
        <FormKit
            v-model="form.email"
            type="email"
            :label="$t('Email')"
            name="email"
            readonly
            :errors="errors(form.errors.email)"
        />
        <FmPasswordField
            v-model="form.password"
            :label="$t('Choose a new password')"
            name="password"
            :help="$t('At least 10 characters')"
            autocomplete="new-password"
            :errors="errors(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Save the password')
        }}</FmButton>
    </form>
</template>
