/**
 * The stories of `FmFileTile`: the sheet has no
 * props, so there is one case.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmFileTile from './FmFileTile.vue';

const meta = {
    title: 'Files/FmFileTile',
    component: FmFileTile,
} satisfies Meta<typeof FmFileTile>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};
