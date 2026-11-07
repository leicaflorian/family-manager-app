/**
 * The stories of `FmActions`: one action, four (the
 * last goes in the menu), and with a menu-only action that deletes.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { Download, Pencil, Plus, Trash2, Upload } from '@lucide/vue';
import FmActions from './FmActions.vue';

const meta = {
    title: 'Basics/FmActions',
    component: FmActions,
} satisfies Meta<typeof FmActions>;

export default meta;

type Story = StoryObj<typeof meta>;

export const OneAction: Story = {
    name: 'One action',
    args: {
        actions: [{ label: 'Upload', icon: Upload, href: '/upload' }],
    },
};

export const FourActions: Story = {
    name: 'Four actions',
    args: {
        actions: [
            { label: 'Edit', icon: Pencil, href: '/documents/5/edit' },
            { label: 'Add a person', icon: Plus, href: '/people/create' },
            { label: 'Upload', icon: Upload, href: '/upload' },
            {
                label: 'Download',
                icon: Download,
                href: '/documents/5/file',
                download: true,
            },
        ],
    },
};

export const WithADeleteInTheMenu: Story = {
    name: 'With a delete in the menu',
    args: {
        actions: [
            { label: 'Edit', icon: Pencil, href: '/documents/5/edit' },
            {
                label: 'Delete',
                icon: Trash2,
                variant: 'danger',
                menuOnly: true,
                confirm: {
                    url: '/documents/5',
                    question: 'Delete this document?',
                    text: 'The file goes too.',
                    confirmLabel: 'Delete the document',
                },
            },
        ],
    },
};
