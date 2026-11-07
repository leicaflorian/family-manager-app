/**
 * The stories of the choice among few options (FormKit `togglebuttons`).
 * Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Toggle buttons',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'A choice among two or three options: the segments sit on a grey track and the chosen one is white with a shadow. It is as high as the other fields, track included. It has the other measures of the fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const options = [
    { value: 'month', label: 'Month' },
    { value: 'year', label: 'Year' },
];

const states = fieldStates(
    { type: 'togglebuttons', name: 'interval_unit', label: 'Repeats', options },
    { value: 'month' },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = {
    ...states.focus,
    parameters: { pseudo: { focusVisible: true } },
};
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;
