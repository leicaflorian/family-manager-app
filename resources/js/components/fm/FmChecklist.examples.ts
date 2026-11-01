/**
 * The cases of `FmChecklist` for the component catalogue: the first steps of a
 * new family, with one done and with none.
 */
import { defineExamples } from '@/catalog/examples';
import FmChecklist from './FmChecklist.vue';

const steps = [
    {
        title: 'The home bills',
        subtitle:
            'Electricity, gas, water and internet are already in the areas',
        href: '/areas',
        done: false,
    },
    {
        title: 'Upload the first bill',
        subtitle: 'Take a photo or choose the PDF',
        href: '/upload',
        done: false,
    },
    {
        title: 'Add the car, a policy or a subscription',
        subtitle: 'Each one is a card, with its due dates',
        href: '/cards/new',
        done: false,
    },
    {
        title: 'Invite Laura',
        subtitle: 'So she gets the alerts too',
        href: '/family/invitations',
        done: false,
    },
];

export default defineExamples(FmChecklist, [
    { name: 'Nothing done', props: { steps } },
    {
        name: 'One done',
        props: {
            steps: steps.map((step, index) => ({ ...step, done: index === 0 })),
        },
    },
]);
