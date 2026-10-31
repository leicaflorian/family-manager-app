/**
 * The case of `FmConfirm` for the component catalogue: the panel that asks
 * again, opened with *Open* on the catalogue page.
 */
import { defineExamples } from '@/catalog/examples';
import FmConfirm from './FmConfirm.vue';

export default defineExamples(FmConfirm, [
    {
        name: 'Remove a link',
        props: {
            open: false,
            question: 'Remove this link?',
            text: 'The document stays, only the link goes away.',
            confirmLabel: 'Remove the link',
        },
    },
]);
