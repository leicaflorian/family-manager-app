/**
 * The cases of `FmEmpty` for the component catalogue: with only the title,
 * with the reason, and with a button.
 */
import { FileText } from '@lucide/vue';
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmButton from './FmButton.vue';
import FmEmpty from './FmEmpty.vue';

export default defineExamples(FmEmpty, [
    {
        name: 'Title only',
        props: { icon: FileText, title: 'No documents yet' },
    },
    {
        name: 'With the reason',
        props: {
            icon: FileText,
            title: 'No documents yet',
            text: 'Upload the first one and it will appear here.',
        },
    },
    {
        name: 'With a button',
        props: {
            icon: FileText,
            title: 'No documents yet',
            text: 'Upload the first one and it will appear here.',
        },
        slots: {
            default: () =>
                h(FmButton, { href: '/upload' }, () => 'Upload a document'),
        },
    },
]);
