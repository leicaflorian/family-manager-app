/**
 * The cases of `FmAvatar` for the component catalogue: the initials, another
 * colour, a small one and a photo.
 */
import { defineExamples } from '@/catalog/examples';
import FmAvatar from './FmAvatar.vue';

/** A small inline picture, so the case needs no file. */
const image =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><rect width="8" height="8" fill="#2f6fed"/><circle cx="4" cy="3" r="1.5" fill="#fff"/><rect x="1.5" y="5" width="5" height="3" rx="1.5" fill="#fff"/></svg>',
    );

export default defineExamples(FmAvatar, [
    { name: 'Initials', props: { initials: 'MB', name: 'Marco Bianchi' } },
    {
        name: 'Another colour',
        props: { initials: 'LB', name: 'Laura Bianchi', color: 'bg-paid' },
    },
    {
        name: 'Small',
        props: { initials: 'GB', name: 'Giulia Bianchi', size: 32 },
    },
    {
        name: 'With a photo',
        props: {
            initials: 'MB',
            name: 'Marco Bianchi',
            photo: image,
        },
    },
]);
