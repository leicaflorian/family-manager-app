<script setup lang="ts">
/**
 * *Confirm the password* page: before something delicate (for example changing
 * the password) the current one is written again.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import { errors } from '@/lib/errors';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: trans('Confirm the password'),
        subtitle: trans(
            'For safety, write your password again before going on.',
        ),
        panel: {
            title: trans('Just a check.'),
            text: trans('We ask for it before the delicate things.'),
        },
    },
});

const form = useForm({ password: '' });

/**
 * Sends the password; it is emptied however it goes.
 */
function confirm(): void {
    form.submit(store(), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="$t('Confirm the password')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="confirm">
        <FmPasswordField
            v-model="form.password"
            :label="$t('Password')"
            name="password"
            :errors="errors(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Confirm')
        }}</FmButton>
    </form>
</template>
