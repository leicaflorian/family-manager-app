/**
 * The stories of `FmLoanFigures`: the totals of a
 * loan, one without its cost and one not yet worked out.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmLoanFigures from './FmLoanFigures.vue';

const meta = {
    title: 'Figures/FmLoanFigures',
    component: FmLoanFigures,
} satisfies Meta<typeof FmLoanFigures>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Complete: Story = {
    args: { figures: { total_to_pay: 9072, cost: 1072 } },
};

export const WithoutTheCost: Story = {
    name: 'Without the cost',
    args: { figures: { total_to_pay: 9072, cost: null } },
};

export const NotWorkedOut: Story = {
    name: 'Not worked out',
    args: { figures: null },
};
