/**
 * The stories of `FmBackLink`: a short name and a
 * long one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmBackLink from './FmBackLink.vue';

const meta = {
    title: 'Navigation/FmBackLink',
    component: FmBackLink,
} satisfies Meta<typeof FmBackLink>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Short: Story = {
    args: { href: '/documents', label: 'Documents' },
};

export const LongName: Story = {
    name: 'Long name',
    args: {
        href: '/cards/1',
        label: 'Contratto di locazione di via Garibaldi 12, Milano',
    },
};
