/**
 * The cases of `FmCardBudget` for the component catalogue: the electricity
 * with bills to pay, a policy with its renewal, the instalment of a loan, a
 * member who cannot record payments and a card without payments.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import { luceBudget, policyBudget } from '@/test/fixtures';
import FmCardBudget from './FmCardBudget.vue';

export default defineExamples(FmCardBudget, [
    {
        name: 'Bills to pay',
        props: { budget: luceBudget, card: { type: 'utility', id: 3 } },
    },
    {
        name: 'Renewal',
        props: {
            budget: {
                ...policyBudget,
                next: policyBudget.next && {
                    ...policyBudget.next,
                    date: fromToday(26),
                    original_date: fromToday(26),
                },
            },
            card: { type: 'insurance_policy', id: 1 },
        },
    },
    {
        name: 'Loan instalment',
        props: {
            budget: {
                ...policyBudget,
                next: policyBudget.next && {
                    ...policyBudget.next,
                    date: fromToday(9),
                    original_date: fromToday(9),
                    title: 'Mutuo casa',
                    planned_title: 'Mutuo casa',
                    amount: 189,
                },
                next_label: 'Next instalment',
                installment_number: 33,
                installments_count: 48,
                installments_paid: 32,
                paid_amount: 6048,
                left_amount: 3024,
            },
            card: { type: 'loan', id: 2 },
        },
    },
    {
        name: 'Member',
        props: {
            budget: {
                ...luceBudget,
                can_pay: false,
                can_add_planned: false,
                can_add_expense: false,
            },
            card: { type: 'utility', id: 3 },
        },
    },
    {
        name: 'No payments',
        props: { budget: null, card: { type: 'utility', id: 3 } },
    },
]);
