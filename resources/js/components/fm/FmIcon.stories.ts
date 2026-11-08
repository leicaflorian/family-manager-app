/**
 * The stories of `FmIcon`: the default size, a big
 * thin one and one with a label for screen readers.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { FileText, Home } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const meta = {
    title: 'Basics/FmIcon',
    component: FmIcon,
} satisfies Meta<typeof FmIcon>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {
    args: { icon: FileText },
};

export const BigAndThin: Story = {
    name: 'Big and thin',
    args: { icon: Home, size: 48, stroke: 1 },
};

export const WithALabel: Story = {
    name: 'With a label',
    args: { icon: FileText, label: 'Document' },
};
