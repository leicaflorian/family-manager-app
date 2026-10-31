/**
 * The case of `FmConfirmDelete` for the component catalogue: the panel that
 * asks before deleting a document, opened with *Open* on the catalogue page.
 */
import { defineExamples } from '@/catalog/examples';
import FmConfirmDelete from './FmConfirmDelete.vue';

export default defineExamples(FmConfirmDelete, [
    {
        name: 'Delete a document',
        props: {
            open: false,
            url: '/documents/3',
            question: 'Delete this document?',
            text: 'The file goes too, and the cards it is linked to lose it.',
            confirmLabel: 'Delete the document',
        },
    },
]);
