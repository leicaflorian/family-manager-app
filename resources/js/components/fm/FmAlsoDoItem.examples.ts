/**
 * The cases of `FmAlsoDoItem` for the component catalogue: ticked with a
 * hint, with a warning, with fields and actions, and disabled.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

export default defineExamples(FmAlsoDoItem, [
    {
        name: 'Ticked, with a hint',
        props: {
            label: 'Create the expense of 84,20 €',
            hint: 'It goes in Home, with the bill.',
            modelValue: true,
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
        name: 'With fields and actions',
        props: { label: 'Pay the deadline of 15/11/2026', modelValue: true },
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
        props: { label: 'Create the expense of 84,20 €', modelValue: false },
    },
    {
        name: 'Disabled',
        props: {
            label: 'Create the expense of 84,20 €',
            modelValue: false,
            disabled: true,
        },
    },
]);
