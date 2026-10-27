<script setup lang="ts">
/**
 * *Confirm the email* page: after signing up an email arrives with a link, which
 * confirms the address. From here it can be sent again, or one can log out.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: trans('Confirm your email'),
        subtitle: trans(
            'We sent you an email with a link: open it and you are in.',
        ),
        panel: {
            title: trans('One more step.'),
            text: trans(
                'We need a confirmed email to send you the reminders and, if you forget it, the new password.',
            ),
        },
    },
});

const { status } = defineProps<{
    /** `verification-link-sent` after a new sending. */
    status?: string;
}>();

const form = useForm({});
</script>

<template>
    <Head :title="$t('Confirm the email')" />

    <form
        class="flex grow flex-col gap-3"
        @submit.prevent="form.submit(send())"
    >
        <FmNotice v-if="status === 'verification-link-sent'" tone="ok">
            {{
                $t(
                    'We sent you a new email. If you cannot find it, look in the spam folder.',
                )
            }}
        </FmNotice>
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Send me the email again')
        }}</FmButton>
        <Link
            :href="logout()"
            as="button"
            class="mx-auto h-11 px-3 text-base font-semibold text-action"
        >
            {{ $t('Log out') }}
        </Link>
    </form>
</template>
