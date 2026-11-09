/**
 * The stories of `FmPageStrip`: the row of the phone, the column of the computer with the numbers, and a
 * single page, which has no order to change.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmPageStrip from './FmPageStrip.vue';

const pages = [
    { id: 'a', url: null },
    { id: 'b', url: null },
    { id: 'c', url: null },
];

const meta = {
    title: 'Files/FmPageStrip',
    component: FmPageStrip,
    args: { pages, selected: 'a', orientation: 'row' },
} satisfies Meta<typeof FmPageStrip>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Row: Story = {};

export const Column: Story = {
    args: { orientation: 'column', selected: 'b' },
};

export const SinglePage: Story = {
    name: 'Single page',
    args: { pages: [pages[0]] },
};
