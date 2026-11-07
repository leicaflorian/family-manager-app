/**
 * The stories of `FmActiveFilter`: a short filter
 * and a long one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmActiveFilter from './FmActiveFilter.vue';

const meta = {
    title: 'Fields/FmActiveFilter',
    component: FmActiveFilter,
} satisfies Meta<typeof FmActiveFilter>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Person: Story = {
    args: { label: 'Laura' },
};

export const LongLabel: Story = {
    name: 'Long label',
    args: { label: 'Lavoro › Buste paga › Altro' },
};
