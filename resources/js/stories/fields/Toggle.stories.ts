/**
 * The stories of the switch for a yes or no (FormKit `toggle`). Measures and
 * states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Toggle',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The switch for a yes or no. A switch that was never touched sends `false`, not an empty value. It has the measures of the other fields: see *Fields / Text*.',
            },
        },
    },
} satisfies Meta<typeof FieldStory>;

export default meta;

const states = fieldStates(
    { type: 'toggle', name: 'remember', label: 'Stay signed in' },
    { value: true },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = {
    ...states.focus,
    parameters: { pseudo: { focusVisible: true } },
};
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;
