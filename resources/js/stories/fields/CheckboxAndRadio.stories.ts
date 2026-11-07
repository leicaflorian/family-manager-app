/**
 * The stories of the checkbox and of the single choice (FormKit `checkbox`
 * and `radio`). Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Checkbox and radio',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The checkbox is plain, with a box of 22 px and a tick; the single choice (`radio`) lists its options one under the other. Both are at least 44 px high to the touch. They have the other measures of the fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const checkbox = fieldStates(
    {
        type: 'checkbox',
        name: 'terms',
        label: 'I have read and accept the terms and the privacy policy',
    },
    { value: true },
    'This field is required.',
);

const levels = [
    { value: 'admin', label: 'Everything, like you' },
    { value: 'member', label: 'Sees everything and uploads the documents' },
];

const radio = fieldStates(
    { type: 'radio', name: 'role', label: 'What they can do', options: levels },
    { value: 'member' },
);

export const CheckboxEmpty = { name: 'Checkbox, empty', ...checkbox.empty };
export const CheckboxFilled = { name: 'Checkbox, filled', ...checkbox.filled };
export const CheckboxFocus = {
    name: 'Checkbox, focus',
    ...checkbox.focus,
    parameters: { pseudo: { focusVisible: true } },
};
export const CheckboxWithAnError = {
    name: 'Checkbox, with an error',
    ...checkbox.error,
};
export const CheckboxDisabled = {
    name: 'Checkbox, disabled',
    ...checkbox.disabled,
};
export const RadioEmpty = { name: 'Radio, empty', ...radio.empty };
export const RadioFilled = { name: 'Radio, filled', ...radio.filled };
export const RadioFocus = {
    name: 'Radio, focus',
    ...radio.focus,
    parameters: { pseudo: { focusVisible: true } },
};
export const RadioWithAnError = {
    name: 'Radio, with an error',
    ...radio.error,
};
export const RadioDisabled = { name: 'Radio, disabled', ...radio.disabled };
