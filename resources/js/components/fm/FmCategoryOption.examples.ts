/**
 * The cases of `FmCategoryOption` for the component catalogue: a main
 * category, a subcategory indented under it and a plain choice such as *All*.
 */
import { defineExamples } from '@/catalog/examples';
import FmCategoryOption from './FmCategoryOption.vue';

export default defineExamples(FmCategoryOption, [
    {
        name: 'Main category',
        props: { option: { label: 'Lavoro', name: 'Lavoro', depth: 0 } },
    },
    {
        name: 'Subcategory',
        props: {
            option: {
                label: 'Lavoro › Buste paga',
                name: 'Buste paga',
                depth: 1,
            },
        },
    },
    { name: 'Plain choice', props: { option: { label: 'All' } } },
]);
