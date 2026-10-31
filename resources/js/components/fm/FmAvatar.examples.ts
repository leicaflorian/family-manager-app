/**
 * The cases of `FmAvatar` for the component catalogue: the initials, another
 * colour, a small one and a photo.
 */
import { defineExamples } from '@/catalog/examples';
import FmAvatar from './FmAvatar.vue';

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
            photo: '/images/marco.jpg',
        },
    },
]);
