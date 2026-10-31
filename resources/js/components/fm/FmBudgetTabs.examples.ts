/**
 * The cases of `FmBudgetTabs` for the component catalogue: one for each page
 * of the budget that can be open.
 */
import { defineExamples } from '@/catalog/examples';
import FmBudgetTabs from './FmBudgetTabs.vue';

export default defineExamples(FmBudgetTabs, [
    { name: 'Movements', props: { current: 'movements' } },
    { name: 'Planned and spent', props: { current: 'comparison' } },
    { name: 'Planned expenses', props: { current: 'planned' } },
]);
