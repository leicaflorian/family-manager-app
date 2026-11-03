/**
 * The cases of `FmReadingProgress` for the component catalogue: a file just
 * started, a file with its pages and one with the lines found so far.
 */
import { defineExamples } from '@/catalog/examples';
import FmReadingProgress from './FmReadingProgress.vue';

export default defineExamples(FmReadingProgress, [
    { name: 'Just started', props: { name: 'bolletta-edison-ottobre.pdf' } },
    {
        name: 'With the pages',
        props: { name: 'bolletta-edison-ottobre.pdf', pages: 2 },
    },
    {
        name: 'With what it found',
        props: {
            name: 'bolletta-edison-ottobre.pdf',
            pages: 1,
            found: [
                { label: 'From', value: 'Edison' },
                { label: 'Amount', value: '84,20 €' },
            ],
        },
    },
]);
