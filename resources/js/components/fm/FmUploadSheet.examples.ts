/**
 * The case of `FmUploadSheet` for the component catalogue: the panel to
 * upload files to a card, opened with *Open* on the catalogue page.
 */
import { defineExamples } from '@/catalog/examples';
import FmUploadSheet from './FmUploadSheet.vue';

export default defineExamples(FmUploadSheet, [
    {
        name: 'Upload to a card',
        props: { open: false, link: { type: 'vehicle', id: 1 } },
    },
]);
