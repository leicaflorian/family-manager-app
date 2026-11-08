/**
 * The stories of `FmMonthSwitch`: a month in the
 * middle of the year and one at the turn of the year.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmMonthSwitch from './FmMonthSwitch.vue';

const meta = {
    title: 'Navigation/FmMonthSwitch',
    component: FmMonthSwitch,
} satisfies Meta<typeof FmMonthSwitch>;

export default meta;

type Story = StoryObj<typeof meta>;

export const October: Story = {
    args: { month: '2026-10' },
};

export const January: Story = {
    args: { month: '2027-01' },
};
