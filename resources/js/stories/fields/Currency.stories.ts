/**
 * The stories of the amount (FormKit `currency`): `84,20 €` in the box, `84.2`
 * in the form. Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Currency',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The amount in euros: the box shows `84,20 €`, the form gets `84.2`. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const states = fieldStates(
    { type: 'currency', name: 'amount', label: 'Amount' },
    { value: 84.2 },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;
