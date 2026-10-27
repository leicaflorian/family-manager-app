<script setup lang="ts">
/**
 * *Invitation sent* page: the email has left, and the link can be copied to send
 * it in another way too.
 */
import { Head } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useFormat } from '@/composables/useFormat';
import { family } from '@/routes';

const { invitation } = defineProps<{
    /** The invitation just sent. */
    invitation: App.Data.SentInvitationData;
}>();

const { date } = useFormat();
const copied = ref(false);

/**
 * Copies the link to the clipboard.
 */
async function copy(): Promise<void> {
    await navigator.clipboard.writeText(invitation.link);
    copied.value = true;
}
</script>

<template>
    <Head :title="$t('Invitation sent')" />

    <div class="flex max-w-[560px] flex-col gap-5">
        <FmTitle :title="$t('Invitation sent')" />
        <FmNotice tone="ok">
            {{
                $t(
                    'We sent the email to :email. If it does not arrive, copy the link and send it yourself, for example in a message.',
                    { email: invitation.email },
                )
            }}
        </FmNotice>
        <FormKit
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
            <FmButton :icon="copied ? Check : Copy" @click="copy">{{
                copied ? $t('Copied') : $t('Copy the link')
            }}</FmButton>
            <FmButton variant="text" :href="family().url">{{
                $t('Back to the family')
            }}</FmButton>
        </div>
    </div>
</template>
