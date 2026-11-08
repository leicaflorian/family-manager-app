/**
 * The stories of the menu (FormKit `dropdown`): a short list of choices, with
 * the list open. Measures and states: see *Text*.
 */
import type { Meta } from '@storybook/vue3-vite';
import { userEvent, within } from 'storybook/test';
import FieldStory from './FieldStory.vue';
import { fieldStates } from './fieldStates';

const meta = {
    title: 'Fields/Dropdown',
    component: FieldStory,
    parameters: {
        docs: {
            description: {
                component:
                    'The menu for a short list of choices: it opens under the box, or over it when there is no room below. A long list that is searched by typing is the *Autocomplete*. It has the measures of the other fields: see *Fields / Text*.',
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
    { type: 'dropdown', name: 'interval_unit', label: 'Repeats', options },
    { value: 'month' },
);

export const Empty = states.empty;
export const Filled = states.filled;
export const Focus = states.focus;
export const WithAnError = { name: 'With an error', ...states.error };
export const Disabled = states.disabled;

/** The list open, with the chosen option marked. */
export const Open = {
    args: states.filled.args,
    parameters: { docs: { story: { inline: false, iframeHeight: 260 } } },
    play: async ({ canvasElement }: { canvasElement: HTMLElement }) => {
        await userEvent.click(
            await within(canvasElement).findByRole('button', {
                name: /Si ripete|Repeats/,
            }),
        );
    },
};
