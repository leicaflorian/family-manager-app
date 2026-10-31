/**
 * The cases of `FmBarChart` for the component catalogue: the last six months
 * with October in progress, a short run of months and an empty year.
 */
import { defineExamples } from '@/catalog/examples';
import { monthBars } from '@/test/fixtures';
import FmBarChart from './FmBarChart.vue';

export default defineExamples(FmBarChart, [
    { name: 'Six months', props: { months: monthBars } },
    { name: 'Three months', props: { months: monthBars.slice(3) } },
    {
        name: 'Nothing spent',
        props: {
            months: monthBars.map((month) => ({
                ...month,
                spent: 0,
                planned: 0,
            })),
        },
    },
]);
