/**
 * The cases of `FmCardPayments` for the component catalogue: the bills paid
 * year by year, with a way to add an expense, an income and one with several
 * pages.
 */
import { defineExamples } from '@/catalog/examples';
import { luceBudget, salaryDeadline } from '@/test/fixtures';
import FmCardPayments from './FmCardPayments.vue';

export default defineExamples(FmCardPayments, [
    {
        name: 'Bills paid',
        props: { budget: luceBudget, card: { type: 'utility', id: 3 } },
    },
    {
        name: 'Can add an expense',
        props: {
            budget: { ...luceBudget, can_add_expense: true },
            card: { type: 'utility', id: 3 },
        },
    },
    {
        name: 'Income',
        props: {
            budget: {
                ...luceBudget,
                kind: 'income',
                payments_label: 'Salaries received',
                due: [salaryDeadline],
            },
            card: { type: 'employment', id: 3 },
        },
    },
    {
        name: 'Several pages',
        props: {
            budget: {
                ...luceBudget,
                payments_previous: '/cards/utility/3?payments_page=1',
                payments_next: '/cards/utility/3?payments_page=3',
            },
            card: { type: 'utility', id: 3 },
        },
    },
]);
