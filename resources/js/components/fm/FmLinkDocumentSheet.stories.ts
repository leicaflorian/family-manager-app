/**
 * The stories of `FmLinkDocumentSheet`: the panel
 * with some documents to link, with one already chosen and hidden, with a
 * next page to load and with nothing found. Opened with *Open*.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { healthCard } from '@/test/fixtures';
import FmLinkDocumentSheet from './FmLinkDocumentSheet.vue';

/** Some documents of the Bianchis that can be linked. */
const documents: App.Data.DocumentData[] = [
    healthCard,
    {
        ...healthCard,
        id: 19,
        title: 'Carta d’identità',
        person: 'Marco',
        organization: 'Comune di Bologna',
        expiry_date: '2031-03-02',
    },
    {
        ...healthCard,
        id: 20,
        title: 'Polizza RC auto Panda',
        category: 'Assicurazioni',
        person: null,
        organization: 'UnipolSai',
        expiry_date: '2026-11-20',
    },
];

const meta = {
    title: 'Panels/FmLinkDocumentSheet',
    component: FmLinkDocumentSheet,
} satisfies Meta<typeof FmLinkDocumentSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Documents: Story = {
    args: { open: false, linkable: documents },
};

export const OneAlreadyChosen: Story = {
    name: 'One already chosen',
    args: { open: false, linkable: documents, hidden: [18] },
};

export const NextPageToLoad: Story = {
    name: 'Next page to load',
    args: { open: false, linkable: documents, linkableNext: 2 },
};

export const Searching: Story = {
    args: {
        open: false,
        linkable: [documents[1]],
        linkableSearch: 'carta',
    },
};

export const NothingFound: Story = {
    name: 'Nothing found',
    args: { open: false, linkable: [] },
};
