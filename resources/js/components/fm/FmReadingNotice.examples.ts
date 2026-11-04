/**
 * The cases of `FmReadingNotice` for the component catalogue: a reading done, one that failed with its action,
 * and one that is off.
 */
import { defineExamples } from '@/catalog/examples';
import FmReadingNotice from './FmReadingNotice.vue';

export default defineExamples(FmReadingNotice, [
    {
        name: 'Done',
        props: { status: 'done', headline: 'I found an electricity bill' },
    },
    {
        name: 'Failed',
        props: { status: 'failed', error: 'The file could not be read.' },
        slots: { action: 'Read again' },
    },
    {
        name: 'Off',
        props: { status: 'off', error: 'Automatic reading is not on.' },
    },
]);
