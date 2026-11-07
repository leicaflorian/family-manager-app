/**
 * The stories of `FmToSortLink`: three files, one file and many.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmToSortLink from './FmToSortLink.vue';

const meta = {
    title: 'Navigation/FmToSortLink',
    component: FmToSortLink,
    // Three files wait in *To sort*, as the server shares it with every page
    parameters: { page: { to_sort_count: 3 } },
} satisfies Meta<typeof FmToSortLink>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The link beside the title of *Documents*, with three files waiting. */
export const ThreeFiles: Story = {
    args: { count: 3 },
};

export const OneFile: Story = {
    name: 'One file',
    args: { count: 1 },
};

export const ManyFiles: Story = {
    name: 'Many files',
    args: { count: 4 },
};
