/**
 * The stories of `FmFileDrop`: the large zone, the
 * compact one below a form, a custom label and the zone while it sends.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmFileDrop from './FmFileDrop.vue';

const meta = {
    title: 'Forms/FmFileDrop',
    component: FmFileDrop,
} satisfies Meta<typeof FmFileDrop>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Compact: Story = {
    args: { compact: true },
};

export const CustomLabel: Story = {
    name: 'Custom label',
    args: { label: 'Add the payslip' },
};

export const Processing: Story = {
    args: { processing: true },
};
