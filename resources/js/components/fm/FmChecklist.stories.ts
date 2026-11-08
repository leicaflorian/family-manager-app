/**
 * The stories of `FmChecklist`: the first steps of a
 * new family, with one done and with none.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
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

const meta = {
    title: 'Page sections/FmChecklist',
    component: FmChecklist,
} satisfies Meta<typeof FmChecklist>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NothingDone: Story = {
    name: 'Nothing done',
    args: { steps },
};

export const OneDone: Story = {
    name: 'One done',
    args: {
        steps: steps.map((step, index) => ({ ...step, done: index === 0 })),
    },
};
