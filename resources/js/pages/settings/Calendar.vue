<script setup lang="ts">
/**
 * *Settings → Calendar*: the family's calendar to subscribe to from Google
 * Calendar. An administrator turns it on and off and changes its secret
 * address; everyone sees the address and copies it.
 */
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCalendarLink from '@/components/fm/FmCalendarLink.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmConfirm from '@/components/fm/FmConfirm.vue';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { settings } from '@/routes';
import { destroy, reset, store } from '@/routes/calendar';
import { usePageTrail } from '@/composables/usePageTrail';

const { calendar } = defineProps<{
    /** Whether the calendar is on, its address and whether who looks can change it. */
    calendar: App.Data.CalendarSettingsData;
}>();

/** The switch: follows the server, and asks it when the person moves it. */
const enabled = ref(calendar.on);
const confirmingReset = ref(false);

watch(
    () => calendar.on,
    (on) => {
        enabled.value = on;
    },
);

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
 * Changes the address, after the confirmation.
 */
function resetAddress(): void {
    router.post(reset.url(), {}, { preserveScroll: true });
}

usePageTrail(() => [
    { label: trans('Settings'), href: settings().url },
    { label: trans('Calendar') },
]);
</script>

<template>
    <Head :title="$t('Calendar')" />

    <FmSettingsPage current="calendar">
        <template #title>
            <FmTitle
                :title="$t('Calendar')"
                :subtitle="$t('Reminders and deadlines in Google Calendar')"
                :back="settings().url"
                :back-label="$t('Settings')"
            />
        </template>
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
                    <FmCalendarLink :calendar="calendar" />
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
    </FmSettingsPage>
</template>
