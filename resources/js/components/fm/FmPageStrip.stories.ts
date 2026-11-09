/**
 * The stories of `FmPageStrip`: the row of the phone, the column of the computer with the numbers, a single page,
 * which has no order to change, and pages turned by a quarter (the scanner turns a side only in the PDF).
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

/** A small sheet with a title line, as a picture. */
const sheet = `data:image/svg+xml,${encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="52" height="70"><rect width="52" height="70" fill="white"/><rect x="6" y="6" width="26" height="4" fill="#3b6fd8"/><rect x="6" y="16" width="40" height="2" fill="#bbb"/></svg>',
)}`;

export const TurnedPage: Story = {
    name: 'Turned page',
    args: {
        pages: [
            { id: 'a', url: sheet, rotation: 90 },
            { id: 'b', url: sheet },
        ],
        orientation: 'column',
    },
};
