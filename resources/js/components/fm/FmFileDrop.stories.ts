/**
 * The stories of `FmFileDrop`: the large zone, with a file dragged over it,
 * the compact one below a form, a custom label and the zone while it sends.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fireEvent, within } from 'storybook/test';
import FmFileDrop from './FmFileDrop.vue';

const meta = {
    title: 'Fields/FmFileDrop',
    component: FmFileDrop,
} satisfies Meta<typeof FmFileDrop>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** A file is dragged over the zone: the border and the background turn blue. */
export const DraggedOver: Story = {
    name: 'File dragged over',
    play: async ({ canvasElement }) => {
        const zone = within(canvasElement)
            .getByLabelText(/Scegli i file|Choose the files/)
            .closest('label');

        if (zone) {
            await fireEvent.dragOver(zone);
        }
    },
};

export const Compact: Story = {
    args: { compact: true },
};

export const CustomLabel: Story = {
    name: 'Custom label',
    args: { label: 'Add the payslip' },
};

export const Processing: Story = {
    args: { processing: true },
};
