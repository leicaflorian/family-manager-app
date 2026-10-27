<script setup lang="ts">
/**
 * *This invitation is no longer valid* page: the link expired, was already used
 * or never existed. The page is the same in the three cases.
 */
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmHeader from '@/components/fm/FmHeader.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        panel: {
            title: trans('Everything about the family, in one place.'),
            text: trans(
                'Invitations are valid for 7 days and can be used only once.',
            ),
        },
    },
});
</script>

<template>
    <Head :title="$t('Invalid invitation')" />

    <div class="flex grow flex-col gap-5">
        <FmHeader
            :title="$t('This invitation is no longer valid')"
            :subtitle="
                $t(
                    'Maybe it expired, or it was already used. Ask who invited you to send you a new one; if you already have an account, enter from Log in.',
                )
            "
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmButton :href="login().url" wide>{{ $t('Go to Log in') }}</FmButton>
    </div>
</template>
