/**
 * The cases of `FmPayButton` for the component catalogue: one open due date,
 * several planned items (it asks which one), an income and none (no button).
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import { danceDeadline, lateDeadline, salaryDeadline } from '@/test/fixtures';
import FmPayButton from './FmPayButton.vue';

export default defineExamples(FmPayButton, [
    {
        name: 'One due date',
        props: {
            deadlines: [{ ...lateDeadline, date: fromToday(-3) }],
            label: 'Record payment',
        },
    },
    {
        name: 'Several planned items',
        props: {
            deadlines: [
                { ...lateDeadline, date: fromToday(-3) },
                danceDeadline,
            ],
            label: 'Record payment',
        },
    },
    {
        name: 'Income',
        props: { deadlines: [salaryDeadline], label: 'Record income' },
    },
    {
        name: 'No open due date',
        props: { deadlines: [], label: 'Record payment' },
    },
]);
