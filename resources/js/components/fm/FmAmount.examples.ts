/**
 * The cases of `FmAmount` for the component catalogue: an amount, an
 * estimate, an amount changed from its planned one and an income.
 */
import { defineExamples } from '@/catalog/examples';
import FmAmount from './FmAmount.vue';

export default defineExamples(FmAmount, [
    { name: 'Expense', props: { amount: 84.2 } },
    { name: 'Estimated', props: { amount: 71.3, estimated: true } },
    { name: 'Changed from the planned', props: { amount: 62.4, base: 72 } },
    { name: 'Income', props: { amount: 1850, income: true } },
]);
