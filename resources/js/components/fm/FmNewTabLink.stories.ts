/**
 * The stories of `FmNewTabLink`: an organization and
 * a person.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmNewTabLink from './FmNewTabLink.vue';

const meta = {
    title: 'Navigation/FmNewTabLink',
    component: FmNewTabLink,
} satisfies Meta<typeof FmNewTabLink>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Organization: Story = {
    args: { href: '/organizations/1', text: 'Edison' },
};

export const Person: Story = {
    args: { href: '/people/1', text: 'Marco Bianchi' },
};
