/**
 * The stories of `FmCardDocumentActions`: the
 * buttons beside a document on a card page, for an `admin` (can delete), for a
 * `member` and in the form of the card (only the menu).
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { healthCard } from '@/test/fixtures';
import FmCardDocumentActions from './FmCardDocumentActions.vue';

/** The card the document is linked to: the Panda. */
const card = { type: 'vehicle', id: 1 };

const meta = {
    title: 'Page sections/FmCardDocumentActions',
    component: FmCardDocumentActions,
} satisfies Meta<typeof FmCardDocumentActions>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Administrator: Story = {
    args: { document: healthCard, card, canDelete: true },
};

export const Member: Story = {
    args: { document: healthCard, card, canDelete: false },
};

export const InTheForm: Story = {
    name: 'In the form',
    args: { document: healthCard, card, canDelete: true, inForm: true },
};
