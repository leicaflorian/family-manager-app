/**
 * The stories of `FmBottomBar`: Home marked, and
 * More marked because Family is open.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmBottomBar from './FmBottomBar.vue';
import { navigationItems } from './navigation';

const items = navigationItems();

const meta = {
    title: 'Navigation/FmBottomBar',
    component: FmBottomBar,
} satisfies Meta<typeof FmBottomBar>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Home: Story = {
    args: { items, active: 'home' },
};

export const Documents: Story = {
    args: { items, active: 'documents' },
};

export const FilesToSort: Story = {
    name: 'Files to sort',
    args: { items, active: 'home', uploadCount: 4 },
};

export const MoreIsMarked: Story = {
    name: 'More is marked',
    args: { items, active: 'family' },
};

/** The + of Upload: 60 px, with the `fab` shadow, rising over the bar. */
export const UploadButton: Story = {
    name: 'Upload button',
    args: { items, active: 'home' },
};
