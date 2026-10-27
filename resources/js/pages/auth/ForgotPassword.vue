<script setup lang="ts">
/**
 * *Forgot password* page: the email is written and a link arrives to choose a
 * new one. After sending it says where to look and offers to send it again.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { errors } from '@/lib/errors';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: trans('Forgot password'),
        subtitle: trans(
            'Write your email: we will send you a link to choose a new one.',
        ),
        panel: {
            title: trans('It happens to everyone.'),
            text: trans(
                'We will send you a link: you choose the new password, and the family data stay as they are.',
            ),
        },
        otherWay: {
            text: trans('Remembered it?'),
            link: trans('Log in'),
            href: login().url,
        },
    },
});

const { status } = defineProps<{
    /** It is there when the link was sent. */
    status?: string;
}>();

const form = useForm({ email: '' });

/**
 * Asks for the link for the email written.
 */
function sendLink(): void {
    form.submit(email());
}
</script>

<template>
    <Head :title="$t('Forgot password')" />

    <form
        class="flex grow flex-col gap-3"
        novalidate
        @submit.prevent="sendLink"
    >
        <FormKit
            v-model="form.email"
            type="email"
            :label="$t('Email')"
            name="email"
            autocomplete="email"
            :errors="errors(form.errors.email)"
        />
        <FmNotice v-if="status" tone="ok">
            <strong>{{
                form.email
                    ? $t('We wrote to :email.', { email: form.email })
                    : $t('We wrote to you.')
            }}</strong>
            {{
                $t(
                    'The link is valid for one hour. If you cannot find it, look in the spam folder.',
                )
            }}
        </FmNotice>
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">
            {{ status ? $t('Send me the link again') : $t('Send me the link') }}
        </FmButton>
    </form>
</template>
