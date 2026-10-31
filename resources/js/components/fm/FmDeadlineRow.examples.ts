/**
 * The cases of `FmDeadlineRow` for the component catalogue: an upcoming due
 * date, a late one, an estimated one, a changed one, a paid one and an income.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import {
    danceDeadline,
    lateDeadline,
    paidDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import FmDeadlineRow from './FmDeadlineRow.vue';

export default defineExamples(FmDeadlineRow, [
    {
        name: 'Upcoming',
        props: {
            deadline: {
                ...danceDeadline,
                date: fromToday(12),
                original_date: fromToday(12),
                amount: 45,
                base_amount: null,
                notes: null,
            },
            href: '/budget/planned/expense/4/occurrences/2026-12-05',
        },
    },
    {
        name: 'Late and estimated',
        props: {
            deadline: { ...lateDeadline, date: fromToday(-3) },
            href: '/budget/planned/expense/7/occurrences/2026-10-23',
        },
    },
    {
        name: 'Changed amount',
        props: {
            deadline: { ...danceDeadline, date: fromToday(5) },
            href: '/budget/planned/expense/4/occurrences/2026-12-05',
        },
    },
    {
        name: 'Paid',
        props: {
            deadline: paidDeadline,
            href: '/budget/planned/expense/4/occurrences/2026-11-05',
        },
    },
    {
        name: 'Skipped',
        props: {
            deadline: { ...danceDeadline, skipped: true },
            href: '/budget/planned/expense/4/occurrences/2026-12-05',
        },
    },
    {
        name: 'Income',
        props: {
            deadline: { ...salaryDeadline, date: fromToday(4) },
            href: '/budget/planned/income/2/occurrences/2026-10-27',
        },
    },
]);
