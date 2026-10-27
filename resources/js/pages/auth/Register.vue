<script setup lang="ts">
/**
 * *Sign up* page: who signs up creates their family and is the first account,
 * with the level *Everything, like you*. The surname is also the name of the
 * family.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import { errors } from '@/lib/errors';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: trans('Sign up your family'),
        subtitle: trans('Four questions and you are ready.'),
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                'Start with yourself: then invite the people you live with.',
            ),
            points: [
                trans('The deadlines of the whole family, in one place'),
                trans('Bills read on their own, by email too'),
                trans("Everyone's documents, always at hand"),
            ],
        },
        otherWay: {
            text: trans('Already have an account?'),
            link: trans('Log in'),
            href: login().url,
        },
    },
});

defineProps<{
    /** The password rules, for the hint of the browser. */
    passwordRules: string;
}>();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    terms: false,
});

/**
 * Sends the form; the password is emptied however it goes.
 */
function signUp(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="$t('Sign up')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="signUp">
        <div class="grid grid-cols-2 gap-3">
            <FormKit
                v-model="form.first_name"
                type="text"
                :label="$t('Your first name')"
                name="first_name"
                autocomplete="given-name"
                :errors="errors(form.errors.first_name)"
            />
            <FormKit
                v-model="form.last_name"
                type="text"
                :label="$t('Family surname')"
                name="last_name"
                autocomplete="family-name"
                :errors="errors(form.errors.last_name)"
            />
        </div>
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
            :label="$t('Choose a password')"
            name="password"
            :help="$t('At least 10 characters')"
            autocomplete="new-password"
            :errors="errors(form.errors.password)"
        />
        <FormKit
            v-model="form.terms"
            type="checkbox"
            :label="
                $t('I have read and accept the terms and the privacy policy')
            "
            name="terms"
            :errors="errors(form.errors.terms)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Get started')
        }}</FmButton>
    </form>
</template>
