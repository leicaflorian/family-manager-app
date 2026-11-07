/**
 * The stories of `FmDocumentActions`: the buttons
 * of a document for an admin, for a member who cannot delete or edit, and in
 * the compact form of the panel beside the list.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { documentDetail } from '@/test/fixtures';
import FmDocumentActions from './FmDocumentActions.vue';

const meta = {
    title: 'Files/FmDocumentActions',
    component: FmDocumentActions,
} satisfies Meta<typeof FmDocumentActions>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Admin: Story = {
    args: { document: documentDetail },
};

export const Member: Story = {
    args: {
        document: {
            ...documentDetail,
            can_edit: true,
            can_delete: false,
        },
    },
};

export const ReadOnly: Story = {
    name: 'Read only',
    args: {
        document: {
            ...documentDetail,
            can_edit: false,
            can_delete: false,
        },
    },
};

export const Compact: Story = {
    args: { document: documentDetail, compact: true },
};
