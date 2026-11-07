/**
 * The cases of `FmAlsoDo` for the component catalogue: the box with two
 * items, and with another title.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmAlsoDo from './FmAlsoDo.vue';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

const items = () => [
    h(FmAlsoDoItem, {
        label: 'Create the expense of 84,20 €',
        hint: 'It goes in Home, with the bill.',
        modelValue: true,
    }),
    h(FmAlsoDoItem, { label: 'Link the policy', modelValue: false }),
];

export default defineExamples(FmAlsoDo, [
    { name: 'Two items', props: {}, slots: { default: items } },
    {
        name: 'Another title',
        props: { title: 'And also' },
        slots: { default: items },
    },
]);
