/**
 * The cases of `FmSearchField` for the component catalogue: empty, and with a
 * text already typed.
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
]);
