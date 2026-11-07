/**
 * The cases of `FmAlsoDo` for the component catalogue: the box with two
 * items, and with another title.
 */
import { HeartPulse, Wallet } from '@lucide/vue';
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmAlsoDo from './FmAlsoDo.vue';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

const items = () => [
    h(FmAlsoDoItem, {
        label: 'Create the expense of 84,20 €',
        hint: 'Already paid on 22/10 · Laura’s card',
        icon: Wallet,
        modelValue: true,
    }),
    h(FmAlsoDoItem, {
        label: 'Add a health event',
        icon: HeartPulse,
        modelValue: false,
    }),
];

export default defineExamples(FmAlsoDo, [
    { name: 'Two items', props: {}, slots: { default: items } },
    {
        name: 'Another title',
        props: { title: 'And also' },
        slots: { default: items },
    },
]);
