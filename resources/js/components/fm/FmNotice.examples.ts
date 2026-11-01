/**
 * The cases of `FmNotice` for the component catalogue: the four tones, a title
 * and an action.
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
    {
        name: 'Danger',
        props: { tone: 'danger' },
        slots: { default: 'The insurance expired 3 days ago.' },
    },
    {
        name: 'With a title and an action',
        props: { tone: 'warning', title: 'Due in 26 days' },
        slots: {
            default: 'The reminder went out on 07/10.',
            action: '<a href="#">Upload the new one</a>',
        },
    },
]);
