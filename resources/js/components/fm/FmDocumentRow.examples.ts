/**
 * The cases of `FmDocumentRow` for the component catalogue: a document in a
 * list, as a link, with its expiry (ahead and passed), without the date and
 * as the one open beside the list.
 */
import { fromToday } from '@/catalog/dates';
import { defineExamples } from '@/catalog/examples';
import { healthCard } from '@/test/fixtures';
import FmDocumentRow from './FmDocumentRow.vue';

export default defineExamples(FmDocumentRow, [
    {
        name: 'Link',
        props: { document: healthCard, href: '/documents/18' },
    },
    {
        name: 'Expiry ahead',
        props: {
            document: { ...healthCard, expiry_date: fromToday(40) },
            href: '/documents/18',
            expiry: true,
        },
    },
    {
        name: 'Expired',
        props: {
            document: { ...healthCard, expiry_date: fromToday(-10) },
            href: '/documents/18',
            expiry: true,
        },
    },
    {
        name: 'Without date',
        props: { document: healthCard, href: '/documents/18', dateless: true },
    },
    {
        name: 'Current',
        props: { document: healthCard, href: '/documents/18', current: true },
    },
    {
        name: 'Long title',
        props: {
            document: {
                ...healthCard,
                title: 'Contratto di locazione dell’appartamento di via Garibaldi 12, Milano - rinnovo 2026',
                organization: 'Agenzia Immobiliare Rossi e Figli',
            },
            href: '/documents/19',
        },
    },
]);
