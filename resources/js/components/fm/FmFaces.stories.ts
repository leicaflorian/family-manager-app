/**
 * The stories of `FmFaces`: the three Bianchis, two
 * people, and a small row.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { peopleList } from '@/test/fixtures';
import FmFaces from './FmFaces.vue';

const meta = {
    title: 'Basics/FmFaces',
    component: FmFaces,
} satisfies Meta<typeof FmFaces>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TheFamily: Story = {
    name: 'The family',
    args: { people: peopleList },
};

export const TwoPeople: Story = {
    name: 'Two people',
    args: { people: peopleList.slice(0, 2) },
};

export const Small: Story = {
    args: { people: peopleList, size: 32 },
};
