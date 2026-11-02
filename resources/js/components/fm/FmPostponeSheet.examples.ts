/**
 * The cases of `FmPostponeSheet` for the component catalogue: the panel of
 * *Choose a date…* of a late reminder, opened with *Open* on the catalogue page.
 */
import { defineExamples } from '@/catalog/examples';
import { toReminderItem } from '@/lib/reminders';
import { healthCardReminder } from '@/test/fixtures';
import FmPostponeSheet from './FmPostponeSheet.vue';

export default defineExamples(FmPostponeSheet, [
    {
        name: 'Choose a date',
        props: { open: false, reminder: toReminderItem(healthCardReminder) },
    },
]);
