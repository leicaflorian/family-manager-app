/**
 * The cases of `FmBackLink` for the component catalogue: a short name and a
 * long one.
 */
import { defineExamples } from '@/catalog/examples';
import FmBackLink from './FmBackLink.vue';

export default defineExamples(FmBackLink, [
    { name: 'Short', props: { href: '/documents', label: 'Documents' } },
    {
        name: 'Long name',
        props: {
            href: '/cards/1',
            label: 'Contratto di locazione di via Garibaldi 12, Milano',
        },
    },
]);
