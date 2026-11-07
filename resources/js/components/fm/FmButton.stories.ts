/**
 * Stories of `FmButton` for Storybook.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmButton from './FmButton.vue';

const meta = {
    title: 'Basics/FmButton',
    component: FmButton,
} satisfies Meta<typeof FmButton>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The main button of a page. */
export const Solid: Story = {
    args: { variant: 'solid' },
    render: (args) => ({
        components: { FmButton },
        setup: () => ({ args }),
        template: '<FmButton v-bind="args">Save</FmButton>',
    }),
};
