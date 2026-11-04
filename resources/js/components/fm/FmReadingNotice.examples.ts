/**
 * The cases of `FmReadingNotice` for the component catalogue: a reading done, one that failed with *Read again*,
 * one that failed without a reason, and one that is off, with and without *Read again*.
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
        props: {
            status: 'failed',
            error: 'The file could not be read.',
            again: true,
        },
    },
    {
        name: 'Failed without a reason',
        props: { status: 'failed', error: null, again: true },
    },
    {
        name: 'Off',
        props: { status: 'off', error: 'Automatic reading is not on.' },
    },
    {
        name: 'Off, with Read again',
        props: {
            status: 'off',
            error: 'Automatic reading is not on.',
            again: true,
        },
    },
]);
