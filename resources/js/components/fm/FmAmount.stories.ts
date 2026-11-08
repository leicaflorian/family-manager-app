/**
 * The stories of `FmAmount`: an amount, an
 * estimate, an amount changed from its planned one and an income.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmAmount from './FmAmount.vue';

const meta = {
    title: 'Figures/FmAmount',
    component: FmAmount,
} satisfies Meta<typeof FmAmount>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Expense: Story = {
    args: { amount: 84.2 },
};

export const Estimated: Story = {
    args: { amount: 71.3, estimated: true },
};

export const ChangedFromThePlanned: Story = {
    name: 'Changed from the planned',
    args: { amount: 62.4, base: 72 },
};

export const Income: Story = {
    args: { amount: 1850, income: true },
};
