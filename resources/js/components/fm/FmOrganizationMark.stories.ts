/**
 * The stories of `FmOrganizationMark`: the icon of a
 * bank, of a utility provider, and a logo.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmOrganizationMark from './FmOrganizationMark.vue';

/** A small inline picture, so the case needs no file. */
const image =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><rect width="8" height="8" fill="#2f6fed"/><circle cx="4" cy="3" r="1.5" fill="#fff"/><rect x="1.5" y="5" width="5" height="3" rx="1.5" fill="#fff"/></svg>',
    );

const meta = {
    title: 'Basics/FmOrganizationMark',
    component: FmOrganizationMark,
} satisfies Meta<typeof FmOrganizationMark>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Bank: Story = {
    args: { types: ['bank'], name: 'BPER Banca' },
};

export const UtilityProvider: Story = {
    name: 'Utility provider',
    args: { types: ['utility_provider'], name: 'Edison' },
};

export const WithALogo: Story = {
    name: 'With a logo',
    args: {
        types: ['bank'],
        name: 'BPER Banca',
        logo: image,
    },
};
