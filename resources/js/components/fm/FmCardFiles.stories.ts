/**
 * The stories of `FmCardFiles`: the documents of a
 * card beside its form, for a new card, with linked documents, with the
 * documents that can be linked, and with files refused by the server.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { healthCard } from '@/test/fixtures';
import FmCardFiles from './FmCardFiles.vue';

/** The card the documents belong to: the Panda. */
const card = { type: 'vehicle', id: 1 };

/** Another document of the Bianchis. */
const policy: App.Data.DocumentData = {
    ...healthCard,
    id: 20,
    title: 'Polizza RC auto Panda',
    category: 'Assicurazioni',
    person: null,
    organization: 'UnipolSai',
    expiry_date: '2026-11-20',
};

const meta = {
    title: 'Page sections/FmCardFiles',
    component: FmCardFiles,
} satisfies Meta<typeof FmCardFiles>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NewCard: Story = {
    name: 'New card',
};

export const LinkedDocuments: Story = {
    name: 'Linked documents',
    args: { documents: [healthCard, policy], card, canDelete: true },
};

export const Member: Story = {
    args: { documents: [healthCard, policy], card, canDelete: false },
};

export const DocumentsToLink: Story = {
    name: 'Documents to link',
    args: {
        documents: [healthCard],
        card,
        hasLinkable: true,
        linkable: [policy],
    },
};

export const FilesRefused: Story = {
    name: 'Files refused',
    args: {
        card,
        errors: ['Il file Contratto.exe non è di un tipo accettato.'],
    },
};
