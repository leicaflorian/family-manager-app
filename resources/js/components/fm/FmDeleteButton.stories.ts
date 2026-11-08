/**
 * The stories of `FmDeleteButton`: with the text,
 * compact (only the bin) and with another name.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { userEvent, within } from 'storybook/test';
import FmDeleteButton from './FmDeleteButton.vue';

const asks = {
    url: '/documents/3',
    question: 'Delete this document?',
    text: 'The file goes too, and the cards it is linked to lose it.',
    confirmLabel: 'Delete the document',
};

const meta = {
    title: 'Basics/FmDeleteButton',
    component: FmDeleteButton,
} satisfies Meta<typeof FmDeleteButton>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Closed, as the page shows it: the panel opens with the tap. */
export const WithTheText: Story = {
    name: 'With the text',
    args: asks,
};

export const Compact: Story = {
    args: { ...asks, compact: true },
};

export const WithAnotherName: Story = {
    name: 'With another name',
    args: { ...asks, label: 'Delete the copy', compact: true },
};

/** The panel that asks again, opened as the person does: tapping *Delete*. */
export const AskingToConfirm: Story = {
    name: 'Asking to confirm',
    args: asks,
    play: async ({ canvasElement }) => {
        await userEvent.click(within(canvasElement).getByRole('button'));
    },
};
