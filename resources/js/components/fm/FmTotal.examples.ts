/**
 * The cases of `FmTotal` for the component catalogue: with a late payment,
 * with none late, with more than one late and with nothing to pay.
 */
import { defineExamples } from '@/catalog/examples';
import FmTotal from './FmTotal.vue';

export default defineExamples(FmTotal, [
    { name: 'One late', props: { total: 1488.79, payments: 8, late: 1 } },
    { name: 'None late', props: { total: 84.2, payments: 1, late: 0 } },
    { name: 'More late', props: { total: 640.5, payments: 5, late: 2 } },
    { name: 'Nothing to pay', props: { total: 0, payments: 0, late: 0 } },
]);
