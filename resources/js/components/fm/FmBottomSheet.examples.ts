/**
 * The cases of `FmBottomSheet` for the component catalogue: the panel with a
 * short text and with a long one, opened with *Open* on the catalogue page.
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
]);
