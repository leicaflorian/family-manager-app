/**
 * The stories of `FmSearchField`: empty, with a text already typed, in focus
 * and small as in a toolbar.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmSearchField from './FmSearchField.vue';

const meta = {
    title: 'Fields/FmSearchField',
    component: FmSearchField,
} satisfies Meta<typeof FmSearchField>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Empty: Story = {
    args: { label: 'Search a document', modelValue: '' },
};

export const Typed: Story = {
    args: { label: 'Search a document', modelValue: 'bolletta' },
};

/** The box has no ring of its own: the border stays `edge` and the caret shows the focus. */
export const Focus: Story = {
    args: { label: 'Search a document', modelValue: '' },
    parameters: { pseudo: { focusWithin: true } },
};

export const SmallInAToolbar: Story = {
    name: 'Small, in a toolbar',
    args: { label: 'Search a reminder', modelValue: '', small: true },
};
