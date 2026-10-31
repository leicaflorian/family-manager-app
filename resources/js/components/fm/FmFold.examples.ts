/**
 * The cases of `FmFold` for the component catalogue: the closed cards and the
 * planned expenses that ended, folded. Press the button to open the rows.
 */
import { defineExamples } from '@/catalog/examples';
import FmFold from './FmFold.vue';

export default defineExamples(FmFold, [
    {
        name: 'Closed cards',
        props: { label: 'Closed (2)' },
        slots: { default: 'Luce - Enel, Gas - Enel' },
    },
    {
        name: 'Ended expenses',
        props: { label: 'Ended (1)' },
        slots: { default: 'Palestra Giulia' },
    },
]);
