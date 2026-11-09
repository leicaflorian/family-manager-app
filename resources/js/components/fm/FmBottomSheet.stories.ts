/**
 * The stories of `FmBottomSheet`: the panel with a
 * short text, with a long one and with a question before closing, opened with
 * the `open` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import FmBottomSheet from './FmBottomSheet.vue';

const meta = {
    title: 'Panels/FmBottomSheet',
    component: FmBottomSheet,
} satisfies Meta<typeof FmBottomSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ShortContent: Story = {
    name: 'Short content',
    args: { open: false, title: 'Filters' },
    render: withSlots({ default: 'Choose what to show in the list.' }),
};

export const Large: Story = {
    args: { open: false, title: 'Link the phone', size: 'lg' },
    render: withSlots({
        default:
            'A panel 640 px wide on the computer, for a picture beside its text.',
    }),
};

export const LongContent: Story = {
    name: 'Long content',
    args: { open: false, title: 'Rules of the house' },
    render: withSlots({
        default: () =>
            Array.from({ length: 24 }, (_, i) =>
                h('p', `Voce numero ${i + 1} dell’elenco della famiglia.`),
            ),
    }),
};

export const AsksBeforeClosing: Story = {
    name: 'Asks before closing',
    args: {
        open: false,
        title: 'Pay this deadline',
        beforeClose: () => Promise.resolve(true),
    },
    render: withSlots({
        default: 'Closing it with the ×, Esc or outside asks first.',
    }),
};

export const AboveTheOtherPanels: Story = {
    name: 'Above the other panels',
    args: { open: false, title: 'Leave without saving?', above: true },
    render: withSlots({
        default:
            'A question asked from an open panel goes over it, as FmConfirm does.',
    }),
};
