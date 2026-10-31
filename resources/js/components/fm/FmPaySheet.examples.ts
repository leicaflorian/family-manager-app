/**
 * The cases of `FmPaySheet` for the component catalogue: the panel of *I paid
 * it*, opened with *Open* on the catalogue page. An expense, a late estimate,
 * a salary and an income that is not a salary.
 */
import { defineExamples } from '@/catalog/examples';
import {
    accountOptions,
    danceDeadline,
    lateDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import FmPaySheet from './FmPaySheet.vue';

export default defineExamples(FmPaySheet, [
    {
        name: 'Expense',
        props: {
            open: false,
            deadline: danceDeadline,
            accounts: accountOptions,
            action: '/budget/planned/expense/4/occurrences/2026-12-05/pay',
        },
    },
    {
        name: 'Late estimate',
        props: {
            open: false,
            deadline: lateDeadline,
            accounts: accountOptions,
            action: '/budget/planned/expense/7/occurrences/2026-10-23/pay',
        },
    },
    {
        name: 'Salary',
        props: {
            open: false,
            deadline: salaryDeadline,
            accounts: accountOptions,
            action: '/budget/planned/income/2/occurrences/2026-10-27/pay',
        },
    },
    {
        name: 'Other income',
        props: {
            open: false,
            deadline: {
                ...salaryDeadline,
                income_type: 'other',
                title: 'Rimborso 730',
                planned_title: 'Rimborso 730',
                card: null,
            },
            accounts: accountOptions,
            action: '/budget/planned/income/5/occurrences/2026-10-27/pay',
        },
    },
]);
