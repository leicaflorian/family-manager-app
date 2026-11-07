/**
 * The stories of `FmPlannedRow`: a monthly expense
 * with a card, an estimate, one without a card, an income and one that ended.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { danceRow, gasRow, netflixRow, salaryRow } from '@/test/fixtures';
import FmPlannedRow from './FmPlannedRow.vue';

const meta = {
    title: 'Rows/FmPlannedRow',
    component: FmPlannedRow,
} satisfies Meta<typeof FmPlannedRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const MonthlyWithACard: Story = {
    name: 'Monthly with a card',
    args: { row: danceRow, href: '/budget/planned/expense/4' },
};

export const Estimated: Story = {
    args: { row: gasRow, href: '/budget/planned/expense/9' },
};

export const WithoutACard: Story = {
    name: 'Without a card',
    args: { row: netflixRow, href: '/budget/planned/expense/11' },
};

export const Income: Story = {
    args: { row: salaryRow, href: '/budget/planned/income/2' },
};

export const Ended: Story = {
    args: {
        row: {
            ...danceRow,
            end_date: '2026-06-05',
            recurring: false,
            ended: true,
        },
        href: '/budget/planned/expense/4',
    },
};
