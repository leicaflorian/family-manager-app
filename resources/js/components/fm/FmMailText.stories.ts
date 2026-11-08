/**
 * The stories of `FmMailText`: the text in its frame, with both versions,
 * with an original too large, and with no text kept. The frame shows a page
 * written here, as the route of the text would answer.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmMailText from './FmMailText.vue';

/** A page like the one the route of the text answers, for the frame of the stories. */
const sample = `data:text/html;charset=utf-8,${encodeURIComponent(
    '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{margin:16px;font:14px/1.6 sans-serif}</style></head>' +
        "<body>Gentile contribuente,<br>in allegato trova l'avviso di pagamento della TARI 2026. L'importo di 196,00 € si può pagare entro il 02/12/2026.</body></html>",
)}`;

const meta = {
    title: 'Files/FmMailText',
    component: FmMailText,
} satisfies Meta<typeof FmMailText>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TheText: Story = {
    name: 'The text',
    args: { src: sample },
};

export const PlainTextAndOriginal: Story = {
    name: 'Plain text and original',
    args: { src: sample, both: true },
};

export const OriginalTooLarge: Story = {
    name: 'Original too large',
    args: { src: sample, tooLarge: 1_468_006 },
};

export const NoTextKept: Story = {
    name: 'No text kept',
    args: { src: null },
};
