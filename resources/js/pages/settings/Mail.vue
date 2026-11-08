<script setup lang="ts">
/**
 * *Settings → Address for the bills*: the address the family forwards its bills
 * to (with *Copy*), the three steps to do it from Gmail, who already wrote, and
 * who gets the notice of a new file. Everyone sees it and copies the address;
 * an administrator changes the address (after a confirmation) and the choice of
 * who is told, which is saved with *Save*. An account unchecked is not told;
 * with none checked nobody is.
 */
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy, Mail as MailIcon, RefreshCw } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useCopy } from '@/composables/useCopy';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { settings as settingsIndex } from '@/routes';
import { index as mailIndex } from '@/routes/mail';
import { address as changeAddress, update } from '@/routes/mail-settings';

const { settings } = defineProps<{
    /** The address, who already wrote, the accounts, the choice saved and whether who looks can change it. */
    settings: App.Data.MailSettingsData;
}>();

const copier = useCopy();
const confirmingChange = ref(false);
const saving = ref(false);
/** Which accounts are checked, by id. */
const chosen = reactive<Record<number, boolean>>({});
const attachmentsOnly = ref(settings.attachments_only);

/** Takes the choice saved by the server as the one on the page. */
function load(): void {
    for (const account of settings.accounts) {
        chosen[account.id] = account.chosen;
    }

    attachmentsOnly.value = settings.attachments_only;
}

load();

/** The ids of the accounts checked, as the server wants them. */
const notify = computed((): number[] =>
    settings.accounts
        .filter((account) => chosen[account.id] === true)
        .map((account) => account.id),
);

const changes = useUnsavedChanges(() => ({
    notify: notify.value,
    attachmentsOnly: attachmentsOnly.value,
}));

// A save or a new address redirects here with what the server kept: start from it
watch(
    () => [settings.accounts, settings.attachments_only, settings.address],
    () => {
        load();
        changes.reset();
        copier.reset();
    },
);

/** Copies the address to the clipboard; when the browser refuses, selects it for the person to copy. */
async function copyAddress(): Promise<void> {
    if (settings.address !== null && !(await copier.copy(settings.address))) {
        document
            .querySelector<HTMLInputElement>('input[name="address"]')
            ?.select();
    }
}

/** Changes the address, after the confirmation. */
function changeTheAddress(): void {
    confirmingChange.value = false;
    router.post(changeAddress.url(), {}, { preserveScroll: true });
}

/** Saves who gets the notice and whether it is only for mails with files. */
function save(): void {
    saving.value = true;

    router.put(
        update.url(),
        { notify: notify.value, attachments_only: attachmentsOnly.value },
        {
            preserveScroll: true,
            onSuccess: () => changes.reset(),
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

useSaveShortcut(save, () => settings.can_edit && !saving.value);
</script>

<template>
    <Head :title="$t('Address for the bills')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Address for the bills')"
            :subtitle="
                $t('Forward the bills that arrive by email: we read them')
            "
            :back="settingsIndex().url"
            :back-label="$t('Settings')"
        >
            <template #actions>
                <FmButton
                    variant="outline"
                    :icon="MailIcon"
                    :href="mailIndex().url"
                    >{{ $t('Received mail') }}</FmButton
                >
            </template>
        </FmTitle>

        <FmCard>
            <div class="flex flex-col gap-4">
                <p class="m-0 text-[15px] leading-normal text-ink-soft">
                    {{
                        $t(
                            'The mails sent here arrive in To sort, with their files.',
                        )
                    }}
                </p>
                <template v-if="settings.address !== null">
                    <FormKit
                        type="text"
                        name="address"
                        :label="$t('Address')"
                        :value="settings.address"
                        readonly
                    />
                    <div class="flex flex-wrap items-center gap-2.5">
                        <FmButton
                            :icon="copier.copied.value ? Check : Copy"
                            @click="copyAddress"
                            >{{ $t('Copy') }}</FmButton
                        >
                        <FmButton
                            v-if="settings.can_edit"
                            variant="text"
                            :icon="RefreshCw"
                            @click="confirmingChange = true"
                            >{{ $t('Change the address') }}</FmButton
                        >
                        <span
                            v-if="copier.copied.value"
                            role="status"
                            class="text-sm"
                            >{{ $t('Address copied') }}</span
                        >
                        <span
                            v-if="copier.failed.value"
                            role="status"
                            class="text-sm"
                            >{{
                                $t(
                                    'Could not copy: select the text and copy it by hand.',
                                )
                            }}</span
                        >
                    </div>
                </template>
                <FmNotice v-else tone="warning">{{
                    $t('The mail is not active yet.')
                }}</FmNotice>
            </div>
        </FmCard>

        <FmCard :title="$t('How to get them by themselves')">
            <div class="flex flex-col gap-4">
                <ol class="m-0 flex list-none flex-col gap-3 p-0">
                    <li
                        v-for="(step, index) in [
                            $t(
                                'Gmail, on the computer: open a mail from Edison and choose «Filter messages like this».',
                            ),
                            $t(
                                'Tap «Create filter», check «Forward it to» and choose the family address.',
                            ),
                            $t(
                                'The first time Gmail sends a confirmation code: it arrives in Received mail, and you copy it from there.',
                            ),
                        ]"
                        :key="index"
                        class="flex items-start gap-3 text-[15px] leading-snug"
                    >
                        <span
                            class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-action-soft text-[13px] font-extrabold text-action-dark"
                            >{{ index + 1 }}</span
                        >
                        <span>{{ step }}</span>
                    </li>
                </ol>
                <div
                    v-if="settings.senders.length > 0"
                    class="flex flex-col gap-2"
                >
                    <span class="text-[13px] font-semibold text-ink-soft">{{
                        $t('They already wrote here')
                    }}</span>
                    <ul class="m-0 flex list-none flex-wrap gap-1.5 p-0">
                        <li
                            v-for="sender in settings.senders"
                            :key="sender.address"
                            :title="sender.address"
                            class="rounded-tag bg-divider px-2 py-0.5 text-[13px] font-semibold whitespace-nowrap text-ink-soft"
                        >
                            {{ sender.name }}
                        </li>
                    </ul>
                </div>
            </div>
        </FmCard>

        <FmCard :title="$t('Who gets the notice of a new file')">
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <div class="flex flex-col gap-3">
                    <FormKit
                        v-for="account in settings.accounts"
                        :key="account.id"
                        v-model="chosen[account.id]"
                        type="checkbox"
                        :name="`account-${account.id}`"
                        :label="account.name"
                        :help="account.email"
                        :disabled="!settings.can_edit"
                    />
                    <FormKit
                        v-for="name in settings.people_without_account"
                        :key="name"
                        type="checkbox"
                        :name="`person-${name}`"
                        :label="name"
                        :help="$t('Has no account')"
                        :model-value="false"
                        disabled
                    />
                    <p
                        v-if="notify.length === 0"
                        role="status"
                        class="m-0 text-sm text-ink-soft"
                    >
                        {{
                            $t(
                                'Nobody is told: the mails still arrive in To sort.',
                            )
                        }}
                    </p>
                </div>
                <FormKit
                    v-model="attachmentsOnly"
                    type="toggle"
                    name="attachments_only"
                    :label="$t('Only for mails with files')"
                    :help="
                        $t(
                            'A mail without files becomes a notice in To sort, with no bell',
                        )
                    "
                    :disabled="!settings.can_edit"
                />
                <div v-if="settings.can_edit" class="flex justify-end">
                    <FmButton type="submit" :disabled="saving">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </form>
        </FmCard>

        <FmConfirm
            v-model:open="confirmingChange"
            :question="$t('Change the address?')"
            :text="
                $t(
                    'The old address stops receiving: the forwards you set up (Gmail, filters) must be redone with the new one.',
                )
            "
            :confirm-label="$t('Change the address')"
            @confirm="changeTheAddress"
        />
    </div>
</template>
