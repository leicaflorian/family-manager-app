/**
 * The cases of `FmPlannedRow` for the component catalogue: a monthly expense
 * with a card, an estimate, one without a card, an income and one that ended.
 */
import { defineExamples } from '@/catalog/examples';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import FmPlannedRow from './FmPlannedRow.vue';

export default defineExamples(FmPlannedRow, [
    {
        name: 'Monthly with a card',
        props: { row: danceRow, href: '/budget/planned/expense/4' },
    },
    {
        name: 'Estimated',
        props: { row: gasRow, href: '/budget/planned/expense/9' },
    },
    {
        name: 'Without a card',
        props: { row: netflixRow, href: '/budget/planned/expense/11' },
    },
    {
        name: 'Income',
        props: { row: salaryRow, href: '/budget/planned/income/2' },
    },
    {
        name: 'Ended',
        props: {
            row: {
                ...danceRow,
                end_date: '2026-06-05',
                recurring: false,
                ended: true,
            },
            href: '/budget/planned/expense/4',
        },
    },
]);
