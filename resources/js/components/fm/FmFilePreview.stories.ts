/**
 * The stories of `FmFilePreview`: the image the
 * server made of a PDF, an image as it is, a file without preview and an image
 * that does not load. The pictures are inline, so no file is needed.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmFilePreview from './FmFilePreview.vue';

/** A small inline picture of a page, as the server would draw a preview. */
const page =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="560" viewBox="0 0 420 560"><rect width="420" height="560" fill="#fff" stroke="#ccd"/><rect x="40" y="48" width="220" height="16" fill="#99a"/><rect x="40" y="96" width="340" height="10" fill="#ccd"/><rect x="40" y="120" width="300" height="10" fill="#ccd"/><rect x="40" y="144" width="320" height="10" fill="#ccd"/></svg>',
    );

const meta = {
    title: 'Files/FmFilePreview',
    component: FmFilePreview,
} satisfies Meta<typeof FmFilePreview>;

export default meta;

type Story = StoryObj<typeof meta>;

export const PDFPreview: Story = {
    name: 'PDF preview',
    args: {
        url: '/files/12',
        mimeType: 'application/pdf',
        name: 'Busta paga ottobre 2026.pdf',
        previewUrl: page,
    },
};

export const Image: Story = {
    args: {
        url: page,
        mimeType: 'image/svg+xml',
        name: 'Foto.svg',
        previewUrl: null,
    },
};

export const NoPreview: Story = {
    name: 'No preview',
    args: {
        url: '/files/13',
        mimeType: 'application/pdf',
        name: 'Contratto.pdf',
        previewUrl: null,
    },
};

export const ImageFailsToLoad: Story = {
    name: 'Image fails to load',
    args: {
        url: 'data:image/png;base64,broken',
        mimeType: 'image/png',
        name: 'Rotta.png',
        previewUrl: null,
    },
};
