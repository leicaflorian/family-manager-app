/**
 * The cases of `FmSectionLink` for the component catalogue: a link, a link
 * with an icon and a button without an address.
 */
import { Plus } from '@lucide/vue';
import { defineExamples } from '@/catalog/examples';
import FmSectionLink from './FmSectionLink.vue';

export default defineExamples(FmSectionLink, [
    {
        name: 'Link',
        props: { href: '/budget/planned' },
        slots: { default: 'See all the bills, year by year' },
    },
    {
        name: 'With an icon',
        props: { href: '/budget/planned/create', icon: Plus },
        slots: { default: 'Add a planned expense' },
    },
    { name: 'Button', props: {}, slots: { default: 'Show more' } },
]);
