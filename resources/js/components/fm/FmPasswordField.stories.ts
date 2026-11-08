/**
 * The stories of `FmPasswordField`: empty, with a
 * hint, with an error of the server and for a new password.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmPasswordField from './FmPasswordField.vue';

const meta = {
    title: 'Fields/FmPasswordField',
    component: FmPasswordField,
} satisfies Meta<typeof FmPasswordField>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Empty: Story = {
    args: { label: 'Password', name: 'password' },
};

export const WithAHint: Story = {
    name: 'With a hint',
    args: {
        label: 'New password',
        name: 'new_password',
        help: 'At least 10 characters',
        autocomplete: 'new-password',
    },
};

export const WithAnError: Story = {
    name: 'With an error',
    args: {
        label: 'Password',
        name: 'password_error',
        modelValue: 'sbagliata',
        errors: ['The password is not correct.'],
    },
};
