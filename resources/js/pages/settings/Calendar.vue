<script setup lang="ts">
/**
 * *Settings → Calendar*: the family's calendar to subscribe to from Google
 * Calendar. An administrator turns it on and off and changes its secret
 * address; everyone sees the address and copies it.
 */
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useCopy } from '@/composables/useCopy';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { settings } from '@/routes';
import { destroy, reset, store } from '@/routes/calendar';

const { calendar } = defineProps<{
    /** Whether the calendar is on, its address and whether who looks can change it. */
    calendar: App.Data.CalendarSettingsData;
}>();

/** The switch: follows the server, and asks it when the person moves it. */
const enabled = ref(calendar.on);
/** `failed`: the browser refused to copy, so the address is selected for the person to copy by hand. */
const {
    copied,
    failed: copyFailed,
    copy: copyText,
    reset: forgetCopy,
} = useCopy();
const confirmingReset = ref(false);

watch(
    () => calendar.on,
    (on) => {
        enabled.value = on;
    },
);

watch(() => calendar.url, forgetCopy);

/** Puts the switch back to what the server says, when it refused the change. */
function restoreSwitch(): void {
    enabled.value = calendar.on;
}

watch(enabled, (value) => {
    if (value === calendar.on) {
        return;
    }

    if (value) {
        router.post(
            store.url(),
            {},
            { preserveScroll: true, onError: restoreSwitch },
        );
    } else {
        router.delete(destroy.url(), {
            preserveScroll: true,
            onError: restoreSwitch,
        });
    }
});

/**
 * Copies the address to the clipboard.
 */
async function copy(): Promise<void> {
    if (calendar.url === null) {
        return;
    }

    if (!(await copyText(calendar.url))) {
        document
            .querySelector<HTMLInputElement>('input[name="address"]')
            ?.select();
    }
}

/**
 * Changes the address, after the confirmation.
 */
function resetAddress(): void {
    router.post(reset.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Calendar')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Calendar')"
            :subtitle="$t('Reminders and deadlines in Google Calendar')"
            :back="settings().url"
            :back-label="$t('Settings')"
        />
        <FmCard>
            <div class="flex flex-col gap-4">
                <FormKit
                    v-if="calendar.can_change"
                    v-model="enabled"
                    type="toggle"
                    name="enabled"
                    :label="$t('Calendar on')"
                    :help="
                        $t(
                            'Anyone with the address can read the dates, so keep it for the family.',
                        )
                    "
                />
                <template v-if="calendar.on">
                    <template v-if="calendar.url !== null">
                        <FormKit
                            type="text"
                            name="address"
                            :label="$t('Calendar address')"
                            :value="calendar.url"
                            readonly
                        />
                        <div class="flex flex-wrap items-center gap-2.5">
                            <FmButton
                                :icon="copied ? Check : Copy"
                                @click="copy"
                                >{{ $t('Copy') }}</FmButton
                            >
                            <span
                                v-if="copied"
                                role="status"
                                class="text-body-sm"
                                >{{ $t('Address copied') }}</span
                            >
                            <span
                                v-if="copyFailed"
                                role="status"
                                class="text-body-sm"
                                >{{
                                    $t(
                                        'Could not copy: select the address and copy it.',
                                    )
                                }}</span
                            >
                        </div>
                    </template>
                    <FmNotice v-else tone="warning">
                        {{
                            $t(
                                "The address can't be shown any more. Change it to get a new one.",
                            )
                        }}
                    </FmNotice>
                    <div v-if="calendar.can_change">
                        <FmButton
                            variant="outline"
                            @click="confirmingReset = true"
                            >{{
                                $t(
                                    'Change the address (the old one stops working)',
                                )
                            }}</FmButton
                        >
                    </div>
                </template>
            </div>
        </FmCard>
        <FmCard v-if="calendar.on" :title="$t('Add it to Google Calendar')">
            <ol class="flex list-decimal flex-col gap-2 pl-5">
                <li>
                    {{
                        $t(
                            'In Google Calendar, next to «Other calendars», tap +, then «From URL».',
                        )
                    }}
                </li>
                <li>{{ $t('Paste the address and tap «Add calendar».') }}</li>
                <li>
                    {{
                        $t('Google updates it by itself, even every few hours.')
                    }}
                </li>
            </ol>
            <p class="mt-3 text-body-sm">
                {{
                    $t(
                        "Don't download the file and import it: it would not update. Subscribe with the address.",
                    )
                }}
            </p>
        </FmCard>
        <FmConfirm
            v-model:open="confirmingReset"
            :question="$t('Change the address?')"
            :text="
                $t(
                    'The old address stops working: whoever uses it has to add the new one.',
                )
            "
            :confirm-label="$t('Change the address')"
            @confirm="resetAddress"
        />
    </div>
</template>
