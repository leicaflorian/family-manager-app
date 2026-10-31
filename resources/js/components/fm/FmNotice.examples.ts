/**
 * The cases of `FmNotice` for the component catalogue: the three tones.
 */
import { defineExamples } from '@/catalog/examples';
import FmNotice from './FmNotice.vue';

export default defineExamples(FmNotice, [
    {
        name: 'Information',
        props: { tone: 'info' },
        slots: {
            default: 'The files are kept for you, only the family sees them.',
        },
    },
    {
        name: 'Done',
        props: { tone: 'ok' },
        slots: { default: 'We sent the invitation to laura@example.com.' },
    },
    {
        name: 'Warning',
        props: { tone: 'warning' },
        slots: { default: 'This bill expired 3 days ago.' },
    },
]);
