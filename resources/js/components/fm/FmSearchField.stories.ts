/**
 * The stories of `FmSearchField`: empty, with a
 * text already typed, and small as in a toolbar.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmSearchField from './FmSearchField.vue';

const meta = {
    title: 'Forms/FmSearchField',
    component: FmSearchField,
} satisfies Meta<typeof FmSearchField>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Empty: Story = {
    args: { label: 'Search the documents', modelValue: '' },
};

export const Typed: Story = {
    args: { label: 'Search the documents', modelValue: 'bolletta' },
};

export const SmallInAToolbar: Story = {
    name: 'Small, in a toolbar',
    args: { label: 'Search a reminder', modelValue: '', small: true },
};
