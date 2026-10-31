/**
 * The cases of `FmDocumentActions` for the component catalogue: the buttons
 * of a document for an admin, for a member who cannot delete or edit, and in
 * the compact form of the panel beside the list.
 */
import { defineExamples } from '@/catalog/examples';
import { documentDetail } from '@/test/fixtures';
import FmDocumentActions from './FmDocumentActions.vue';

export default defineExamples(FmDocumentActions, [
    { name: 'Admin', props: { document: documentDetail } },
    {
        name: 'Member',
        props: {
            document: {
                ...documentDetail,
                can_edit: true,
                can_delete: false,
            },
        },
    },
    {
        name: 'Read only',
        props: {
            document: {
                ...documentDetail,
                can_edit: false,
                can_delete: false,
            },
        },
    },
    { name: 'Compact', props: { document: documentDetail, compact: true } },
]);
