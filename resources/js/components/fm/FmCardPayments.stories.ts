/**
 * The stories of `FmCardPayments`: the bills paid
 * year by year, with a way to add an expense, an income and one with several
 * pages.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { luceBudget, salaryDeadline } from '@/test/fixtures';
import FmCardPayments from './FmCardPayments.vue';

const meta = {
    title: 'Page sections/FmCardPayments',
    component: FmCardPayments,
} satisfies Meta<typeof FmCardPayments>;

export default meta;

type Story = StoryObj<typeof meta>;

export const BillsPaid: Story = {
    name: 'Bills paid',
    args: { budget: luceBudget, card: { type: 'utility', id: 3 } },
};

export const CanAddAnExpense: Story = {
    name: 'Can add an expense',
    args: {
        budget: { ...luceBudget, can_add_expense: true },
        card: { type: 'utility', id: 3 },
    },
};

export const Income: Story = {
    args: {
        budget: {
            ...luceBudget,
            kind: 'income',
            payments_label: 'Salaries received',
            due: [salaryDeadline],
        },
        card: { type: 'employment', id: 3 },
    },
};

export const SeveralPages: Story = {
    name: 'Several pages',
    args: {
        budget: {
            ...luceBudget,
            payments_previous: '/cards/utility/3?payments_page=1',
            payments_next: '/cards/utility/3?payments_page=3',
        },
        card: { type: 'utility', id: 3 },
    },
};
