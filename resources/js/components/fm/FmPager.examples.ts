/**
 * The cases of `FmPager` for the component catalogue: a page in the middle,
 * the first page, the last page and a single page (no links).
 */
import { defineExamples } from '@/catalog/examples';
import FmPager from './FmPager.vue';

export default defineExamples(FmPager, [
    {
        name: 'Middle page',
        props: { previous: '/budget?page=1', next: '/budget?page=3' },
    },
    { name: 'First page', props: { previous: null, next: '/budget?page=2' } },
    { name: 'Last page', props: { previous: '/budget?page=2', next: null } },
    { name: 'Single page', props: { previous: null, next: null } },
]);
