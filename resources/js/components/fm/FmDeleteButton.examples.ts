/**
 * The cases of `FmDeleteButton` for the component catalogue: with the text,
 * compact (only the bin) and with another name.
 */
import { defineExamples } from '@/catalog/examples';
import FmDeleteButton from './FmDeleteButton.vue';

const asks = {
    url: '/documents/3',
    question: 'Delete this document?',
    text: 'The file goes too, and the cards it is linked to lose it.',
    confirmLabel: 'Delete the document',
};

export default defineExamples(FmDeleteButton, [
    { name: 'With the text', props: asks },
    { name: 'Compact', props: { ...asks, compact: true } },
    {
        name: 'With another name',
        props: { ...asks, label: 'Delete the copy', compact: true },
    },
]);
