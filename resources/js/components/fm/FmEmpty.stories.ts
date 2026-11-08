/**
 * The stories of `FmEmpty`: with only the title,
 * with the reason, and with a button.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { FileText } from '@lucide/vue';
import { h } from 'vue';
import FmButton from './FmButton.vue';
import FmEmpty from './FmEmpty.vue';

const meta = {
    title: 'Basics/FmEmpty',
    component: FmEmpty,
} satisfies Meta<typeof FmEmpty>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TitleOnly: Story = {
    name: 'Title only',
    args: { icon: FileText, title: 'No documents yet' },
};

export const WithTheReason: Story = {
    name: 'With the reason',
    args: {
        icon: FileText,
        title: 'No documents yet',
        text: 'Upload the first one and it will appear here.',
    },
};

export const WithAButton: Story = {
    name: 'With a button',
    args: {
        icon: FileText,
        title: 'No documents yet',
        text: 'Upload the first one and it will appear here.',
    },
    render: withSlots({
        default: () =>
            h(FmButton, { href: '/upload' }, () => 'Upload a document'),
    }),
};
