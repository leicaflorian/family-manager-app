/**
 * The stories of `FmMailOutcome`: the five colours, with the labels of the
 * board.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmMailOutcome from './FmMailOutcome.vue';

const meta = {
    title: 'Basics/FmMailOutcome',
    component: FmMailOutcome,
} satisfies Meta<typeof FmMailOutcome>;

export default meta;

type Story = StoryObj<typeof meta>;

export const FilesSaved: Story = {
    name: 'Files saved',
    args: { label: '1 file saved · 2 deliveries', tone: 'success' },
};

export const NoticeSaved: Story = {
    name: 'Notice saved',
    args: { label: 'Notice saved', tone: 'info' },
};

export const Discarded: Story = {
    args: { label: 'Discarded', tone: 'neutral' },
};

export const BeingChecked: Story = {
    name: 'Being checked',
    args: { label: 'Being checked', tone: 'warning' },
};

export const Failed: Story = {
    args: { label: 'Error', tone: 'danger' },
};
