/**
 * The stories of `FmCardBudget`: the electricity
 * with bills to pay, a policy with its renewal, the instalment of a loan, a
 * member who cannot record payments and a card without payments.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { luceBudget, policyBudget } from '@/test/fixtures';
import FmCardBudget from './FmCardBudget.vue';

const meta = {
    title: 'Page sections/FmCardBudget',
    component: FmCardBudget,
} satisfies Meta<typeof FmCardBudget>;

export default meta;

type Story = StoryObj<typeof meta>;

export const BillsToPay: Story = {
    name: 'Bills to pay',
    args: { budget: luceBudget, card: { type: 'utility', id: 3 } },
};

export const Renewal: Story = {
    args: {
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
};

export const LoanInstalment: Story = {
    name: 'Loan instalment',
    args: {
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
};

export const Member: Story = {
    args: {
        budget: {
            ...luceBudget,
            can_pay: false,
            can_add_planned: false,
            can_add_expense: false,
        },
        card: { type: 'utility', id: 3 },
    },
};

export const NoPayments: Story = {
    name: 'No payments',
    args: { budget: null, card: { type: 'utility', id: 3 } },
};
