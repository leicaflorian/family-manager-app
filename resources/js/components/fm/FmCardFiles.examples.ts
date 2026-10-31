/**
 * The cases of `FmCardFiles` for the component catalogue: the documents of a
 * card beside its form, for a new card, with linked documents, with the
 * documents that can be linked, and with files refused by the server.
 */
import { defineExamples } from '@/catalog/examples';
import { healthCard } from '@/test/fixtures';
import FmCardFiles from './FmCardFiles.vue';

/** The card the documents belong to: the Panda. */
const card = { type: 'vehicle', id: 1 };

/** Another document of the Bianchis. */
const policy: App.Data.DocumentData = {
    ...healthCard,
    id: 20,
    title: 'Polizza RC auto Panda',
    category: 'Assicurazioni',
    person: null,
    organization: 'UnipolSai',
    expiry_date: '2026-11-20',
};

export default defineExamples(FmCardFiles, [
    { name: 'New card', props: {} },
    {
        name: 'Linked documents',
        props: { documents: [healthCard, policy], card, canDelete: true },
    },
    {
        name: 'Member',
        props: { documents: [healthCard, policy], card, canDelete: false },
    },
    {
        name: 'Documents to link',
        props: {
            documents: [healthCard],
            card,
            hasLinkable: true,
            linkable: [policy],
        },
    },
    {
        name: 'Files refused',
        props: {
            card,
            errors: ['Il file Contratto.exe non è di un tipo accettato.'],
        },
    },
]);
