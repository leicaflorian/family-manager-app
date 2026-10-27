<script setup lang="ts">
/**
 * *Invitation accepted* page: who receives the invitation sees the faces of the
 * family, chooses the password and joins. The email is the one of the
 * invitation, already confirmed.
 */
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmFaces from '@/components/fm/FmFaces.vue';
import FmHeader from '@/components/fm/FmHeader.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import { useFormat } from '@/composables/useFormat';
import { errors } from '@/lib/errors';
import { listNames } from '@/lib/names';
import { store } from '@/routes/invitation';

const { token, invitation } = defineProps<{
    /** The code of the link. */
    token: string;
    /** The invitation, as who receives it reads it. */
    invitation: App.Data.InvitationData;
}>();

setLayoutProps({
    panel: {
        title: trans('Welcome to the :family family.', {
            family: invitation.family,
        }),
        text: transChoice(
            '{1} :names already uses Family Manager for the deadlines, the bills and the documents of the home.|[2,*] :names already use Family Manager for the deadlines, the bills and the documents of the home.',
            invitation.people.length,
            { names: listNames(invitation.people.map((p) => p.first_name)) },
        ),
    },
});

const { date } = useFormat();
const form = useForm({ password: '' });

/**
 * Creates the account and joins; the password is emptied however it goes.
 */
function join(): void {
    form.submit(store(token), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="$t('Join the family')" />

    <form class="flex grow flex-col gap-3" novalidate @submit.prevent="join">
        <FmFaces :people="invitation.people" />
        <FmHeader
            :title="$t('Hi, :name', { name: invitation.first_name })"
            :subtitle="
                $t(
                    ':inviter invited you to the :family family. You will see everyone\'s deadlines and documents, and you will be able to upload new ones.',
                    { inviter: invitation.inviter, family: invitation.family },
                )
            "
        />
        <FormKit
            type="email"
            :label="$t('Your email')"
            name="email"
            :value="invitation.email"
            disabled
        />
        <FmPasswordField
            v-model="form.password"
            :label="$t('Choose a password')"
            name="password"
            :help="$t('At least 10 characters')"
            autocomplete="new-password"
            :errors="errors(form.errors.password)"
        />
        <span class="min-h-6 grow lg:hidden" />
        <FmButton type="submit" wide :disabled="form.processing">{{
            $t('Join the family')
        }}</FmButton>
        <p class="m-0 text-center text-[13px] text-ink-soft">
            {{
                $t('The invitation is valid until :date', {
                    date: date(invitation.expires_on),
                })
            }}
        </p>
    </form>
</template>
