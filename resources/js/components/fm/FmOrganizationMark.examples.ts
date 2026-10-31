/**
 * The cases of `FmOrganizationMark` for the component catalogue: the icon of a
 * bank, of a utility provider, and a logo.
 */
import { defineExamples } from '@/catalog/examples';
import FmOrganizationMark from './FmOrganizationMark.vue';

/** A small inline picture, so the case needs no file. */
const image =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><rect width="8" height="8" fill="#2f6fed"/><circle cx="4" cy="3" r="1.5" fill="#fff"/><rect x="1.5" y="5" width="5" height="3" rx="1.5" fill="#fff"/></svg>',
    );

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
            logo: image,
        },
    },
]);
