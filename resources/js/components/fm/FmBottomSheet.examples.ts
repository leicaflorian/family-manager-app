/**
 * The cases of `FmBottomSheet` for the component catalogue: the panel with a
 * short text, with a long one and with a question before closing, opened with
 * *Open* on the catalogue page.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmBottomSheet from './FmBottomSheet.vue';

export default defineExamples(FmBottomSheet, [
    {
        name: 'Short content',
        props: { open: false, title: 'Filters' },
        slots: { default: 'Choose what to show in the list.' },
    },
    {
        name: 'Long content',
        props: { open: false, title: 'Rules of the house' },
        slots: {
            default: () =>
                Array.from({ length: 24 }, (_, i) =>
                    h('p', `Voce numero ${i + 1} dell’elenco della famiglia.`),
                ),
        },
    },
    {
        // In the app the form inside asks «Leave without saving?»; here the
        // answer is always yes, so the panel closes
        name: 'Asks before closing',
        props: {
            open: false,
            title: 'Pay this deadline',
            beforeClose: () => Promise.resolve(true),
        },
        slots: { default: 'Closing it with the ×, Esc or outside asks first.' },
    },
    {
        name: 'Above the other panels',
        props: { open: false, title: 'Leave without saving?', above: true },
        slots: {
            default:
                'A question asked from an open panel goes over it, as FmConfirm does.',
        },
    },
]);
