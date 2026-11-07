/**
 * The stories of `FmConfirm`: the panel that asks
 * again, opened with the `open` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmConfirm from './FmConfirm.vue';

const meta = {
    title: 'Basics/FmConfirm',
    component: FmConfirm,
} satisfies Meta<typeof FmConfirm>;

export default meta;

type Story = StoryObj<typeof meta>;

export const RemoveALink: Story = {
    name: 'Remove a link',
    args: {
        open: false,
        question: 'Remove this link?',
        text: 'The document stays, only the link goes away.',
        confirmLabel: 'Remove the link',
    },
};
