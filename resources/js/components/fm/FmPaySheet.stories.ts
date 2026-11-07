/**
 * The stories of `FmPaySheet`: the panel of *I paid
 * it*, opened with the `open` control. An expense, a late estimate,
 * a salary and an income that is not a salary.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import {
    accountOptions,
    danceDeadline,
    lateDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import FmPaySheet from './FmPaySheet.vue';

const meta = {
    title: 'Panels/FmPaySheet',
    component: FmPaySheet,
} satisfies Meta<typeof FmPaySheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Expense: Story = {
    args: {
        open: false,
        deadline: danceDeadline,
        accounts: accountOptions,
        action: '/budget/planned/expense/4/occurrences/2026-12-05/pay',
    },
};

export const LateEstimate: Story = {
    name: 'Late estimate',
    args: {
        open: false,
        deadline: lateDeadline,
        accounts: accountOptions,
        action: '/budget/planned/expense/7/occurrences/2026-10-23/pay',
    },
};

export const Salary: Story = {
    args: {
        open: false,
        deadline: salaryDeadline,
        accounts: accountOptions,
        action: '/budget/planned/income/2/occurrences/2026-10-27/pay',
    },
};

export const OtherIncome: Story = {
    name: 'Other income',
    args: {
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
};
