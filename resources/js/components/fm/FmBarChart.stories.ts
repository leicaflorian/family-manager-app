/**
 * The stories of `FmBarChart`: the last six months
 * with October in progress, a short run of months and an empty year.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { monthBars } from '@/test/fixtures';
import FmBarChart from './FmBarChart.vue';

const meta = {
    title: 'Figures/FmBarChart',
    component: FmBarChart,
} satisfies Meta<typeof FmBarChart>;

export default meta;

type Story = StoryObj<typeof meta>;

export const SixMonths: Story = {
    name: 'Six months',
    args: { months: monthBars },
};

export const ThreeMonths: Story = {
    name: 'Three months',
    args: { months: monthBars.slice(3) },
};

export const NothingSpent: Story = {
    name: 'Nothing spent',
    args: {
        months: monthBars.map((month) => ({
            ...month,
            spent: 0,
            planned: 0,
        })),
    },
};
