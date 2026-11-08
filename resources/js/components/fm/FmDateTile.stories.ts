/**
 * The stories of `FmDateTile`: a date in the future,
 * an expiring date already passed (red), and a date that does not expire.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import FmDateTile from './FmDateTile.vue';

const meta = {
    title: 'Figures/FmDateTile',
    component: FmDateTile,
} satisfies Meta<typeof FmDateTile>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ExpiresSoon: Story = {
    name: 'Expires soon',
    args: {
        date: { label: 'Expires', date: fromToday(20), expires: true },
    },
};

export const Expired: Story = {
    args: {
        date: { label: 'Expires', date: fromToday(-3), expires: true },
    },
};

export const DoesNotExpire: Story = {
    name: 'Does not expire',
    args: {
        date: { label: 'Issued', date: fromToday(-200), expires: false },
    },
};
