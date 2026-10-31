/**
 * The cases of `FmCategoryBar` for the component catalogue: spent as planned,
 * an estimate, a late payment, over the plan and nothing planned.
 */
import { defineExamples } from '@/catalog/examples';
import FmCategoryBar from './FmCategoryBar.vue';

export default defineExamples(FmCategoryBar, [
    {
        name: 'As planned',
        props: {
            category: 'Casa e affitto',
            spent: 612,
            planned: 612,
            color: '#2563eb',
        },
    },
    {
        name: 'Estimated',
        props: {
            category: 'Spesa',
            spent: 150.1,
            planned: 320,
            estimated: true,
            color: '#16a34a',
        },
    },
    {
        name: 'Late',
        props: {
            category: 'Bollette e utenze',
            spent: 29.9,
            planned: 223.3,
            lateTitle: 'Luce',
            color: '#e35169',
        },
    },
    {
        name: 'Over the plan',
        props: { category: 'Svago', spent: 180, planned: 120 },
    },
    {
        name: 'Nothing planned',
        props: {
            category: 'Salute',
            spent: 108.4,
            planned: 0,
            color: '#d9467a',
        },
    },
]);
