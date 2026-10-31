/**
 * The cases of `FmMonthSwitch` for the component catalogue: a month in the
 * middle of the year and one at the turn of the year.
 */
import { defineExamples } from '@/catalog/examples';
import FmMonthSwitch from './FmMonthSwitch.vue';

export default defineExamples(FmMonthSwitch, [
    { name: 'October', props: { month: '2026-10' } },
    { name: 'January', props: { month: '2027-01' } },
]);
