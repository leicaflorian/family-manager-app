/**
 * The stories of `FmDeadlineRow`: an upcoming due
 * date, a late one, an estimated one, a changed one, a paid one and an income.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import {
    danceDeadline,
    lateDeadline,
    paidDeadline,
    salaryDeadline,
} from '@/test/fixtures';
import FmDeadlineRow from './FmDeadlineRow.vue';

const meta = {
    title: 'Rows/FmDeadlineRow',
    component: FmDeadlineRow,
} satisfies Meta<typeof FmDeadlineRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Upcoming: Story = {
    args: {
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
};

export const LateAndEstimated: Story = {
    name: 'Late and estimated',
    args: {
        deadline: { ...lateDeadline, date: fromToday(-3) },
        href: '/budget/planned/expense/7/occurrences/2026-10-23',
    },
};

export const ChangedAmount: Story = {
    name: 'Changed amount',
    args: {
        deadline: { ...danceDeadline, date: fromToday(5) },
        href: '/budget/planned/expense/4/occurrences/2026-12-05',
    },
};

export const Paid: Story = {
    args: {
        deadline: paidDeadline,
        href: '/budget/planned/expense/4/occurrences/2026-11-05',
    },
};

export const Skipped: Story = {
    args: {
        deadline: { ...danceDeadline, skipped: true },
        href: '/budget/planned/expense/4/occurrences/2026-12-05',
    },
};

export const Income: Story = {
    args: {
        deadline: { ...salaryDeadline, date: fromToday(4) },
        href: '/budget/planned/income/2/occurrences/2026-10-27',
    },
};
