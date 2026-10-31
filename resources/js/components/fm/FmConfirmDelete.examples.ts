/**
 * The case of `FmConfirmDelete` for the component catalogue: the panel open,
 * before deleting a document.
 */
import { defineExamples } from '@/catalog/examples';
import FmConfirmDelete from './FmConfirmDelete.vue';

export default defineExamples(FmConfirmDelete, [
    {
        name: 'Open',
        props: {
            open: true,
            url: '/documents/3',
            question: 'Delete this document?',
            text: 'The file goes too, and the cards it is linked to lose it.',
            confirmLabel: 'Delete the document',
        },
    },
]);
