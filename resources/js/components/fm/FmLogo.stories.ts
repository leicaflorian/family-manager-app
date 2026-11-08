/**
 * The stories of `FmLogo`: the logo on its square,
 * a big one, and the white drawing for the blue panel.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmLogo from './FmLogo.vue';

const meta = {
    title: 'Basics/FmLogo',
    component: FmLogo,
} satisfies Meta<typeof FmLogo>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Big: Story = {
    args: { size: 72 },
};

export const White: Story = {
    args: { white: true, size: 72 },
};
