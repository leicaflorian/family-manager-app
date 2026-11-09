/**
 * The stories of `FmQrCode`: a code drawn as the server draws it, and
 * a text that is not an SVG, which shows an empty frame.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { sampleQr } from '@/stories/qr';
import FmQrCode from './FmQrCode.vue';

const meta = {
    title: 'Figures/FmQrCode',
    component: FmQrCode,
} satisfies Meta<typeof FmQrCode>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Code: Story = {
    args: { svg: sampleQr, label: 'Code to link the phone' },
};

export const NotADrawing: Story = {
    name: 'Not a drawing',
    args: { svg: '<p>not an svg</p>', label: 'Code to link the phone' },
};
