/**
 * The cases of `FmCardDocumentActions` for the component catalogue: the
 * buttons beside a document on a card page, for an `admin` (can delete), for a
 * `member` and in the form of the card (only the menu).
 */
import { defineExamples } from '@/catalog/examples';
import { healthCard } from '@/test/fixtures';
import FmCardDocumentActions from './FmCardDocumentActions.vue';

/** The card the document is linked to: the Panda. */
const card = { type: 'vehicle', id: 1 };

export default defineExamples(FmCardDocumentActions, [
    {
        name: 'Administrator',
        props: { document: healthCard, card, canDelete: true },
    },
    {
        name: 'Member',
        props: { document: healthCard, card, canDelete: false },
    },
    {
        name: 'In the form',
        props: { document: healthCard, card, canDelete: true, inForm: true },
    },
]);
