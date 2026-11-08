/**
 * The stories of `FmAvatar`: the initials, another
 * colour, a small one and a photo.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmAvatar from './FmAvatar.vue';

/** A small inline picture, so the case needs no file. */
const image =
    'data:image/svg+xml,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"><rect width="8" height="8" fill="#2f6fed"/><circle cx="4" cy="3" r="1.5" fill="#fff"/><rect x="1.5" y="5" width="5" height="3" rx="1.5" fill="#fff"/></svg>',
    );

const meta = {
    title: 'Basics/FmAvatar',
    component: FmAvatar,
} satisfies Meta<typeof FmAvatar>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Initials: Story = {
    args: { initials: 'MB', name: 'Marco Bianchi' },
};

export const AnotherColour: Story = {
    name: 'Another colour',
    args: { initials: 'LB', name: 'Laura Bianchi', color: 'bg-paid' },
};

export const Small: Story = {
    args: { initials: 'GB', name: 'Giulia Bianchi', size: 32 },
};

export const WithAPhoto: Story = {
    name: 'With a photo',
    args: {
        initials: 'MB',
        name: 'Marco Bianchi',
        photo: image,
    },
};
