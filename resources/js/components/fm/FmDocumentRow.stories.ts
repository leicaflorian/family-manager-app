/**
 * The stories of `FmDocumentRow`: a document in a
 * list, as a link, with its expiry (ahead and passed), without the date and
 * as the one open beside the list.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { healthCard } from '@/test/fixtures';
import FmDocumentRow from './FmDocumentRow.vue';

const meta = {
    title: 'Rows/FmDocumentRow',
    component: FmDocumentRow,
} satisfies Meta<typeof FmDocumentRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Link: Story = {
    args: { document: healthCard, href: '/documents/18' },
};

export const ExpiryAhead: Story = {
    name: 'Expiry ahead',
    args: {
        document: { ...healthCard, expiry_date: fromToday(40) },
        href: '/documents/18',
        expiry: true,
    },
};

export const Expired: Story = {
    args: {
        document: { ...healthCard, expiry_date: fromToday(-10) },
        href: '/documents/18',
        expiry: true,
    },
};

export const WithoutDate: Story = {
    name: 'Without date',
    args: { document: healthCard, href: '/documents/18', dateless: true },
};

export const Current: Story = {
    args: { document: healthCard, href: '/documents/18', current: true },
};

export const LongTitle: Story = {
    name: 'Long title',
    args: {
        document: {
            ...healthCard,
            title: 'Contratto di locazione dell’appartamento di via Garibaldi 12, Milano - rinnovo 2026',
            organization: 'Agenzia Immobiliare Rossi e Figli',
        },
        href: '/documents/19',
    },
};
