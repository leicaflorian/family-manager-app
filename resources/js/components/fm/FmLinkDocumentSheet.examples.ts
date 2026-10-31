/**
 * The cases of `FmLinkDocumentSheet` for the component catalogue: the panel
 * with some documents to link, with one already chosen and hidden, with a
 * next page to load and with nothing found. Opened with *Open*.
 */
import { defineExamples } from '@/catalog/examples';
import { healthCard } from '@/test/fixtures';
import FmLinkDocumentSheet from './FmLinkDocumentSheet.vue';

/** Some documents of the Bianchis that can be linked. */
const documents: App.Data.DocumentData[] = [
    healthCard,
    {
        ...healthCard,
        id: 19,
        title: 'Carta d’identità',
        person: 'Marco',
        organization: 'Comune di Bologna',
        expiry_date: '2031-03-02',
    },
    {
        ...healthCard,
        id: 20,
        title: 'Polizza RC auto Panda',
        category: 'Assicurazioni',
        person: null,
        organization: 'UnipolSai',
        expiry_date: '2026-11-20',
    },
];

export default defineExamples(FmLinkDocumentSheet, [
    { name: 'Documents', props: { open: false, linkable: documents } },
    {
        name: 'One already chosen',
        props: { open: false, linkable: documents, hidden: [18] },
    },
    {
        name: 'Next page to load',
        props: { open: false, linkable: documents, linkableNext: 2 },
    },
    {
        name: 'Searching',
        props: {
            open: false,
            linkable: [documents[1]],
            linkableSearch: 'carta',
        },
    },
    { name: 'Nothing found', props: { open: false, linkable: [] } },
]);
