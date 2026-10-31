/**
 * The cases of `FmPasswordField` for the component catalogue: empty, with a
 * hint, with an error of the server and for a new password.
 */
import { defineExamples } from '@/catalog/examples';
import FmPasswordField from './FmPasswordField.vue';

export default defineExamples(FmPasswordField, [
    { name: 'Empty', props: { label: 'Password', name: 'password' } },
    {
        name: 'With a hint',
        props: {
            label: 'New password',
            name: 'new_password',
            help: 'At least 10 characters',
            autocomplete: 'new-password',
        },
    },
    {
        name: 'With an error',
        props: {
            label: 'Password',
            name: 'password_error',
            modelValue: 'sbagliata',
            errors: ['The password is not correct.'],
        },
    },
]);
