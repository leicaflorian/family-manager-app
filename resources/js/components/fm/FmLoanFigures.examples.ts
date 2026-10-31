/**
 * The cases of `FmLoanFigures` for the component catalogue: the totals of a
 * loan, one without its cost and one not yet worked out.
 */
import { defineExamples } from '@/catalog/examples';
import FmLoanFigures from './FmLoanFigures.vue';

export default defineExamples(FmLoanFigures, [
    {
        name: 'Complete',
        props: { figures: { total_to_pay: 9072, cost: 1072 } },
    },
    {
        name: 'Without the cost',
        props: { figures: { total_to_pay: 9072, cost: null } },
    },
    { name: 'Not worked out', props: { figures: null } },
]);
