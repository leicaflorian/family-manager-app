/**
 * The cases of `FmBell` for the component catalogue: nothing new, a few
 * notices and a lot of them.
 */
import { defineExamples } from '@/catalog/examples';
import FmBell from './FmBell.vue';

export default defineExamples(FmBell, [
    { name: 'Nothing new', props: { count: 0 } },
    { name: 'Two new', props: { count: 2 } },
    { name: 'Many', props: { count: 120 } },
]);
