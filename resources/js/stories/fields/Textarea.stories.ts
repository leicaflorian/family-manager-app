/**
 * The stories of the long text (FormKit `textarea`). Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Textarea',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The long text, for notes. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const states = fieldStates(
    { type: 'textarea', name: 'notes', label: 'Notes' },
    { value: 'Contatore nel garage, lettura il 15 di ogni mese.' },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;
