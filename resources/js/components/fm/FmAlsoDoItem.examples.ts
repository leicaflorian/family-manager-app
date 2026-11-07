/**
 * The cases of `FmAlsoDoItem` for the component catalogue: an expense ticked
 * with its icon and hint, a health event with fields and actions, a reminder
 * unticked, a warning, and a disabled one.
 */
import { Bell, HeartPulse, Wallet } from '@lucide/vue';
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

export default defineExamples(FmAlsoDoItem, [
    {
        name: 'Ticked, with icon and hint',
        props: {
            label: 'Create the expense of 84,20 €',
            hint: 'Already paid on 22/10 · Laura’s card',
            icon: Wallet,
            modelValue: true,
        },
    },
    {
        name: 'With fields and actions',
        props: {
            label: 'Add a health event',
            icon: HeartPulse,
            modelValue: true,
        },
        slots: {
            default: () =>
                h('p', { class: 'm-0 text-sm' }, 'The fields to correct'),
            actions: () =>
                h(
                    'button',
                    { type: 'button', class: 'font-semibold underline' },
                    'Back to the proposal',
                ),
        },
    },
    {
        name: 'Unticked',
        props: {
            label: 'Remind me to book the checkup',
            icon: Bell,
            modelValue: false,
        },
    },
    {
        name: 'With a warning',
        props: {
            label: 'Link the policy',
            warnings: ['More than one policy fits, so I link none.'],
            modelValue: true,
        },
    },
    {
        name: 'Disabled',
        props: {
            label: 'Create the expense of 84,20 €',
            icon: Wallet,
            modelValue: false,
            disabled: true,
        },
    },
]);
