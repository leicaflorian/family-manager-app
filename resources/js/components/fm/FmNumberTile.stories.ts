/**
 * The stories of `FmNumberTile`: an amount with a
 * hint, a count, an amount inside the hint and a tile without a hint.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmNumberTile from './FmNumberTile.vue';

const meta = {
    title: 'Figures/FmNumberTile',
    component: FmNumberTile,
} satisfies Meta<typeof FmNumberTile>;

export default meta;

type Story = StoryObj<typeof meta>;

export const AmountWithAHint: Story = {
    name: 'Amount with a hint',
    args: {
        number: {
            label: 'Planned',
            amount: 72,
            count: null,
            hint: 'per month, estimated',
            hint_amount: null,
        },
    },
};

export const Count: Story = {
    args: {
        number: {
            label: 'Bills paid',
            amount: null,
            count: 9,
            hint: null,
            hint_amount: null,
        },
    },
};

export const AmountInTheHint: Story = {
    name: 'Amount in the hint',
    args: {
        number: {
            label: 'Paid in 2026',
            amount: 642.1,
            count: null,
            hint: 'of :amount planned',
            hint_amount: 864,
        },
    },
};

export const WithoutAHint: Story = {
    name: 'Without a hint',
    args: {
        number: {
            label: 'Premium',
            amount: 418,
            count: null,
            hint: null,
            hint_amount: null,
        },
    },
};
