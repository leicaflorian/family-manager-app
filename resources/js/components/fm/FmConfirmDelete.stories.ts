/**
 * The stories of `FmConfirmDelete`: the panel that
 * asks before deleting a document, opened with the `open` control.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmConfirmDelete from './FmConfirmDelete.vue';

const meta = {
    title: 'Basics/FmConfirmDelete',
    component: FmConfirmDelete,
} satisfies Meta<typeof FmConfirmDelete>;

export default meta;

type Story = StoryObj<typeof meta>;

export const DeleteADocument: Story = {
    name: 'Delete a document',
    args: {
        open: false,
        url: '/documents/3',
        question: 'Delete this document?',
        text: 'The file goes too, and the cards it is linked to lose it.',
        confirmLabel: 'Delete the document',
    },
};
