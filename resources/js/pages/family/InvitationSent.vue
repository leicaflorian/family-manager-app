<script setup lang="ts">
/**
 * *Invitation sent* page: the email has left, and the link can be copied to send
 * it in another way too. During a visit of the owner the link is not sent to the
 * page, and a note says so.
 */
import { Head } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useCopy } from '@/composables/useCopy';
import { useFormat } from '@/composables/useFormat';
import { family } from '@/routes';
import { usePageTrail } from '@/composables/usePageTrail';

const { invitation } = defineProps<{
    /** The invitation just sent. */
    invitation: App.Data.SentInvitationData;
}>();

const { date } = useFormat();
/** `failed`: the browser refused to copy, so the person selects the link and copies it by hand. */
const { copied, failed: copyFailed, copy: copyText } = useCopy();

/**
 * Copies the link to the clipboard.
 */
async function copy(): Promise<void> {
    if (invitation.link !== null) {
        await copyText(invitation.link);
    }
}

usePageTrail(() => [
    { label: trans('Family'), href: family().url },
    { label: trans('Invitation sent') },
]);
</script>

<template>
    <Head :title="$t('Invitation sent')" />

    <div class="flex max-w-[560px] flex-col gap-5">
        <FmTitle
            :title="$t('Invitation sent')"
            :back="family().url"
            :back-label="$t('Family')"
        />
        <FmNotice tone="ok">
            {{
                $t(
                    'We sent the email to :email. If it does not arrive, copy the link and send it yourself, for example in a message.',
                    { email: invitation.email },
                )
            }}
        </FmNotice>
        <FmNotice v-if="invitation.link === null">
            {{ $t('The link is hidden during a visit.') }}
        </FmNotice>
        <FormKit
            v-else
            type="text"
            :label="$t('The link to join')"
            name="link"
            :value="invitation.link"
            readonly
            :help="
                $t(
                    'It is valid for 7 days, until :date, and can be used only once.',
                    {
                        date: date(invitation.expires_on),
                    },
                )
            "
        />
        <div class="flex flex-wrap gap-2.5">
            <FmButton
                v-if="invitation.link !== null"
                :icon="copied ? Check : Copy"
                @click="copy"
                >{{ copied ? $t('Copied') : $t('Copy the link') }}</FmButton
            >
            <span
                v-if="copyFailed"
                role="status"
                class="self-center text-body-sm"
                >{{
                    $t('Could not copy: select the text and copy it by hand.')
                }}</span
            >
            <FmButton variant="text" :href="family().url">{{
                $t('Back to the family')
            }}</FmButton>
        </div>
    </div>
</template>
