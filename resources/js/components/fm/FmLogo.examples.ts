/**
 * The cases of `FmLogo` for the component catalogue: the logo on its square,
 * a big one, and the white drawing for the blue panel.
 */
import { defineExamples } from '@/catalog/examples';
import FmLogo from './FmLogo.vue';

export default defineExamples(FmLogo, [
    { name: 'Default', props: {} },
    { name: 'Big', props: { size: 72 } },
    { name: 'White', props: { white: true, size: 72 } },
]);
