/**
 * The stories of `FmPaymentYears`: the payments by
 * year, folded, with a count per year, linked and with the full date.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { lucePayments } from '@/test/fixtures';
import FmPaymentYears from './FmPaymentYears.vue';

const meta = {
    title: 'Page sections/FmPaymentYears',
    component: FmPaymentYears,
} satisfies Meta<typeof FmPaymentYears>;

export default meta;

type Story = StoryObj<typeof meta>;

export const AllOpen: Story = {
    name: 'All open',
    args: { years: lucePayments },
};

export const Folded: Story = {
    args: { years: lucePayments, folded: true },
};

export const WithCounts: Story = {
    name: 'With counts',
    args: {
        years: lucePayments,
        counts: { 2026: '3 bills', 2025: '1 bill' },
    },
};

export const LinkedFullDate: Story = {
    name: 'Linked, full date',
    args: { years: lucePayments, linked: true, fullDate: true },
};

export const NothingPaid: Story = {
    name: 'Nothing paid',
    args: { years: [] },
};
