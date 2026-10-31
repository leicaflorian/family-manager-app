/**
 * The cases of `FmNewTabLink` for the component catalogue: an organization and
 * a person.
 */
import { defineExamples } from '@/catalog/examples';
import FmNewTabLink from './FmNewTabLink.vue';

export default defineExamples(FmNewTabLink, [
    {
        name: 'Organization',
        props: { href: '/organizations/1', text: 'Edison' },
    },
    { name: 'Person', props: { href: '/people/1', text: 'Marco Bianchi' } },
]);
