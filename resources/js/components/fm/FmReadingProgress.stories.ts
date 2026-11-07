/**
 * The stories of `FmReadingProgress`: a file just
 * started, a file with its pages and one with the lines found so far.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmReadingProgress from './FmReadingProgress.vue';

const meta = {
    title: 'Basics/FmReadingProgress',
    component: FmReadingProgress,
} satisfies Meta<typeof FmReadingProgress>;

export default meta;

type Story = StoryObj<typeof meta>;

export const JustStarted: Story = {
    name: 'Just started',
    args: { name: 'bolletta-edison-ottobre.pdf' },
};

export const WithThePages: Story = {
    name: 'With the pages',
    args: { name: 'bolletta-edison-ottobre.pdf', pages: 2 },
};

export const WithWhatItFound: Story = {
    name: 'With what it found',
    args: {
        name: 'bolletta-edison-ottobre.pdf',
        pages: 1,
        found: [
            { label: 'From', value: 'Edison' },
            { label: 'Amount', value: '84,20 €' },
        ],
    },
};
