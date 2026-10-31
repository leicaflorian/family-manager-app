/**
 * The cases of `FmOrganizationMark` for the component catalogue: the icon of a
 * bank, of a utility provider, and a logo.
 */
import { defineExamples } from '@/catalog/examples';
import FmOrganizationMark from './FmOrganizationMark.vue';

export default defineExamples(FmOrganizationMark, [
    { name: 'Bank', props: { types: ['bank'], name: 'BPER Banca' } },
    {
        name: 'Utility provider',
        props: { types: ['utility_provider'], name: 'Edison' },
    },
    {
        name: 'With a logo',
        props: {
            types: ['bank'],
            name: 'BPER Banca',
            logo: '/images/bper.png',
        },
    },
]);
