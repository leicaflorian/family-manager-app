<script setup lang="ts">
/**
 * The family's calendar address and how to subscribe to it: the read-only
 * address with the privacy line, the *Copy the address* button, the choice
 * *Where do you add it?* (Google, iPhone and Mac, Outlook) with its steps, and
 * the warning not to import the file. With the calendar off it says so, and
 * who can turn it on gets the way to Settings. It is the content of the panel
 * *Link to a calendar* of the Agenda, under its title, and of the page
 * *Settings → Calendar*, which adds the switch and *Change the address*.
 *
 * @group panels
 */
import { Check, Copy } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, useId, watch } from 'vue';
import { useCopy } from '@/composables/useCopy';
import FmButton from './FmButton.vue';
import FmNotice from './FmNotice.vue';

/** Where the calendar is added: each place has its own steps. */
type Place = 'google' | 'apple' | 'outlook';

const { calendar, settingsHref } = defineProps<{
    /** Whether the calendar is on, its address and whether who looks can change it. */
    calendar: App.Data.CalendarSettingsData;
    /** Where the settings of the calendar are: with it, who can change them gets the way there. */
    settingsHref?: string;
}>();

/** The choice of the place: Google first, as the steps most people need. */
const place = ref<Place>('google');
/** The id of the address field: this block's own, so the selection never reaches another field. */
const addressId = useId();
/** `failed`: the browser refused to copy, so the address is selected for the person to copy by hand. */
const {
    copied,
    failed: copyFailed,
    copy: copyText,
    reset: forgetCopy,
} = useCopy();

/** The steps of each place, in the words the person reads. */
const steps = computed((): Record<Place, string[]> => ({
    google: [
        trans(
            'In Google Calendar, next to «Other calendars», tap +, then «From URL».',
        ),
        trans('Paste the address and tap «Add calendar».'),
        trans('Google updates it by itself, even every few hours.'),
    ],
    apple: [
        trans(
            'On iPhone open Settings › Apps › Calendar › Calendar Accounts › Add Account › Other › Add Subscribed Calendar.',
        ),
        trans(
            'On the Mac, in Calendar choose File › New Calendar Subscription.',
        ),
        trans('Paste the address and confirm: the calendar updates by itself.'),
    ],
    outlook: [
        trans(
            'In Outlook on the web open the calendar and choose «Add calendar» › «Subscribe from web».',
        ),
        trans('Paste the address, give it a name and choose «Import».'),
        trans('Outlook updates it by itself, even every few hours.'),
    ],
}));

watch(() => calendar.url, forgetCopy);

/**
 * Copies the address to the clipboard; if the browser refuses, selects it so
 * the person can copy it by hand.
 */
async function copy(): Promise<void> {
    if (calendar.url === null) {
        return;
    }

    if (!(await copyText(calendar.url))) {
        document
            .querySelector<HTMLInputElement>(`input[id="${addressId}"]`)
            ?.select();
    }
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <template v-if="calendar.on">
            <template v-if="calendar.url !== null">
                <FormKit
                    type="text"
                    name="address"
                    :id="addressId"
                    :label="$t('Calendar address')"
                    :help="
                        $t(
                            'Anyone with the address can read the dates, so keep it for the family.',
                        )
                    "
                    :value="calendar.url"
                    readonly
                />
                <div class="flex flex-col gap-2">
                    <FmButton
                        wide
                        :icon="copied ? Check : Copy"
                        @click="copy"
                        >{{ $t('Copy the address') }}</FmButton
                    >
                    <span v-if="copied" role="status" class="text-body-sm">{{
                        $t('Address copied')
                    }}</span>
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
                <div class="flex flex-col gap-2.5">
                    <FormKit
                        v-model="place"
                        type="togglebuttons"
                        enforced
                        name="place"
                        :label="$t('Where do you add it?')"
                        :options="[
                            { value: 'google', label: $t('Google') },
                            { value: 'apple', label: $t('iPhone and Mac') },
                            { value: 'outlook', label: $t('Outlook') },
                        ]"
                    />
                    <ol class="flex list-decimal flex-col gap-2 pl-5 text-body">
                        <li v-for="step in steps[place]" :key="step">
                            {{ step }}
                        </li>
                    </ol>
                </div>
                <FmNotice tone="warning" class="text-ink!">
                    {{
                        $t(
                            "Don't download the file and import it: it would not update. Subscribe with the address.",
                        )
                    }}
                </FmNotice>
            </template>
            <FmNotice v-else-if="calendar.hidden">
                {{ $t('The address is hidden during a visit.') }}
            </FmNotice>
            <FmNotice v-else tone="warning">
                {{
                    $t(
                        "The address can't be shown any more. Change it to get a new one.",
                    )
                }}
            </FmNotice>
            <FmButton
                v-if="calendar.can_change && settingsHref"
                variant="text"
                :href="settingsHref"
                class="self-start"
                >{{
                    $t(
                        'Change the address or turn off the calendar in Settings',
                    )
                }}</FmButton
            >
        </template>
        <template v-else>
            <template v-if="calendar.can_change">
                <p class="text-body">{{ $t('The calendar is off.') }}</p>
                <FmButton
                    v-if="settingsHref"
                    :href="settingsHref"
                    class="self-start"
                    >{{ $t('Turn on the calendar') }}</FmButton
                >
            </template>
            <p v-else class="text-body">
                {{
                    $t('Whoever manages the family can turn it on in Settings.')
                }}
            </p>
        </template>
    </div>
</template>
