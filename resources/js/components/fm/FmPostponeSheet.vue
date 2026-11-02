<script setup lang="ts">
/**
 * *Choose a date…* of *Postpone*: the panel that moves a reminder to a day the
 * person picks, proposed tomorrow. The day must come after today; the message
 * of the server shows under it otherwise.
 *
 * @group panels
 */
import { ref, watch } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import { addDays } from '@/lib/days';
import { errors } from '@/lib/errors';
import { todayInRome } from '@/lib/format';
import type { ReminderItem } from '@/lib/reminders';
import { postponeReminder } from '@/lib/reminders';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

const { reminder } = defineProps<{
    /** The reminder to postpone. */
    reminder: Pick<ReminderItem, 'id' | 'title' | 'due_date' | 'subject'>;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { date: formatDate } = useFormat();

/**
 * The day the field starts from: tomorrow.
 *
 * @returns `yyyy-mm-dd`
 */
function proposed(): string {
    return addDays(todayInRome(), 1);
}

/** The new day, `yyyy-mm-dd`. */
const day = ref(proposed());

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The day is being sent. */
const processing = ref(false);

/** Asks before leaving or closing with the day changed and not saved. */
const changes = useUnsavedChanges(() => day.value, {
    active: () => open.value,
});

// Opened again, the field starts again from tomorrow
watch(open, (isOpen) => {
    if (isOpen) {
        day.value = proposed();
        problems.value = {};
        changes.reset();
    }
});

/** Sends the new day. */
function save(): void {
    processing.value = true;
    problems.value = {};

    postponeReminder(reminder.id, day.value, {
        onError: (messages) => {
            problems.value = messages;
        },
        onSuccess: () => {
            open.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <FmBottomSheet
        v-model:open="open"
        :before-close="changes.confirmClose"
        :title="$t('Postpone')"
    >
        <div class="flex flex-col rounded-2xl bg-canvas p-3.5">
            <span class="truncate text-base font-bold">{{
                reminder.title
            }}</span>
            <span class="truncate text-[13px] text-ink-soft">{{
                [reminder.subject?.label, formatDate(reminder.due_date)]
                    .filter(Boolean)
                    .join(' · ')
            }}</span>
        </div>
        <form class="flex flex-col gap-3" novalidate @submit.prevent="save">
            <FormKit
                v-model="day"
                type="datepicker"
                name="to"
                :label="$t('Postpone to')"
                :errors="errors(problems.to)"
            />
            <div class="flex justify-end gap-2.5">
                <FmButton variant="text" @click="open = false">{{
                    $t('Cancel')
                }}</FmButton>
                <FmButton type="submit" :disabled="processing">{{
                    $t('Save')
                }}</FmButton>
            </div>
        </form>
    </FmBottomSheet>
</template>
