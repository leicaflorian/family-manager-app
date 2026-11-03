/**
 * The cases of `FmToSortLink` for the component catalogue: one file and many.
 */
import { defineExamples } from '@/catalog/examples';
import FmToSortLink from './FmToSortLink.vue';

export default defineExamples(FmToSortLink, [
    { name: 'One file', props: { count: 1 } },
    { name: 'Many files', props: { count: 4 } },
]);
