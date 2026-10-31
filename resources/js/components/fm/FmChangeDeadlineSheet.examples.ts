/**
 * The cases of `FmChangeDeadlineSheet` for the component catalogue: the panel
 * that changes one due date, opened with *Open* on the catalogue page. It
 * starts from a changed one, a skipped one and a plain one.
 */
import { defineExamples } from '@/catalog/examples';
import { danceDeadline, lateDeadline } from '@/test/fixtures';
import FmChangeDeadlineSheet from './FmChangeDeadlineSheet.vue';

export default defineExamples(FmChangeDeadlineSheet, [
    {
        name: 'Changed amount and note',
        props: {
            open: false,
            deadline: danceDeadline,
            action: '/budget/planned/expense/4/occurrences/2026-12-05',
        },
    },
    {
        name: 'Skipped',
        props: {
            open: false,
            deadline: { ...danceDeadline, skipped: true },
            action: '/budget/planned/expense/4/occurrences/2026-12-05',
        },
    },
    {
        name: 'Estimated and late',
        props: {
            open: false,
            deadline: lateDeadline,
            action: '/budget/planned/expense/7/occurrences/2026-10-23',
        },
    },
]);
