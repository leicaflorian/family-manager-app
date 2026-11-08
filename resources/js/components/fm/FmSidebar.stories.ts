/**
 * The stories of `FmSidebar`: Home, inside Areas on
 * an area, and Settings.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { areaCounts } from '@/test/fixtures';
import FmSidebar from './FmSidebar.vue';
import { navigationItems } from './navigation';

const shared = {
    items: navigationItems(),
    areas: areaCounts,
    family: 'Bianchi',
    user: 'Marco Bianchi',
};

const meta = {
    title: 'Navigation/FmSidebar',
    component: FmSidebar,
} satisfies Meta<typeof FmSidebar>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Home: Story = {
    args: { ...shared, active: 'home', path: '/' },
};

export const FilesToSort: Story = {
    name: 'Files to sort',
    args: { ...shared, active: 'home', path: '/', uploadCount: 4 },
};

export const InsideAnArea: Story = {
    name: 'Inside an area',
    args: { ...shared, active: 'areas', path: '/areas/home' },
};

export const Settings: Story = {
    args: { ...shared, active: 'settings', path: '/settings' },
};
