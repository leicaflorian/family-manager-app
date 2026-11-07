/**
 * The stories of `FmPager`: a page in the middle,
 * the first page, the last page and a single page (no links).
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmPager from './FmPager.vue';

const meta = {
    title: 'Navigation/FmPager',
    component: FmPager,
} satisfies Meta<typeof FmPager>;

export default meta;

type Story = StoryObj<typeof meta>;

export const MiddlePage: Story = {
    name: 'Middle page',
    args: { previous: '/budget?page=1', next: '/budget?page=3' },
};

export const FirstPage: Story = {
    name: 'First page',
    args: { previous: null, next: '/budget?page=2' },
};

export const LastPage: Story = {
    name: 'Last page',
    args: { previous: '/budget?page=2', next: null },
};

export const SinglePage: Story = {
    name: 'Single page',
    args: { previous: null, next: null },
};
