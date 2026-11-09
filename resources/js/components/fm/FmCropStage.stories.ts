/**
 * The stories of `FmCropStage`: the photo with the corners of the sheet to move, the same photo lying on its
 * side, the cut out sheet, which shows the original when held, the cut out sheet with a badge on it, and the photo fitted in a
 * short box.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmCropStage from './FmCropStage.vue';

/** A photo of a sheet on a table, drawn as a picture. */
const photo = `data:image/svg+xml;utf8,${encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="350" height="470" viewBox="0 0 350 470"><rect width="350" height="470" fill="#6b5444"/><polygon points="59.5,56.4 294,42.3 308,413.6 45.5,427.7" fill="#fbfbf8"/><line x1="82" y1="85" x2="189" y2="78" stroke="#c9a7eb" stroke-width="10" stroke-linecap="round"/><line x1="81" y1="114" x2="239" y2="105" stroke="#d5dbe3" stroke-width="6" stroke-linecap="round"/><line x1="80" y1="137" x2="220" y2="128" stroke="#d5dbe3" stroke-width="6" stroke-linecap="round"/><line x1="79" y1="181" x2="270" y2="170" stroke="#d5dbe3" stroke-width="6" stroke-linecap="round"/></svg>',
)}`;

const meta = {
    title: 'Files/FmCropStage',
    component: FmCropStage,
    args: {
        image: photo,
        corners: [
            { x: 0.17, y: 0.12 },
            { x: 0.84, y: 0.09 },
            { x: 0.88, y: 0.88 },
            { x: 0.13, y: 0.91 },
        ],
        turns: 0,
        editing: true,
    },
    decorators: [
        () => ({
            template:
                '<div class="dark" style="max-width: 350px; padding: 16px; background: var(--camera-bg)"><story /></div>',
        }),
    ],
} satisfies Meta<typeof FmCropStage>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Editing: Story = {};

export const TurnedPhoto: Story = {
    name: 'Turned photo',
    args: {
        turns: 1,
        corners: [
            { x: 0.12, y: 0.17 },
            { x: 0.91, y: 0.13 },
            { x: 0.88, y: 0.84 },
            { x: 0.09, y: 0.87 },
        ],
    },
};

export const CutSheet: Story = {
    name: 'Cut sheet',
    args: { editing: false },
};

export const FitInAShortBox: Story = {
    name: 'Fit in a short box',
    args: { fit: true },
    decorators: [
        () => ({
            template:
                '<div style="height: 280px; display: flex; flex-direction: column"><story /></div>',
        }),
    ],
};

export const WithABadge: Story = {
    name: 'With a badge',
    args: { editing: false },
    render: (args) => ({
        components: { FmCropStage },
        setup: () => ({ args }),
        template:
            '<FmCropStage v-bind="args"><span class="absolute top-3 right-3 rounded-tag bg-paid-soft px-2.5 py-1 text-xs font-semibold text-paid">Cropped</span></FmCropStage>',
    }),
};
