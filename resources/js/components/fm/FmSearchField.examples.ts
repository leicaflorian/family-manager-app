/**
 * The cases of `FmSearchField` for the component catalogue: empty, with a
 * text already typed, and small as in a toolbar.
 */
import { defineExamples } from '@/catalog/examples';
import FmSearchField from './FmSearchField.vue';

export default defineExamples(FmSearchField, [
    {
        name: 'Empty',
        props: { label: 'Search the documents', modelValue: '' },
    },
    {
        name: 'Typed',
        props: { label: 'Search the documents', modelValue: 'bolletta' },
    },
    {
        name: 'Small, in a toolbar',
        props: { label: 'Search a reminder', modelValue: '', small: true },
    },
]);
