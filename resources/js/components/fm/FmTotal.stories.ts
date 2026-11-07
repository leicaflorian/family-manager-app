/**
 * The stories of `FmTotal`: with a late payment,
 * with none late, with more than one late and with nothing to pay.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmTotal from './FmTotal.vue';

const meta = {
    title: 'Figures/FmTotal',
    component: FmTotal,
} satisfies Meta<typeof FmTotal>;

export default meta;

type Story = StoryObj<typeof meta>;

export const OneLate: Story = {
    name: 'One late',
    args: { total: 1488.79, payments: 8, late: 1 },
};

export const NoneLate: Story = {
    name: 'None late',
    args: { total: 84.2, payments: 1, late: 0 },
};

export const MoreLate: Story = {
    name: 'More late',
    args: { total: 640.5, payments: 5, late: 2 },
};

export const NothingToPay: Story = {
    name: 'Nothing to pay',
    args: { total: 0, payments: 0, late: 0 },
};
