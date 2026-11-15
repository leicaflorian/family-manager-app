<script setup lang="ts">
/**
 * The panel *Connect a calendar* of the calendar view of the *Agenda*, as the
 * boards *Agenda-calendario-link* and *Agenda-calendario-link-pc* draw it:
 * from below on the phone, floating beside the page on the computer, with the
 * title, the sentence under it, the × and then `FmCalendarLink`. When it
 * opens it asks the server for the address (`calendar_link`) without changing
 * the address of the page; the address shows once it arrives.
 *
 * Used only by the page *Agenda*.
 */
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { Component } from 'vue';
import { computed, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCalendarLink from '@/components/fm/FmCalendarLink.vue';
import FmSidePanel from '@/components/fm/FmSidePanel.vue';
import { useComputerLayout } from '@/composables/useLayoutMode';
import { edit as calendarSettings } from '@/routes/calendar';

const { calendar } = defineProps<{
    /** The calendar of the family, once the server sent it; null until then. */
    calendar: App.Data.CalendarSettingsData | null;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { required: true });

const desktop = useComputerLayout();

/** The title of the panel, also its name for screen readers. */
const title = computed(() => trans('Connect a calendar'));

/** The panel that holds the content: beside the page on the computer, from below on the phone. */
const container = computed(
    (): { is: Component; props: Record<string, unknown> } =>
        desktop.value
            ? {
                  is: FmSidePanel,
                  props: { label: title.value, onClose: close },
              }
            : {
                  is: FmBottomSheet,
                  props: {
                      open: open.value,
                      title: title.value,
                      hideTitle: true,
                      'onUpdate:open': (next: boolean) => {
                          open.value = next;
                      },
                  },
              },
);

/** Closes the panel. */
function close(): void {
    open.value = false;
}

watch(open, (opened) => {
    if (opened) {
        router.reload({ only: ['calendar_link'], preserveUrl: true });
    }
});
</script>

<template>
    <component
        :is="container.is"
        v-if="open || !desktop"
        v-bind="container.props"
    >
        <div class="flex flex-col gap-5" :class="desktop && 'bg-card p-6'">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                    <h2 class="m-0 text-sheet-title lg:text-stat-lg">
                        {{ title }}
                    </h2>
                    <span class="text-body-sm text-ink-soft">{{
                        $t(
                            'Reminders and deadlines in the calendar of your phone or computer. They update by themselves.',
                        )
                    }}</span>
                </div>
                <FmButton
                    variant="neutral"
                    small
                    :icon="X"
                    :label="$t('Close')"
                    @click="close"
                />
            </div>
            <FmCalendarLink
                v-if="calendar"
                :calendar="calendar"
                :settings-href="calendarSettings().url"
            />
        </div>
    </component>
</template>
