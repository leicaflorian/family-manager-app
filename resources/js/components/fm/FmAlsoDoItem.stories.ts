/**
 * The stories of `FmAlsoDoItem`: an expense ticked
 * with its icon and hint, a health event with fields and actions, a reminder
 * unticked, a warning, and a disabled one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { Bell, HeartPulse, Wallet } from '@lucide/vue';
import { h } from 'vue';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

const meta = {
    title: 'Page sections/FmAlsoDoItem',
    component: FmAlsoDoItem,
} satisfies Meta<typeof FmAlsoDoItem>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TickedWithIconAndHint: Story = {
    name: 'Ticked, with icon and hint',
    args: {
        label: 'Create the expense of 84,20 €',
        hint: 'Already paid on 22/10 · Laura’s card',
        icon: Wallet,
        modelValue: true,
    },
};

export const WithFieldsAndActions: Story = {
    name: 'With fields and actions',
    args: {
        label: 'Add a health event',
        icon: HeartPulse,
        modelValue: true,
    },
    render: withSlots({
        default: () =>
            h('p', { class: 'm-0 text-sm' }, 'The fields to correct'),
        actions: () =>
            h(
                'button',
                { type: 'button', class: 'font-semibold underline' },
                'Back to the proposal',
            ),
    }),
};

export const Unticked: Story = {
    args: {
        label: 'Remind me to book the checkup',
        icon: Bell,
        modelValue: false,
    },
};

export const WithAWarning: Story = {
    name: 'With a warning',
    args: {
        label: 'Link the policy',
        warnings: ['More than one policy fits, so I link none.'],
        modelValue: true,
    },
};

export const Disabled: Story = {
    args: {
        label: 'Create the expense of 84,20 €',
        icon: Wallet,
        modelValue: false,
        disabled: true,
    },
};
