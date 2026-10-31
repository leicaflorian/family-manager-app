/**
 * The cases of `FmActiveFilter` for the component catalogue: a short filter
 * and a long one.
 */
import { defineExamples } from '@/catalog/examples';
import FmActiveFilter from './FmActiveFilter.vue';

export default defineExamples(FmActiveFilter, [
    { name: 'Person', props: { label: 'Laura' } },
    { name: 'Long label', props: { label: 'Lavoro › Buste paga › Altro' } },
]);
