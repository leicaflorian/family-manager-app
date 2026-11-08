/**
 * The stories of `FmUploadSheet`: the panel to
 * upload files to a card, opened with the `open` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmUploadSheet from './FmUploadSheet.vue';

const meta = {
    title: 'Panels/FmUploadSheet',
    component: FmUploadSheet,
} satisfies Meta<typeof FmUploadSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const UploadToACard: Story = {
    name: 'Upload to a card',
    args: { open: false, link: { type: 'vehicle', id: 1 } },
};
