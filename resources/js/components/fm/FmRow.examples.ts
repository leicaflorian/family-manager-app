/**
 * The cases of `FmRow` for the component catalogue: a link, an overdue
 * deadline, a button, the dot of the area, a long title and a value on the
 * right.
 */
import { defineExamples } from '@/catalog/examples';
import FmRow from './FmRow.vue';

export default defineExamples(FmRow, [
    {
        name: 'Link',
        props: {
            title: 'Bolletta luce ottobre',
            subtitle: 'Scade il 15/11/2026',
            href: '/cards/utility/1',
        },
    },
    {
        name: 'Overdue',
        props: {
            title: 'Bolletta luce ottobre',
            subtitle: 'Scade il 15/11/2026',
            href: '/cards/utility/1',
            tone: 'overdue',
        },
    },
    {
        name: 'Button',
        props: { title: 'Carica un documento', button: true },
    },
    {
        name: 'Area dot',
        props: {
            title: 'Assicurazione auto',
            subtitle: 'Scade il 03/02/2027',
            area: 'vehicles',
        },
    },
    {
        name: 'Long title',
        props: {
            title: 'Contratto di locazione dell’appartamento di via Garibaldi 12, Milano - rinnovo 2026',
            subtitle: 'Scade il 31/12/2028',
        },
    },
    {
        name: 'Done',
        props: { title: 'Carica la prima bolletta', done: true },
    },
    {
        name: 'With a value on the right',
        props: { title: 'Bolletta luce ottobre', subtitle: 'Pagata' },
        slots: { after: '84,20 €' },
    },
]);
